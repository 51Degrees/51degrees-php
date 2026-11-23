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

use fiftyone\pipeline\core\HttpRequest;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The web integration adds the web request to Evidence as a read-only view
 * under server.http-request, whether or not any element accepts that key.
 */
class HttpRequestEvidenceTests extends TestCase
{
    private function flowData()
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]);

        return $builder->build()->createFlowData();
    }

    /**
     * @return array<string, string>
     */
    private function server(): array
    {
        return [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/a%20b/page?x=1&y=2',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTPS' => 'on',
            'HTTP_USER_AGENT' => 'agent',
            'HTTP_X_CUSTOM' => 'custom',
            'HTTP_COOKIE' => 'a=1; 51D_b=2',
        ];
    }

    public function testViewIsAddedWithNoElementAcceptingIt()
    {
        $flowData = $this->flowData();
        $flowData->evidence->setFromWebRequest(
            $this->server(),
            ['a' => '1', '51D_b' => '2'],
            ['x' => '1', 'y' => '2']
        );

        $request = $flowData->evidence->get('server.http-request');

        $this->assertInstanceOf(HttpRequest::class, $request);
        $this->assertSame('agent', $request->getHeaders()['user-agent']);
        $this->assertSame('custom', $request->getHeaders()['x-custom']);
        $this->assertSame('a=1; 51D_b=2', $request->getHeaders()['cookie']);
        $this->assertSame(['a' => '1', '51D_b' => '2'], $request->getCookies());
        $this->assertSame(['x' => '1', 'y' => '2'], $request->getQuery());
        $this->assertSame('203.0.113.9', $request->getClientIp());
        $this->assertSame('https', $request->getProtocol());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/a%20b/page', $request->getPath());
    }

    public function testProtocolIsTheRequestsOwnNotTheReferers()
    {
        $server = $this->server();
        unset($server['HTTPS']);
        $server['HTTP_REFERER'] = 'https://example.com/page';
        $flowData = $this->flowData();
        $flowData->evidence->setFromWebRequest($server, [], []);

        $this->assertSame(
            'http',
            $flowData->evidence->get('server.http-request')->getProtocol()
        );
    }

    public function testSessionValuesAreInTheView()
    {
        $saved = $_SESSION ?? null;
        $_SESSION = ['51D_s' => 'v'];

        try {
            $flowData = $this->flowData();
            $flowData->evidence->setFromWebRequest($this->server(), [], []);
            $request = $flowData->evidence->get('server.http-request');
        } finally {
            if ($saved === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $saved;
            }
        }

        $this->assertSame(['51D_s' => 'v'], $request->getSession());
    }

    public function testViewIsAddedOnEveryRequest()
    {
        $first = $this->flowData();
        $first->evidence->setFromWebRequest(['REQUEST_METHOD' => 'GET'], [], []);
        $second = $this->flowData();
        $second->evidence->setFromWebRequest($this->server(), [], []);

        $this->assertSame('GET', $first->evidence->get('server.http-request')->getMethod());
        $this->assertSame('POST', $second->evidence->get('server.http-request')->getMethod());
        $this->assertSame('', $first->evidence->get('server.http-request')->getPath());
    }

    public function testViewIsNotPartOfTheEvidenceElementsFilter()
    {
        $flowData = $this->flowData();
        $flowData->evidence->setFromWebRequest($this->server(), [], []);

        $this->assertArrayNotHasKey('server.http-request', $flowData->evidence->getAll());
    }

    public function testViewIsReadOnly()
    {
        $request = new HttpRequest(['h' => 'v'], [], [], [], '1.2.3.4', 'http', 'GET', '/');
        $headers = $request->getHeaders();
        $headers['h'] = 'changed';

        $this->assertSame(['h' => 'v'], $request->getHeaders());
        $this->expectException(\Error::class);
        $request->headers = [];
    }
}
