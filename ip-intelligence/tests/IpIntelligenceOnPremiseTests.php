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

use fiftyone\pipeline\ipintelligence\Constants as IpiConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;
use fiftyone\pipeline\ipintelligence\Messages;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * On-premise IP intelligence tests.
 *
 * These tests require the native FiftyOneDegreesIpIntelligenceEngine
 * extension to be built and loaded, and an .ipi data file to be available.
 * When either is missing the tests are skipped (the same convention used by
 * device-detection-php-onpremise), so the suite is green in environments
 * where the native engine has not been built.
 */
class IpIntelligenceOnPremiseTests extends TestCase
{
    private static ?string $dataFile = null;

    public static function setUpBeforeClass(): void
    {
        self::$dataFile = self::locateDataFile();
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        if (self::$dataFile === null) {
            $this->markTestSkipped('No .ipi data file found (set _51DEGREES_IPI_PATH or place a file under assets or cxx/data/ip-intelligence).');
        }
    }

    private static function locateDataFile(): ?string
    {
        $env = getenv(Constants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR);
        if ($env && file_exists($env)) {
            return $env;
        }

        $candidates = array_merge(
            glob(__DIR__ . '/../assets/*.ipi') ?: [],
            glob(__DIR__ . '/../../cxx/data/ip-intelligence/*.ipi') ?: []
        );

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildEngine(): IpIntelligenceOnPremise
    {
        return new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]);
    }

    public function testElementDataKey(): void
    {
        $engine = $this->buildEngine();
        try {
            $this->assertSame('ip', $engine->dataKey);
        } finally {
            $engine->close();
        }
    }

    public function testPropertiesPopulated(): void
    {
        $engine = $this->buildEngine();
        try {
            $properties = $engine->getProperties();
            $this->assertNotEmpty($properties);
            // The synthetic echo properties are always present.
            $this->assertArrayHasKey('ip', $properties);
            $this->assertArrayHasKey('ipv6', $properties);
        } finally {
            $engine->close();
        }
    }

    public function testEvidenceKeyFilter(): void
    {
        $engine = $this->buildEngine();
        try {
            $filter = $engine->getEvidenceKeyFilter();
            // The engine should accept at least one client-ip evidence key.
            $accepts = $filter->filterEvidenceKey('query.client-ip')
                || $filter->filterEvidenceKey('server.client-ip')
                || $filter->filterEvidenceKey('query.client-ip-51d');
            $this->assertTrue($accepts);
        } finally {
            $engine->close();
        }
    }

    public function testProcessIpv4(): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();

        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $result = $flowData->process();

            $ip = $result->ip;

            // The supplied IP is echoed back.
            $this->assertTrue($ip->ip->hasValue);
            $this->assertSame(Constants::IPV4_ADDRESS, $ip->ip->value);

            // RegisteredName is a core IP intelligence property. It is only
            // present in some data files (for example it is absent from the ASN
            // file), and get() throws for a property the data file does not
            // contain, so only assert on it when the engine actually exposes it.
            $properties = $pipeline->getElement('ip')->getProperties();
            if (array_key_exists('registeredname', $properties)) {
                $registeredName = $ip->get('registeredname');
                $this->assertNotNull($registeredName);
                if ($registeredName->hasValue) {
                    $this->assertNotNull($registeredName->value);
                } else {
                    $this->assertNotNull($registeredName->noValueMessage);
                }
            }
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    /**
     * An IPv4-mapped IPv6 address is the IPv4 address it carries: it is
     * reported as Ip, IpV6 has no value, and every property reads the same
     * as for the plain IPv4 address.
     */
    public function testMappedAddressGivesTheIpv4Answers(): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();

        $plain = $pipeline->createFlowData();
        try {
            $plain->evidence->set('query.client-ip', '8.8.8.8');
            $expected = $plain->process()->ip;

            foreach (['::ffff:8.8.8.8', '::FFFF:8.8.8.8', '::ffff:808:808', '0:0:0:0:0:ffff:808:808'] as $form) {
                $flowData = $pipeline->createFlowData();
                try {
                    $flowData->evidence->set('query.client-ip', $form);
                    $actual = $flowData->process()->ip;

                    $this->assertTrue($actual->ip->hasValue, $form);
                    $this->assertSame('8.8.8.8', $actual->ip->value, $form);
                    $this->assertFalse($actual->ipv6->hasValue, $form);

                    foreach (array_keys($pipeline->getElement('ip')->getProperties()) as $key) {
                        if ($key === 'ip' || $key === 'ipv6') {
                            continue;
                        }
                        $this->assertEquals($expected->get($key), $actual->get($key), "$form: $key");
                    }
                } finally {
                    $flowData->close();
                }
            }
        } finally {
            $plain->close();
            $pipeline->close();
        }
    }

    /**
     * Closing the FlowData releases its results and closing the pipeline
     * releases the engine, and each throws an exception if used afterwards.
     */
    public function testCloseReleasesResultsAndEngine(): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();

        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $echo = $flowData->process()->ip->ip->value;
        } finally {
            $flowData->close();
            $pipeline->close();
        }

        $this->assertSame(Constants::IPV4_ADDRESS, $echo);
        $this->assertNull($pipeline->getElement('ip')->engine);

        $message = null;
        try {
            $flowData->ip->get('ip');
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $this->assertSame(Messages::RESULTS_CLOSED, $message);

        $after = $pipeline->createFlowData();
        try {
            $after->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $this->expectExceptionMessage(Messages::ENGINE_CLOSED);
            $after->process();
        } finally {
            $after->close();
        }
    }

    public function testInvalidPerformanceProfileRejected(): void
    {
        $message = null;
        try {
            new IpIntelligenceOnPremise([
                'dataFile' => self::$dataFile,
                'performanceProfile' => 'NotARealProfile'
            ]);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $this->assertSame(
            sprintf(
                Messages::INVALID_PERFORMANCE_PROFILE,
                'NotARealProfile',
                implode(', ', IpiConstants::PERFORMANCE_PROFILES)
            ),
            $message
        );
    }

    /**
     * An engine built from a dataFile setting opens its own file handles in
     * the process that builds it, so it takes any profile. The php.ini
     * profile is for the engine the extension builds when PHP starts, so it
     * does not stop one.
     */
    public function testAnEngineBuiltFromSettingsTakesAnyProfile(): void
    {
        $setting = 'FiftyOneDegreesIpIntelligenceEngine.performance_profile';
        $original = ini_get($setting);
        ini_set($setting, 'LowMemory');

        try {
            foreach (['LowMemory', 'Balanced', 'HighPerformance', 'MaxPerformance', null] as $profile) {
                $engine = new IpIntelligenceOnPremise([
                    'dataFile' => self::$dataFile,
                    'performanceProfile' => $profile
                ]);
                try {
                    $this->assertSame('ip', $engine->dataKey, (string) $profile);
                } finally {
                    $engine->close();
                }
            }
        } finally {
            ini_set($setting, $original === false ? '' : $original);
        }
    }

    /**
     * The engine the extension builds when PHP starts only exists when the
     * data_file php.ini setting was given at start-up.
     */
    private function requireStartupEngine(): void
    {
        if (!function_exists('ipi_engine_get') || \ipi_engine_get() === null) {
            $this->markTestSkipped('No start-up engine. Start PHP with FiftyOneDegreesIpIntelligenceEngine.data_file set to run this test.');
        }
    }

    /**
     * The start-up engine is built before the process manager forks its
     * workers, so it always holds the data file in memory. A different
     * profile in php.ini raises a clear exception rather than being silently
     * ignored.
     */
    public function testStartupEngineRefusesAnotherIniProfile(): void
    {
        $this->requireStartupEngine();
        $setting = 'FiftyOneDegreesIpIntelligenceEngine.performance_profile';
        $original = ini_get($setting);

        try {
            foreach (['LowMemory', 'Balanced', 'HighPerformance', 'NotARealProfile'] as $profile) {
                ini_set($setting, $profile);
                $message = null;
                try {
                    new IpIntelligenceOnPremise([]);
                } catch (\Throwable $e) {
                    $message = $e->getMessage();
                }

                $this->assertSame(
                    sprintf(Messages::PERFORMANCE_PROFILE_ERROR, $profile),
                    $message,
                    $profile
                );
            }
        } finally {
            ini_set($setting, $original === false ? '' : $original);
        }
    }

    /**
     * MaxPerformance, in any case, and leaving the profile unset are what
     * the start-up engine runs with, so they are accepted.
     */
    public function testStartupEngineAcceptsMaxPerformance(): void
    {
        $this->requireStartupEngine();
        $setting = 'FiftyOneDegreesIpIntelligenceEngine.performance_profile';
        $original = ini_get($setting);

        try {
            foreach (['', 'MaxPerformance', 'maxperformance'] as $profile) {
                ini_set($setting, $profile);
                $engine = new IpIntelligenceOnPremise([]);
                try {
                    $this->assertSame('ip', $engine->dataKey, $profile);
                } finally {
                    $engine->close();
                }
            }
        } finally {
            ini_set($setting, $original === false ? '' : $original);
        }
    }

    /**
     * PHP started with a data_file setting builds the start-up engine in
     * memory, so IpIntelligenceOnPremise takes it up when the php.ini profile
     * is unset or MaxPerformance, and refuses any other.
     */
    public function testStartupEngineProfileWhenPhpStarts(): void
    {
        $this->assertSame('OK', $this->startPhp(null)['result']);
        $this->assertSame('OK', $this->startPhp('MaxPerformance')['result']);
        $this->assertSame(
            sprintf(Messages::PERFORMANCE_PROFILE_ERROR, 'LowMemory'),
            $this->startPhp('LowMemory')['result']
        );
    }

    /**
     * The start-up engine holds the whole data file in memory, so it keeps no
     * handle on the file for forked workers to share. A LowMemory engine
     * built from a dataFile setting does keep handles, which shows the count
     * works.
     */
    public function testStartupEngineKeepsNoFileHandles(): void
    {
        if (!is_dir('/proc/self/fd')) {
            $this->markTestSkipped(
                'Counting open file handles needs /proc/self/fd, which this ' .
                'system does not have.'
            );
        }
        $startup = $this->startPhp(null);
        $this->assertSame('OK', $startup['result']);
        $this->assertSame(0, $startup['handles']);

        $lowMemory = $this->startPhp(null, 'LowMemory');
        $this->assertSame('OK', $lowMemory['result']);
        $this->assertGreaterThan(0, $lowMemory['handles']);
    }

    // Builds IpIntelligenceOnPremise from the start-up engine, or from the
    // data file with the profile given, and prints as JSON OK or the
    // exception's message, and the open handles on the data file, or -1
    // where /proc/self/fd does not exist. The arguments are the autoloader,
    // the data file and the profile, empty for the start-up engine.
    private const START_PHP = <<<'PHP'
        require $argv[1];
        $settings = $argv[3] === ''
            ? []
            : ['dataFile' => $argv[2], 'performanceProfile' => $argv[3]];
        try {
            $engine = new fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise(
                $settings
            );
            $result = 'OK';
        } catch (Throwable $e) {
            $result = $e->getMessage();
        }
        $handles = -1;
        if (is_dir('/proc/self/fd')) {
            $handles = 0;
            foreach (scandir('/proc/self/fd') as $fd) {
                if (@readlink("/proc/self/fd/{$fd}") === $argv[2]) {
                    ++$handles;
                }
            }
        }
        echo json_encode(['result' => $result, 'handles' => $handles]);
        PHP;

    /**
     * Starts PHP with the php.ini settings naming the Lite data file and
     * $iniProfile, runs START_PHP, and returns what it printed. With
     * $dataFileProfile, php.ini names no data file and START_PHP builds an
     * engine from a dataFile setting with that profile instead.
     *
     * @return array{result: string, handles: int}
     */
    private function startPhp(
        ?string $iniProfile,
        string $dataFileProfile = ''
    ): array {
        $dataFile = realpath($this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $command = escapeshellarg(PHP_BINARY);
        if ($dataFileProfile === '') {
            $command .= ' -d ' . escapeshellarg(
                "FiftyOneDegreesIpIntelligenceEngine.data_file={$dataFile}"
            );
        }
        if ($iniProfile !== null) {
            $command .= ' -d ' . escapeshellarg(
                "FiftyOneDegreesIpIntelligenceEngine.performance_profile={$iniProfile}"
            );
        }
        $command .= ' -r ' . escapeshellarg(self::START_PHP) . ' -- ' .
            escapeshellarg($autoload) . ' ' . escapeshellarg($dataFile) . ' ' .
            escapeshellarg($dataFileProfile) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $started = json_decode($printed, true);
        $this->assertIsArray($started, $printed);

        return $started;
    }

    /**
     * The free Lite data file. CI fetches it into the package's assets
     * folder under its asset name, and a fresh clone downloads it into the
     * data repository.
     */
    private function liteDataFile(): string
    {
        $asset = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (is_file($asset)) {
            return $asset;
        }
        $path = __DIR__ . '/../../cxx/data/ip-intelligence/' .
            ExampleConstants::LITE_DATA_FILE_NAME;
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile($path),
            'The free Lite data file could not be found or downloaded.'
        );

        return $path;
    }
}
