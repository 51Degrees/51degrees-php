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

use PHPUnit\Framework\TestCase;

/**
 * The on-premise engine gives the data set's components, profiles and
 * values itself, as well as its properties, and lists the Metrics
 * component that holds the match metrics. The engine is built when PHP
 * starts, so the case runs in its own PHP with the free Lite data file.
 */
class MetaDataTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    // The match metrics, in the order the specification's table gives.
    private const METRICS = ['MatchedNodes', 'Difference', 'Drift', 'DeviceId',
        'UserAgents', 'Iterations', 'Method'];

    // Reads the components, profiles and values through the engine, and the
    // same through the native metadata to compare with, and prints them as
    // JSON. The argument is the autoloader.
    private const READ = <<<'PHP'
        require $argv[1];
        $engine = new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise();
        $metaData = FiftyOneDegreesHashEngine::engine_get()->getMetaData();
        $profiles = ['count' => 0, 'components' => [], 'first' => null];
        foreach ($engine->getProfiles() as $profile) {
            ++$profiles['count'];
            $profiles['components'][$profile['component']] = true;
            $profiles['first'] = $profiles['first'] ?? $profile;
        }
        $profiles['components'] = array_keys($profiles['components']);
        $values = ['count' => 0, 'properties' => [], 'first' => null];
        foreach ($engine->getValues() as $value) {
            ++$values['count'];
            $values['properties'][$value['property']] = true;
            $values['first'] = $values['first'] ?? $value;
        }
        $values['properties'] = array_keys($values['properties']);
        $native = $metaData->getComponents();
        $nativeComponents = [];
        for ($i = 0; $i < $native->getSize(); ++$i) {
            $nativeComponents[] = $native->getByIndex($i)->getName();
        }
        $components = [];
        foreach ($engine->getProperties() as $property) {
            $components[$property['name']] = $property['component'];
        }
        echo json_encode([
            'components' => $engine->getComponents(),
            'profiles' => $profiles,
            'values' => $values,
            'nativeComponents' => $nativeComponents,
            'nativeProfiles' => $metaData->getProfiles()->getSize(),
            'nativeValues' => $metaData->getValues()->getSize(),
            'propertyComponents' => $components,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        PHP;

    public function testComponents(): void
    {
        $printed = $this->read();
        $components = $printed['components'];

        // The data set's components, then Metrics.
        $this->assertSame(
            array_merge($printed['nativeComponents'], ['Metrics']),
            array_column($components, 'name')
        );
        foreach ($printed['nativeComponents'] as $name) {
            $component = $components[strtolower($name)];
            $this->assertIsInt($component['componentId'], $name);
            $this->assertIsInt($component['defaultProfileId'], $name);
            $this->assertNotEmpty($component['properties'], $name);
        }
        $this->assertSame(self::METRICS, $components['metrics']['properties']);

        // Each property's component is in the list, and lists the property.
        foreach ($printed['propertyComponents'] as $property => $component) {
            $this->assertArrayHasKey(strtolower($component), $components, $property);
            $this->assertContains(
                $property,
                $components[strtolower($component)]['properties'],
                $property
            );
        }
    }

    public function testProfiles(): void
    {
        $printed = $this->read();
        $profiles = $printed['profiles'];

        $this->assertGreaterThan(0, $printed['nativeProfiles']);
        $this->assertSame($printed['nativeProfiles'], $profiles['count']);
        $this->assertIsInt($profiles['first']['profileId']);
        $this->assertEmpty(array_diff(
            $profiles['components'],
            $printed['nativeComponents']
        ));
    }

    public function testValues(): void
    {
        $printed = $this->read();
        $values = $printed['values'];

        $this->assertGreaterThan(0, $printed['nativeValues']);
        $this->assertSame($printed['nativeValues'], $values['count']);
        $this->assertSame(
            ['name', 'property', 'description', 'url'],
            array_keys($values['first'])
        );
        $this->assertEmpty(array_diff(
            $values['properties'],
            array_keys($printed['propertyComponents'])
        ));
    }

    /**
     * What the engine read in a PHP started with the Lite data file.
     *
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}") .
            ' -r ' . escapeshellarg(self::READ) . ' -- ' .
            escapeshellarg($this->autoload()) . ' 2>&1';
        exec($command, $lines, $status);
        $output = implode("\n", $lines);
        $this->assertSame(0, $status, $output);
        $printed = json_decode($output, true);
        $this->assertIsArray($printed, $output);

        return $printed;
    }

    private function autoload(): string
    {
        return dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
    }
}
