<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

declare(strict_types=1);

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use fiftyone\pipeline\core\Utils;
use PHPUnit\Framework\TestCase;

/**
 * The device detection usage examples say "It MUST be simple for the user
 * to create a web application where: 1. Each request (following any
 * filtering, etc) will have all relevant Evidence values extracted, added
 * to a Flow Data and processed. 2. This Flow Data will be made available in
 * whatever mechanism is common for shared web session data in that
 * environment, allowing other components to easily make use of the Device
 * Detection results."
 *
 * PHP builds its request state afresh for every request, so the request's
 * own Flow Data is kept for the rest of that request, where any other part
 * of the application can read it without being handed it.
 */
class WebRequestFlowDataTests extends TestCase
{
    private const USER_AGENT = 'Mozilla/5.0 (test)';

    /**
     * The server values a web server gives a script for a request.
     *
     * @var array<string, string>
     */
    private const SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => self::USER_AGENT
    ];

    public function testEachRequestHasItsEvidenceExtractedAddedAndProcessed(): void
    {
        $flowData = Utils::processWebRequest($this->pipeline(), self::SERVER, [], []);

        $this->assertTrue($flowData->processed);
        $this->assertSame(
            self::USER_AGENT,
            $flowData->evidence->get('header.user-agent')
        );
        $this->assertSame(5, $flowData->example1->integer);
    }

    public function testTheRequestsFlowDataIsAvailableToOtherComponents(): void
    {
        $processed = Utils::processWebRequest($this->pipeline(), self::SERVER, [], []);

        // Another part of the application, given nothing.
        $otherComponent = static function (): ?FlowData {
            return Utils::getFlowData();
        };

        $this->assertSame($processed, $otherComponent());
        $this->assertSame(5, $otherComponent()->example1->integer);
    }

    public function testEachRequestKeepsItsOwnFlowData(): void
    {
        $pipeline = $this->pipeline();
        $first = Utils::processWebRequest($pipeline, self::SERVER, [], []);
        $second = Utils::processWebRequest(
            $pipeline,
            ['HTTP_USER_AGENT' => 'Another'] + self::SERVER,
            [],
            []
        );

        $this->assertNotSame($first, $second);
        $this->assertSame($second, Utils::getFlowData());
        $this->assertSame(
            'Another',
            Utils::getFlowData()->evidence->get('header.user-agent')
        );
    }

    /**
     * A PHP that serves one request after another, as a worker does, keeps
     * its static state between them, so a Flow Data replaced by the next
     * request's is closed then rather than when that PHP stops.
     */
    public function testTheFlowDataAnotherRequestReplacesIsClosed(): void
    {
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false
        ]))->add($this->closingElement())->build();

        $first = Utils::processWebRequest($pipeline, self::SERVER, [], []);
        $this->assertFalse($first->closing->closed);

        $second = Utils::processWebRequest($pipeline, self::SERVER, [], []);

        $this->assertTrue($first->closing->closed);
        $this->assertFalse($second->closing->closed);
        $this->assertSame($second, Utils::getFlowData());
    }

    /**
     * In a PHP that serves one request after another, as a worker does, the
     * kept Flow Data stays until the next call to processWebRequest()
     * replaces it. So a request there that has not called it reads the
     * previous request's Flow Data, and getFlowData()'s comment and the
     * readme's web section say so rather than that it is null.
     */
    public function testARequestThatHasNotProcessedReadsThePreviousRequestsFlowData(): void
    {
        $previous = Utils::processWebRequest($this->pipeline(), self::SERVER, [], []);

        // The next request, which answers without calling processWebRequest().
        $this->assertSame($previous, Utils::getFlowData());

        $phrase = "the previous request's flow data";

        $utils = (string)file_get_contents(__DIR__ . '/../src/Utils.php');
        $doc = strtolower($this->words(
            $this->docAbove($utils, 'public static function getFlowData(')
        ));
        $this->assertStringContainsString($phrase, $doc);
        $this->assertStringNotContainsString('not been called in this request', $doc);

        $section = strtolower($this->words($this->webSection()));
        $this->assertStringContainsString($phrase, $section);
        $this->assertStringNotContainsString('never kept for another request', $section);
    }

    /**
     * The Flow Data a call replaces is closed after the new one is kept, so
     * an error from closing it still leaves the new one to be read.
     */
    public function testTheNewFlowDataIsKeptWhenClosingTheOneItReplacesThrows(): void
    {
        $element = $this->closingElement(true);
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false
        ]))->add($element)->build();

        $replaced = Utils::processWebRequest($pipeline, self::SERVER, [], []);

        try {
            Utils::processWebRequest(
                $pipeline,
                ['HTTP_USER_AGENT' => 'Another'] + self::SERVER,
                [],
                []
            );
            $this->fail('The error from closing the replaced Flow Data is thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('close failed', $e->getMessage());
        } finally {
            // The Flow Data still kept is closed by a later test or at
            // shutdown, which must not throw.
            $element->closeThrows = false;
        }

        $this->assertNotSame($replaced, Utils::getFlowData());
        $this->assertSame(
            'Another',
            Utils::getFlowData()->evidence->get('header.user-agent')
        );
    }

    /**
     * Web integration documentation MUST say that a Flow Data cannot be used
     * by two threads at once, and MUST show it, so the readme's web section
     * says it, its example says it just above the line that creates the Flow
     * Data, and so does processWebRequest()'s own comment. Code comments and
     * documentation MUST also say that the caller is responsible for any
     * locking, so each of those says that too.
     */
    public function testTheWebDocumentationSaysAFlowDataIsUsedByOneThread(): void
    {
        $phrase = 'must not be used by two threads at once';
        $locking = 'the caller is responsible for any locking';

        $section = $this->webSection();
        $this->assertStringContainsString($phrase, $this->words($section));
        $this->assertStringContainsString(
            $locking,
            strtolower($this->words($section))
        );

        // The comment lines just above the line that creates the Flow Data.
        $lines = explode("\n", str_replace("\r", '', $section));
        $comment = [];
        foreach ($lines as $line) {
            if (strpos($line, '= Utils::processWebRequest(') !== false) {
                break;
            }
            $comment = strpos(ltrim($line), '//') === 0
                ? array_merge($comment, [ltrim($line, " /")])
                : [];
        }
        $this->assertStringContainsString(
            $phrase,
            $this->words(implode(' ', $comment)),
            'The example says it just above the line that creates the Flow Data'
        );
        $this->assertStringContainsString(
            $locking,
            strtolower($this->words(implode(' ', $comment))),
            'The example says who locks just above the line that creates the Flow Data'
        );

        $utils = (string)file_get_contents(__DIR__ . '/../src/Utils.php');
        $doc = $this->docAbove($utils, 'public static function processWebRequest(');
        $this->assertStringContainsString($phrase, $this->words($doc));
        $this->assertStringContainsString($locking, strtolower($this->words($doc)));
    }

    /**
     * The readme's Web applications section.
     */
    private function webSection(): string
    {
        $readme = (string)file_get_contents(__DIR__ . '/../readme.md');
        $start = strpos($readme, '## Web applications');
        $this->assertNotFalse($start, 'The readme has a Web applications section');
        $end = strpos($readme, "\n## ", $start + 1);

        return $end === false
            ? substr($readme, $start)
            : substr($readme, $start, $end - $start);
    }

    /**
     * The doc comment just above a line of the source.
     */
    private function docAbove(string $source, string $line): string
    {
        $at = strpos($source, $line);
        $this->assertNotFalse($at, $line . ' is in the source');
        $doc = substr($source, 0, $at);

        return substr($doc, (int)strrpos($doc, '/**'));
    }

    /**
     * The text with comment markers and line breaks made single spaces, so a
     * phrase wrapped over two lines is still found.
     */
    private function words(string $text): string
    {
        $text = (string)preg_replace('/\R[ \t]*(\*(?!\/)|\/\/)/', ' ', $text);

        return (string)preg_replace('/\s+/', ' ', $text);
    }

    /**
     * An element whose element data has a close method of its own, as the
     * results of an on-premise engine have.
     */
    private function closingElement(bool $closeThrows = false): FlowElement
    {
        return new class($closeThrows) extends FlowElement {
            public string $dataKey = 'closing';

            public bool $closeThrows;

            public function __construct(bool $closeThrows)
            {
                parent::__construct();
                $this->closeThrows = $closeThrows;
            }

            public function processInternal($flowData): void
            {
                $flowData->setElementData(new class($this) extends ElementData {
                    public bool $closed = false;

                    public function close(): void
                    {
                        $this->closed = true;
                        if ($this->flowElement->closeThrows) {
                            throw new \RuntimeException('close failed');
                        }
                    }
                });
            }
        };
    }

    private function pipeline(): Pipeline
    {
        return (new PipelineBuilder())
            ->add(new ExampleFlowElement1())
            ->build();
    }
}
