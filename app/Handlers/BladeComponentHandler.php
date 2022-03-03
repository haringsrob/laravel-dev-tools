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
use Phpactor\LanguageServerProtocol\TextDocumentSyncKind;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\Log\LoggerInterface;

class BladeComponentHandler implements Handler, CanRegisterCapabilities
{
    public LoggerInterface $logger;
    public Workspace $workspace;
    public DataStore $store;

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
        $capabilities->documentSymbolProvider = DocumentSymbolClientCapabilities::class;
        $capabilities->definitionProvider = DefinitionClientCapabilities::class;
        $capabilities->hoverProvider = HoverClientCapabilities::class;
        $capabilities->textDocumentSync = TextDocumentSyncKind::FULL;
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
        Logger::logdbg($element);
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
            if ($prev && $cur) {
                if ($prev === '<' || $cur === '<') {
                    /** @var BladeComponentData $data */
                    foreach ($components as $name => $data) {
                        $snippet = "<{$data->name} $0/>";
                        if ($data->hasSlot) {
                            $snippet = "<{$data->name}>$0</{$data->name}>";
                        }
                        Logger::logdbg($snippet);
                        $completionItems[] = new CompletionItem(
                            label: "<{$data->name}",
                            documentation: $data->getHoverData(),
                            detail: $data->getFile(),
                            kind: CompletionItemKind::TYPE_PARAMETER,
                            insertText: $snippet,
                            insertTextFormat: InsertTextFormat::SNIPPET
                        );
                        yield \Amp\delay(1);
                    }
                }
                if (
                    $prev === null || $prev === ':' || $cur === ':'
                ) {
                    $element = $this->getElementAtPosition($textDocument, $params->position);

                    if ($element) {
                        /** @var BladeComponentData $component */
                        $component = $components->firstWhere('name', $element->name);
                        $usedArguments = $element->getUsedArguments();

                        // Find a matching component.
                        if ($component) {
                            foreach ($component->arguments as $name => $argumentData) {
                                if (!in_array($name, $usedArguments)) {
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
                                            insertText: $name . '$0',
                                            insertTextFormat: InsertTextFormat::SNIPPET
                                        );
                                    }
                                    $completionItems[] = new CompletionItem(
                                        label: ':' . $name . '=""',
                                        detail: $argumentData['type'] ?? '',
                                        documentation: $argumentData['doc'] ?? '',
                                        kind: CompletionItemKind::TYPE_PARAMETER,
                                        insertText: ':' . $name . '="$0"',
                                        insertTextFormat: InsertTextFormat::SNIPPET
                                    );
                                    yield \Amp\delay(1);
                                }
                            }
                        }
                    }
                }
                if ($prev === '\\') {
                    // Potential closing tag.
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
