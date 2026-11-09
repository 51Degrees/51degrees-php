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

use Composer\Autoload\ClassLoader;
use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\core\tests\classes\ClientSideApp;
use fiftyone\pipeline\core\tests\classes\SwitchableElement;
use PHPUnit\Framework\TestCase;

/**
 * The cache headers of the client-side JavaScript and JSON responses, as
 * the cache header detail of the web integration specification sets them.
 */
class ClientSideResponseTests extends TestCase
{
    private const CACHEABLE = 'max-age=1800, private';
    private const NOT_CACHEABLE = 'private, no-cache';

    /**
     * A weak entity tag of RFC 9110 section 8.8.3, because the ETag depends
     * on the Evidence alone and the response can change while the Evidence
     * does not.
     */
    private const WEAK_ETAG = '/^W\/"[0-9a-f]{32}"$/D';

    private const SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        . 'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 '
        . 'Safari/605.1.15';

    private ClientSideApp $app;

    protected function setUp(): void
    {
        SwitchableElement::$failing = false;
        $this->app = new ClientSideApp();
    }

    protected function tearDown(): void
    {
        SwitchableElement::$failing = false;
    }

    public function testAGoodResponseMayBeKeptByTheBrowserAlone(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $response = $this->app->respond($method);

            $this->assertSame(200, $response->status, $method);
            $this->assertSame(
                self::CACHEABLE,
                $response->headers['Cache-Control'],
                $method
            );
            $this->assertMatchesRegularExpression(
                self::WEAK_ETAG,
                $response->headers['ETag'] ?? '',
                $method
            );
            $this->assertSame(
                'Host, User-Agent',
                $response->headers['Vary'],
                $method
            );
            $this->assertSame(
                (string) strlen($response->body),
                $response->headers['Content-Length'],
                $method
            );
        }
    }

    public function testTheContentTypeSaysWhichEndpointAnswered(): void
    {
        $script = $this->app->respond('GET');
        $results = $this->app->respond('POST');

        $this->assertSame(
            'application/x-javascript',
            $script->headers['Content-Type']
        );
        $this->assertStringContainsString('fiftyoneDegreesManager', $script->body);
        $this->assertSame('application/json', $results->headers['Content-Type']);
        $this->assertSame(
            42,
            json_decode($results->body, true)['switchable']['answer']
        );
    }

    public function testTheEtagFollowsTheEvidence(): void
    {
        $first = $this->app->respond('GET')->headers['ETag'];
        $again = $this->app->respond('GET')->headers['ETag'];
        $other = $this->app->respond(
            'GET',
            ['HTTP_USER_AGENT' => self::SAFARI]
        )->headers['ETag'];

        $this->assertSame($first, $again);
        $this->assertNotSame($first, $other);
    }

    public function testTheEtagIsTheSameFromTwoPipelines(): void
    {
        $this->assertMatchesRegularExpression(
            self::WEAK_ETAG,
            ClientSideApp::scriptEtag()
        );
        $this->assertSame(
            ClientSideApp::scriptEtag(),
            ClientSideApp::scriptEtag()
        );
    }

    public function testTheEtagIsTheSameInEveryProcess(): void
    {
        $autoload = dirname(
            (new \ReflectionClass(ClassLoader::class))->getFileName(),
            2
        ) . '/autoload.php';
        $code = 'require $argv[1]; echo '
            . '\fiftyone\pipeline\core\tests\classes\ClientSideApp::scriptEtag();';
        $etags = [];
        foreach ([1, 2] as $run) {
            $etags[] = trim((string) shell_exec(
                escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code)
                    . ' -- ' . escapeshellarg($autoload)
            ));
        }

        $this->assertMatchesRegularExpression(self::WEAK_ETAG, $etags[0]);
        $this->assertSame($etags[0], $etags[1]);
        $this->assertSame(ClientSideApp::scriptEtag(), $etags[0]);
    }

    /**
     * The Sequence Element gives a request with no session id a new one
     * while processing, and the script carries it. The ETag covers only the
     * Evidence the request supplied, so it is the same for both requests.
     */
    public function testTheSessionIdTheSequenceElementAddsIsNotInTheEtag(): void
    {
        $responses = [
            $this->app->respond('GET'),
            $this->app->respond('GET'),
        ];
        $sessionIds = array_map(function ($response) {
            $this->assertSame(
                1,
                preg_match('/sessionId = "([^"]*)"/', $response->body, $found)
            );

            return $found[1];
        }, $responses);

        $this->assertNotSame('', $sessionIds[0]);
        $this->assertNotSame($sessionIds[0], $sessionIds[1]);
        $this->assertMatchesRegularExpression(
            self::WEAK_ETAG,
            $responses[0]->headers['ETag']
        );
        $this->assertSame(
            $responses[0]->headers['ETag'],
            $responses[1]->headers['ETag']
        );
    }

    /**
     * Values of If-None-Match, each with whether it matches the ETag by RFC
     * 9110 section 13.1.2. A value that is neither "*" nor a list of entity
     * tags matches nothing, even when it holds the ETag.
     *
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function ifNoneMatchCases(string $etag): array
    {
        $quoted = substr($etag, strlen('W/'));

        return [
            // The weak comparison ignores W/ on either side.
            [$etag, true],
            [$quoted, true],
            ['*', true],
            ['"other", ' . $etag, true],
            ['W/"other", ' . $quoted, true],
            // A list may hold a comma inside a tag, a tab or no space
            // around a comma, and empty elements (RFC 9110 section 5.6.1.2).
            ['W/"a,b", ' . $etag, true],
            ["\"other\",\t" . $etag, true],
            ["\"other\"\t," . $etag, true],
            ['"other",' . $etag, true],
            [', ' . $etag . ' ,', true],
            ['"other"', false],
            ['W/"other", "another"', false],
            ['', false],
            // Not a list of entity tags.
            [$etag . ', other', false],
            ['other, ' . $etag, false],
            ['"other" ' . $etag, false],
            ['"other"' . $etag, false],
            [$etag . ' "other"', false],
            [$etag . ', "a b"', false],
            [substr($quoted, 0, -1) . ' "', false],
            ['w/' . $quoted, false],
            ['W/ ' . $quoted, false],
            ['*, ' . $etag, false],
            [$etag . ', *', false],
            [substr($quoted, 1, -1), false],
            [substr($quoted, 0, -1), false],
        ];
    }

    public function testIfNoneMatchOnAGetIsComparedWeakly(): void
    {
        $etag = $this->app->respond('GET')->headers['ETag'];
        $this->assertMatchesRegularExpression(self::WEAK_ETAG, $etag);

        foreach (self::ifNoneMatchCases($etag) as [$value, $matches]) {
            $response = $this->app->respond(
                'GET',
                ['HTTP_IF_NONE_MATCH' => $value]
            );

            $this->assertSame($matches ? 304 : 200, $response->status, $value);
            $this->assertSame($etag, $response->headers['ETag'] ?? null, $value);
        }
    }

    public function testIfNoneMatchOnAPostIsComparedWeakly(): void
    {
        $etag = $this->app->respond('POST')->headers['ETag'];
        $this->assertMatchesRegularExpression(self::WEAK_ETAG, $etag);

        foreach (self::ifNoneMatchCases($etag) as [$value, $matches]) {
            $response = $this->app->respond(
                'POST',
                ['HTTP_IF_NONE_MATCH' => $value]
            );

            if ($matches) {
                $this->assertSame(412, $response->status, $value);
                $this->assertSame('', $response->body, $value);
                $this->assertSame([], $response->headers, $value);
            } else {
                $this->assertSame(200, $response->status, $value);
                $this->assertSame(
                    42,
                    json_decode($response->body, true)['switchable']['answer'],
                    $value
                );
            }
        }
    }

    /**
     * A 304 carries the headers a 200 would, as RFC 9110 section 15.4.5
     * asks, and no content.
     */
    public function testRevalidationWithTheEtagIsAnsweredNotModified(): void
    {
        $good = $this->app->respond('GET');

        foreach (['GET', 'HEAD'] as $method) {
            $response = $this->app->respond($method, [
                'HTTP_IF_NONE_MATCH' => $good->headers['ETag'],
                // A browser that reloads the page sends this as well, and it
                // is not Evidence.
                'HTTP_CACHE_CONTROL' => 'max-age=0',
            ]);

            $this->assertSame(304, $response->status, $method);
            $this->assertSame('', $response->body, $method);
            $this->assertSame([
                'Cache-Control' => $good->headers['Cache-Control'],
                'Vary' => $good->headers['Vary'],
                'ETag' => $good->headers['ETag'],
            ], $response->headers, $method);
        }
    }

    public function testAHeadRequestGetsTheHeadersWithoutTheContent(): void
    {
        $get = $this->app->respond('GET');
        $head = $this->app->respond('HEAD');

        $this->assertSame(200, $head->status);
        $this->assertSame('', $head->body);
        $this->assertSame(
            $get->headers['Content-Length'],
            $head->headers['Content-Length']
        );
        $this->assertSame($get->headers['ETag'], $head->headers['ETag']);
    }

    public function testAResponseToAFailedRequestCarriesNoEtag(): void
    {
        SwitchableElement::$failing = true;

        foreach (['GET', 'POST'] as $method) {
            $response = $this->app->respond($method);

            $this->assertSame(200, $response->status, $method);
            $this->assertSame(
                self::NOT_CACHEABLE,
                $response->headers['Cache-Control'],
                $method
            );
            $this->assertArrayNotHasKey('ETag', $response->headers, $method);
        }
    }

    /**
     * The ETag depends on the Evidence alone, so a matching If-None-Match
     * gets the same answer while processing fails, and a browser keeps the
     * good copy it holds.
     */
    public function testAMatchingEtagIsAnsweredWhileProcessingFails(): void
    {
        $etags = [
            'GET' => $this->app->respond('GET')->headers['ETag'],
            'POST' => $this->app->respond('POST')->headers['ETag'],
        ];
        SwitchableElement::$failing = true;

        $this->assertSame(304, $this->app->respond(
            'GET',
            ['HTTP_IF_NONE_MATCH' => $etags['GET']]
        )->status);
        $this->assertSame(412, $this->app->respond(
            'POST',
            ['HTTP_IF_NONE_MATCH' => $etags['POST']]
        )->status);
    }

    /**
     * The browser kept the failed response with no ETag, so it has nothing
     * to revalidate with and asks again in full.
     */
    public function testAFailedResponseIsReplacedOnceProcessingWorks(): void
    {
        SwitchableElement::$failing = true;
        $failed = $this->app->respond('GET');
        SwitchableElement::$failing = false;

        $headers = [];
        if (isset($failed->headers['ETag'])) {
            $headers['HTTP_IF_NONE_MATCH'] = $failed->headers['ETag'];
        }
        $response = $this->app->respond('GET', $headers);

        $this->assertSame(200, $response->status);
        $this->assertSame(self::CACHEABLE, $response->headers['Cache-Control']);
    }

    public function testTheMaxAgeCanBeSet(): void
    {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_USER_AGENT' => ClientSideApp::CHROME,
        ];
        $flowData = $this->app->pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest($server, [], []);

        $response = Utils::javascriptResponse($flowData, $server, 60);

        $this->assertSame('max-age=60, private', $response->headers['Cache-Control']);
    }

    /**
     * Processing adds Evidence, so the ETag cannot be calculated from a
     * processed FlowData.
     */
    public function testAProcessedFlowDataIsRefused(): void
    {
        $server = ['REQUEST_METHOD' => 'GET'];
        $flowData = $this->app->pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest($server, [], []);
        $flowData->process();

        $this->expectException(\LogicException::class);
        Utils::javascriptResponse($flowData, $server);
    }

    public function testSendWritesTheContent(): void
    {
        $response = $this->app->respond('GET');
        $written = [];

        $response->send(function (string $body) use (&$written) {
            $written[] = $body;
        });

        $this->assertSame([$response->body], $written);
    }
}
