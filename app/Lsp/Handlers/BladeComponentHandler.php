<?php

namespace App\Lsp\Handlers;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
use App\DataStore;
use App\Dto\Element;
use App\Logger;
use App\Lsp\CompletionRequest;
use App\Util\PositionConverter;
use App\Lsp\CompletionResultFinder;
use Exception;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\DefinitionClientCapabilities;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverClientCapabilities;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentIdentifier;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\Log\LoggerInterface;

class BladeComponentHandler implements Handler, CanRegisterCapabilities
{
    public LoggerInterface $logger;
    public Workspace $workspace;
    public DataStore $store;
    public CompletionResultFinder $resultFinder;

    private const MATCH_PARAM = 'param';
    private const MATCH_NONE = 'none';
    private const MATCH_COMPONENT = 'component';

    public function __construct(LoggerInterface $logger, Workspace $workspace, DataStore $store)
    {
        $this->workspace = $workspace;
        $this->logger = $logger;
        $this->store = $store;
        $this->resultFinder = new CompletionResultFinder($store);
        $this->store->refreshAvailableComponents(true);
    }

    public function registerCapabiltiies(ServerCapabilities $capabilities): void
    {
        $options = new CompletionOptions();
        $options->triggerCharacters = ['<', ':', '@'];

        $capabilities->completionProvider = $options;
        $capabilities->definitionProvider = DefinitionClientCapabilities::class;
        $capabilities->hoverProvider = HoverClientCapabilities::class;
    }

    public function methods(): array
    {
        return [
            'textDocument/hover' => 'hover',
            'textDocument/definition' => 'definition',
            'textDocument/completion' => 'complete'
        ];
    }

    public function hover(HoverParams $params, CancellationToken $cancellationToken): Promise
    {
        $request = $this->getCompletionRequest($params->textDocument, $params->position);
        if ($request && $request->element->getComponent()) {
            return new Success(new Hover($request->element->getComponent()->getHoverData()));
        }

        return new Success(null);
    }

    public function definition(DefinitionParams $params, CancellationToken $cancellationToken): Promise
    {
        $request = $this->getCompletionRequest($params->textDocument, $params->position);

        if ($request && $info = $request->element->getComponent()) {
            $locations = array_values($info->views);
            $locations[] = $info->file;

            $locationsResponse = [];

            $position = new Position(0, 0);

            foreach ($locations as $location) {
                $locationsResponse[] = new Location(
                    TextDocumentUri::fromString($location)->__toString(),
                    new Range($position, $position)
                );
            }

            return new Success($locationsResponse);
        }
        return new Success(null);
    }

    public function complete(CompletionParams $params, CancellationToken $cancellation): Promise
    {
        return \Amp\call(function () use ($params) {
            if (null === $this->store->availableComponents) {
                return false;
            }

            $completionRequest = $this->getCompletionRequest($params->textDocument, $params->position);

            if ($completionRequest->type === self::MATCH_COMPONENT) {
                try {
                    return $this->resultFinder->getComponents($completionRequest);
                } catch (Exception $e) {
                    Logger::logdbg($e->getMessage());
                    return [];
                }
            } elseif ($completionRequest->type === self::MATCH_PARAM) {
                try {
                    return $this->resultFinder->getArguments($completionRequest);
                } catch (Exception $e) {
                    Logger::logdbg($e->getMessage());
                    return [];
                }
            }

            return [];
        });
    }

    private function getCompletionRequest(TextDocumentIdentifier $textDocument, Position $position): ?CompletionRequest
    {
        $textDocument = $this->workspace->get($textDocument->uri);
        $byteOffset = PositionConverter::positionToByteOffset($position, $textDocument->text);

        $offsetLeft = $byteOffset->toInt() - 1;
        $type = null;
        $doneMatcher = false;

        $chars = [];

        $searchChars = [];

        $doneMatchers = [">", "<"];

        $searchRangeEnd = $byteOffset->toInt();
        $searchRangeStart = 0;
        $fullStartIndex = 0;

        // Find the start.
        while ($offsetLeft >= 0 && !$doneMatcher) {
            $char = $textDocument->text[$offsetLeft];

            $chars[] = $char;

            if (!$type) {
                $searchChars[] = $char;
            }

            if (in_array($char, $doneMatchers)) {
                if ($char === '>') {
                    $type = self::MATCH_NONE;
                } else {
                    $fullStartIndex = $offsetLeft;
                }
                $doneMatcher = true;
            }

            if ($char == "<") {
                if (!$type) {
                    $type = self::MATCH_COMPONENT;
                }
                $searchRangeStart = $offsetLeft;
                $doneMatcher = true;
            }

            if ($char == " ") {
                if (!$searchRangeStart) {
                    $searchRangeStart = $offsetLeft;
                }
                $type = self::MATCH_PARAM;
            }

            $offsetLeft--;
        }

        $triggerCharacter = $textDocument->text[$byteOffset->toInt() + 1];

        // If we are inside of an argument we can just skip.
        if (in_array('"', $searchChars) || in_array('\'', $searchChars)) {
            return null;
        }

        // Nothing else to do here.
        if ($type === self::MATCH_NONE) {
            return null;
        }

        // Find the end.
        $foundEnd = false;
        $offsetRight = $byteOffset->toInt();
        $endMatchers = ['>', '/>', '<', '@'];
        $docLength = strlen($textDocument->text);
        $fullEndIndex = 0;
        while ($offsetRight && !$foundEnd) {
            $char = $textDocument->text[$offsetRight];

            if (in_array($char, $endMatchers)) {
                $foundEnd = true;
                $fullEndIndex = $offsetRight;
            }

            $offsetRight++;

            if ($offsetRight == $docLength) {
                $foundEnd = true;
            }

            array_unshift($chars, $char);
        }

        // Now the $chars contains the full element.
        // Extract the name.
        $fullElement = join('', array_reverse($chars));
        $name = ltrim(strtok($fullElement, " "), '<');

        $element = new Element(
            $name,
            $fullElement,
            $fullStartIndex,
            $fullEndIndex,
            $this->store
        );

        $search = join('', array_reverse($searchChars));
        if ($type === self::MATCH_COMPONENT) {
            $search = ltrim($search, '<');
        }
        if ($type === self::MATCH_PARAM) {
            $search = ltrim($search);
        }

        $replaceRange = new Range(
            PositionConverter::intByteOffsetToPosition($searchRangeStart, $textDocument->text),
            PositionConverter::intByteOffsetToPosition($searchRangeEnd, $textDocument->text)
        );

        return new CompletionRequest(
            search: $search,
            element: $element,
            type: $type,
            replaceRange: $replaceRange,
            triggerChar: $triggerCharacter
        );
    }
}
