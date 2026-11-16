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
     * An element whose element data has a close method of its own, as the
     * results of an on-premise engine have.
     */
    private function closingElement(): FlowElement
    {
        return new class() extends FlowElement {
            public string $dataKey = 'closing';

            public function processInternal($flowData): void
            {
                $flowData->setElementData(new class($this) extends ElementData {
                    public bool $closed = false;

                    public function close(): void
                    {
                        $this->closed = true;
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
