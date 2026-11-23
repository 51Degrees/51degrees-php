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

use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\NativeModelLookupConsole;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\TacLookupConsole;
use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the TAC and native model lookup examples against a stand-in for the
 * cloud service in hardwareLookupStandInCloud.php, which answers as the
 * cloud service does for a resource key without the hardware lookup. Each
 * example must say so in one line that is not indented, with tagged links
 * to create a key that has the lookup and to contact us, list no lookups,
 * ask the stand-in for no lookup, and end normally. The examples run as a
 * person runs them, each in its own PHP, and their classes run as
 * ExampleTests runs them, so no real key and no network are needed.
 */
class HardwareLookupStandInTests extends TestCase
{
    // A resource key for the stand-in, which answers every key the same.
    private const STAND_IN_KEY = 'stand-in-key';

    // How long the stand-in has to start listening.
    private const START_SECONDS = 30;

    // How long to wait before trying a stand-in that is not yet listening
    // again.
    private const RETRY_MICROSECONDS = 100000;

    // What the stand-in writes to standard error before each request.
    private const REQUEST_MARKER = 'Stand-in request: ';

    // The start of the line the examples print in place of the lookups.
    private const NO_HARDWARE_LOOKUP =
        'The resource key does not include HardwarePlatform.Profiles, ' .
        'the hardware lookup this example needs, so no devices are listed. ';

    // The start of the two links that line must hold.
    private const CONFIGURE =
        'https://51degrees.com/configure/QKyYH5XT?utm_source=code&utm_medium=example&utm_campaign=51degrees-php';
    private const CONTACT =
        'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php';

    // The start of the heading of each lookup.
    private const LOOKUP_HEADING = 'Which devices are associated';

    // The path of a lookup, being the resource key's JSON.
    private const LOOKUP_PATH = '~^/api/v4/[^/?]+\.json(\?|$)~i';

    // The path of the accessible properties, which every example asks for.
    private const PROPERTIES_PATH = '~^/api/v4/accessibleproperties(\?|$)~i';

    /** @var null|resource The stand-in for the cloud service */
    private $cloud;

    /** @var string The address the stand-in listens on */
    private $cloudAddress = '';

    /** @var string The file the stand-in's standard error goes to */
    private $cloudErrors = '';

    protected function setUp(): void
    {
        $this->cloudErrors = (string) tempnam(sys_get_temp_dir(), 'stand-in');
        $this->cloudAddress = self::freeAddress();
        $this->cloud = self::serve(
            $this->cloudAddress,
            __DIR__ . '/hardwareLookupStandInCloud.php',
            $this->cloudErrors
        );
    }

    protected function tearDown(): void
    {
        self::stop($this->cloud);
        $this->cloud = null;
        if (is_file($this->cloudErrors)) {
            unlink($this->cloudErrors);
        }
    }

    public function testTacLookupConsole()
    {
        $this->runExampleAgainstStandIn('cloud/tacLookupConsole.php');
    }

    public function testNativeModelLookupConsole()
    {
        $this->runExampleAgainstStandIn('cloud/nativeModelLookupConsole.php');
    }

    /**
     * The class returns normally, given the stand-in as its cloud service.
     */
    public function testTacLookupConsoleClass()
    {
        $config = json_decode(
            (string) file_get_contents(__DIR__ . '/../examples/cloud/tacLookupConsole.json'),
            true
        );
        ExampleUtils::setResourceKeyInConfig($config, self::STAND_IN_KEY);

        $output = $this->runClass(function ($record) use ($config) {
            (new TacLookupConsole())->run(
                $config,
                new Logger('info'),
                $record,
                $this->cloudEndPoint()
            );
        });

        $this->assertSaysTheKeyLacksTheLookup($output, $output);
        $this->assertNoLookupWasAskedFor();
    }

    /**
     * The class returns normally, given the stand-in as its cloud service.
     */
    public function testNativeModelLookupConsoleClass()
    {
        $output = $this->runClass(function ($record) {
            (new NativeModelLookupConsole())->run(
                self::STAND_IN_KEY,
                new Logger('info'),
                $record,
                $this->cloudEndPoint()
            );
        });

        $this->assertSaysTheKeyLacksTheLookup($output, $output);
        $this->assertNoLookupWasAskedFor();
    }

    /**
     * Runs the example in its own PHP with the stand-in as its cloud
     * service and checks it ended with exit status 0, said the key lacks
     * the lookup, ended with Find out more and asked for no lookup.
     */
    private function runExampleAgainstStandIn(string $script): void
    {
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            [],
            [],
            [
                ExampleUtils::RESOURCE_KEY_ENV_VAR => self::STAND_IN_KEY,
                ExampleUtils::CLOUD_ENDPOINT_VARIABLE => $this->cloudEndPoint()
            ]
        );

        $this->assertSame(0, $run->status, $run->printed());
        $this->assertSaysTheKeyLacksTheLookup($run->output, $run->printed());
        $run->assertEndsWithFindOutMore();
        $this->assertNoLookupWasAskedFor();
    }

    /**
     * Runs an example's class, which must return normally, and gives
     * everything it wrote, one line per call.
     */
    private function runClass(callable $example): string
    {
        $lines = [];
        $example(function ($line) use (&$lines) {
            $lines[] = $line;
        });

        return implode("\n", $lines);
    }

    /**
     * Checks the output holds the line saying the key lacks the lookup
     * once, not indented and with both links, and no lookup heading.
     */
    private function assertSaysTheKeyLacksTheLookup(string $output, string $printed): void
    {
        $lines = explode("\n", $output);
        $said = array_values(array_filter($lines, function ($line) {
            return strpos($line, 'HardwarePlatform.Profiles') !== false;
        }));

        $this->assertCount(1, $said, $printed);
        $this->assertStringStartsWith(self::NO_HARDWARE_LOOKUP, $said[0], $printed);
        $this->assertStringContainsString(self::CONFIGURE, $said[0], $printed);
        $this->assertStringContainsString(self::CONTACT, $said[0], $printed);

        foreach ($lines as $line) {
            $this->assertStringStartsNotWith(self::LOOKUP_HEADING, $line, $printed);
        }
    }

    /**
     * Checks the stand-in was asked for the accessible properties, so the
     * example used it, and was asked for no lookup.
     */
    private function assertNoLookupWasAskedFor(): void
    {
        $paths = [];
        foreach (file($this->cloudErrors, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (strpos($line, self::REQUEST_MARKER) === 0) {
                $request = explode(' ', substr($line, strlen(self::REQUEST_MARKER)), 2);
                $paths[] = $request[1] ?? '';
            }
        }
        $asked = implode("\n", $paths);

        $this->assertNotEmpty(
            preg_grep(self::PROPERTIES_PATH, $paths),
            "The stand-in was not asked for the accessible properties.\n{$asked}"
        );
        $this->assertEmpty(
            preg_grep(self::LOOKUP_PATH, $paths),
            "The stand-in was asked for a lookup.\n{$asked}"
        );
    }

    /**
     * The address of the stand-in as the examples take it.
     */
    private function cloudEndPoint(): string
    {
        return 'http://' . $this->cloudAddress . '/api/v4/';
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
     * Serves a script with PHP's built-in server, writing its standard
     * error to a file, and waits until it listens.
     *
     * @return resource
     */
    private static function serve(string $address, string $script, string $errors)
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open(
            [PHP_BINARY, '-S', $address, $script],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $errors, 'w']],
            $pipes,
            dirname($script)
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
