<?php

namespace App\Handlers;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Success;
use App\Dto\SnippetDto;
use App\DataStore;
use App\Dto\BladeComponentData;
use App\Dto\Element;
use App\Logger;
use App\Util\PositionConverter;
use Phpactor\LanguageServer\Core\Handler\CanRegisterCapabilities;
use Phpactor\LanguageServer\Core\Handler\Handler;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\LanguageServerProtocol\CompletionItem;
use Phpactor\LanguageServerProtocol\CompletionItemKind;
use Phpactor\LanguageServerProtocol\CompletionList;
use Phpactor\LanguageServerProtocol\CompletionParams;
use Phpactor\LanguageServerProtocol\ServerCapabilities;
use Phpactor\LanguageServerProtocol\CompletionOptions;
use Phpactor\LanguageServerProtocol\DefinitionClientCapabilities;
use Phpactor\LanguageServerProtocol\DefinitionParams;
use Phpactor\LanguageServerProtocol\Hover;
use Phpactor\LanguageServerProtocol\HoverClientCapabilities;
use Phpactor\LanguageServerProtocol\HoverParams;
use Phpactor\LanguageServerProtocol\InsertTextFormat;
use Phpactor\LanguageServerProtocol\Location;
use Phpactor\LanguageServerProtocol\Position;
use Phpactor\LanguageServerProtocol\Range;
use Phpactor\LanguageServerProtocol\TextDocumentItem;
use Phpactor\LanguageServerProtocol\TextEdit;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\Log\LoggerInterface;

class BladeComponentHandler implements Handler, CanRegisterCapabilities
{
    public LoggerInterface $logger;
    public Workspace $workspace;
    public DataStore $store;

    private const MATCH_PARAM = 'param';
    private const MATCH_NONE = 'none';
    private const MATCH_COMPONENT = 'component';

    public function __construct(LoggerInterface $logger, Workspace $workspace, DataStore $store)
    {
        $this->workspace = $workspace;
        $this->logger = $logger;
        $this->store = $store;
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
        $textDocument = $this->workspace->get($params->textDocument->uri);
        $element = $this->getElementAtPosition($textDocument, $params->position);
        if ($element && $info = $this->getElementInformation($element)) {
            return new Success(new Hover($info->getHoverData()));
        }

        return new Success(null);
    }

    public function definition(DefinitionParams $params, CancellationToken $cancellationToken): Promise
    {
        $textDocument = $this->workspace->get($params->textDocument->uri);
        $element = $this->getElementAtPosition($textDocument, $params->position);
        if ($element && $info = $this->getElementInformation($element)) {
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
        return \Amp\call(function () use ($cancellation, $params) {
            if (null === $this->store->availableComponents) {
                return false;
            }

            $completionItems = [];

            $textDocument = $this->workspace->get($params->textDocument->uri);
            $byteOffset = PositionConverter::positionToByteOffset($params->position, $textDocument->text);

            $completionItems = [];

            $components = $this->store->availableComponents->whereIn('type', [SnippetDto::TYPE_COMPONENT, SnippetDto::TYPE_LIVEWIRE]);

            // Check to see if we are inside a blade component. <x-some-thi<cur> :<cur> h<cur>/>
            // The document is potentially incomplete.
            // We do this by checking that we find <str before >
            $cur = $textDocument->text[$byteOffset->toInt() - 1];
            $prev = $textDocument->text[$byteOffset->toInt() - 2];

            // Todo: Figure out the full component name or argument.
            //
            // Component name.
            $offset = $byteOffset->toInt() - 1;
            $type = null;
            $doneMatcher = false;

            $chars = [];

            $searchChars = [];

            $noneMatchers = [">", "\"", "'", "\\", "="];

            $searchRangeEnd = $byteOffset->toInt();
            $searchRangeStart = 0;

            // Find the start.
            while ($offset >= 0 && !$doneMatcher) {
                $char = $textDocument->text[$offset];

                $chars[] = $char;

                if (!$type) {
                    $searchChars[] = $char;
                }

                if (in_array($char, $noneMatchers)) {
                    if (!$type) {
                        $type = self::MATCH_NONE;
                    }
                    $doneMatcher = true;
                }

                if ($char == "<") {
                    if (!$type) {
                        $type = self::MATCH_COMPONENT;
                    }
                    $searchRangeStart = $offset;
                    $doneMatcher = true;
                }

                if ($char == " ") {
                    $searchRangeStart = $offset;
                    $type = self::MATCH_PARAM;
                }

                $offset--;
            }

            $search = join('', array_reverse($searchChars));
            if ($type === self::MATCH_COMPONENT) {
                $search = ltrim($search, '<');
            } elseif ($type === self::MATCH_PARAM) {
                $search = ltrim($search);
            }

            $replaceRange = new Range(
                PositionConverter::intByteOffsetToPosition($searchRangeStart, $textDocument->text),
                PositionConverter::intByteOffsetToPosition($searchRangeEnd, $textDocument->text)
            );

            Logger::logdbg(join('', array_reverse($chars)));
            Logger::logdbg('type:' . $type . '|search:' . $search . '|cur:' . $cur . '|prev:' . $prev);
            Logger::logdbg('components:' . $components->count());

            if ($type === self::MATCH_COMPONENT) {
                /** @var BladeComponentData $data */
                foreach ($components as $name => $data) {
                    if (strpos($data->name, $search) === false) {
                        continue;
                    }
                    $snippet = "<{$data->name} $0/>";
                    if ($data->hasSlot) {
                        $snippet = "<{$data->name}>$0</{$data->name}>";
                    }
                    $completionItems[] = new CompletionItem(
                        label: "<{$data->name} />",
                        documentation: $data->getHoverData(),
                        detail: $data->getFile(),
                        kind: CompletionItemKind::MODULE,
                        textEdit: new TextEdit($replaceRange, $snippet),
                        insertTextFormat: InsertTextFormat::SNIPPET
                    );
                    yield \Amp\delay(1);
                }
            } elseif ($type === self::MATCH_PARAM) {
                $element = $this->getElementAtPosition($textDocument, $params->position);

                if ($element) {
                    /** @var BladeComponentData $component */
                    $component = $components->firstWhere('name', $element->name);

                    // Find a matching component.
                    if ($component) {
                        $usedArguments = $element->getUsedArguments();
                        foreach ($component->arguments as $name => $argumentData) {
                            $test = 'test';
                            if (!in_array($name, $usedArguments) && strpos($name, ltrim($search, ':')) !== false) {
                                if (!str_starts_with($search, ':')) {
                                    if ($argumentData['type'] !== 'bool') {
                                        $completionItems[] = new CompletionItem(
                                            label: $name . '=""',
                                            detail: $argumentData['type'] ?? '',
                                            documentation: $argumentData['doc'] ?? '',
                                            kind: CompletionItemKind::TYPE_PARAMETER,
                                            insertText: $name . '="$0"',
                                            insertTextFormat: InsertTextFormat::SNIPPET
                                        );
                                    } else {
                                        // Booleans can be true if no args.
                                        $completionItems[] = new CompletionItem(
                                            label: $name,
                                            detail: $argumentData['type'] ?? '',
                                            documentation: $argumentData['doc'] ?? '',
                                            kind: CompletionItemKind::TYPE_PARAMETER,
                                            insertText: $name,
                                            insertTextFormat: InsertTextFormat::SNIPPET
                                        );
                                    }
                                }
                                $completionItems[] = new CompletionItem(
                                    label: ':' . $name . '=""',
                                    detail: $argumentData['type'] ?? '',
                                    documentation: $argumentData['doc'] ?? '',
                                    commitCharacters: [':', '-'],
                                    kind: CompletionItemKind::TYPE_PARAMETER,
                                    insertText: $name . '="$0"',
                                    insertTextFormat: InsertTextFormat::SNIPPET
                                );
                                Logger::logdbg($completionItems);
                                yield \Amp\delay(1);
                            }
                        }
                    }
                }
            }

            foreach ($completionItems as $completion) {
                try {
                    $cancellation->throwIfRequested();
                } catch (\Amp\CancelledException) {
                    break;
                }
            }

            return new CompletionList(true, $completionItems);
        });
    }

    private function getElementInformation(Element $element): ?BladeComponentData
    {
        $components = $this->store->availableComponents->whereIn(
            'type',
            [
                SnippetDto::TYPE_COMPONENT,
                SnippetDto::TYPE_LIVEWIRE,
                SnippetDto::TYPE_VIEW
            ]
        );
        return $components->firstWhere('name', $element->name);
    }

    private function getElementAtPosition(TextDocumentItem $document, Position $position): ?Element
    {
        $byteOffset = PositionConverter::positionToByteOffset($position, $document->text);

        $currPos = $byteOffset->toInt();
        $docText = $document->text;

        // Now we should figure out if we are inside of an html tag.
        $closingPosSelfClosing = strpos($docText, '/>', $currPos);
        $closingPosElement = strpos($docText, '>', $currPos);
        $nextOpeningPos = strpos($docText, '<', $currPos);

        if ($closingPosElement && $closingPosSelfClosing) {
            if ($closingPosSelfClosing < $closingPosElement) {
                $closingPos = $closingPosSelfClosing + 2;
            } else {
                $closingPos = $closingPosElement + 1;
            }
        } elseif ($closingPosElement) {
            $closingPos = $closingPosElement + 1;
        } else {
            // Unclosed
            if ($nextOpeningPos) {
                $closingPos = $nextOpeningPos;
            } else {
                $closingPos = $currPos;
            }
        }

        $openingPos = strrpos(substr($docText, 0, $closingPos ?? 0), "<");
        $openingPosClosingElement = strrpos(substr($docText, 0, $closingPos ?? 0), "</");

        if ($currPos > $openingPos && $currPos < $closingPos) {
            // Parse element details.
            // @todo: Make a separate blade component parser based on ComponentTagCompiler.
            $element = substr($docText, $openingPos, $closingPos - $openingPos);

            $firstWhiteSpacePos = strpos($element, ' ');
            $firstClosingPos = strpos($element, '>');
            $endingPositionToUse = min($firstClosingPos, $firstWhiteSpacePos);
            $starting = $openingPos === $openingPosClosingElement ? 2 : 1;
            $elementName = substr($element, $starting, $endingPositionToUse - 1);

            return new Element($elementName, $element, $openingPos, $closingPos - $openingPos);
        }

        return null;
    }
}
