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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\tests\classes\ExampleRun;
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\TestCase;

class ExampleTests extends TestCase
{
    // How long to wait for the built-in web server to answer once started,
    // and how long to wait between tries.
    private const SERVER_START_SECONDS = 30;
    private const SERVER_TRY_MICROSECONDS = 250000;

    public function testCustomFlowElementExample()
    {
        ob_start(); // hide output
        include __DIR__ . '/../examples/CustomFlowElement.php';
        ob_end_clean(); // discard output

        $this->assertTrue(strpos($output, 'Starsign') !== false);
    }

    public function testPipelineExample()
    {
        ob_start(); // hide output
        include __DIR__ . '/../examples/Pipeline.php';
        ob_end_clean(); // discard output

        $this->assertTrue($flowData->example1->exampleProperty1 === 5);
    }

    /**
     * The page, rendered in its own PHP for a request for the home page,
     * ends with the links to find out more.
     */
    public function testCustomFlowElementEndsWithFindOutMore()
    {
        $run = $this->runExample('CustomFlowElement.php', ExampleRun::WEB_REQUEST);
        $run->assertPageEndsWithFindOutMore();
    }

    /**
     * The example, run in its own PHP, ends with the links to find out more.
     */
    public function testPipelineEndsWithFindOutMore()
    {
        $run = $this->runExample('Pipeline.php', []);
        $run->assertEndsWithFindOutMore();
    }

    /**
     * The page, served from the examples folder by PHP's built-in web
     * server as the readme says, answers the home page and ends with the
     * links to find out more.
     */
    public function testCustomFlowElementServedAsTheReadmeSays()
    {
        $port = $this->freePort();
        $log = (string) tempnam(sys_get_temp_dir(), 'example');
        $server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", 'CustomFlowElement.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']],
            $pipes,
            __DIR__ . '/../examples'
        );
        $this->assertIsResource($server);
        fclose($pipes[0]);
        try {
            [$status, $page] = $this->getPage($server, $port);
        } finally {
            proc_terminate($server);
            proc_close($server);
            $printed = (string) file_get_contents($log);
            unlink($log);
        }

        $run = new ExampleRun();
        $run->status = 0;
        $run->output = str_replace("\r\n", "\n", (string) $page);
        $run->errors = $printed;
        $this->assertStringContainsString(' 200 ', $status, $run->printed());
        $this->assertStringContainsString('<h1>Starsign</h1>', $run->output, $run->printed());
        $run->assertPageEndsWithFindOutMore();
    }

    /**
     * Runs an example in its own PHP from the examples folder with nothing
     * loaded first, as a person runs it.
     *
     * @param array<string, string> $set Environment variables to add
     */
    private function runExample(string $script, array $set): ExampleRun
    {
        $run = ExampleRun::run(__DIR__ . "/../examples/{$script}", [], [], $set);
        $this->assertSame(0, $run->status, $run->printed());

        return $run;
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
     * The status line and page the server answers the home page with,
     * waiting for it to start, or an empty status and null where it stops
     * or does not answer in time.
     *
     * @param resource $server
     * @return array{0: string, 1: ?string}
     */
    private function getPage($server, int $port): array
    {
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
            'timeout' => self::SERVER_START_SECONDS
        ]]);
        $deadline = microtime(true) + self::SERVER_START_SECONDS;
        while (microtime(true) < $deadline && proc_get_status($server)['running']) {
            $stream = PhpMessage::take(function () use (&$port, &$context) {
                return fopen("http://127.0.0.1:{$port}/", 'r', false, $context);
            });
            if ($stream !== false) {
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $page = (string) stream_get_contents($stream);
                fclose($stream);

                return [(string) ($headers[0] ?? ''), $page];
            }
            usleep(self::SERVER_TRY_MICROSECONDS);
        }

        return ['', null];
    }
}
