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
use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;
use fiftyone\pipeline\ipintelligence\Messages;
use fiftyone\pipeline\ipintelligence\SwigHelpers;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\core\PipelineBuilder;
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
        $env = EnvironmentVariable::read(
            Constants::IPI_PATH_VARIABLE,
            Constants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
        );
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
     * The client IP is chosen from the keys the engine accepts, in the order
     * of its getKeys(), every query key before every server key, and the
     * chosen value's address is looked up whatever its family. Each case
     * gets the answers of the chosen address given alone.
     */
    public function testTheClientIpIsChosenInTheEngineOrder(): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();
        $keys = $pipeline->getElement('ip')->getEvidenceKeyFilter()->getList();
        $query = array_values(array_filter($keys, fn ($key) => strncmp($key, 'query.', 6) === 0));
        $server = array_values(array_filter($keys, fn ($key) => strncmp($key, 'server.', 7) === 0));
        $this->assertNotEmpty($query);
        $this->assertCount(count($query), $server);

        $cases = [
            [
                [
                    'header.x-forwarded-for' => '8.8.8.8',
                    $server[0] => Constants::IPV4_ADDRESS,
                    end($query) => Constants::IPV6_ADDRESS,
                ],
                [null, Constants::IPV6_ADDRESS],
            ],
            [
                [end($query) => Constants::IPV4_ADDRESS, $query[0] => '8.8.8.8'],
                ['8.8.8.8', null],
            ],
            [
                [end($server) => Constants::IPV4_ADDRESS, $server[0] => '8.8.8.8'],
                ['8.8.8.8', null],
            ],
            [
                ['header.x-forwarded-for' => '8.8.8.8'],
                [null, null],
            ],
        ];

        try {
            foreach ($cases as [$evidence, [$ipv4, $ipv6]]) {
                $label = json_encode($evidence);
                $flowData = $pipeline->createFlowData();
                $alone = $pipeline->createFlowData();
                try {
                    foreach ($evidence as $key => $value) {
                        $flowData->evidence->set($key, $value);
                    }
                    $actual = $flowData->process()->ip;

                    $this->assertSame($ipv4 !== null, $actual->ip->hasValue, $label);
                    $this->assertSame($ipv6 !== null, $actual->ipv6->hasValue, $label);
                    if ($ipv4 !== null) {
                        $this->assertSame($ipv4, $actual->ip->value, $label);
                    }
                    if ($ipv6 !== null) {
                        $this->assertSame($ipv6, $actual->ipv6->value, $label);
                    }

                    $alone->evidence->set($query[0], $ipv4 ?? $ipv6 ?? '');
                    $expected = $alone->process()->ip;
                    foreach (array_keys($pipeline->getElement('ip')->getProperties()) as $key) {
                        if ($key === 'ip' || $key === 'ipv6') {
                            continue;
                        }
                        $this->assertEquals($expected->get($key), $actual->get($key), "$label: $key");
                    }
                } finally {
                    $flowData->close();
                    $alone->close();
                }
            }
        } finally {
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

    /**
     * Asking a closed engine whether it has a property answers rather than
     * throwing, because isset must not throw. Looking a name up needs the
     * engine, which closing the pipeline releases.
     *
     * The pipeline leaves out the two elements that read every property,
     * because each of them builds the table of every property, and the
     * table answers without the engine, so the closed engine would never
     * be reached.
     */
    public function testAskingAClosedEngineAboutAPropertyAnswersFalse(): void
    {
        $engine = new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]);
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false
        ]))->add($engine)->build();

        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $flowData->process();
            $known = isset($flowData->ip->registeredname);
        } finally {
            $flowData->close();
            $pipeline->close();
        }

        // A name the engine was built with is looked up on its own, so the
        // table is still unbuilt. Without this the test would pass on the
        // table alone and never reach the closed engine.
        $this->assertTrue($known);
        $this->assertFalse(
            (new \ReflectionProperty($engine, 'properties'))
                ->isInitialized($engine)
        );
        $this->assertNull($engine->engine);

        // The name has to be one nothing asked about while the engine was
        // open, because the engine keeps what it has already looked up.
        $this->assertFalse(isset($flowData->ip->registeredcountry));
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
     * Under a web server, here PHP's built-in one, whose SAPI is not 'cli',
     * PHP builds this class in every request, so the "dataFile" setting,
     * which would load the data file in every request, is refused. The
     * engine built when PHP started is used with no settings.
     */
    public function testDataFileSettingIsRefusedUnderAWebServer(): void
    {
        $answers = $this->askWebServer(['dataFile', 'startup']);
        $this->assertSame(
            Messages::DATA_FILE_SETTING_REFUSED,
            $answers['dataFile']
        );
        $this->assertSame('OK', $answers['startup']);
    }

    /**
     * On the command line a tool builds its engine once for its whole run,
     * so the "dataFile" setting is accepted there.
     */
    public function testDataFileSettingIsAcceptedOnTheCommandLine(): void
    {
        $this->assertSame('cli', PHP_SAPI);
        $engine = new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'LowMemory'
        ]);
        try {
            $this->assertSame('ip', $engine->dataKey);
        } finally {
            $engine->close();
        }
    }

    // Answers each request to PHP's built-in web server with OK, or the
    // message of the exception, after building IpIntelligenceOnPremise from
    // the "dataFile" setting when the path is /dataFile, and with no
    // settings otherwise. The placeholders are the autoloader and the data
    // file.
    private const SERVE_PHP = <<<'PHP'
        <?php
        require %s;
        $settings = $_SERVER['REQUEST_URI'] === '/dataFile'
            ? ['dataFile' => %s, 'performanceProfile' => 'LowMemory']
            : [];
        try {
            $engine = new fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise(
                $settings
            );
            $engine->close();
            echo 'OK';
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
        PHP;

    /**
     * Starts PHP's built-in web server with the php.ini data file naming the
     * Lite file, asks it for each path, and returns its answers by path.
     *
     * @param array<string> $paths
     * @return array<string, string>
     */
    private function askWebServer(array $paths): array
    {
        $dataFile = realpath($this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $folder = sys_get_temp_dir() . '/ipi-web-server-' . uniqid();
        mkdir($folder);
        $router = $folder . '/router.php';
        $log = $folder . '/server.log';
        file_put_contents($router, sprintf(
            self::SERVE_PHP,
            var_export($autoload, true),
            var_export($dataFile, true)
        ));

        // A free port, from a socket the system gives one to and then closes.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $server = proc_open(
            [
                PHP_BINARY,
                '-d',
                "FiftyOneDegreesIpIntelligenceEngine.data_file={$dataFile}",
                '-S',
                $address,
                $router
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes
        );
        $this->assertIsResource($server);
        try {
            $answers = [];
            foreach ($paths as $path) {
                $answers[$path] = $this->ask("http://{$address}/{$path}", $log);
            }

            return $answers;
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($router);
            @unlink($log);
            @rmdir($folder);
        }
    }

    /**
     * The body of the answer from the URL, waiting up to 30 seconds for the
     * server to start.
     */
    private function ask(string $url, string $log): string
    {
        $deadline = microtime(true) + 30;
        do {
            $answer = @file_get_contents($url);
            if ($answer !== false) {
                return $answer;
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        $this->fail("No answer from {$url}. " . @file_get_contents($log));
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

    /**
     * PHP builds the engine class and the pipeline on every request, so a
     * request that reads a few values builds neither the table of every
     * property nor the pipeline's database of them. The table, when asked
     * for, is the one the engine built when it was constructed.
     */
    public function testAnEngineBuiltWithNamesBuildsNoTableForARead(): void
    {
        $this->assertARequestBuildsNoTable(
            $this->buildEngineWith(['RegisteredName', 'RegisteredCountry'])
        );
    }

    /**
     * An engine built with no names does the same.
     */
    public function testAnEngineBuiltWithNoNamesBuildsNoTableForARead(): void
    {
        $this->assertARequestBuildsNoTable($this->buildEngine());
    }

    private function assertARequestBuildsNoTable(IpIntelligenceOnPremise $engine): void
    {
        $names = ['registeredname', 'RegisteredCountry', 'ip'];
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $flowData->process();
            $values = $this->readEach($flowData, $names);

            $this->assertFalse(
                (new \ReflectionProperty($engine, 'properties'))->isInitialized($engine)
            );
            $this->assertFalse(
                (new \ReflectionProperty($pipeline, 'propertyDatabase'))->isInitialized($pipeline)
            );
            $this->assertSame(Constants::IPV4_ADDRESS, $values['ip']);

            // The table holds every available property in the data set as
            // it did, and the two echoed addresses.
            $expected = $this->tableOf($engine);
            $table = $engine->getProperties();
            $this->assertSame(['ip', 'ipv6'], array_keys(array_diff_key($table, $expected)));
            $this->assertSame($expected, array_intersect_key($table, $expected));

            // Read again, now through the table, which gives the same.
            $this->assertEquals($values, $this->readEach($flowData, $names));
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    /**
     * The value of each property named, or its no value message.
     *
     * @param array<string> $names
     * @return array<string, mixed>
     */
    private function readEach($flowData, array $names): array
    {
        $values = [];
        foreach ($names as $name) {
            $value = $flowData->ip->get($name);
            $values[$name] = $value->hasValue ? $value->value : $value->noValueMessage;
        }

        return $values;
    }

    /**
     * The table of every available property, built from the native metadata
     * the way the engine class built it when it was constructed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function tableOf(IpIntelligenceOnPremise $engine): array
    {
        $metaData = $engine->engine->getMetaData();
        $native = $metaData->getProperties();
        $table = [];
        for ($i = 0; $i < $native->getSize(); ++$i) {
            $property = $native->getByIndex($i);
            if ($property->getAvailable()) {
                $table[strtolower($property->getName())] = [
                    'name' => $property->getName(),
                    'type' => $property->getType(),
                    'datafiles' => SwigHelpers::vectorToArray($property->getDataFilesWherePresent()),
                    'category' => $property->getCategory(),
                    'description' => $property->getDescription(),
                    'component' => $metaData->getComponentForProperty($property)->getName(),
                ];
            }
        }

        return $table;
    }

    /**
     * An engine built with names in an order of their own gets the values
     * an engine built with no names gets.
     */
    public function testAnEngineBuiltWithNamesReadsTheSameValues(): void
    {
        $none = $this->buildEngine();
        try {
            $names = [];
            foreach ($none->getProperties() as $key => $property) {
                if ($key !== 'ip' && $key !== 'ipv6') {
                    $names[] = $property['name'];
                }
            }
            $names = array_slice(array_reverse($names), 0, 3);
            $this->assertNotEmpty($names);
            $byName = $this->valuesOf($none, $names);
        } finally {
            $none->close();
        }

        $named = $this->buildEngineWith($names);
        try {
            $this->assertEquals($byName, $this->valuesOf($named, $names));
        } finally {
            $named->close();
        }
    }

    /**
     * @param array<string> $names
     */
    private function buildEngineWith(array $names): IpIntelligenceOnPremise
    {
        return new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance',
            'restrictedProperties' => $names
        ]);
    }

    /**
     * The value of each property named, or its no value message, for one
     * IPv4 address.
     *
     * @param array<string> $names
     * @return array<string, mixed>
     */
    private function valuesOf(IpIntelligenceOnPremise $engine, array $names): array
    {
        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $flowData->process();
            $values = [];
            foreach ($names as $name) {
                $value = $flowData->ip->get(strtolower($name));
                $values[$name] = $value->hasValue
                    ? $value->value
                    : $value->noValueMessage;
            }
        } finally {
            $flowData->close();
            $pipeline->close();
        }

        return $values;
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
        // An empty data file setting overrides one in php.ini, so that PHP
        // builds no engine when it starts.
        $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg(
            'FiftyOneDegreesIpIntelligenceEngine.data_file=' .
            ($dataFileProfile === '' ? $dataFile : '')
        );
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
