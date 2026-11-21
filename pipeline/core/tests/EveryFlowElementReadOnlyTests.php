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

namespace fiftyone\pipeline\core\tests;

use PHPUnit\Framework\TestCase;

/**
 * Every FlowElement class in the repository's packages has a test that
 * processes it and then writes to its ElementData, expecting the error, as
 * the specification asks. The classes are found from the source, so a
 * FlowElement added later fails this until a test names it here.
 */
class EveryFlowElementReadOnlyTests extends TestCase
{
    /**
     * The test that checks each class, by its path from the repository
     * root.
     */
    private const READ_ONLY_TESTS = [
        'FlowElement' => 'pipeline/core/tests/FinishedWritesTests.php',
        'SequenceElement' => 'pipeline/core/tests/ReadOnlyWebElementsTests.php',
        'JsonBundlerElement' => 'pipeline/core/tests/ReadOnlyWebElementsTests.php',
        'JavascriptBuilderElement' => 'pipeline/core/tests/ReadOnlyWebElementsTests.php',
        'SetHeaderElement' => 'pipeline/core/tests/ReadOnlyWebElementsTests.php',
        'Engine' => 'pipeline/engines/tests/ReadOnlyTests.php',
        'CloudEngineBase' => 'pipeline/cloudrequestengine/tests/ReadOnlyTests.php',
        'CloudEngine' => 'pipeline/cloudrequestengine/tests/ReadOnlyTests.php',
        'CloudRequestEngine' => 'pipeline/cloudrequestengine/tests/ReadOnlyTests.php',
        'TranslationEngine' => 'pipeline/translation/tests/ReadOnlyTests.php',
        'DerivedPropertyElement' => 'pipeline/derivedproperty/tests/ReadOnlyTests.php',
        'DeviceDetectionCloud' => 'device-detection/cloud/tests/ReadOnlyTests.php',
        'HardwareProfileCloud' => 'device-detection/cloud/tests/ReadOnlyTests.php',
        'DeviceDetectionOnPremise' => 'device-detection/onpremise/tests/ReadOnlyTests.php',
        'IpIntelligenceCloud' => 'ip-intelligence/tests/ReadOnlyTests.php',
        'IpIntelligenceOnPremise' => 'ip-intelligence/tests/ReadOnlyOnPremiseTests.php',
        'CountryCodeTranslationEngine' => 'ip-intelligence/tests/ReadOnlyTests.php',
        'CountriesTranslationEngine' => 'ip-intelligence/tests/ReadOnlyTests.php',
        'GeoLocationCloud' => 'location/tests/ReadOnlyTests.php',
    ];

    /** Folders that hold no package source. */
    private const SKIPPED = ['vendor', 'tests', 'examples', 'cxx',
        'javascript-templates', 'derived-properties', 'docs', 'ci'];

    public function testEveryFlowElementHasAReadOnlyTest(): void
    {
        $root = dirname(__DIR__, 3);
        $extensions = [];
        foreach (['pipeline', 'device-detection', 'ip-intelligence', 'location'] as $folder) {
            foreach (self::sourceFiles($root . '/' . $folder) as $file) {
                preg_match_all(
                    '/class\s+(\w+)\s+extends\s+\\\\?([\w\\\\]+)/',
                    (string) file_get_contents($file),
                    $matches,
                    PREG_SET_ORDER
                );
                foreach ($matches as $match) {
                    $base = explode('\\', $match[2]);
                    $extensions[] = [$match[1], end($base)];
                }
            }
        }

        $found = ['FlowElement' => true];
        do {
            $added = false;
            foreach ($extensions as [$name, $base]) {
                if (isset($found[$base]) && !isset($found[$name])) {
                    $found[$name] = true;
                    $added = true;
                }
            }
        } while ($added);

        $this->assertGreaterThan(10, count($found));
        $missing = [];
        foreach (array_keys($found) as $name) {
            $file = self::READ_ONLY_TESTS[$name] ?? null;
            if ($file === null || !is_file($root . '/' . $file) ||
                strpos((string) file_get_contents($root . '/' . $file), $name) === false) {
                $missing[] = $name;
            }
        }
        $this->assertSame([], $missing);
    }

    /**
     * Every PHP file below a folder, leaving out folders of no package
     * source.
     *
     * @return array<int, string>
     */
    private static function sourceFiles(string $folder): array
    {
        $files = [];
        foreach (scandir($folder) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $path = $folder . '/' . $entry;
            if (is_dir($path)) {
                if (!in_array($entry, self::SKIPPED, true)) {
                    $files = array_merge($files, self::sourceFiles($path));
                }
            } elseif (substr($entry, -4) === '.php') {
                $files[] = $path;
            }
        }

        return $files;
    }
}
