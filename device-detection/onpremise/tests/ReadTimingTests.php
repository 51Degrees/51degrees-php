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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * Times the native engine PHP builds when it starts, with the free Lite data
 * file, in PHP processes of their own, and prints the figures. Process on an
 * engine configured with no names is timed against one configured with
 * every name, and a read of a value by name against a read by its position.
 * Each test fails when its multiple passes the limit set from the runners'
 * measurements.
 */
class ReadTimingTests extends TestCase
{
    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    private const NAMES_SETTING = 'FiftyOneDegreesHashEngine.required_properties';

    /**
     * Rounds timed. The figure compared is the median round's.
     */
    private const ROUNDS = 9;

    /**
     * Processes before the timed ones in each PHP process.
     */
    private const WARM_UP_PROCESSES = 100;

    /**
     * Processes timed in each PHP process.
     */
    private const TIMED_PROCESSES = 2000;

    /**
     * Requests in each round of reads, each processing the User-Agent once.
     */
    private const REQUESTS_PER_ROUND = 500;

    /**
     * How many times each value is read in a request, after a first read
     * that walks the graphs, so that only the reads are timed.
     */
    private const READS_PER_REQUEST = 5;

    /**
     * How many string and boolean properties the reads are timed over.
     */
    private const TIMED_PROPERTIES = 8;

    /**
     * The least that process on an engine configured with every name may
     * take, as a multiple of process on one configured with no names. It is
     * under half the smallest multiple measured on the runners the test runs
     * on. An engine created with no names walks no graph in process, so a
     * multiple near 1 means the names were passed after all.
     */
    private const MIN_EVERY_NAME_OVER_NO_NAMES = 4.0;

    /**
     * The most a read by name may take, as a multiple of a read by position.
     * It is a quarter above the largest multiple measured on the runners the
     * test runs on, so this fails when reading by name, which SwigData does,
     * becomes much slower than reading by position.
     */
    private const MAX_NAME_OVER_POSITION = 1.5;

    // Times process on the engine PHP built when it started, and prints the
    // names the engine was built with and the mean time of a process in
    // nanoseconds. The arguments are the autoloader, the User-Agent, and the
    // processes to warm up with and to time.
    private const PROCESS = <<<'CODE'
        require $argv[1];
        $engine = (new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise())->engine;
        $names = fiftyone\pipeline\devicedetection\SwigHelpers::vectorToArray(
            $engine->getRequiredProperties()
        );
        $evidence = new EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', $argv[2]);
        for ($i = 0; $i < (int) $argv[3]; ++$i) {
            $results = $engine->process($evidence);
        }
        $count = (int) $argv[4];
        $start = hrtime(true);
        for ($i = 0; $i < $count; ++$i) {
            $results = $engine->process($evidence);
        }
        $elapsed = hrtime(true) - $start;
        echo json_encode(['names' => $names, 'nanoseconds' => $elapsed / $count]);
        CODE;

    // Times reads of string and boolean values from the engine PHP built
    // when it started, by name, by position, and by a position found for
    // each read through a method call, strtolower and an array lookup. Each
    // round times all three, in an order that turns round each round. Prints
    // the names timed, whether a read by name and by position gave the same
    // value for each, and the mean time of a read in each round, in
    // nanoseconds. The arguments are the autoloader, the User-Agent, the
    // properties to time, the rounds, the requests in a round and the reads
    // of each value in a request.
    private const READS = <<<'CODE'
        require $argv[1];
        $engine = (new fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise())->engine;
        $required = fiftyone\pipeline\devicedetection\SwigHelpers::vectorToArray(
            $engine->getRequiredProperties()
        );
        $positions = array_flip($required);
        $metaData = $engine->getMetaData()->getProperties();
        $accessors = ['bool' => 'getValueAsBool', 'string' => 'getValueAsString'];
        $names = [];
        foreach ($required as $name) {
            $property = $metaData->getByKey($name);
            if ($property !== null && isset($accessors[$property->getType()])) {
                $names[$name] = $accessors[$property->getType()];
            }
            if (count($names) === (int) $argv[3]) {
                break;
            }
        }
        $finder = new class (array_change_key_case($positions)) {
            private array $positions;
            public function __construct(array $positions)
            {
                $this->positions = $positions;
            }
            public function position(string $name): int
            {
                return $this->positions[strtolower($name)];
            }
        };
        $evidence = new EvidenceDeviceDetectionSwig();
        $evidence->set('header.user-agent', $argv[2]);
        $text = static function ($value): string {
            return $value->hasValue()
                ? var_export($value->getValue(), true)
                : 'no value: ' . $value->getNoValueMessage();
        };
        $results = $engine->process($evidence);
        $agree = [];
        foreach ($names as $name => $accessor) {
            $agree[$name] = $text($results->{$accessor}($name)) ===
                $text($results->{$accessor}($positions[$name]));
        }
        $requests = (int) $argv[5];
        $reads = (int) $argv[6];
        $perRead = $requests * $reads * count($names);
        $ways = [
            'name' => static function () use ($engine, $evidence, $names, $requests, $reads): int {
                $elapsed = 0;
                for ($request = 0; $request < $requests; ++$request) {
                    $results = $engine->process($evidence);
                    foreach ($names as $name => $accessor) {
                        $results->{$accessor}($name);
                    }
                    $start = hrtime(true);
                    for ($read = 0; $read < $reads; ++$read) {
                        foreach ($names as $name => $accessor) {
                            $results->{$accessor}($name);
                        }
                    }
                    $elapsed += hrtime(true) - $start;
                }
                return $elapsed;
            },
            'position' => static function () use ($engine, $evidence, $names, $positions, $requests, $reads): int {
                $elapsed = 0;
                for ($request = 0; $request < $requests; ++$request) {
                    $results = $engine->process($evidence);
                    foreach ($names as $name => $accessor) {
                        $results->{$accessor}($name);
                    }
                    $start = hrtime(true);
                    for ($read = 0; $read < $reads; ++$read) {
                        foreach ($names as $name => $accessor) {
                            $results->{$accessor}($positions[$name]);
                        }
                    }
                    $elapsed += hrtime(true) - $start;
                }
                return $elapsed;
            },
            'found' => static function () use ($engine, $evidence, $names, $finder, $requests, $reads): int {
                $elapsed = 0;
                for ($request = 0; $request < $requests; ++$request) {
                    $results = $engine->process($evidence);
                    foreach ($names as $name => $accessor) {
                        $results->{$accessor}($name);
                    }
                    $start = hrtime(true);
                    for ($read = 0; $read < $reads; ++$read) {
                        foreach ($names as $name => $accessor) {
                            $results->{$accessor}($finder->position($name));
                        }
                    }
                    $elapsed += hrtime(true) - $start;
                }
                return $elapsed;
            },
        ];
        $order = array_keys($ways);
        $rounds = [];
        for ($round = 0; $round < (int) $argv[4]; ++$round) {
            $figures = [];
            foreach ($order as $way) {
                $figures[$way] = $ways[$way]() / $perRead;
            }
            $rounds[] = $figures;
            $order[] = array_shift($order);
        }
        echo json_encode(['names' => array_keys($names), 'agree' => $agree, 'rounds' => $rounds]);
        CODE;

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
     * An engine configured with no names, whether the setting is left out
     * or empty, is created with no names, so its process walks no graph and
     * is faster than one configured with every name.
     */
    public function testProcessWithNoNamesAgainstEveryName(): void
    {
        $names = $this->timeProcess(null)['names'];
        $this->assertNotSame([], $names);
        $configurations = [
            'no setting' => null,
            'an empty setting' => '',
            'every name' => implode(',', $names),
        ];

        $figures = array_fill_keys(array_keys($configurations), []);
        $order = array_keys($configurations);
        for ($round = 0; $round < self::ROUNDS; ++$round) {
            foreach ($order as $configuration) {
                $printed = $this->timeProcess($configurations[$configuration]);

                // Each has the same names, so only how the engine was
                // created differs.
                $this->assertSame($names, $printed['names'], $configuration);
                $figures[$configuration][] = $printed['nanoseconds'];
            }
            $order[] = array_shift($order);
        }

        $every = self::median($figures['every name']);
        foreach (['no setting', 'an empty setting'] as $configuration) {
            $multiple = $every / self::median($figures[$configuration]);
            $summary = sprintf(
                'Device Detection process on PHP %s, %s, %d names: %s %.1f ns ' .
                '(%.1f to %.1f), every name %.1f ns (%.1f to %.1f), every ' .
                'name over %s %.2f times, %s faster in %d of %d rounds.',
                PHP_VERSION,
                PHP_OS_FAMILY,
                count($names),
                $configuration,
                self::median($figures[$configuration]),
                min($figures[$configuration]),
                max($figures[$configuration]),
                $every,
                min($figures['every name']),
                max($figures['every name']),
                $configuration,
                $multiple,
                $configuration,
                self::roundsBelow($figures[$configuration], $figures['every name']),
                self::ROUNDS
            );
            fwrite(STDERR, PHP_EOL . $summary . PHP_EOL);

            $this->assertGreaterThanOrEqual(
                self::MIN_EVERY_NAME_OVER_NO_NAMES,
                $multiple,
                $summary
            );
        }
    }

    /**
     * A read by name is timed against a read by position, which is what the
     * specification's exception for reading by name rests on, and against a
     * read by a position found for each read in PHP, which is printed only.
     */
    public function testReadingByNameAgainstByPosition(): void
    {
        $command = $this->php(null) . ' -r ' . escapeshellarg(self::READS) .
            ' -- ' . escapeshellarg($this->autoload()) . ' ' .
            escapeshellarg(self::IPHONE) . ' ' . self::TIMED_PROPERTIES . ' ' .
            self::ROUNDS . ' ' . self::REQUESTS_PER_ROUND . ' ' .
            self::READS_PER_REQUEST;
        $printed = $this->runPhp($command);

        $this->assertCount(self::TIMED_PROPERTIES, $printed['names']);
        // Both reads give the same value, so the positions are right.
        $this->assertSame(
            array_fill_keys($printed['names'], true),
            $printed['agree']
        );

        $ways = ['name' => [], 'position' => [], 'found' => []];
        foreach ($printed['rounds'] as $round) {
            foreach (array_keys($ways) as $way) {
                $ways[$way][] = $round[$way];
            }
        }
        $multiple = self::median($ways['name']) / self::median($ways['position']);
        $summary = sprintf(
            'Device Detection read on PHP %s, %s, %d values: by name %.1f ns ' .
            '(%.1f to %.1f), by position %.1f ns (%.1f to %.1f), by a ' .
            'position found for each read %.1f ns (%.1f to %.1f), by name ' .
            'over by position %.2f times, by name over by a position found ' .
            '%.2f times, by name faster than by position in %d of %d rounds ' .
            'and than by a position found in %d.',
            PHP_VERSION,
            PHP_OS_FAMILY,
            count($printed['names']),
            self::median($ways['name']),
            min($ways['name']),
            max($ways['name']),
            self::median($ways['position']),
            min($ways['position']),
            max($ways['position']),
            self::median($ways['found']),
            min($ways['found']),
            max($ways['found']),
            $multiple,
            self::median($ways['name']) / self::median($ways['found']),
            self::roundsBelow($ways['name'], $ways['position']),
            self::ROUNDS,
            self::roundsBelow($ways['name'], $ways['found'])
        );
        fwrite(STDERR, PHP_EOL . $summary . PHP_EOL);

        $this->assertLessThanOrEqual(self::MAX_NAME_OVER_POSITION, $multiple, $summary);
    }

    /**
     * Starts PHP with the Lite data file and the names setting given, left
     * out when null, and times process in it.
     *
     * @return array<mixed>
     */
    private function timeProcess(?string $names): array
    {
        $command = $this->php($names) . ' -r ' . escapeshellarg(self::PROCESS) .
            ' -- ' . escapeshellarg($this->autoload()) . ' ' .
            escapeshellarg(self::IPHONE) . ' ' . self::WARM_UP_PROCESSES .
            ' ' . self::TIMED_PROCESSES;

        return $this->runPhp($command);
    }

    private function php(?string $names): string
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}");
        if ($names !== null) {
            $command .= ' -d ' . escapeshellarg(self::NAMES_SETTING . "={$names}");
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

    /**
     * @param array<float> $figures
     */
    private static function median(array $figures): float
    {
        sort($figures);
        $middle = intdiv(count($figures), 2);

        return count($figures) % 2 === 1
            ? (float) $figures[$middle]
            : ($figures[$middle - 1] + $figures[$middle]) / 2;
    }

    /**
     * In how many rounds the first figure was the lower.
     *
     * @param array<float> $first
     * @param array<float> $second
     */
    private static function roundsBelow(array $first, array $second): int
    {
        $below = 0;
        foreach ($first as $round => $figure) {
            if ($figure < $second[$round]) {
                ++$below;
            }
        }

        return $below;
    }
}
