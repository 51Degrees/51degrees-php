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
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;

/**
 * The timeout of a request to the cloud service, the TimeoutSeconds option
 * of the Cloud Request Engine specification, which defaults to 2 seconds.
 * A local server that answers each request only after
 * SERVER_DELAY_SECONDS stands in for a slow cloud service.
 */
class TimeoutTests extends CloudRequestEngineTestsBase
{
    // How long the local server waits before it answers, well beyond every
    // timeout the tests use.
    private const SERVER_DELAY_SECONDS = 8;

    // How long the local server has to start listening.
    private const SERVER_START_SECONDS = 10;

    // How much later than its timeout a request may stop on a busy
    // runner, and still be well before the server answers.
    private const LATE_SECONDS = 4.0;

    // How much earlier than its timeout a request may stop, which a
    // request that failed for any other reason would not meet.
    private const EARLY_SECONDS = 0.25;

    // An address where nothing listens, so that a request an engine makes
    // while it is built fails at once and the build goes on.
    private const REFUSED_ENDPOINT = 'http://127.0.0.1:1/';

    /** @var null|resource */
    private static $server = null;

    private static string $folder = '';

    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        self::$folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'slow-cloud-' . bin2hex(random_bytes(6));
        mkdir(self::$folder);
        $router = self::$folder . DIRECTORY_SEPARATOR . 'router.php';
        file_put_contents(
            $router,
            '<?php sleep(' . self::SERVER_DELAY_SECONDS . '); ' .
            "header('Content-Type: application/json'); " .
            "echo '{\"Products\": {}}';"
        );

        $port = self::freePort();
        $log = self::$folder . DIRECTORY_SEPARATOR . 'server.log';
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'a'],
                2 => ['file', $log, 'a'],
            ],
            $pipes
        );
        self::$baseUrl = 'http://127.0.0.1:' . $port . '/';

        $deadline = microtime(true) + self::SERVER_START_SECONDS;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(100000);
        }
        self::fail('The local server did not start. ' . (string) @file_get_contents($log));
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        foreach (glob(self::$folder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(self::$folder);
    }

    public function testARequestStopsAfterTwoSecondsByDefault(): void
    {
        $client = new HttpClient();

        $this->assertStopsAfter(2.0, function () use ($client): void {
            $client->makeCloudRequest('GET', self::$baseUrl . 'evidencekeys', null, null);
        });
    }

    public function testThePostOfTheDataRequestStopsToo(): void
    {
        $client = new HttpClient();
        $client->setTimeoutSeconds(1);

        $this->assertStopsAfter(1.0, function () use ($client): void {
            $client->makeCloudRequest('POST', self::$baseUrl . 'key.json', 'a=b', null);
        });
    }

    /**
     * The engine's timeoutSeconds setting is the timeout of the requests it
     * makes.
     */
    public function testTheTimeoutSecondsSettingIsUsed(): void
    {
        $engine = new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'cloudEndPoint' => self::$baseUrl,
            'timeoutSeconds' => 1,
        ]);

        $this->assertStopsAfter(1.0, function () use ($engine): void {
            $engine->getEngineProperties();
        });
    }

    /**
     * An HttpClient given to the engine takes the engine's timeoutSeconds
     * setting, and keeps its own timeout when the setting is not given.
     */
    public function testAGivenHttpClientTakesTheSetting(): void
    {
        $client = new HttpClient();
        new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'cloudEndPoint' => self::REFUSED_ENDPOINT,
            'httpClient' => $client,
            'timeoutSeconds' => 5,
        ]);
        $this->assertSame(5.0, $client->getTimeoutSeconds());

        $client->setTimeoutSeconds(3);
        new CloudRequestEngine([
            'resourceKey' => self::resourceKey,
            'cloudEndPoint' => self::REFUSED_ENDPOINT,
            'httpClient' => $client,
        ]);
        $this->assertSame(3.0, $client->getTimeoutSeconds());
    }

    /**
     * A timeout of zero or less would never stop a request, so it is
     * refused when the engine is built, as anything that is not a number
     * is.
     */
    public function testATimeoutThatIsNotAPositiveNumberIsRefused(): void
    {
        foreach ([0, -1, 'two'] as $value) {
            try {
                new CloudRequestEngine([
                    'resourceKey' => self::resourceKey,
                    'httpClient' => $this->mockHttp(),
                    'timeoutSeconds' => $value,
                ]);
                $this->fail(sprintf(
                    'A timeoutSeconds of %s was not refused.',
                    var_export($value, true)
                ));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('timeoutSeconds', $e->getMessage());
            }
        }
    }

    public function testSetTimeoutSecondsRefusesZeroOrLess(): void
    {
        $client = new HttpClient();
        foreach ([0.0, -1.0] as $value) {
            try {
                $client->setTimeoutSeconds($value);
                $this->fail(sprintf('A timeout of %s was not refused.', $value));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('timeoutSeconds', $e->getMessage());
            }
        }
        $this->assertSame(2.0, $client->getTimeoutSeconds());
    }

    /**
     * Asserts that the request fails with the cloud request error for a
     * request that could not be made, close to the timeout given.
     */
    private function assertStopsAfter(float $timeoutSeconds, callable $request): void
    {
        $start = microtime(true);
        try {
            $request();
        } catch (CloudRequestException $e) {
            $elapsed = microtime(true) - $start;
            $this->assertStringContainsString('could not be made', $e->getMessage());
            $this->assertGreaterThanOrEqual(
                $timeoutSeconds - self::EARLY_SECONDS,
                $elapsed,
                'The request stopped before its timeout.'
            );
            $this->assertLessThan(
                $timeoutSeconds + self::LATE_SECONDS,
                $elapsed,
                'The request did not stop at its timeout.'
            );

            return;
        }

        $this->fail(sprintf(
            'The request waited %.1f seconds for the answer, where it should ' .
            'have stopped after %.1f seconds.',
            microtime(true) - $start,
            $timeoutSeconds
        ));
    }

    /**
     * A port nothing is listening on.
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::fail('No free port: ' . $errstr);
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
