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

use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
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
 * ask the stand-in for no lookup, and end normally. That holds too when the
 * request for the key's properties fails once, and when it fails every time
 * the example must end with the cloud service's error instead. A key with
 * the hardware lookup whose lookups match no device must get a line saying
 * so under each heading, also when the request for its properties fails
 * once. The examples run as a person runs them, each in
 * its own PHP, and their classes run as ExampleTests runs them, so no real
 * key and no network are needed.
 */
class HardwareLookupStandInTests extends TestCase
{
    // The resource keys the stand-in knows. It answers the first with the
    // properties of a key without the hardware lookup, and fails the
    // request for the properties of the second once and of the third every
    // time. The last two have the hardware lookup, and their lookups match
    // no device, and the request for the properties of the last fails once.
    private const STAND_IN_KEY = 'stand-in-key';
    private const FAILS_ONCE_KEY = 'fails-once-key';
    private const FAILS_ALWAYS_KEY = 'fails-always-key';
    private const NO_DEVICES_KEY = 'no-devices-key';
    private const FAILS_ONCE_WITH_LOOKUP_KEY = 'fails-once-with-lookup-key';

    // The values each example looks up, the subject its headings name, and
    // the line it prints under a heading when a lookup matches no device.
    private const LOOKUPS = [
        self::TAC_SCRIPT => [
            ['35925406', '86386802'],
            'TAC',
            'No devices are associated with this TAC.'
        ],
        self::NATIVE_MODEL_SCRIPT => [
            ['SC-03L', 'iPhone11,8'],
            'native model name',
            'No devices are associated with this native model name.'
        ]
    ];

    // The error the stand-in gives when a request fails.
    private const FAILURE = 'Stand-in failure';

    // The examples, as scripts.
    private const TAC_SCRIPT = 'cloud/tacLookupConsole.php';
    private const NATIVE_MODEL_SCRIPT = 'cloud/nativeModelLookupConsole.php';

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

    // Text only that line holds.
    private const NOT_INCLUDED = 'does not include HardwarePlatform.Profiles';

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

    /** @var string The stand-in's document root, where it keeps its state */
    private $cloudRoot = '';

    /** @var string[] What an example's class wrote, one line per call */
    private $lines = [];

    protected function setUp(): void
    {
        $this->cloudErrors = (string) tempnam(sys_get_temp_dir(), 'stand-in');
        $this->cloudRoot = (string) tempnam(sys_get_temp_dir(), 'stand-in');
        unlink($this->cloudRoot);
        mkdir($this->cloudRoot);
        $this->cloudAddress = self::freeAddress();
        $this->cloud = self::serve(
            $this->cloudAddress,
            __DIR__ . '/hardwareLookupStandInCloud.php',
            $this->cloudRoot,
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
        if (is_dir($this->cloudRoot)) {
            array_map('unlink', (array) glob($this->cloudRoot . '/*'));
            rmdir($this->cloudRoot);
        }
    }

    public function testTacLookupConsole()
    {
        $this->runScriptForKeyWithoutLookup(self::TAC_SCRIPT, self::STAND_IN_KEY, 1);
    }

    public function testNativeModelLookupConsole()
    {
        $this->runScriptForKeyWithoutLookup(self::NATIVE_MODEL_SCRIPT, self::STAND_IN_KEY, 1);
    }

    /**
     * The class returns normally, given the stand-in as its cloud service.
     */
    public function testTacLookupConsoleClass()
    {
        $this->runClassForKeyWithoutLookup(self::TAC_SCRIPT, self::STAND_IN_KEY, 1);
    }

    /**
     * The class returns normally, given the stand-in as its cloud service.
     */
    public function testNativeModelLookupConsoleClass()
    {
        $this->runClassForKeyWithoutLookup(self::NATIVE_MODEL_SCRIPT, self::STAND_IN_KEY, 1);
    }

    /**
     * The example asks for the properties again when the request made as
     * the pipeline was built failed, and decides from the answer.
     */
    public function testTacLookupConsoleWhenThePropertiesFailOnce()
    {
        $this->runScriptForKeyWithoutLookup(self::TAC_SCRIPT, self::FAILS_ONCE_KEY, 2);
    }

    public function testNativeModelLookupConsoleWhenThePropertiesFailOnce()
    {
        $this->runScriptForKeyWithoutLookup(self::NATIVE_MODEL_SCRIPT, self::FAILS_ONCE_KEY, 2);
    }

    public function testTacLookupConsoleClassWhenThePropertiesFailOnce()
    {
        $this->runClassForKeyWithoutLookup(self::TAC_SCRIPT, self::FAILS_ONCE_KEY, 2);
    }

    public function testNativeModelLookupConsoleClassWhenThePropertiesFailOnce()
    {
        $this->runClassForKeyWithoutLookup(self::NATIVE_MODEL_SCRIPT, self::FAILS_ONCE_KEY, 2);
    }

    /**
     * When the properties cannot be read at all, the example ends with the
     * cloud service's error, and says nothing about the key.
     */
    public function testTacLookupConsoleWhenThePropertiesAlwaysFail()
    {
        $this->runScriptWhenThePropertiesAlwaysFail(self::TAC_SCRIPT);
    }

    public function testNativeModelLookupConsoleWhenThePropertiesAlwaysFail()
    {
        $this->runScriptWhenThePropertiesAlwaysFail(self::NATIVE_MODEL_SCRIPT);
    }

    public function testTacLookupConsoleClassWhenThePropertiesAlwaysFail()
    {
        $this->runClassWhenThePropertiesAlwaysFail(self::TAC_SCRIPT);
    }

    public function testNativeModelLookupConsoleClassWhenThePropertiesAlwaysFail()
    {
        $this->runClassWhenThePropertiesAlwaysFail(self::NATIVE_MODEL_SCRIPT);
    }

    /**
     * A key with the hardware lookup whose lookups match no device gets a
     * line saying so under each heading.
     */
    public function testTacLookupConsoleWhenNoDeviceMatches()
    {
        $this->runScriptWhenNoDeviceMatches(self::TAC_SCRIPT, self::NO_DEVICES_KEY, 1);
    }

    public function testNativeModelLookupConsoleWhenNoDeviceMatches()
    {
        $this->runScriptWhenNoDeviceMatches(self::NATIVE_MODEL_SCRIPT, self::NO_DEVICES_KEY, 1);
    }

    public function testTacLookupConsoleClassWhenNoDeviceMatches()
    {
        $this->runClassWhenNoDeviceMatches(self::TAC_SCRIPT, self::NO_DEVICES_KEY, 1);
    }

    public function testNativeModelLookupConsoleClassWhenNoDeviceMatches()
    {
        $this->runClassWhenNoDeviceMatches(self::NATIVE_MODEL_SCRIPT, self::NO_DEVICES_KEY, 1);
    }

    /**
     * A key with the hardware lookup is still taken as having it when the
     * request for its properties made as the pipeline was built failed.
     */
    public function testTacLookupConsoleWhenThePropertiesOfAKeyWithTheLookupFailOnce()
    {
        $this->runScriptWhenNoDeviceMatches(self::TAC_SCRIPT, self::FAILS_ONCE_WITH_LOOKUP_KEY, 2);
    }

    public function testNativeModelLookupConsoleWhenThePropertiesOfAKeyWithTheLookupFailOnce()
    {
        $this->runScriptWhenNoDeviceMatches(self::NATIVE_MODEL_SCRIPT, self::FAILS_ONCE_WITH_LOOKUP_KEY, 2);
    }

    public function testTacLookupConsoleClassWhenThePropertiesOfAKeyWithTheLookupFailOnce()
    {
        $this->runClassWhenNoDeviceMatches(self::TAC_SCRIPT, self::FAILS_ONCE_WITH_LOOKUP_KEY, 2);
    }

    public function testNativeModelLookupConsoleClassWhenThePropertiesOfAKeyWithTheLookupFailOnce()
    {
        $this->runClassWhenNoDeviceMatches(self::NATIVE_MODEL_SCRIPT, self::FAILS_ONCE_WITH_LOOKUP_KEY, 2);
    }

    /**
     * Runs the example in its own PHP with a key whose lookups match no
     * device, and checks it ended with exit status 0, printed each heading
     * with the line for no devices under it and nothing saying the key
     * lacks the lookup, ended with Find out more, asked for the properties
     * the given number of times and asked for every lookup.
     */
    private function runScriptWhenNoDeviceMatches(
        string $script,
        string $key,
        int $propertyRequests
    ): void {
        [$values, $subject, $noDevices] = self::LOOKUPS[$script];

        $run = $this->runScript($script, $key);

        $this->assertSame(0, $run->status, $run->printed());
        $this->assertNoDevicesListed($run->output, $subject, $values, $noDevices, $run->printed());
        $run->assertEndsWithFindOutMore();
        $this->assertRequests($propertyRequests, count($values));
    }

    /**
     * Runs the example's class with a key whose lookups match no device,
     * which must return normally, and checks it printed each heading with
     * the line for no devices under it and nothing saying the key lacks the
     * lookup, asked for the properties the given number of times and asked
     * for every lookup.
     */
    private function runClassWhenNoDeviceMatches(
        string $script,
        string $key,
        int $propertyRequests
    ): void {
        [$values, $subject, $noDevices] = self::LOOKUPS[$script];

        $output = $this->runClass($script, $key);

        $this->assertNoDevicesListed($output, $subject, $values, $noDevices, $output);
        $this->assertRequests($propertyRequests, count($values));
    }

    /**
     * Checks each value's heading is followed by the line for no devices,
     * after a tab, and by no other device line, and that nothing says the
     * key lacks the lookup.
     *
     * @param string[] $values
     */
    private function assertNoDevicesListed(
        string $output,
        string $subject,
        array $values,
        string $noDevices,
        string $printed
    ): void {
        $lines = explode("\n", $output);
        foreach ($values as $value) {
            $heading = array_search(
                "Which devices are associated with the {$subject} '{$value}'?",
                $lines,
                true
            );
            $this->assertNotFalse($heading, $printed);
            $this->assertSame("\t{$noDevices}", $lines[$heading + 1] ?? null, $printed);
            $this->assertStringStartsNotWith("\t", $lines[$heading + 2] ?? '', $printed);
        }
        $this->assertStringNotContainsString(self::NOT_INCLUDED, $output, $printed);
    }

    /**
     * Runs the example in its own PHP with the key and checks it ended with
     * exit status 0, said the key lacks the lookup, ended with Find out
     * more, asked for the properties the given number of times and asked
     * for no lookup.
     */
    private function runScriptForKeyWithoutLookup(
        string $script,
        string $key,
        int $propertyRequests
    ): void {
        $run = $this->runScript($script, $key);

        $this->assertSame(0, $run->status, $run->printed());
        $this->assertSaysTheKeyLacksTheLookup($run->output, $run->printed());
        $run->assertEndsWithFindOutMore();
        $this->assertRequests($propertyRequests);
    }

    /**
     * Runs the example's class with the key, which must return normally,
     * and checks it said the key lacks the lookup, asked for the properties
     * the given number of times and asked for no lookup.
     */
    private function runClassForKeyWithoutLookup(
        string $script,
        string $key,
        int $propertyRequests
    ): void {
        $output = $this->runClass($script, $key);

        $this->assertSaysTheKeyLacksTheLookup($output, $output);
        $this->assertRequests($propertyRequests);
    }

    /**
     * Runs the example in its own PHP with a key whose properties cannot be
     * read, and checks it failed with the cloud service's error, said
     * nothing about the key and asked for no lookup.
     */
    private function runScriptWhenThePropertiesAlwaysFail(string $script): void
    {
        $run = $this->runScript($script, self::FAILS_ALWAYS_KEY);

        $this->assertNotSame(0, $run->status, $run->printed());
        $this->assertStringContainsString(
            self::FAILURE,
            $run->output . $run->errors,
            $run->printed()
        );
        $this->assertStringNotContainsString(self::NOT_INCLUDED, $run->output, $run->printed());
        $this->assertRequests(2);
    }

    /**
     * Runs the example's class with a key whose properties cannot be read,
     * and checks it threw the cloud service's error, said nothing about the
     * key and asked for no lookup.
     */
    private function runClassWhenThePropertiesAlwaysFail(string $script): void
    {
        try {
            $this->runClass($script, self::FAILS_ALWAYS_KEY);
            $this->fail(
                "The example did not end with the cloud service's error.\n" .
                implode("\n", $this->lines)
            );
        } catch (CloudRequestException $e) {
            $this->assertStringContainsString(self::FAILURE, $e->getMessage());
        }

        $output = implode("\n", $this->lines);
        $this->assertStringNotContainsString(self::NOT_INCLUDED, $output, $output);
        $this->assertRequests(2);
    }

    /**
     * Runs the example in its own PHP, as a person runs it, with the key
     * and the stand-in as its cloud service.
     */
    private function runScript(string $script, string $key): ExampleRun
    {
        return ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            [],
            [],
            [
                ExampleUtils::RESOURCE_KEY_ENV_VAR => $key,
                ExampleUtils::CLOUD_ENDPOINT_VARIABLE => $this->cloudEndPoint()
            ]
        );
    }

    /**
     * Runs the class of the example, as ExampleTests does, with the key and
     * the stand-in passed as its cloud service, and gives everything it
     * wrote, one line per call. What it wrote is also kept in $lines, for
     * when it throws.
     */
    private function runClass(string $script, string $key): string
    {
        $this->lines = [];
        $record = function ($line) {
            $this->lines[] = $line;
        };

        if ($script === self::TAC_SCRIPT) {
            $config = json_decode(
                (string) file_get_contents(__DIR__ . '/../examples/cloud/tacLookupConsole.json'),
                true
            );
            ExampleUtils::setResourceKeyInConfig($config, $key);
            (new TacLookupConsole())->run(
                $config,
                new Logger('info'),
                $record,
                $this->cloudEndPoint()
            );
        } else {
            (new NativeModelLookupConsole())->run(
                $key,
                new Logger('info'),
                $record,
                $this->cloudEndPoint()
            );
        }

        return implode("\n", $this->lines);
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
     * Checks the stand-in was asked for the accessible properties the given
     * number of times, which also shows the example used it, and for the
     * given number of lookups, none unless given.
     */
    private function assertRequests(int $propertyRequests, int $lookups = 0): void
    {
        $paths = [];
        foreach (file($this->cloudErrors, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (strpos($line, self::REQUEST_MARKER) === 0) {
                $request = explode(' ', substr($line, strlen(self::REQUEST_MARKER)), 2);
                $paths[] = $request[1] ?? '';
            }
        }
        $asked = implode("\n", $paths);

        $this->assertCount(
            $propertyRequests,
            preg_grep(self::PROPERTIES_PATH, $paths),
            "The stand-in was not asked for the accessible properties " .
            "{$propertyRequests} times.\n{$asked}"
        );
        $this->assertCount(
            $lookups,
            preg_grep(self::LOOKUP_PATH, $paths),
            "The stand-in was not asked for {$lookups} lookups.\n{$asked}"
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
     * Serves a script with PHP's built-in server from the document root,
     * writing its standard error to a file, and waits until it listens.
     *
     * @return resource
     */
    private static function serve(string $address, string $script, string $root, string $errors)
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $root, $script],
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
