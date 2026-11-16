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
use fiftyone\pipeline\core\tests\classes\FailingJavascriptBuilderElement;
use fiftyone\pipeline\core\tests\classes\FailingJsonBundlerElement;
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
    private const SERVER_START_SECONDS = 30;
    private const SERVER_TRY_MICROSECONDS = 250000;

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

    /**
     * When the Set Headers Element itself failed, no response header is
     * set from it and setting the headers does not throw, so neither the
     * element's missing value message nor an error reaches the browser.
     * PHP from the command line sends no headers, so the request is served
     * by PHP's built-in web server.
     */
    public function testNoResponseHeaderIsSetWhenTheSetHeadersElementFailed(): void
    {
        [$headers, $body] = $this->serve('failed');

        $this->assertSame('DONE', $body);
        $names = self::headerNames($headers);
        $this->assertNotContains('accept-ch', $names);
        $this->assertNotContains('novaluemessage', $names);
        $this->assertNotContains('hasvalue', $names);
    }

    /**
     * The same request with a working Set Headers Element sets the header,
     * which shows that the server answers with the headers the script sets.
     */
    public function testTheResponseHeaderIsSetWhenTheSetHeadersElementWorked(): void
    {
        [$headers, $body] = $this->serve('working');

        $this->assertSame('DONE', $body);
        $this->assertContains('Accept-CH: Sec-CH-UA', $headers);
    }

    /**
     * When the JSON Bundler Element failed, the JSON is an empty object,
     * which the client-side JavaScript reads as no properties, and the
     * response is not kept by the browser.
     */
    public function testTheJsonIsAnEmptyObjectWhenTheJsonBundlerFailed(): void
    {
        $pipeline = (new PipelineBuilder(['suppressProcessExceptions' => true]))
            ->add(new FailingJsonBundlerElement())
            ->build();

        $server = self::server('POST');
        $json = Utils::jsonResponse(self::flowData($pipeline, $server), $server);

        $this->assertSame(200, $json->status);
        $this->assertSame('{}', $json->body);
        $this->assertSame('private, no-cache', $json->headers['Cache-Control']);
        $this->assertArrayNotHasKey('ETag', $json->headers);
    }

    /**
     * When the JavaScript Builder Element failed, the script is empty,
     * which a browser runs without an error, and the response is not kept
     * by the browser.
     */
    public function testTheJavaScriptIsEmptyWhenTheJavaScriptBuilderFailed(): void
    {
        $pipeline = (new PipelineBuilder(['suppressProcessExceptions' => true]))
            ->add(new FailingJavascriptBuilderElement([
                'endpoint' => '/json',
                'minify' => false
            ]))
            ->build();

        $server = self::server('GET');
        $script = Utils::javascriptResponse(
            self::flowData($pipeline, $server),
            $server
        );

        $this->assertSame(200, $script->status);
        $this->assertSame('', $script->body);
        $this->assertSame('private, no-cache', $script->headers['Cache-Control']);
        $this->assertArrayNotHasKey('ETag', $script->headers);
    }

    /**
     * Serves scripts/set-response-header.php with PHP's built-in web server
     * and requests it with the element query value given. PHP's own
     * messages, such as the deprecation notices Mustache gives on PHP 8.4
     * and later, go to the server's log rather than the body, so the body
     * is what the script writes whatever the php.ini of the machine says.
     *
     * @return array{0: array<int, string>, 1: string} The response header
     * lines and the body
     */
    private function serve(string $element): array
    {
        $port = $this->freePort();
        $log = (string) tempnam(sys_get_temp_dir(), 'set-header');
        $server = proc_open(
            [
                PHP_BINARY,
                '-d',
                'display_errors=0',
                '-d',
                'log_errors=1',
                '-S',
                "127.0.0.1:{$port}",
                'set-response-header.php'
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            __DIR__ . '/scripts'
        );
        $this->assertIsResource($server);
        fclose($pipes[0]);
        try {
            $response = $this->request($server, $port, $element);
        } finally {
            proc_terminate($server);
            proc_close($server);
            $printed = (string) file_get_contents($log);
            unlink($log);
        }
        $this->assertNotNull($response, "The server did not answer:\n" . $printed);

        return $response;
    }

    /**
     * A port nothing is listening on.
     */
    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /**
     * The response, waiting for the server to start, or null where it
     * stops or does not answer in time.
     *
     * @param resource $server
     * @return null|array{0: array<int, string>, 1: string}
     */
    private function request($server, int $port, string $element): ?array
    {
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
            'timeout' => self::SERVER_START_SECONDS
        ]]);
        $url = "http://127.0.0.1:{$port}/?element={$element}";
        $deadline = microtime(true) + self::SERVER_START_SECONDS;
        while (microtime(true) < $deadline && proc_get_status($server)['running']) {
            $stream = @fopen($url, 'r', false, $context);
            if ($stream !== false) {
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $body = (string) stream_get_contents($stream);
                fclose($stream);

                return [array_map('strval', (array) $headers), $body];
            }
            usleep(self::SERVER_TRY_MICROSECONDS);
        }

        return null;
    }

    /**
     * The lower case name of each header line, leaving out the status line.
     *
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    private static function headerNames(array $headers): array
    {
        $names = [];
        foreach ($headers as $line) {
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $names[] = strtolower(trim(substr($line, 0, $colon)));
            }
        }

        return $names;
    }
}
