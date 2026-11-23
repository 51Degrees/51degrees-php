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

// Fake remote address for web integration

$_SERVER['REMOTE_ADDR'] = '0.0.0.0';
$_SERVER['REQUEST_URI'] = 'http://localhost';

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\GettingStartedConsole;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\MetadataConsole;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\NativeModelLookupConsole;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\TacLookupConsole;
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;
use PHPUnit\Framework\TestCase;

class ExampleTests extends TestCase
{
    /**
     * The timeout a test gives a request to the cloud service. It is far
     * above anything measured on a runner, because a test must not fail
     * when the service or the network is slow. The package's own default of
     * 2 seconds, which the specification sets, is left as it is.
     */
    private const CLOUD_TIMEOUT_SECONDS = 30;

    /**
     * A timeout no request to the cloud service can meet, so a test can see
     * that an example passes the timeout it is given on.
     */
    private const TIMEOUT_NO_REQUEST_MEETS = 0.001;

    // The values the TAC and native model examples look up.
    private const TACS = ['35925406', '86386802'];
    private const NATIVE_MODELS = ['SC-03L', 'iPhone11,8'];

    // What the TAC and native model examples print when the resource key
    // lacks the hardware lookup.
    private const NO_HARDWARE_LOOKUP = 'does not include HardwarePlatform.Profiles';

    // How the examples print a property with no value, before its reason.
    private const NO_VALUE = 'Unknown';

    // Text that shows an example printed a programming fault rather than a
    // result. An example that reaches any of these is broken, however
    // little of the data the resource key is entitled to.
    private const FAULT_MARKERS = [
        'TypeError',
        'ValueError',
        'Fatal error',
        'Warning:',
        'Undefined',
        'Traceback',
        'Unknown ()'
    ];

    public function testGettingStartedConsole()
    {
        $key = $this->getResourceKey();

        $output = $this->runExample(function ($record) use ($key) {
            $logger = new Logger('info');
            $config = json_decode(file_get_contents(__DIR__ . '/../examples/cloud/gettingStartedConsole.json'), true);
            ExampleUtils::setResourceKeyInConfig($config, $key);
            $this->setCloudTimeoutInConfig($config);
            (new GettingStartedConsole())->run($config, $logger, $record);
        });

        $this->assertStringContainsString('Input values:', $output);
        $this->assertStringContainsString('Mobile Device:', $output);
    }

    public function testTacLookupConsole()
    {
        $key = $this->getResourceKey();

        $output = $this->runExample(function ($record) use ($key) {
            $logger = new Logger('info');
            $config = json_decode(file_get_contents(__DIR__ . '/../examples/cloud/tacLookupConsole.json'), true);
            ExampleUtils::setResourceKeyInConfig($config, $key);
            $this->setCloudTimeoutInConfig($config);
            (new TacLookupConsole())->run($config, $logger, $record);
        });

        $this->assertDevicesListed(
            $output,
            'TAC lookup',
            'TAC',
            self::TACS
        );
    }

    public function testNativeModelLookupConsole()
    {
        $key = $this->getResourceKey();

        $output = $this->runExample(function ($record) use ($key) {
            $logger = new Logger('info');
            (new NativeModelLookupConsole())->run(
                $key,
                $logger,
                $record,
                '',
                self::CLOUD_TIMEOUT_SECONDS
            );
        });

        $this->assertDevicesListed(
            $output,
            'native model lookup',
            'native model name',
            self::NATIVE_MODELS
        );
    }

    /**
     * The native model example passes the timeout it is given on to the
     * Cloud Request Engine. A timeout no request can meet makes it fail.
     */
    public function testNativeModelLookupConsoleUsesTheTimeoutItIsGiven()
    {
        $key = $this->getResourceKey();

        $this->expectException(\Exception::class);

        (new NativeModelLookupConsole())->run(
            $key,
            new Logger('info'),
            static function ($line) {},
            '',
            self::TIMEOUT_NO_REQUEST_MEETS
        );
    }

    public function testMetadataConsole()
    {
        $key = $this->getResourceKey();

        $output = $this->runExample(function ($record) use ($key) {
            $logger = new Logger('info');
            (new MetaDataConsole())->run(
                $key,
                $logger,
                $record,
                self::CLOUD_TIMEOUT_SECONDS
            );
        });

        $this->assertNotEmpty($output);
    }

    /**
     * The metadata example passes the timeout it is given on to the Cloud
     * Request Engine. A timeout no request can meet makes it fail.
     */
    public function testMetadataConsoleUsesTheTimeoutItIsGiven()
    {
        $key = $this->getResourceKey();

        $this->expectException(\Exception::class);

        (new MetaDataConsole())->run(
            $key,
            new Logger('info'),
            static function ($line) {},
            self::TIMEOUT_NO_REQUEST_MEETS
        );
    }

    /**
     * Gives the Cloud Request Engine in an example's configuration the
     * timeout the tests use, which the configuration file leaves out.
     *
     * @param array $config The decoded configuration file
     */
    private function setCloudTimeoutInConfig(array &$config): void
    {
        foreach ($config['PipelineOptions']['Elements'] as &$element) {
            if ($element['BuilderName'] === CloudRequestEngine::class) {
                $element['BuildParameters']['timeoutSeconds'] = self::CLOUD_TIMEOUT_SECONDS;
            }
        }
    }

    /**
     * Run an example, collecting everything it writes through its output
     * callback, and fail if any of it reads as a programming fault.
     *
     * @param callable $example Given the output callback to pass on
     * @return string Everything the example wrote, one line per call
     */
    private function runExample(callable $example): string
    {
        $lines = [];

        $example(function ($line) use (&$lines) { $lines[] = $line; });

        $output = implode("\n", $lines);

        $this->assertGreaterThan(
            0,
            count($lines),
            'The example produced no output at all'
        );

        foreach (self::FAULT_MARKERS as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $output,
                "The example output contains '{$marker}', which means it " .
                "failed rather than reporting a result. Output was:\n" . $output
            );
        }

        return $output;
    }

    /**
     * Fails when the TAC or native model example said the resource key
     * lacks the hardware lookup, and otherwise when any value it looks up
     * lists no device with a vendor and a model under its heading.
     *
     * @param string $output Everything the example wrote
     * @param string $lookup The lookup, such as 'TAC lookup'
     * @param string $subject What each value is, as the heading names it
     * @param string[] $values The values the example looks up
     */
    private function assertDevicesListed(
        string $output,
        string $lookup,
        string $subject,
        array $values
    ): void {
        if (strpos($output, self::NO_HARDWARE_LOOKUP) !== false) {
            $this->fail(
                'The resource key in ' . Constants::RESOURCE_ENV_VAR .
                ' does not include HardwarePlatform.Profiles, which the ' .
                "{$lookup} needs.\n{$output}"
            );
        }

        $lines = explode("\n", $output);
        foreach ($values as $value) {
            $heading = array_search(
                "Which devices are associated with the {$subject} '{$value}'?",
                $lines,
                true
            );
            // The device lines are the tab indented lines under the heading.
            $listed = false;
            $at = $heading === false ? count($lines) : $heading + 1;
            while (isset($lines[$at]) && strpos($lines[$at], "\t") === 0) {
                if (self::hasVendorAndModel(substr($lines[$at], 1))) {
                    $listed = true;
                }
                $at++;
            }
            $this->assertTrue(
                $listed,
                'No device with a vendor and a model was listed for the ' .
                "{$subject} '{$value}'.\n{$output}"
            );
        }
    }

    /**
     * Whether a device line, '<vendor> <name> (<model>)', has a vendor and
     * a model. The vendor is the text before the first space and the model
     * the text inside the final parentheses, and neither may be empty or a
     * property with no value, which the examples print as 'Unknown' and
     * its reason.
     */
    private static function hasVendorAndModel(string $line): bool
    {
        $line = rtrim($line);
        $space = strpos($line, ' ');
        if ($space === false || substr($line, -1) !== ')') {
            return false;
        }

        // Find the parenthesis that opens the final one, as a model that
        // has no value holds its reason in parentheses of its own.
        $depth = 0;
        for ($open = strlen($line) - 1; $open >= 0; $open--) {
            if ($line[$open] === ')') {
                $depth++;
            } elseif ($line[$open] === '(' && --$depth === 0) {
                break;
            }
        }
        if ($open < 0) {
            return false;
        }

        $vendor = substr($line, 0, $space);
        $model = trim(substr($line, $open + 1, -1));

        foreach ([$vendor, $model] as $part) {
            if ($part === '' || strpos($part, self::NO_VALUE) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * The failure to match example is a script rather than a class, so it
     * is run as its own process.
     *
     * It used to keep the pipeline it built in a file beside the examples
     * and reuse it on the next run, so a run with one resource key answered
     * with the properties of whichever key had run first. The file name was
     * also built without a separator, so it landed outside the folder the
     * example lives in. The example builds its pipeline on every run now,
     * and this checks that it answers and leaves nothing behind.
     */
    public function testFailureToMatchLeavesNoPipelineBehind()
    {
        $key = $this->getResourceKey();
        $examples = __DIR__ . '/../examples';

        foreach ($this->pipelineFilesUnder($examples) as $stale) {
            unlink($stale);
        }

        $output = $this->runScript(
            $examples . '/cloud/failureToMatch.php',
            $key
        );

        $this->assertStringContainsString(
            'represent a mobile device',
            $output
        );

        $this->assertSame(
            [],
            $this->pipelineFilesUnder($examples),
            'The example left a serialized pipeline behind, and the next run '
            . 'would answer from whatever resource key wrote it'
        );
    }

    /**
     * Runs a PHP script with the resource key in the variable the examples
     * read, and returns everything it wrote.
     */
    private function runScript(string $script, string $key): string
    {
        $environment = getenv();
        $environment[ExampleUtils::RESOURCE_KEY_ENV_VAR] = $key;

        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment
        );
        $this->assertIsResource($process, 'Could not start ' . $script);

        $output = stream_get_contents($pipes[1])
            . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }

    /**
     * Every serialized pipeline file in the examples folder, in the folders
     * below it, and beside it, because a name built without a separator
     * lands beside the folder rather than in it.
     *
     * @return string[]
     */
    private function pipelineFilesUnder(string $folder): array
    {
        $found = array_merge(
            (array) glob($folder . '/*.pipeline'),
            (array) glob($folder . '/*/*.pipeline'),
            (array) glob(dirname($folder) . '/*.pipeline')
        );

        return array_values(array_filter($found, 'is_file'));
    }

    private function getResourceKey()
    {
        $resourceKey = ResourceKeys::find(Constants::RESOURCE_ENV_VAR);

        if ($resourceKey === null) {
            $this->fail(ResourceKeys::missingMessage(Constants::RESOURCE_ENV_VAR));
        }

        return $resourceKey;
    }
}
