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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\core\tests\classes\UnreachableElement;
use PHPUnit\Framework\TestCase;

/**
 * A request whose processing fails still gets its response headers and its
 * client-side content, so that a failing element cannot take down the web
 * site, as the Web integration section of exception-handling.md in the
 * Pipeline specification requires. The Pipeline keeps errors in the Flow
 * Data, which is how a production site runs it.
 */
class WebIntegrationFailureTests extends TestCase
{
    private static function pipeline(): Pipeline
    {
        return (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => '/json',
                'minify' => false
            ],
            'suppressProcessExceptions' => true,
        ]))->add(new UnreachableElement())->build();
    }

    /**
     * @return array<string, string>
     */
    private static function server(string $method): array
    {
        return [
            'REQUEST_METHOD' => $method,
            'HTTP_HOST' => 'www.example.com',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'REMOTE_ADDR' => '203.0.113.7',
        ];
    }

    /**
     * @param array<string, string> $server
     */
    private static function flowData(Pipeline $pipeline, array $server): FlowData
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest($server, [], []);

        return $flowData;
    }

    /**
     * Only the element that failed records an error. The Set Headers
     * Element leaves out the headers of an element that has no data, and
     * setting the response headers then works.
     */
    public function testTheResponseHeadersAreSetWhenAnElementFailed(): void
    {
        $flowData = self::flowData(self::pipeline(), self::server('GET'));
        $flowData->process();

        $this->assertSame(['unreachable'], array_keys($flowData->errors));
        $this->assertSame(
            ['Accept-CH' => ''],
            $flowData->get('set-headers')->get('responseheaderdictionary')
        );

        // Writes no header, as the value is empty, so it runs here.
        Utils::setResponseHeader($flowData);
        $this->addToAssertionCount(1);
    }

    /**
     * The JavaScript and the JSON are still served, with content a browser
     * can use, when an element failed.
     */
    public function testTheClientSideContentIsServedWhenAnElementFailed(): void
    {
        $pipeline = self::pipeline();

        $server = self::server('GET');
        $script = Utils::javascriptResponse(
            self::flowData($pipeline, $server),
            $server
        );
        $this->assertSame(200, $script->status);
        $this->assertStringContainsString(
            'fiftyoneDegreesManager',
            $script->body
        );

        $server = self::server('POST');
        $json = Utils::jsonResponse(self::flowData($pipeline, $server), $server);
        $this->assertSame(200, $json->status);
        $decoded = json_decode($json->body, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('javascriptProperties', $decoded);
    }
}
