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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Serves the web example with the PHP built-in server, with php.ini's data
 * file set to the Lite file and _51DEGREES_DD_PATH naming another file.
 * The first request reloads the engine from the file
 * the variable names, and every request must then answer from that file.
 * The server runs the example through a router of the same name, which
 * records the data file the engine used for each request.
 */
class WebExampleReloadTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    // Long enough for the server to load the Lite file and listen.
    private const START_SECONDS = 60;

    // Long enough for a request to reload the engine from the Enterprise
    // data file.
    private const ANSWER_SECONDS = 300;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 ' .
        'Safari/537.36';

    // The requests made, in order. The first reloads the engine.
    private const PATHS = ['/', '/', '/json'];

    private const EXAMPLE = __DIR__ .
        '/../examples/onpremise/gettingStartedWeb.php';

    // The router the server runs. Once each request is done, it adds the
    // data file the engine used to the file named first. It has the
    // example's file name, which the example checks before it runs. It is
    // a router rather than auto_prepend_file, which the built-in server
    // does not run before a router script in every PHP version.
    private const ROUTER = <<<'PHP'
        <?php
        register_shutdown_function(function () {
            $engine = FiftyOneDegreesHashEngine::engine_get();
            file_put_contents(
                %s,
                ($engine === null ? '' : $engine->getDataFilePath()) . "\n",
                FILE_APPEND
            );
        });
        require %s;
        PHP;

    /** @var string A folder of this test's own files */
    private $folder;

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesHashEngine extension is not loaded.'
            );
        }
        $this->folder = sys_get_temp_dir() . '/dd-reload-' . getmypid();
        if (!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    protected function tearDown(): void
    {
        if ($this->folder !== null) {
            array_map('unlink', glob($this->folder . '/*') ?: []);
            @rmdir($this->folder);
        }
    }

    /**
     * A copy of the Lite file at another path, which every build has. The
     * page still shows the properties only the Enterprise data file has
     * as needing it.
     */
    public function testReloadsFromACopyOfTheLiteFile()
    {
        $copy = $this->folder . '/' . ExampleUtils::LITE_DATA_FILE_NAME;
        $this->assertTrue(copy($this->liteDataFile(), $copy));

        foreach ($this->serve($copy) as $page) {
            $this->assertStringContainsString(
                'Hardware Vendor:</td><td class="c-eg-table__cell">' .
                ExampleUtils::NEEDS_ENTERPRISE . '</td>',
                $page
            );
        }
    }

    /**
     * The data file php.ini names for the other tests when it is not the
     * Lite file, as CI names the Enterprise data file, so the page shows
     * the properties the Lite file does not have.
     */
    public function testReloadsFromTheDataFilePhpIniNames()
    {
        $named = realpath(
            (string) get_cfg_var(ExampleUtils::DATA_FILE_SETTING)
        );
        if ($named === false || $named === $this->liteDataFile()) {
            $this->markTestSkipped(
                'php.ini names no data file other than the Lite file.'
            );
        }

        foreach ($this->serve($named) as $page) {
            $this->assertStringNotContainsString(
                ExampleUtils::NEEDS_ENTERPRISE,
                $page
            );
        }
    }

    /**
     * Serves the web example with php.ini's data file set to the Lite file
     * and _51DEGREES_DD_PATH naming the data file, makes the requests in
     * PATHS, and checks each answer and that the engine used the data
     * file for each. Returns the pages.
     *
     * @return array<string>
     */
    private function serve(string $dataFile): array
    {
        $record = $this->folder . '/data-files.txt';
        $router = $this->folder . '/' . basename(self::EXAMPLE);
        file_put_contents($router, sprintf(
            self::ROUTER,
            var_export($record, true),
            var_export(realpath(self::EXAMPLE), true)
        ));
        $log = $this->folder . '/server.txt';
        $environment = getenv();
        unset($environment[ExampleUtils::DATA_FILE_ENV_VAR]);
        $environment[ExampleUtils::DD_PATH_VARIABLE] = $dataFile;
        $port = $this->freePort();
        $server = proc_open(
            [
                PHP_BINARY,
                '-d', ExampleUtils::DATA_FILE_SETTING . '=' .
                    $this->liteDataFile(),
                '-S', "localhost:{$port}",
                $router
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'w'],
                2 => ['file', $log, 'a']
            ],
            $pipes,
            __DIR__ . '/..',
            $environment
        );
        $this->assertIsResource($server);
        fclose($pipes[0]);
        $answers = [];
        try {
            foreach (self::PATHS as $path) {
                $answers[] = $this->get($server, $port, $path);
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
        $printed = "Server output:\n" . file_get_contents($log);

        $pages = [];
        foreach (self::PATHS as $i => $path) {
            [$status, $body] = $answers[$i];
            $this->assertNotNull($body, "No answer for {$path}.\n{$printed}");
            $this->assertStringContainsString(' 200 ', $status, $body);
            if ($path === '/json') {
                $json = json_decode($body, true);
                $this->assertIsArray($json, $body);
                $this->assertArrayHasKey('device', $json, $body);
                continue;
            }
            $this->assertStringContainsString(
                'Browser Name:</td><td class="c-eg-table__cell">Chrome</td>',
                $body
            );
            $run = new ExampleRun();
            $run->status = 0;
            $run->output = $body;
            $run->assertPageEndsWithFindOutMore();
            $pages[] = $body;
        }

        $used = file_exists($record)
            ? file($record, FILE_IGNORE_NEW_LINES)
            : [];
        $this->assertSame(
            array_fill(0, count(self::PATHS), realpath($dataFile)),
            array_map('realpath', $used),
            "The engine did not use {$dataFile} for every request.\n" .
            $printed
        );

        return $pages;
    }

    /**
     * The status line and content the server answers the path with,
     * waiting for it to start, or an empty status and null when it stops
     * or does not start in time.
     *
     * @param resource $server
     * @return array{0: string, 1: ?string}
     */
    private function get($server, int $port, string $path): array
    {
        $context = stream_context_create(['http' => [
            'ignore_errors' => true,
            'timeout' => self::ANSWER_SECONDS,
            'header' => 'User-Agent: ' . self::CHROME . "\r\n"
        ]]);
        $deadline = microtime(true) + self::START_SECONDS;
        while (proc_get_status($server)['running']) {
            $stream = @fopen(
                "http://localhost:{$port}{$path}",
                'r',
                false,
                $context
            );
            if ($stream !== false) {
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $body = (string) stream_get_contents($stream);
                fclose($stream);

                return [(string) ($headers[0] ?? ''), $body];
            }
            if (microtime(true) > $deadline) {
                break;
            }
            usleep(250000);
        }

        return ['', null];
    }

    /**
     * The free Lite data file, downloaded when it is missing.
     */
    private function liteDataFile(): string
    {
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE),
            'The Lite data file could not be found or downloaded.'
        );

        return (string) realpath(self::LITE_DATA_FILE);
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
}
