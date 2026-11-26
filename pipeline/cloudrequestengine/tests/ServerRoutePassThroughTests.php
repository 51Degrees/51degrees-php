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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The server route. A publisher's server serves the pipeline's JavaScript
 * to the browser, and the browser posts the answers of the scripts it ran
 * back to the server, which passes them on to the cloud through the Cloud
 * Request Engine. These tests follow that back and forth as a web
 * integration fills the Evidence, and check what reaches the cloud on each
 * iteration: the session id, the sequence, the browser test answers, and
 * the visitor's IP address, JA4 and H2 that the server supplies as query
 * Evidence.
 */
class ServerRoutePassThroughTests extends TestCase
{
    public const resourceKey = 'AAAAAAAAAAAA';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 '
        . 'Safari/537.36';

    // The visitor's connection as the server's TLS terminator saw it. The
    // JA4 is in the form the cloud captures. The H2 is the HTTP/2 SETTINGS
    // text.
    private const VISITOR_IP = '203.0.113.24';
    private const VISITOR_JA4 = 't13d1516h2_8daaf6152771_02713d6af862';
    private const VISITOR_H2 = '1:65536;2:0;4:6291456;6:262144|15663105|0|m,a,s,p';

    // Browser test answers are sent under a name that starts 51D_ and
    // changes with each script, and their value starts with a schema and a
    // bar. A value a snippet stored as a cookie is not posted.
    private const ANSWER_NAME = '51D_k3Jq9xT';
    private const COOKIE_NAME = '51D_Pos_m8Rt';
    private const COOKIE_VALUE = '3|cookie-answer';

    /** @var array<array{string, string, null|string}> */
    private array $requests = [];

    /**
     * Every iteration sends the cloud the session id the script was
     * written with, a sequence one more than the iteration before, and the
     * answers the page posted and stored as cookies, unchanged.
     */
    public function testSessionIdSequenceAndAnswersReachTheCloud(): void
    {
        [$sessionId, $answer] = $this->runIterations(false);
        $bodies = $this->dataRequests();
        $this->assertCount(3, $bodies);

        $first = (int) $bodies[0]['sequence'];
        $this->assertSame(
            [$first, $first + 1, $first + 2],
            array_map(fn ($b) => (int) $b['sequence'], $bodies)
        );
        foreach ($bodies as $body) {
            $this->assertSame($sessionId, $body['session-id']);
            $this->assertSame(self::CHROME, $body['user-agent'] ?? $body['User-Agent'] ?? null);
        }
        foreach (array_slice($bodies, 1) as $body) {
            $this->assertSame($answer, $body[self::ANSWER_NAME] ?? null);
            $this->assertSame(self::COOKIE_VALUE, $body[self::COOKIE_NAME] ?? null);
        }
        $this->assertArrayNotHasKey(self::ANSWER_NAME, $bodies[0]);
    }

    /**
     * A server supplies the visitor's IP address, JA4 and H2 as query
     * Evidence, and the cloud gets them on every iteration under the names
     * it reads, with the supplied address replacing the address the
     * request arrived from.
     */
    public function testTheServerSuppliesTheVisitorsConnection(): void
    {
        $this->runIterations(true);
        $bodies = $this->dataRequests();
        $this->assertCount(3, $bodies);
        foreach ($bodies as $body) {
            $this->assertSame(self::VISITOR_IP, $body['client-ip'] ?? null);
            $this->assertSame(self::VISITOR_JA4, $body['ja4'] ?? null);
            $this->assertSame(self::VISITOR_H2, $body['h2'] ?? null);
        }
    }

    /**
     * With nothing supplied, the address sent is the one the request
     * arrived from, and no JA4 or H2 is sent.
     */
    public function testWithoutSuppliedValuesTheConnectionIsTheRequests(): void
    {
        $this->runIterations(false);
        foreach ($this->dataRequests() as $body) {
            $this->assertSame('127.0.0.1', $body['client-ip'] ?? null);
            $this->assertArrayNotHasKey('ja4', $body);
            $this->assertArrayNotHasKey('h2', $body);
        }
    }

    /**
     * The browser's side of the back and forth. It loads the script, then
     * posts the session id and the sequence the script holds with its
     * answers, twice, as a page does when a snippet's answers lead to
     * another iteration. Each request is filled the way a web integration
     * fills it, from the server variables, the cookies and the query and
     * form fields.
     *
     * @return array{string, string} The session id and the answer posted.
     */
    private function runIterations(bool $supplyConnection): array
    {
        $pipeline = $this->pipeline();
        $answer = '3z|' . str_repeat('aB0-_', 12000);

        $js = $this->process($pipeline, 'GET', [], [], $supplyConnection)
            ->javascriptbuilder->javascript;
        $this->assertSame(1, preg_match('/var sessionId = "([^"]*)";/', $js, $id));
        $this->assertSame(1, preg_match('/var sequence = ([0-9]+);/', $js, $seq));
        $sessionId = $id[1];
        $sequence = (int) $seq[1];

        for ($i = 0; $i < 2; $i++) {
            $this->process(
                $pipeline,
                'POST',
                [self::COOKIE_NAME => self::COOKIE_VALUE],
                [
                    'session-id' => $sessionId,
                    'sequence' => (string) $sequence,
                    self::ANSWER_NAME => $answer,
                ],
                $supplyConnection
            );
            $sequence++;
        }

        return [$sessionId, $answer];
    }

    /**
     * One request through the pipeline, with the Evidence a web
     * integration sets, and the visitor's connection added by the server
     * where asked.
     *
     * @param array<string, string> $cookies
     * @param array<string, string> $query
     */
    private function process(
        Pipeline $pipeline,
        string $method,
        array $cookies,
        array $query,
        bool $supplyConnection
    ) {
        $data = $pipeline->createFlowData();
        $data->evidence->setFromWebRequest(
            [
                'HTTP_USER_AGENT' => self::CHROME,
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => '/json',
            ],
            $cookies,
            $query
        );
        if ($supplyConnection) {
            $data->evidence->set('query.client-ip', self::VISITOR_IP);
            $data->evidence->set('query.ja4', self::VISITOR_JA4);
            $data->evidence->set('query.h2', self::VISITOR_H2);
        }
        $data->process();

        return $data;
    }

    private function pipeline(): Pipeline
    {
        $engine = new CloudRequestEngine([
            'httpClient' => $this->recordingClient(),
            'resourceKey' => self::resourceKey,
        ]);

        return (new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => '/json',
                'minify' => false,
            ],
        ]))->add($engine)->build();
    }

    /**
     * The fields of every data request made, in order, read back from the
     * form encoded body without PHP's renaming of dots and spaces.
     *
     * @return array<int, array<string, string>>
     */
    private function dataRequests(): array
    {
        $bodies = [];
        foreach ($this->requests as [$type, $url, $content]) {
            if (strpos($url, self::resourceKey . '.json') === false) {
                continue;
            }
            $fields = [];
            foreach (explode('&', (string) $content) as $pair) {
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $fields[urldecode($name)] = urldecode($value);
            }
            $bodies[] = $fields;
        }

        return $bodies;
    }

    /**
     * A client that records every request as [method, url, body] and
     * answers each endpoint with canned data.
     */
    private function recordingClient(): HttpClient
    {
        $this->requests = [];
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url, ?string $content): string {
                $this->requests[] = [$type, $url, $content];
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    return '{"Products": {"device": {"Properties": [' .
                        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
                }
                if (strpos($lower, 'evidencekeys') !== false) {
                    return '["query.User-Agent","header.user-agent"]';
                }

                return '{"device":{"ismobile":true}}';
            }
        );

        return $client;
    }
}
