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
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * Loads the Device Detection and IP Intelligence extensions in one PHP, in
 * each order, and runs the two examples that use both on the free data
 * files. Each case starts its own PHP with the two extensions in the order
 * it needs and none of the package's php.ini settings.
 */
class MixedExamplesTests extends TestCase
{
    private const DEVICE_DETECTION = 'FiftyOneDegreesHashEngine';
    private const IP_INTELLIGENCE = 'FiftyOneDegreesIpIntelligenceEngine';

    // The extensions as the packages' build-project.ps1 scripts build them.
    private const DEVICE_DETECTION_EXTENSION = __DIR__ .
        '/../../device-detection/onpremise/on-premise/modules/FiftyOneDegreesHashEngine.so';
    private const IP_INTELLIGENCE_EXTENSION = __DIR__ .
        '/../on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so';

    // The free Device Detection data file, in the data repository.
    private const HASH_LITE_FILE = __DIR__ .
        '/../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    // Where the data repository keeps the free IP Intelligence data files.
    private const IPI_DATA = __DIR__ . '/../../cxx/data/ip-intelligence';

    private const EXAMPLES = __DIR__ . '/../examples/onpremise';

    // The start of every link the examples give to ask for a copy of the
    // Enterprise data file.
    private const CONTACT_US = 'https://51degrees.com/contact-us?';

    // The longest a web server that loads both data files when it starts is
    // given to answer. Loading the free files takes seconds.
    private const SERVER_START_SECONDS = 600;

    private const IPHONE =
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) ' .
        'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 ' .
        'Mobile/15E148 Safari/604.1';

    /**
     * @return array<string, array<bool>>
     */
    public static function loadOrders(): array
    {
        return [
            'device detection first' => [true],
            'IP intelligence first' => [false],
        ];
    }

    protected function setUp(): void
    {
        foreach ([
            self::DEVICE_DETECTION_EXTENSION,
            self::IP_INTELLIGENCE_EXTENSION,
        ] as $extension) {
            if (!is_file($extension)) {
                // CI builds both extensions wherever it runs these tests, so
                // a missing one there is a failure, not a reason to skip.
                $message = "{$extension} has not been built.";
                if (getenv('GITHUB_ACTIONS') === 'true') {
                    $this->fail($message);
                }
                $this->markTestSkipped($message);
            }
        }
    }

    /**
     * The extension loaded second leaves the classes of the first as they
     * were when it was loaded alone, with the same names and parents.
     *
     * @dataProvider loadOrders
     */
    public function testEachExtensionKeepsItsOwnClasses(
        bool $deviceDetectionFirst
    ): void {
        $alone = array_merge(
            $this->classes($this->deviceDetection()),
            $this->classes($this->ipIntelligence(false))
        );

        $together = $this->classes(
            $this->both($deviceDetectionFirst, false)
        );

        $this->assertSame($alone, $together);
    }

    /**
     * The IP Intelligence extension's earlier class names still name its
     * classes when it is loaded alone. With the Device Detection extension
     * loaded as well, the names both extensions had are the Device Detection
     * extension's classes, and the rest stay the IP Intelligence extension's.
     */
    public function testEarlierClassNamesAreKept(): void
    {
        $ipi = self::IP_INTELLIGENCE;
        $dd = self::DEVICE_DETECTION;
        $this->assertSame(
            [
                'EngineIpiSwig' => "FiftyOneDegreesIpiEngineIpiSwig {$ipi}",
                'ConfigIpiSwig' => "FiftyOneDegreesIpiConfigIpiSwig {$ipi}",
                'VectorStringSwig' => "FiftyOneDegreesIpiVectorStringSwig {$ipi}",
                'RequiredPropertiesConfigSwig' =>
                    "FiftyOneDegreesIpiRequiredPropertiesConfigSwig {$ipi}",
                $ipi => "FiftyOneDegreesIpi {$ipi}",
            ],
            $this->earlierNames($this->ipIntelligence(false))
        );
        foreach ([true, false] as $deviceDetectionFirst) {
            $this->assertSame(
                [
                    'EngineIpiSwig' => "FiftyOneDegreesIpiEngineIpiSwig {$ipi}",
                    'ConfigIpiSwig' => "FiftyOneDegreesIpiConfigIpiSwig {$ipi}",
                    'VectorStringSwig' => "VectorStringSwig {$dd}",
                    'RequiredPropertiesConfigSwig' =>
                        "RequiredPropertiesConfigSwig {$dd}",
                    $ipi => "FiftyOneDegreesIpi {$ipi}",
                ],
                $this->earlierNames($this->both($deviceDetectionFirst, false))
            );
        }
    }

    /**
     * @dataProvider loadOrders
     */
    public function testMixedConsoleExample(bool $deviceDetectionFirst): void
    {
        [$status, $text] = $this->runPhp(
            $this->both($deviceDetectionFirst, true),
            [self::EXAMPLES . '/mixed/gettingstarted-console.php']
        );

        $this->assertSame(0, $status, $text);
        $this->assertStringContainsString("Device Detection Results:\n", $text);
        $this->assertStringContainsString("\tPlatformName: iOS\n", $text);
        $this->assertStringContainsString(
            "\tHardwareVendor: " . ExampleUtils::NEEDS_ENTERPRISE,
            $text
        );
        $this->assertStringContainsString("IP Intelligence Results:\n", $text);
        $this->assertMatchesRegularExpression(
            '/\tRegisteredCountry: [A-Z]{2}\n/',
            $text
        );
        $this->assertStringContainsString(
            "\tCountry: " . ExampleUtils::NEEDS_ENTERPRISE,
            $text
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            'Contact us for a copy at ' . self::CONTACT_US,
            $text
        );
    }

    /**
     * @dataProvider loadOrders
     */
    public function testMixedWebExample(bool $deviceDetectionFirst): void
    {
        $page = $this->fetchPage(
            $this->both($deviceDetectionFirst, true),
            self::EXAMPLES . '/web-mixed',
            '/index.php?client-ip=8.8.8.8'
        );

        $this->assertStringNotContainsString('c-eg-alert', $page);
        $this->assertStringContainsString(
            $this->row('Platform Name', 'iOS'),
            $page
        );
        $this->assertStringContainsString(
            $this->row('Hardware Vendor', ExampleUtils::NEEDS_ENTERPRISE),
            $page
        );
        $this->assertStringContainsString(
            $this->row('Registered Country', 'US'),
            $page
        );
        $this->assertStringContainsString(
            $this->row('Country', ExampleUtils::NEEDS_ENTERPRISE),
            $page
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            '<a href="' . self::CONTACT_US,
            $page
        );
    }

    /**
     * A row of the web example's results tables, as the page writes it.
     */
    private function row(string $label, string $value): string
    {
        return htmlspecialchars($label) . ':</td>' .
            '<td class="c-eg-table__cell">' . htmlspecialchars($value) .
            '</td>';
    }

    /**
     * Each class of each of the two extensions that is loaded, keyed by the
     * extension and the class, with its parent. Another name for a class is
     * left out, and so are SWIG's own pointer classes, which both extensions
     * have under the same names and nothing in either extension's PHP uses.
     *
     * @param array<string> $settings
     * @return array<string, array<string, ?string>>
     */
    private function classes(array $settings): array
    {
        $code = <<<'PHP'
            $found = [];
            foreach (['FiftyOneDegreesHashEngine',
                'FiftyOneDegreesIpIntelligenceEngine'] as $extension) {
                if (!extension_loaded($extension)) {
                    continue;
                }
                $found[$extension] = [];
                $names = (new ReflectionExtension($extension))->getClassNames();
                foreach ($names as $name) {
                    $class = new ReflectionClass($name);
                    if ($class->getName() !== $name ||
                        strncmp($name, 'SWIG\\', 5) === 0) {
                        continue;
                    }
                    $parent = $class->getParentClass();
                    $found[$extension][$name] =
                        $parent === false ? null : $parent->getName();
                }
                ksort($found[$extension]);
            }
            echo json_encode($found);
            PHP;
        [$status, $text] = $this->runPhp($settings, ['-r', $code]);
        $this->assertSame(0, $status, $text);
        $classes = json_decode($text, true);
        $this->assertIsArray($classes, $text);

        return $classes;
    }

    /**
     * The class and extension each earlier name gives, or missing.
     *
     * @param array<string> $settings
     * @return array<string, string>
     */
    private function earlierNames(array $settings): array
    {
        $code = <<<'PHP'
            $found = [];
            foreach (['EngineIpiSwig', 'ConfigIpiSwig', 'VectorStringSwig',
                'RequiredPropertiesConfigSwig',
                'FiftyOneDegreesIpIntelligenceEngine'] as $name) {
                if (!class_exists($name)) {
                    $found[$name] = 'missing';
                    continue;
                }
                $class = new ReflectionClass($name);
                $found[$name] =
                    $class->getName() . ' ' . $class->getExtensionName();
            }
            echo json_encode($found);
            PHP;
        [$status, $text] = $this->runPhp($settings, ['-r', $code]);
        $this->assertSame(0, $status, $text);
        $names = json_decode($text, true);
        $this->assertIsArray($names, $text);

        return $names;
    }

    /**
     * The settings that load both extensions, in the order given.
     *
     * @return array<string>
     */
    private function both(bool $deviceDetectionFirst, bool $withIpiData): array
    {
        return $deviceDetectionFirst
            ? array_merge(
                $this->deviceDetection(),
                $this->ipIntelligence($withIpiData)
            )
            : array_merge(
                $this->ipIntelligence($withIpiData),
                $this->deviceDetection()
            );
    }

    /**
     * The settings that load the Device Detection extension. It always
     * needs its data file, and builds its engine from it when PHP starts.
     *
     * @return array<string>
     */
    private function deviceDetection(): array
    {
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile(self::HASH_LITE_FILE),
            'The free Device Detection data file could not be found or downloaded.'
        );

        return [
            '-d', 'extension=' . realpath(self::DEVICE_DETECTION_EXTENSION),
            '-d', self::DEVICE_DETECTION . '.data_file=' .
                realpath(self::HASH_LITE_FILE),
        ];
    }

    /**
     * The settings that load the IP Intelligence extension, with the free
     * data file to build its engine from when PHP starts, or without one.
     *
     * @return array<string>
     */
    private function ipIntelligence(bool $withData): array
    {
        $settings = [
            '-d', 'extension=' . realpath(self::IP_INTELLIGENCE_EXTENSION),
        ];
        if ($withData) {
            $settings[] = '-d';
            $settings[] = ExampleUtils::DATA_FILE_SETTING . '=' .
                $this->ipiLiteFile();
        }

        return $settings;
    }

    /**
     * The free Lite IP Intelligence data file. CI fetches it into the
     * package's assets folder under its asset name, and a fresh clone
     * downloads it into the data repository.
     */
    private function ipiLiteFile(): string
    {
        $asset = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (is_file($asset)) {
            return (string) realpath($asset);
        }
        $path = self::IPI_DATA . '/' . ExampleConstants::LITE_DATA_FILE_NAME;
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile($path),
            'The free IP Intelligence data file could not be found or downloaded.'
        );

        return (string) realpath($path);
    }

    /**
     * The environment for a PHP that reads only PHP's own ini files, so that
     * the settings given are the only ones that load the extensions.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        $environment = getenv();
        $environment['PHP_INI_SCAN_DIR'] = PHP_CONFIG_FILE_SCAN_DIR;

        return $environment;
    }

    /**
     * Runs PHP with the settings and arguments, and returns its exit status
     * and what it wrote.
     *
     * @param array<string> $settings
     * @param array<string> $arguments
     * @return array{0: int, 1: string}
     */
    private function runPhp(array $settings, array $arguments): array
    {
        $process = proc_open(
            array_merge([PHP_BINARY], $settings, $arguments),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            null,
            $this->environment()
        );
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $text = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $text];
    }

    /**
     * Starts PHP's web server with the settings, serving the folder, and
     * returns the page at the path, asked for by an iPhone.
     *
     * @param array<string> $settings
     */
    private function fetchPage(
        array $settings,
        string $folder,
        string $path
    ): string {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        $log = (string) tempnam(sys_get_temp_dir(), 'web-mixed');
        $server = proc_open(
            array_merge(
                [PHP_BINARY],
                $settings,
                ['-S', $address, '-t', (string) realpath($folder)]
            ),
            [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            null,
            $this->environment()
        );
        $this->assertIsResource($server);
        fclose($pipes[0]);

        try {
            $deadline = time() + self::SERVER_START_SECONDS;
            while (true) {
                $connection = @fsockopen('tcp://' . $address);
                if ($connection !== false) {
                    fclose($connection);
                    break;
                }
                $this->assertTrue(
                    proc_get_status($server)['running'],
                    'The web server stopped: ' . file_get_contents($log)
                );
                $this->assertLessThan(
                    $deadline,
                    time(),
                    'The web server did not start: ' . file_get_contents($log)
                );
                usleep(200000);
            }

            $page = @file_get_contents(
                "http://{$address}{$path}",
                false,
                stream_context_create(['http' => [
                    'header' => 'User-Agent: ' . self::IPHONE,
                    'ignore_errors' => true,
                    'timeout' => self::SERVER_START_SECONDS,
                ]])
            );
            $this->assertIsString(
                $page,
                'The page was not served: ' . file_get_contents($log)
            );

            return $page;
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($log);
        }
    }
}
