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
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * Every value is read at the position IpIntelligenceOnPremise::getPosition
 * gives, so a position must name the same property as the name does, after
 * a refresh as well as before. The engine is built when PHP starts from the
 * free Lite data file with no required properties set, which takes every
 * property the file has, and is then refreshed from the free ASN data file,
 * which has other properties. Each case starts its own PHP process, as the
 * engine is built when PHP starts and a refresh changes it for the rest of
 * the process.
 */
class PositionsAfterRefreshTests extends TestCase
{
    // Builds the element on the engine PHP started with, refreshes it from
    // the source, looks up the address, and reads every property the native
    // engine now has, once by its name and once at the position the element
    // gives for it. Prints, as JSON, the native engine's names before and
    // after, how many values were compared, and each property whose two
    // reads differ. The arguments are the autoloader, the address and the
    // source.
    private const READ = <<<'PHP'
        require $argv[1];
        [, , $address, $source] = $argv;
        $engine = new fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise();
        $names = static function () use ($engine): array {
            $required = $engine->engine->getRequiredProperties();
            $names = [];
            for ($i = 0; $i < $required->size(); ++$i) {
                $names[] = $required->get($i);
            }
            return $names;
        };
        $accessor = static function (string $type): string {
            switch (strtolower($type)) {
                case 'weightedstring':
                case 'weightedwkbr':
                case 'weightedwkb':
                    return 'getValuesAsWeightedStringList';
                case 'weightedint':
                    return 'getValuesAsWeightedIntegerList';
                case 'weighteddouble':
                case 'weightedsingle':
                    return 'getValuesAsWeightedDoubleList';
                case 'weightedbool':
                    return 'getValuesAsWeightedBoolList';
                case 'int':
                    return 'getValueAsInteger';
                case 'double':
                case 'single':
                    return 'getValueAsDouble';
                case 'bool':
                    return 'getValueAsBool';
                case 'string[]':
                    return 'getValues';
                default:
                    return 'getValueAsString';
            }
        };
        $text = static function ($value): string {
            if (!$value->hasValue()) {
                return 'no value ' . $value->getNoValueMessage();
            }
            $inner = $value->getValue();
            if (!is_object($inner)) {
                return 'value ' . var_export($inner, true);
            }
            $parts = [];
            for ($i = 0; $i < $inner->size(); ++$i) {
                $item = $inner->get($i);
                $parts[] = var_export(is_object($item) ? $item->getValue() : $item, true);
            }
            return 'values ' . implode('|', $parts);
        };
        $before = $names();
        // A lookup before the refresh, as a request makes, so that the
        // element holds the positions of the engine it started with.
        $engine->getPosition($before[0]);
        $error = null;
        try {
            $engine->refreshData($source);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $after = $names();
        $types = [];
        foreach ($engine->getProperties() as $property) {
            $types[strtolower((string) $property['name'])] = (string) $property['type'];
        }
        $results = $engine->engine->process($address);
        $compared = 0;
        $differ = [];
        foreach ($after as $name) {
            $read = $accessor($types[strtolower($name)] ?? 'string');
            $byName = $text($results->{$read}($name));
            $byPosition = $text($results->{$read}($engine->getPosition($name)));
            ++$compared;
            if ($byName !== $byPosition) {
                $differ[$name] = ['by name' => $byName, 'by position' => $byPosition];
            }
        }
        echo json_encode(['error' => $error, 'before' => $before,
            'after' => $after, 'compared' => $compared, 'differ' => $differ]);
        PHP;

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
    }

    /**
     * After a refresh from a data file with other properties, each
     * position the element gives still names the property it is asked for.
     */
    public function testEachPositionNamesItsPropertyAfterARefreshFromAnotherFile(): void
    {
        $lite = $this->dataFile(ExampleConstants::LITE_DATA_FILE_NAME);
        $asn = $this->dataFile(ExampleConstants::ASN_DATA_FILE_NAME);

        $printed = $this->read($lite, $asn);

        $this->assertNull($printed['error']);
        $this->assertNotSame(
            $printed['before'],
            $printed['after'],
            'The two data files gave the engine the same properties, so ' .
            'the test reads nothing a stale position would get wrong.'
        );
        $this->assertGreaterThan(0, $printed['compared']);
        $this->assertSame(
            [],
            $printed['differ'],
            json_encode($printed['differ'], JSON_PRETTY_PRINT)
        );
    }

    /**
     * Starts PHP with the engine built from $dataFile, runs READ and
     * returns what it printed.
     *
     * @return array<string, mixed>
     */
    private function read(string $dataFile, string $source): array
    {
        $autoload = dirname(
            (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))
                ->getFileName(),
            2
        ) . '/autoload.php';
        $command = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg(
            "FiftyOneDegreesIpIntelligenceEngine.data_file={$dataFile}"
        ) . ' -r ' . escapeshellarg(self::READ) . ' -- ' .
            implode(' ', array_map('escapeshellarg', [
                $autoload, Constants::IPV4_ADDRESS, $source
            ])) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $decoded = json_decode($printed, true);
        $this->assertIsArray($decoded, $printed);

        return $decoded;
    }

    /**
     * A free data file in the folder the examples read them from, where CI
     * links them from the run's asset cache. A file that is not there skips
     * the test rather than downloading it, as a job of the build matrix
     * downloads nothing.
     */
    private function dataFile(string $name): string
    {
        $path = realpath(__DIR__ . '/../../cxx/data/ip-intelligence/' . $name);
        if ($path === false) {
            $this->markTestSkipped($name . ' is not in cxx/data/ip-intelligence.');
        }

        return $path;
    }
}
