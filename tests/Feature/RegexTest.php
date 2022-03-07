<?php

namespace Tests\Feature;

use App\Lsp\DiagnosticError;
use Illuminate\View\Compilers\ComponentTagCompiler;
use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

class RegexTest extends TestCase
{
    private $patternSelfClosing = "/
            <
                \s*
                x[-\:]([\w\-\:\.]*)
                \s*
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                [\w\-:.@]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
            \/>
        /x";


    private $patternOpeningTag = "/
            <
                \s*
                x[-\:]([\w\-\:\.]*)
                (?<attributes>
                    (?:
                        \s+
                        (?:
                            (?:
                                \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                            )
                            |
                            (?:
                                [\w\-:.@]+
                                (
                                    =
                                    (?:
                                        \\\"[^\\\"]*\\\"
                                        |
                                        \'[^\']*\'
                                        |
                                        [^\'\\\"=<>]+
                                    )
                                )?
                            )
                        )
                    )*
                    \s*
                )
                (?<![\/=\-])
            >
        /x";

    private $patternClosingTag = "/<\/\s*x[-\:][\w\-\:\.]*\s*>/";

    private function getErrors(string $doc, array $availableComponents): array
    {
        $selfClosing = [];
        $opening = [];
        $closing = [];

        preg_match_all($this->patternSelfClosing, $doc, $selfClosing, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternOpeningTag, $doc, $opening, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternClosingTag, $doc, $closing, PREG_OFFSET_CAPTURE);

        $errors = [];
        foreach ($selfClosing[1] as $item) {
            if (!in_array('x-' . $item[0], $availableComponents)) {
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        $closingTags = [];

        foreach ($closing[0] as $item) {
            $cleaned = rtrim(ltrim($item[0], '</x-'), '>');
            if (!in_array('x-' . $cleaned, $availableComponents)) {
                $closingTags[] = [$cleaned, $item[1]];
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $cleaned,
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        foreach ($opening[1] as $item) {
            $isClosed = false;

            foreach ($closingTags as $key => $closingTag) {
                if ($closingTag[0] === $item[0]) {
                    $isClosed = true;
                    unset($closingTags[$key]);
                    break;
                }
            }

            if (!$isClosed) {
                $errors[] = new DiagnosticError(
                    error: 'Component not closed: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }

            if (!in_array('x-' . $item[0], $availableComponents)) {
                $errors[] = new DiagnosticError(
                    error: 'Component not found: ' . $item[0],
                    startPos: $item[1],
                    endPos: $item[1] + strlen($item[0])
                );
            }
        }

        foreach ($closingTags as $closingTag) {
            $errors[] = new DiagnosticError(
                error: 'Component opening not found: ' . $closingTag[0],
                startPos: $closingTag[1],
                endPos: $closingTag[1] + strlen($closingTag[0])
            );
        }

        return $errors;
    }

    public function testMissingComponent(): void
    {
        $blade = "<x-missing-component />
<x-missing-component></x-missing-component>
<x-existing-not-closed></x-missing-begin>";

        $this->assertEquals($this->getErrors($blade, ['x-existing-not-closed']), []);
    }

    public function matcher()
    {
        $content = file_get_contents(__DIR__ . '/fixtures/blade.blade.php');
        $selfClosing = [];
        $opening = [];
        $closing = [];

        preg_match_all($this->patternSelfClosing, $content, $selfClosing, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternOpeningTag, $content, $opening, PREG_OFFSET_CAPTURE);
        preg_match_all($this->patternClosingTag, $content, $closing, PREG_OFFSET_CAPTURE);

        dd($selfClosing);

        $this->assertTrue('false');
    }
}
