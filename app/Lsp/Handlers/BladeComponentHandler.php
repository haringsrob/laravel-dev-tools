<?php

namespace App\Lsp\Handlers;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Dto\BladeDirectiveData;
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
use Phpactor\LanguageServerProtocol\DocumentSymbolClientCapabilities;
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
    private const MATCH_DIRECTIVE = 'directive';
    private const MATCH_WIRE = 'wire';

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
        $capabilities->documentSymbolProvider = DocumentSymbolClientCapabilities::class;
    }

    public function methods(): array
    {
        return [
            'textDocument/hover' => 'hover',
            'textDocument/definition' => 'definition',
            'textDocument/completion' => 'complete',
            'textDocument/documentSymbol' => 'null',
        ];
    }

    public function null(): \Amp\Success
    {
        return new Success();
    }

    private function getDirectiveForName(string $name): ?BladeDirectiveData
    {
        return $this->store->availableDirectives->first(function (BladeDirectiveData $bladeDirectiveData) use ($name) {
            return $bladeDirectiveData->name === $name;
        });
    }

    public function hover(HoverParams $params, CancellationToken $cancellationToken): Promise
    {
        $request = $this->getCompletionRequest($params->textDocument, $params->position);
        if ($request && $request->type === self::MATCH_DIRECTIVE) {
            if ($directive = $this->getDirectiveForName($request->search)) {
                return new Success(new Hover($directive->getHoverData()));
            }
        } elseif ($request && $request->element->getComponent()) {
            return new Success(new Hover($request->element->getComponent()->getHoverData()));
        }

        return new Success(null);
    }

    public function definition(DefinitionParams $params, CancellationToken $cancellationToken): Promise
    {
        $request = $this->getCompletionRequest($params->textDocument, $params->position);

        if ($request && $request->type === self::MATCH_DIRECTIVE) {
            $directive = $this->getDirectiveForName($request->search);

            if ($directive && $directive->file) {
                $position = new Position($directive->line ?? 0, 0);
                return new Success(new Location(
                    TextDocumentUri::fromString($directive->file)->__toString(),
                    new Range($position, $position)
                ));
            }
        } elseif ($request && $info = $request->element->getComponent()) {
            $locations = array_values($info->views);
            $locations[] = $info->file;

            $locations = array_unique($locations);

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

            // Catch an exception if thrown.
            try {
                $completionRequest = $this->getCompletionRequest($params->textDocument, $params->position);

                if (!$completionRequest) {
                    return;
                }
            } catch (Exception $e) {
                Logger::logException($e);
                return;
            }

            if ($completionRequest->type === self::MATCH_DIRECTIVE) {
                try {
                    $result = $this->resultFinder->getDirectives($completionRequest);
                    return $result;
                } catch (Exception $e) {
                    Logger::logException($e);
                    return [];
                }
            } elseif ($completionRequest->type === self::MATCH_COMPONENT) {
                try {
                    return $this->resultFinder->getComponents($completionRequest);
                } catch (Exception $e) {
                    Logger::logException($e);
                    return [];
                }
            } elseif ($completionRequest->type === self::MATCH_PARAM) {
                try {
                    return $this->resultFinder->getArguments($completionRequest);
                } catch (Exception $e) {
                    Logger::logException($e);
                    return [];
                }
            } elseif ($completionRequest->type === self::MATCH_WIRE) {
                try {
                    return $this->resultFinder->getWireableValues($completionRequest);
                } catch (Exception $e) {
                    Logger::logException($e);
                    return [];
                }
            }

            return [];
        });
    }

    private function getBladeDirectiveRequest(
        TextDocumentIdentifier $textDocument,
        Position $position
    ): ?CompletionRequest {
        $textDocument = $this->workspace->get($textDocument->uri);
        $byteOffset = PositionConverter::positionToByteOffset($position, $textDocument->text);

        $offsetLeft = $byteOffset->toInt() - 1;

        $directiveStart = null;

        $doneLeft = false;
        $doneRight = false;

        $offsetRight = $byteOffset->toInt();

        $charsLeft = [];
        $charsRight = [];

        $isDirective = false;

        while (!$doneLeft || !$doneRight) {
            // Continue left.
            if (!$doneLeft && $offsetLeft >= 0) {
                $charLeft = $textDocument->text[$offsetLeft];
                if (!$doneLeft) {
                    $charsLeft[] = $charLeft;
                }
                if (ctype_space($charLeft)) {
                    $doneLeft = true;
                }
                if ($charLeft === '@') {
                    // Found an @, check the caracter before as it needs to be a whitespace.
                    if ($offsetLeft - 1 <= 0) {
                        // Found the start of the document.
                        $doneLeft = true;
                        $directiveStart = $offsetLeft;
                        $isDirective = true;
                    } elseif (ctype_space($textDocument->text[$offsetLeft - 1])) {
                        $doneLeft = true;
                        $directiveStart = $offsetLeft;
                        $isDirective = true;
                    }
                }
                $offsetLeft--;
            } else {
                $doneLeft = true;
            }

            // Continue Right
            // We search until we find ( or a whiteSpace.
            if (!$doneRight) {
                if (!isset($textDocument->text[$offsetRight])) {
                    $doneRight = true;
                    continue;
                }
                $charRight = $textDocument->text[$offsetRight];
                $charsRight[] = $charRight;
                if (ctype_space($charRight) || $charRight === '(') {
                    $doneRight = true;
                }
                $offsetRight++;
            }
        }

        if (!$isDirective) {
            return null;
        }

        $fullDirective = join('', array_reverse($charsLeft)) . join('', $charsRight);

        $search = ltrim($fullDirective, '@');

        $strPosOpen = strpos($search, '(') === false ? strlen($search) : strpos($search, '(');
        $search = rtrim(substr($search, 0, $strPosOpen));

        $replaceRange = new Range(
            PositionConverter::intByteOffsetToPosition($directiveStart + 1, $textDocument->text),
            PositionConverter::intByteOffsetToPosition($directiveStart + strlen($search), $textDocument->text)
        );

        return new CompletionRequest(
            search: $search,
            type: self::MATCH_DIRECTIVE,
            replaceRange: $replaceRange,
        );
    }

    /**
     * The completion request method finds what our "current scope" is.
     */
    private function getCompletionRequest(TextDocumentIdentifier $textDocument, Position $position): ?CompletionRequest
    {
        // First try to find a directive.
        $directive = $this->getBladeDirectiveRequest($textDocument, $position);
        if ($directive) {
            return $directive;
        }

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

        // @todo: Why isnt this just the current?
        if ($byteOffset->toInt() > 0) {
            $triggerCharacter = $textDocument->text[$byteOffset->toInt() - 1];
        } else {
            $triggerCharacter = '';
        }

        // If we are inside an argument we need different completion options.
        if (in_array('"', $searchChars) || in_array('\'', $searchChars)) {
            // Check if it is a wire:model string.
            $string = trim(implode("", array_reverse($searchChars)));
            if (str_starts_with($string, 'wire:model')) {
                // Now we know it is a wire property. Now we need the search string.
                $search = null;
                if (in_array('"', $searchChars)) {
                    $search = explode('"', $string)[1];
                }
                if (in_array('\'', $searchChars)) {
                    $search = explode('\'', $string)[1];
                }

                $replaceRange = new Range(
                    PositionConverter::intByteOffsetToPosition($searchRangeStart, $textDocument->text),
                    PositionConverter::intByteOffsetToPosition($searchRangeStart, $textDocument->text)
                );

                $file = str_replace('file://', '', $textDocument->uri);

                $matchingComponent = null;

                $components = $this->store->availableComponents;
                // Find the component by its file.
                /** @var \App\Dto\BladeComponentData $component */
                foreach ($components as $component) {
                    if ($component->livewire && $component->matchesView($file)) {
                        $matchingComponent = $component;
                        break;
                    }
                }

                if ($matchingComponent) {
                    $type = self::MATCH_WIRE;

                    return new CompletionRequest(
                        search: $search,
                        element: null,
                        type: self::MATCH_WIRE,
                        replaceRange: $replaceRange,
                        triggerChar: $triggerCharacter,
                        store: $this->store,
                        component: $matchingComponent
                    );
                }
            }

            return null;
        }

        // Nothing else to do here.
        if ($type === self::MATCH_NONE || null === $type) {
            return null;
        }

        // Find the end.
        $foundEnd = false;
        $offsetRight = $byteOffset->toInt();
        $endMatchers = ['>', '/>', '<', '@'];
        $docLength = strlen($textDocument->text);
        $fullEndIndex = 0;
        while ($offsetRight && !$foundEnd) {
            if (!isset($textDocument->text[$offsetRight])) {
                $foundEnd = true;
                $fullEndIndex = $offsetRight - 1;
                continue;
            }
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
        $name = rtrim(rtrim(ltrim(strtok($fullElement, " "), '<'), '>'), '/');

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
