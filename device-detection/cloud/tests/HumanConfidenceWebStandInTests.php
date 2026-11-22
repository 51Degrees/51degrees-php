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

use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * Serves the Human Confidence web example with PHP's built-in server,
 * pointed at a stand-in for the cloud service in
 * humanConfidenceStandInCloud.php, and asks it for the passive step. The
 * stand-in answers each resource key in the shape the cloud service gives
 * that kind of key, so the source the example returns is checked with no
 * key and no network.
 */
class HumanConfidenceWebStandInTests extends TestCase
{
    // How long a server has to start listening.
    private const START_SECONDS = 30;

    // How long the example has to answer one request.
    private const ANSWER_SECONDS = 30;

    // How long to wait before trying a server that is not yet listening
    // again.
    private const RETRY_MICROSECONDS = 100000;

    /** @var null|resource The stand-in for the cloud service */
    private static $cloud;

    /** @var string The address the stand-in listens on */
    private static $cloudAddress = '';

    public static function setUpBeforeClass(): void
    {
        self::$cloudAddress = self::freeAddress();
        self::$cloud = self::serve(
            self::$cloudAddress,
            __DIR__ . '/humanConfidenceStandInCloud.php',
            __DIR__,
            getenv()
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::stop(self::$cloud);
        self::$cloud = null;
    }

    /**
     * A key in the Bespoke product gets the source Passive, as no browser
     * tests have run, and the level the cloud service gave.
     */
    public function testPassiveGivesTheSourceTheCloudServiceGave()
    {
        $this->assertSame(
            ['source' => 'Passive', 'humanConfidence' => 'Medium'],
            self::passive('bespoke-key')
        );
    }

    /**
     * A key outside the Bespoke product is answered with neither property,
     * and the example makes up neither.
     */
    public function testPassiveForAKeyOutsideBespokeGivesNoSource()
    {
        $this->assertSame(
            ['source' => null, 'humanConfidence' => null],
            self::passive('outside-bespoke-key')
        );
    }

    /**
     * A key outside the Bespoke product that has the level is answered
     * with the level and no source, and the example makes up no source.
     */
    public function testPassiveForAKeyOutsideBespokeWithALevelGivesNoSource()
    {
        $this->assertSame(
            ['source' => null, 'humanConfidence' => 'Medium'],
            self::passive('outside-bespoke-level-key')
        );
    }

    /**
     * The example's answer to the passive step, with the resource key given
     * and the stand-in as its cloud service.
     *
     * @return mixed
     */
    private static function passive(string $key)
    {
        $environment = getenv();
        $environment[ExampleUtils::RESOURCE_KEY_ENV_VAR] = $key;
        $environment['_51DEGREES_CLOUD_ENDPOINT'] = 'http://' . self::$cloudAddress . '/api/v4/';
        unset($environment['_51DEGREES_LICENSE_KEY']);
        $address = self::freeAddress();
        $example = self::serve(
            $address,
            __DIR__ . '/humanConfidenceWebRouter.php',
            __DIR__ . '/../examples',
            $environment
        );
        try {
            $context = stream_context_create(['http' => [
                'method' => 'GET',
                'header' => Constants::UA_HEADER . Constants::CHROME_UA . "\r\n",
                'ignore_errors' => true,
                'timeout' => self::ANSWER_SECONDS
            ]]);
            $body = @file_get_contents('http://' . $address . '/passive', false, $context);
            self::assertNotFalse($body, 'The example did not answer /passive.');

            return json_decode($body, true);
        } finally {
            self::stop($example);
        }
    }

    /**
     * An address on this machine with a port nothing listens on.
     */
    private static function freeAddress(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('No free port was found.');
        }
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return $address;
    }

    /**
     * Serves a script with PHP's built-in server and waits until it
     * listens.
     *
     * @param array<string, string> $environment
     * @return resource
     */
    private static function serve(string $address, string $script, string $folder, array $environment)
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open(
            [PHP_BINARY, '-S', $address, $script],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            $folder,
            $environment
        );
        if (!is_resource($process)) {
            throw new \RuntimeException("{$script} could not be served.");
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + self::START_SECONDS;
        do {
            $connection = @stream_socket_client('tcp://' . $address, $code, $reason, 1);
            if ($connection !== false) {
                fclose($connection);

                return $process;
            }
            usleep(self::RETRY_MICROSECONDS);
        } while (microtime(true) < $deadline && proc_get_status($process)['running']);

        self::stop($process);

        throw new \RuntimeException("{$script} did not start listening on {$address}.");
    }

    /**
     * Stops a server started by serve.
     *
     * @param null|resource $process
     */
    private static function stop($process): void
    {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
