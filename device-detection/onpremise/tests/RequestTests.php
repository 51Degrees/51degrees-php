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
 * PHP builds DeviceDetectionOnPremise and the pipeline on every request,
 * while the native engine is built once, when PHP starts. A request that
 * reads a few values must not build the table of every property in the data
 * set, nor the pipeline's database of them, so these are built only when
 * something asks for them. Each case starts its own PHP process with the
 * Lite data file, as the engine is built when PHP starts.
 */
class RequestTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
        'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 ' .
        'Safari/537.36';

    // Runs a request that reads three values, then says whether the table
    // of every property and the pipeline's database were built, then asks
    // for the table and reads the values again. Prints, as JSON, those
    // answers, both sets of values, the table, and the table built here from
    // the native metadata the way the engine class built it when it was
    // constructed. The arguments are the autoloader and the User-Agent.
    private const REQUEST = <<<'PHP'
        require $argv[1];
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', $argv[2]);
        $flowData->process();
        $read = static function () use ($flowData): array {
            $values = [];
            foreach (['ismobile', 'BrowserName', 'PLATFORMNAME', 'difference'] as $name) {
                $value = $flowData->device->get($name);
                $values[$name] = $value->hasValue ? $value->value : $value->noValueMessage;
            }
            return $values;
        };
        $values = $read();
        $built = [
            'table' => (new ReflectionProperty($engine, 'properties'))->isInitialized($engine),
            'database' => (new ReflectionProperty($pipeline, 'propertyDatabase'))->isInitialized($pipeline),
        ];
        $types = ['string' => 'String', 'int' => 'Integer', 'bool' => 'Boolean',
            'double' => 'Double', 'javascript' => 'JavaScript', 'string[]' => 'Array'];
        $metaData = FiftyOneDegreesHashEngine::engine_get()->getMetaData();
        $native = $metaData->getProperties();
        $expected = [];
        for ($i = 0; $i < $native->getSize(); ++$i) {
            $property = $native->getByIndex($i);
            $dataFiles = [];
            $files = $property->getDataFilesWherePresent();
            for ($j = 0; $j < $files->size(); ++$j) {
                $dataFiles[] = $files->get($j);
            }
            $expected[strtolower($property->getName())] = [
                'name' => $property->getName(),
                'type' => $types[$property->getType()] ?? 'String',
                'dataFiles' => $dataFiles,
                'category' => $property->getCategory(),
                'description' => $property->getDescription(),
                'available' => $property->getAvailable(),
                'isList' => $property->getIsList(),
                'component' => $metaData->getComponentForProperty($property)->getName(),
            ];
        }
        $table = $engine->getProperties();
        $after = $read();
        $flowData->close();
        $pipeline->close();
        echo json_encode([
            'built' => $built,
            'values' => $values,
            'after' => $after,
            'table' => $table,
            'expected' => $expected,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        PHP;

    // Builds the engine and reads one property that is in the data set but
    // not among the names the engine was built with, then prints its no
    // value message and whether the table of every property was built, which
    // only the fallback in findProperty does here. The arguments are the
    // autoloader and the User-Agent.
    private const OUTSIDE = <<<'PHP'
        require $argv[1];
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $pipeline = (new fiftyone\pipeline\core\PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', $argv[2]);
        $flowData->process();
        $value = $flowData->device->get('platformversion');
        $table = (new ReflectionProperty($engine, 'properties'))->isInitialized($engine);
        $flowData->close();
        $pipeline->close();
        echo json_encode([$value->hasValue, $value->noValueMessage, $table]);
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
     * An engine built with names reads its values without building the
     * table of every property or the pipeline's database, and the table it
     * gives when asked is the one it built when it was constructed.
     */
    public function testAnEngineBuiltWithNamesBuildsNoTableForARead()
    {
        $this->assertARequest('PlatformName,IsMobile,BrowserName');
    }

    /**
     * An engine built with no names does the same.
     */
    public function testAnEngineBuiltWithNoNamesBuildsNoTableForARead()
    {
        $this->assertARequest(null);
    }

    /**
     * A property in the data set that the engine was not built with has no
     * value, and the reason is the native code's, because every read goes
     * by name.
     */
    public function testAPropertyTheEngineWasNotBuiltWithHasNoValue()
    {
        $command = $this->php('IsMobile') . ' -r ' .
            escapeshellarg(self::OUTSIDE) . ' -- ' .
            escapeshellarg($this->autoload()) . ' ' .
            escapeshellarg(self::CHROME);

        [$hasValue, $message, $table] = $this->runPhp($command);

        $this->assertFalse($hasValue);
        $this->assertIsString($message);
        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString('was not built with', $message);

        // The name is not one the engine was built with, so the read went to
        // the table, which building it here is the proof of.
        $this->assertTrue($table);

        // The reason is the native code's, so it is not the one the missing
        // property service writes, whose text starts with this.
        $this->assertStringNotContainsString(
            'not found in data for element',
            $message
        );
    }

    private function assertARequest(?string $startup): void
    {
        $command = $this->php($startup) . ' -r ' .
            escapeshellarg(self::REQUEST) . ' -- ' .
            escapeshellarg($this->autoload()) . ' ' .
            escapeshellarg(self::CHROME);
        $printed = $this->runPhp($command);

        $this->assertSame(
            ['table' => false, 'database' => false],
            $printed['built']
        );
        $this->assertFalse($printed['values']['ismobile']);
        $this->assertSame('Chrome', $printed['values']['BrowserName']);
        $this->assertSame('Windows', $printed['values']['PLATFORMNAME']);
        $this->assertIsInt($printed['values']['difference']);
        $this->assertSame($printed['values'], $printed['after']);

        // The table holds every property in the data set as it did, and
        // the match metrics, which are not in the data set.
        $metrics = array_diff_key($printed['table'], $printed['expected']);
        $this->assertSame(
            ['matchednodes', 'difference', 'drift', 'deviceid', 'useragents', 'iterations', 'method'],
            array_keys($metrics)
        );
        foreach ($metrics as $metric) {
            $this->assertSame('DeviceMetrics', $metric['category']);
        }
        $this->assertSame(
            $printed['expected'],
            array_intersect_key($printed['table'], $printed['expected'])
        );
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
     * @return array<mixed>
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
