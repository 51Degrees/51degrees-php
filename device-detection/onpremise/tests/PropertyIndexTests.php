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
use PHPUnit\Framework\TestCase;

/**
 * Values are read by their place in the list of property names the engine
 * was built with. The engine is built when PHP starts, so each case starts
 * its own PHP process with the settings on the command line, and reads
 * through the native extension.
 */
class PropertyIndexTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 ' .
        'Safari/537.36';

    // Builds the engine, after setting required_properties for this
    // request where one is given, detects one User-Agent and prints as JSON
    // the index table, the native required names and three values. The
    // arguments are the autoloader, the User-Agent and the value to set for
    // the request.
    private const READ = <<<'PHP'
        require $argv[1];
        if ($argv[3] !== '') {
            ini_set('FiftyOneDegreesHashEngine.required_properties', $argv[3]);
        }
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder())
            ->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', $argv[2]);
        $flowData->process();
        $values = [];
        foreach (['ismobile', 'browsername', 'platformname'] as $name) {
            $value = $flowData->device->get($name);
            $values[$name] = $value->hasValue ? $value->value : null;
        }
        $required = [];
        $names = $engine->engine->getRequiredProperties();
        for ($i = 0; $i < $names->size(); ++$i) {
            $required[] = $names->get($i);
        }
        echo json_encode([
            'indexes' => $engine->propertyIndexes,
            'required' => $required,
            'values' => $values,
        ]);
        PHP;

    // Reads IsMobile from one native result 20,000 times by name and
    // 20,000 times by its index among the engine's required names, and
    // prints the index, both values and the time each way in nanoseconds
    // per read.
    private const TIME = <<<'PHP'
        $evidence = new EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', $argv[1]);
        $engine = FiftyOneDegreesHashEngine::engine_get();
        $results = $engine->process($evidence);
        $required = $engine->getRequiredProperties();
        $index = -1;
        for ($i = 0; $i < $required->size(); ++$i) {
            if ($required->get($i) === 'IsMobile') {
                $index = $i;
                break;
            }
        }
        $reads = 20000;
        $byName = $results->getValueAsBool('IsMobile')->getValue();
        $byIndex = $results->getValueAsBool($index)->getValue();
        $start = hrtime(true);
        for ($i = 0; $i < $reads; ++$i) {
            $results->getValueAsBool('IsMobile');
        }
        $nameTime = hrtime(true) - $start;
        $start = hrtime(true);
        for ($i = 0; $i < $reads; ++$i) {
            $results->getValueAsBool($index);
        }
        $indexTime = hrtime(true) - $start;
        echo json_encode([
            'index' => $index,
            'byName' => $byName,
            'byIndex' => $byIndex,
            'nameNs' => $nameTime / $reads,
            'indexNs' => $indexTime / $reads,
        ]);
        PHP;

    // Builds DeviceDetectionOnPremise 2,000 times from the start-up engine,
    // then builds its index table on its own 2,000 times, and prints the
    // time of each in nanoseconds and the number of names in the table. The
    // argument is the autoloader.
    private const BUILD = <<<'PHP'
        require $argv[1];
        $builds = 2000;
        $class = fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise::class;
        new $class();
        $start = hrtime(true);
        for ($i = 0; $i < $builds; ++$i) {
            new $class();
        }
        $buildNs = (hrtime(true) - $start) / $builds;
        $table = Closure::bind(
            static fn ($engine) => $class::buildPropertyIndexes($engine),
            null,
            $class
        );
        $engine = FiftyOneDegreesHashEngine::engine_get();
        $start = hrtime(true);
        for ($i = 0; $i < $builds; ++$i) {
            $table($engine);
        }
        echo json_encode([
            'buildNs' => $buildNs,
            'tableNs' => (hrtime(true) - $start) / $builds,
            'names' => count($table($engine)),
        ]);
        PHP;

    public static function setUpBeforeClass(): void
    {
        ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE);
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
        }
    }

    /**
     * An engine built with names in an order of their own reads each value
     * by its place in that list, and gets the values an engine built with
     * no names gets.
     */
    public function testAnEngineBuiltWithNamesReadsTheSameValuesByIndex()
    {
        $named = $this->read('PlatformName,IsMobile,BrowserName');
        $none = $this->read(null);

        $this->assertSame(
            ['platformname' => 0, 'ismobile' => 1, 'browsername' => 2],
            $named['indexes']
        );
        $this->assertSame($none['values'], $named['values']);
        $this->assertSame('Chrome', $named['values']['browsername']);
        $this->assertSame('Windows', $named['values']['platformname']);
        $this->assertFalse($named['values']['ismobile']);
    }

    /**
     * An engine built with no names takes the names native code gives after
     * the first load as its list, so its table holds every one of them, in
     * that order, and its values are read by index.
     */
    public function testAnEngineBuiltWithNoNamesReadsByIndex()
    {
        $none = $this->read(null);

        $this->assertContains('IsMobile', $none['required']);
        $this->assertSame(self::firstPlaces($none['required']), $none['indexes']);
        $this->assertSame('Chrome', $none['values']['browsername']);
        $this->assertSame('Windows', $none['values']['platformname']);
        $this->assertFalse($none['values']['ismobile']);
    }

    /**
     * A name given twice keeps the place it was first given, as the native
     * code does.
     */
    public function testANameGivenTwiceKeepsItsFirstIndex()
    {
        $named = $this->read('IsMobile,BrowserName,IsMobile');

        $this->assertSame(0, $named['indexes']['ismobile']);
        $this->assertSame(1, $named['indexes']['browsername']);
    }

    /**
     * A setting given for one request, after the engine was built with no
     * names, does not change the table, which follows the names the engine
     * was built with.
     */
    public function testASettingForTheRequestDoesNotChangeTheTable()
    {
        $request = $this->read(null, 'IsMobile');
        $none = $this->read(null);

        $this->assertNotSame([], $none['indexes']);
        $this->assertSame($none['indexes'], $request['indexes']);
        $this->assertSame($none['values'], $request['values']);
    }

    /**
     * A read by index gives what a read by name gives, through the native
     * extension, and prints the time of each, so that the body of a change
     * can name a measured figure.
     */
    public function testAReadByIndexGivesWhatAReadByNameGives()
    {
        $timed = $this->time('BrowserName,IsMobile');

        $this->assertSame(1, $timed['index']);
        $this->assertSame($timed['byName'], $timed['byIndex']);
        fwrite(STDERR, sprintf(
            "\nIsMobile, 20,000 native reads each way: by name %.1f ns, " .
            "by index %.1f ns per read\n",
            $timed['nameNs'],
            $timed['indexNs']
        ));
    }

    /**
     * On an engine built with no names, a read by index gives what a read
     * by name gives, and the time of each is printed with the time to build
     * DeviceDetectionOnPremise and, within that, the time to build the
     * index table, which is what an engine built with no names now adds.
     */
    public function testAnEngineBuiltWithNoNamesTimesEachRead()
    {
        $timed = $this->time(null);

        $this->assertGreaterThanOrEqual(0, $timed['index']);
        $this->assertSame($timed['byName'], $timed['byIndex']);
        $built = $this->runPhp($this->php(null) . ' -r ' .
            escapeshellarg(self::BUILD) . ' -- ' .
            escapeshellarg($this->autoload()));
        fwrite(STDERR, sprintf(
            "\nNo names, IsMobile, 20,000 native reads each way: by name " .
            "%.1f ns, by index %.1f ns per read, build %.0f ns, index " .
            "table %.0f ns for %d names\n",
            $timed['nameNs'],
            $timed['indexNs'],
            $built['buildNs'],
            $built['tableNs'],
            $built['names']
        ));
    }

    /**
     * Starts PHP with the Lite data file and $startup as required_properties,
     * where one is given, runs READ, and returns what it printed.
     *
     * @return array{indexes: array<string, int>, values: array<string, mixed>}
     */
    private function read(?string $startup, string $request = ''): array
    {
        $command = $this->php($startup) . ' -r ' . escapeshellarg(self::READ) .
            ' -- ' . escapeshellarg($this->autoload()) . ' ' .
            escapeshellarg(self::CHROME) . ' ' . escapeshellarg($request);

        return $this->runPhp($command);
    }

    /**
     * Starts PHP with the Lite data file and $startup as required_properties,
     * runs TIME, and returns what it printed.
     *
     * @return array{index: int, byName: bool, byIndex: bool, nameNs: float, indexNs: float}
     */
    private function time(?string $startup): array
    {
        $command = $this->php($startup) . ' -r ' . escapeshellarg(self::TIME) .
            ' -- ' . escapeshellarg(self::CHROME);

        return $this->runPhp($command);
    }

    /**
     * Each name in lower case against its first place in the list, which is
     * the index native code gives.
     *
     * @param array<string> $names
     * @return array<string, int>
     */
    private static function firstPlaces(array $names): array
    {
        $indexes = [];
        foreach ($names as $index => $name) {
            $indexes[strtolower($name)] ??= $index;
        }

        return $indexes;
    }

    private function php(?string $startup): string
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}");
        if ($startup !== null) {
            $command .= ' -d ' . escapeshellarg(
                "FiftyOneDegreesHashEngine.required_properties={$startup}"
            );
        }

        return $command;
    }

    private function autoload(): string
    {
        return dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function runPhp(string $command): array
    {
        exec($command . ' 2>&1', $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $decoded = json_decode($printed, true);
        $this->assertIsArray($decoded, $printed);

        return $decoded;
    }
}
