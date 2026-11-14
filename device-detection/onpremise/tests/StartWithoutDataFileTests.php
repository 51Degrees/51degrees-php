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
use fiftyone\pipeline\devicedetection\Messages;
use PHPUnit\Framework\TestCase;

/**
 * PHP starts with the extension whatever the data file setting names,
 * DeviceDetectionOnPremise says which setting to set when PHP built no
 * engine, and every on-premise example runs with nothing set. Each case
 * starts its own PHP with the system's own settings and a php.ini that only
 * loads the extension, leaving out the package's php.ini, which names a data
 * file, and the environment variables that name one. The extension is loaded
 * from a settings folder rather than the command line, so a PHP an example
 * starts again loads it too.
 */
class StartWithoutDataFileTests extends TestCase
{
    private const SETTING = 'FiftyOneDegreesHashEngine.data_file';

    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const MISSING_DATA_FILE = __DIR__ . '/missing/51Degrees.hash';

    // Prints what engine_get() gives.
    private const ENGINE =
        'var_export(FiftyOneDegreesHashEngine::engine_get());';

    // Prints the message DeviceDetectionOnPremise refuses with.
    private const REFUSAL = <<<'PHP'
        try {
            new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
            echo 'No exception';
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        PHP;

    private const SERVER_START_SECONDS = 30;

    /** @var null|string The folder holding the php.ini that loads the extension */
    private static $settings;

    public static function tearDownAfterClass(): void
    {
        if (self::$settings !== null) {
            @unlink(self::$settings . '/fiftyone-hash.ini');
            @rmdir(self::$settings);
            self::$settings = null;
        }
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesHashEngine extension is not loaded.'
            );
        }
        if (self::$settings === null) {
            self::$settings = $this->extensionSettings();
        }
    }

    public function testPhpStartsWithNoDataFileSetting()
    {
        [$status, $printed] = $this->runPhp(['-r', self::ENGINE]);
        $this->assertSame(0, $status, $printed);
        $this->assertSame('NULL', $printed);
    }

    public function testPhpStartsWithAnEmptyDataFileSetting()
    {
        [$status, $printed] = $this->runPhp(
            ['-d', self::SETTING . '=', '-r', self::ENGINE]
        );
        $this->assertSame(0, $status, $printed);
        $this->assertSame('NULL', $printed);
    }

    public function testPhpStartsWhenTheDataFileIsMissing()
    {
        [$status, $printed] = $this->runPhp([
            '-d', self::SETTING . '=' . self::MISSING_DATA_FILE,
            '-r', self::ENGINE
        ]);
        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith('NULL', $printed);
        $this->assertStringContainsString(
            "There is no data file at '" . self::MISSING_DATA_FILE .
            "', which " . self::SETTING . ' names, so no device detection ' .
            'engine was built.',
            $printed
        );
    }

    /**
     * A file that is not a data file, such as the pointer git leaves in
     * place of a large file it has not fetched.
     */
    public function testPhpStartsWhenTheDataFileCannotBeLoaded()
    {
        $file = $this->notADataFile();
        try {
            [$status, $printed] = $this->runPhp(
                ['-d', self::SETTING . '=' . $file, '-r', self::ENGINE]
            );
        } finally {
            unlink($file);
        }
        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith('NULL', $printed);
        $this->assertStringContainsString(
            "No device detection engine was built from '{$file}', which " .
            self::SETTING . ' names.',
            $printed
        );
    }

    public function testRefusesWithNoDataFileSetting()
    {
        [$status, $printed] = $this->refusal([]);
        $this->assertSame(0, $status, $printed);
        $this->assertSame(
            sprintf(Messages::NO_ENGINE, Messages::DATA_FILE_NOT_SET),
            $printed
        );
        $this->assertStringContainsString(
            'Set ' . self::SETTING . ' in php.ini',
            $printed
        );
    }

    public function testRefusesWhenTheDataFileIsMissing()
    {
        [$status, $printed] = $this->refusal(
            ['-d', self::SETTING . '=' . self::MISSING_DATA_FILE]
        );
        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith(
            sprintf(
                Messages::NO_ENGINE,
                sprintf(Messages::DATA_FILE_MISSING, self::MISSING_DATA_FILE)
            ),
            $printed
        );
    }

    public function testRefusesWhenTheDataFileCannotBeLoaded()
    {
        $file = $this->notADataFile();
        try {
            [$status, $printed] = $this->refusal(
                ['-d', self::SETTING . '=' . $file]
            );
        } finally {
            unlink($file);
        }
        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith(
            sprintf(
                Messages::NO_ENGINE,
                sprintf(Messages::DATA_FILE_NOT_LOADED, $file)
            ),
            $printed
        );
    }

    public function testRefusesWithoutTheExtension()
    {
        [$status, $printed] = $this->refusal([], false);
        $this->assertSame(0, $status, $printed);
        $this->assertSame(Messages::EXTENSION_NOT_LOADED, $printed);
    }

    public function testGettingStartedConsoleRunsWithNothingSet()
    {
        $this->runExampleWithNothingSet(
            'gettingStartedConsole.php',
            'Results:'
        );
    }

    public function testMetadataConsoleRunsWithNothingSet()
    {
        $this->runExampleWithNothingSet(
            'metadataConsole.php',
            'Accepted evidence keys:'
        );
    }

    public function testMatchMetricsRunsWithNothingSet()
    {
        $this->runExampleWithNothingSet(
            'matchMetrics.php',
            'Matched User-Agent:'
        );
    }

    public function testFailureToMatchRunsWithNothingSet()
    {
        $this->runExampleWithNothingSet(
            'failureToMatch.php',
            'Does User-Agent'
        );
    }

    public function testManualDataUpdateRunsWithNothingSet()
    {
        $this->runExampleWithNothingSet(
            'manualDataUpdate.php',
            'Is User-Agent'
        );
    }

    /**
     * A web server cannot be started again from a request, so the web
     * example answers with a page saying what to set.
     */
    public function testWebExampleSaysWhatToSet()
    {
        $port = $this->freePort();
        $log = tempnam(sys_get_temp_dir(), 'dd-web');
        $server = proc_open(
            [
                PHP_BINARY,
                '-S',
                "127.0.0.1:{$port}",
                'examples/onpremise/gettingStartedWeb.php'
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'w'],
                2 => ['file', $log, 'a']
            ],
            $pipes,
            __DIR__ . '/..',
            $this->environment(true)
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
        $this->assertNotNull($page, "The server did not answer.\n{$printed}");
        $this->assertStringContainsString(' 500 ', $status, $page);
        $this->assertStringContainsString(
            '<code>' . self::SETTING . '</code> in php.ini',
            $page
        );
        $this->assertStringContainsString('51Degrees-LiteV4.1.hash', $page);
    }

    /**
     * Settings the engine cannot be built with leave PHP running with no
     * engine and a warning that gives the reason. It runs after the
     * examples, so that they are the first to need the Lite file.
     */
    public function testPhpStartsWithSettingsTheEngineCannotUse()
    {
        $dataFile = $this->liteDataFile();
        [$status, $printed] = $this->runPhp([
            '-d', self::SETTING . '=' . $dataFile,
            '-d', 'FiftyOneDegreesHashEngine.use_predictive_graph=off',
            '-r', self::ENGINE
        ]);
        $this->assertSame(0, $status, $printed);
        $this->assertStringEndsWith('NULL', $printed);
        $this->assertStringContainsString(
            "No device detection engine was built from '{$dataFile}', " .
            'which ' . self::SETTING . ' names. The configuration provided',
            $printed
        );
    }

    /**
     * Runs an on-premise console example with no data file setting and no
     * data file named, which runs it again with the free Lite file, found
     * or downloaded, and checks it completes and prints the text expected.
     */
    private function runExampleWithNothingSet(string $script, string $expected)
    {
        [$status, $printed] = $this->runPhp(
            [__DIR__ . "/../examples/onpremise/{$script}"]
        );
        $this->assertSame(0, $status, $printed);
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, $printed);
        $this->assertStringContainsString(
            'No device detection engine was built when PHP started, so the ' .
            "example runs again in a PHP that builds one from {$dataFile}.",
            $printed
        );
        $this->assertStringContainsString($expected, $printed);
    }

    /**
     * Constructs DeviceDetectionOnPremise in a PHP started with the options,
     * and returns its exit code and what it printed, the refusal last.
     *
     * @param array<string> $options
     * @return array{0: int, 1: string}
     */
    private function refusal(array $options, bool $extension = true): array
    {
        $autoload = __DIR__ . '/../vendor/autoload.php';

        return $this->runPhp(array_merge($options, [
            '-r',
            'require ' . var_export($autoload, true) . ';' . self::REFUSAL
        ]), $extension);
    }

    /**
     * Runs PHP with the options, and returns its exit code and what it
     * printed, standard error included.
     *
     * @param array<string> $options
     * @return array{0: int, 1: string}
     */
    private function runPhp(array $options, bool $extension = true): array
    {
        $command = implode(' ', array_map(
            'escapeshellarg',
            array_merge([PHP_BINARY], $options)
        )) . ' 2>&1';
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w']],
            $pipes,
            null,
            $this->environment($extension)
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $printed = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);

        return [$status, trim($printed)];
    }

    /**
     * The environment of each PHP started. Its settings folders are the
     * system's own and, when the extension is wanted, the one holding the
     * php.ini that loads it. The variables that name a data file are left
     * out.
     *
     * @return array<string, string>
     */
    private function environment(bool $extension): array
    {
        $environment = getenv();
        unset(
            $environment[ExampleUtils::DD_PATH_VARIABLE],
            $environment[ExampleUtils::DATA_FILE_ENV_VAR]
        );
        $folders = array_filter([
            PHP_CONFIG_FILE_SCAN_DIR,
            $extension ? self::$settings : null
        ]);
        $environment['PHP_INI_SCAN_DIR'] =
            implode(PATH_SEPARATOR, $folders);

        return $environment;
    }

    /**
     * A new folder holding a php.ini that loads the extension the way the
     * PHP running the tests loads it, from the extension line of a php.ini
     * that the system's own settings leave out, or else the extension the
     * build leaves in on-premise/modules.
     */
    private function extensionSettings(): string
    {
        $extension = null;
        $loaded = php_ini_loaded_file();
        $scanned = php_ini_scanned_files();
        $files = $scanned === false
            ? []
            : array_map('trim', explode(',', $scanned));
        $pattern = '/^\s*extension\s*=\s*["\']?' .
            '([^"\'\r\n;]*FiftyOneDegreesHashEngine[^"\'\r\n;]*)/m';
        foreach (array_merge([$loaded], $files) as $file) {
            if (!is_string($file) || $file === '' ||
                preg_match(
                    $pattern,
                    (string) @file_get_contents($file),
                    $match
                ) !== 1) {
                continue;
            }
            $folder = PHP_CONFIG_FILE_SCAN_DIR;
            if ($file === $loaded ||
                ($folder !== '' && strpos($file, $folder) === 0)) {
                $this->markTestSkipped(
                    'The system\'s own php.ini loads the extension, so PHP ' .
                    'cannot be started with the extension and no data file ' .
                    'setting of its own.'
                );
            }
            $extension = trim($match[1]);
            break;
        }
        if ($extension === null) {
            $extension = realpath(
                __DIR__ . '/../on-premise/modules/FiftyOneDegreesHashEngine.so'
            );
            if ($extension === false) {
                $this->markTestSkipped('The built extension was not found.');
            }
        }

        $folder = sys_get_temp_dir() . '/fiftyone-hash-' . getmypid();
        if (!is_dir($folder)) {
            mkdir($folder);
        }
        file_put_contents(
            $folder . '/fiftyone-hash.ini',
            'extension = "' . $extension . "\"\n"
        );

        return $folder;
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
     * A small file that is not a data file.
     */
    private function notADataFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'dd-not-data');
        file_put_contents(
            $file,
            "version https://git-lfs.github.com/spec/v1\n" .
            'oid sha256:' . str_repeat('0', 64) . "\n" .
            "size 36000000\n"
        );

        return $file;
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
     * The status line and page the server answers with, waiting for it to
     * start, or an empty status and null when it stops or does not answer
     * in time.
     *
     * @param resource $server
     * @return array{0: string, 1: ?string}
     */
    private function getPage($server, int $port): array
    {
        $context = stream_context_create(
            ['http' => ['ignore_errors' => true, 'timeout' => 10]]
        );
        $deadline = microtime(true) + self::SERVER_START_SECONDS;
        while (microtime(true) < $deadline &&
            proc_get_status($server)['running']) {
            $stream = @fopen("http://127.0.0.1:{$port}/", 'r', false, $context);
            if ($stream !== false) {
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $page = (string) stream_get_contents($stream);
                fclose($stream);

                return [(string) ($headers[0] ?? ''), $page];
            }
            usleep(250000);
        }

        return ['', null];
    }
}
