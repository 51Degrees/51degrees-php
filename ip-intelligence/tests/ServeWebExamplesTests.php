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

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use PHPUnit\Framework\TestCase;

/**
 * The launcher starts an on-premise web example with the PHP built-in
 * server and the data file it finds, the free Lite file downloaded when
 * there is none, so the example runs from a fresh clone with nothing set.
 * PHP starts with the system's own settings and a php.ini that only loads
 * the extensions the page needs, leaving out the package's php.ini, which
 * names a data file, and the environment variables that name one.
 */
class ServeWebExamplesTests extends TestCase
{
    // Long enough for the launcher to download the Lite file, or for the
    // server to load the largest data file CI has into memory, before it
    // answers.
    private const SERVER_START_SECONDS = 300;

    // The Device Detection extension as its package's build-project.ps1
    // builds it, which the mixed page needs as well.
    private const DEVICE_DETECTION_EXTENSION = __DIR__ .
        '/../../device-detection/onpremise/on-premise/modules/FiftyOneDegreesHashEngine.so';

    // The variable naming the Device Detection data file, which the
    // launcher reads for the mixed page.
    private const DD_PATH_VARIABLE = '_51DEGREES_DD_PATH';

    /** @var array<string> The folders holding the php.ini files written */
    private static $folders = [];

    /** @var string The IP Intelligence extension */
    private $extension;

    public static function tearDownAfterClass(): void
    {
        foreach (self::$folders as $folder) {
            @unlink($folder . '/fiftyone.ini');
            @rmdir($folder);
        }
        self::$folders = [];
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.'
            );
        }
        $this->extension = $this->ipIntelligenceExtension();
    }

    public function testWebRunsWithNothingSet(): void
    {
        $port = $this->freePort();
        [$status, $page, $printed] = $this->serve([
            PHP_BINARY,
            'examples/onpremise/serve.php',
            'web',
            (string) $port
        ], $port, [$this->extension]);
        $this->assertNotNull(
            $page,
            "The launcher's server did not answer.\n{$printed}"
        );
        $this->assertStringContainsString(' 200 ', $status, $page);
        $this->assertStringContainsString('Registered Name:</td>', $page);
        $this->assertStringNotContainsString(
            'The lookup could not be completed',
            $page
        );
    }

    /**
     * The mixed page, with both extensions loaded and no data file named
     * for either, gets both data files from the launcher and shows both
     * results.
     */
    public function testWebMixedRunsWithNothingSet(): void
    {
        $deviceDetection = realpath(self::DEVICE_DETECTION_EXTENSION);
        if ($deviceDetection === false) {
            // CI builds both extensions wherever it runs these tests, so a
            // missing one there is a failure, not a reason to skip.
            $message = self::DEVICE_DETECTION_EXTENSION . ' has not been built.';
            if (getenv('GITHUB_ACTIONS') === 'true') {
                $this->fail($message);
            }
            $this->markTestSkipped($message);
        }
        $port = $this->freePort();
        [$status, $page, $printed] = $this->serve([
            PHP_BINARY,
            'examples/onpremise/serve.php',
            'web-mixed',
            (string) $port
        ], $port, [$deviceDetection, $this->extension]);
        $this->assertNotNull(
            $page,
            "The launcher's server did not answer.\n{$printed}"
        );
        $this->assertStringContainsString(' 200 ', $status, $page);
        $this->assertStringNotContainsString('c-eg-alert', $page);
        $this->assertStringContainsString('Platform Name:</td>', $page);
        $this->assertStringContainsString('Registered Name:</td>', $page);
    }

    /**
     * Starts the command in the package's folder, with PHP loading the
     * extensions, and returns the status line and page the server on the
     * port answers with, or an empty status and null, and what the command
     * printed. The command and the server it started are stopped.
     *
     * @param array<string> $command
     * @param array<string> $extensions
     * @return array{0: string, 1: ?string, 2: string}
     */
    private function serve(array $command, int $port, array $extensions): array
    {
        $log = (string) tempnam(sys_get_temp_dir(), 'ipi-web');
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'w'],
                2 => ['file', $log, 'a']
            ],
            $pipes,
            __DIR__ . '/..',
            $this->environment($this->settingsFolder($extensions))
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        try {
            [$status, $page] = $this->getPage($process, $port);
        } finally {
            // The launcher runs the server as its own child.
            $pid = proc_get_status($process)['pid'];
            exec('pkill -TERM -P ' . (int) $pid . ' 2>/dev/null');
            proc_terminate($process);
            proc_close($process);
            $printed = (string) file_get_contents($log);
            unlink($log);
        }

        return [$status, $page, $printed];
    }

    /**
     * The environment of the PHP started. Its settings folders are the
     * system's own and the one holding the php.ini that loads the
     * extensions. The variables that name a data file are left out.
     *
     * @return array<string, string>
     */
    private function environment(string $settings): array
    {
        $environment = getenv();
        unset(
            $environment[ExampleConstants::IPI_PATH_VARIABLE],
            $environment[ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR],
            $environment[self::DD_PATH_VARIABLE]
        );
        $environment['PHP_INI_SCAN_DIR'] = implode(
            PATH_SEPARATOR,
            array_filter([PHP_CONFIG_FILE_SCAN_DIR, $settings])
        );

        return $environment;
    }

    /**
     * A new folder holding a php.ini that loads the extensions, in order.
     *
     * @param array<string> $extensions
     */
    private function settingsFolder(array $extensions): string
    {
        $folder = sys_get_temp_dir() . '/fiftyone-ipi-' . getmypid() . '-' .
            count(self::$folders);
        if (!is_dir($folder)) {
            mkdir($folder);
        }
        $lines = '';
        foreach ($extensions as $extension) {
            $lines .= 'extension = "' . $extension . "\"\n";
        }
        file_put_contents($folder . '/fiftyone.ini', $lines);
        self::$folders[] = $folder;

        return $folder;
    }

    /**
     * The IP Intelligence extension, loaded the way the PHP running the
     * tests loads it, from the extension line of a php.ini that the
     * system's own settings leave out, or else the extension the build
     * leaves in on-premise/modules.
     */
    private function ipIntelligenceExtension(): string
    {
        $extension = null;
        $loaded = php_ini_loaded_file();
        $scanned = php_ini_scanned_files();
        $files = $scanned === false
            ? []
            : array_map('trim', explode(',', $scanned));
        $pattern = '/^\s*extension\s*=\s*["\']?' .
            '([^"\'\r\n;]*FiftyOneDegreesIpIntelligenceEngine[^"\'\r\n;]*)/m';
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
            $extension = realpath(__DIR__ .
                '/../on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so');
            if ($extension === false) {
                $this->markTestSkipped('The built extension was not found.');
            }
        }

        return $extension;
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
            ['http' => ['ignore_errors' => true, 'timeout' => 30]]
        );
        $deadline = microtime(true) + self::SERVER_START_SECONDS;
        while (microtime(true) < $deadline &&
            proc_get_status($server)['running']) {
            $stream = @fopen("http://localhost:{$port}/", 'r', false, $context);
            if ($stream !== false) {
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                $page = (string) stream_get_contents($stream);
                fclose($stream);

                return [(string) ($headers[0] ?? ''), $page];
            }
            usleep(500000);
        }

        return ['', null];
    }
}
