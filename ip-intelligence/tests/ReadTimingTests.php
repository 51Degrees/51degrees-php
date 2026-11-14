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

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\OnPremiseTestCase;

/**
 * Times the native engine with the data file, in alternate rounds whose
 * order turns round each round. A read of a value by name is timed against
 * a read by its position, and process on an engine created with no names
 * against one created with every name.
 */
class ReadTimingTests extends OnPremiseTestCase
{
    /**
     * Rounds timed. The figure compared is the median round's.
     */
    private const ROUNDS = 9;

    /**
     * Requests in each round, each processing one address.
     */
    private const REQUESTS_PER_ROUND = 500;

    /**
     * How many times each value is read in a request, after a first read
     * that walks the graphs, so that only the reads are timed.
     */
    private const READS_PER_REQUEST = 5;

    private const NANOSECONDS_PER_MICROSECOND = 1000;

    private const PROFILE = 'MaxPerformance';

    public function testReadingByNameAgainstByPosition(): void
    {
        $engine = self::buildEngine(['performanceProfile' => self::PROFILE]);
        try {
            $names = self::weightedStringNames($engine);
            $this->assertNotSame([], $names);
            $positions = array_flip(self::requiredNames($engine));

            $byName = [];
            $byPosition = [];
            for ($round = 0; $round < self::ROUNDS; ++$round) {
                if ($round % 2 === 0) {
                    $byName[] = self::nanosecondsPerRead($engine, $names, null);
                    $byPosition[] = self::nanosecondsPerRead($engine, $names, $positions);
                } else {
                    $byPosition[] = self::nanosecondsPerRead($engine, $names, $positions);
                    $byName[] = self::nanosecondsPerRead($engine, $names, null);
                }
            }

            fwrite(STDERR, PHP_EOL . sprintf(
                'IP Intelligence read on PHP %s, %s, %d values: ' .
                'by name %.1f ns (%.1f to %.1f), by position %.1f ns ' .
                '(%.1f to %.1f), by name faster in %d of %d rounds.',
                PHP_VERSION,
                PHP_OS_FAMILY,
                count($names),
                self::median($byName),
                min($byName),
                max($byName),
                self::median($byPosition),
                min($byPosition),
                max($byPosition),
                self::roundsBelow($byName, $byPosition),
                self::ROUNDS
            ) . PHP_EOL);
        } finally {
            $engine->close();
        }
    }

    public function testProcessWithNoNamesAgainstEveryName(): void
    {
        $noNames = self::buildEngine(['performanceProfile' => self::PROFILE]);
        $everyName = self::buildEngine([
            'performanceProfile' => self::PROFILE,
            'restrictedProperties' => self::requiredNames($noNames),
        ]);
        try {
            // Both have the same names, so only how they were created
            // differs.
            $this->assertSame(
                self::requiredNames($noNames),
                self::requiredNames($everyName)
            );

            $withNone = [];
            $withEvery = [];
            for ($round = 0; $round < self::ROUNDS; ++$round) {
                if ($round % 2 === 0) {
                    $withNone[] = self::microsecondsPerProcess($noNames);
                    $withEvery[] = self::microsecondsPerProcess($everyName);
                } else {
                    $withEvery[] = self::microsecondsPerProcess($everyName);
                    $withNone[] = self::microsecondsPerProcess($noNames);
                }
            }

            fwrite(STDERR, PHP_EOL . sprintf(
                'IP Intelligence process on PHP %s, %s, %d names: ' .
                'created with no names %.3f us (%.3f to %.3f), with every ' .
                'name %.3f us (%.3f to %.3f), every name over no names %.2f ' .
                'times, no names faster in %d of %d rounds.',
                PHP_VERSION,
                PHP_OS_FAMILY,
                count(self::requiredNames($noNames)),
                self::median($withNone),
                min($withNone),
                max($withNone),
                self::median($withEvery),
                min($withEvery),
                max($withEvery),
                self::median($withEvery) / self::median($withNone),
                self::roundsBelow($withNone, $withEvery),
                self::ROUNDS
            ) . PHP_EOL);
        } finally {
            $noNames->close();
            $everyName->close();
        }
    }

    /**
     * The mean time of one read of a value, by name, or by position when
     * the positions are given.
     *
     * @param array<string> $names
     * @param null|array<string, int> $positions keyed by name
     */
    private static function nanosecondsPerRead(
        IpIntelligenceOnPremise $engine,
        array $names,
        ?array $positions
    ): float {
        $elapsed = 0;
        for ($request = 0; $request < self::REQUESTS_PER_ROUND; ++$request) {
            $results = $engine->engine->process(Constants::IPV4_ADDRESS);
            foreach ($names as $name) {
                $results->getValuesAsWeightedStringList($name);
            }
            $start = hrtime(true);
            for ($read = 0; $read < self::READS_PER_REQUEST; ++$read) {
                foreach ($names as $name) {
                    $results->getValuesAsWeightedStringList(
                        $positions === null ? $name : $positions[$name]
                    );
                }
            }
            $elapsed += hrtime(true) - $start;
        }

        return $elapsed /
            (self::REQUESTS_PER_ROUND * self::READS_PER_REQUEST * count($names));
    }

    private static function microsecondsPerProcess(IpIntelligenceOnPremise $engine): float
    {
        $start = hrtime(true);
        for ($request = 0; $request < self::REQUESTS_PER_ROUND; ++$request) {
            $engine->engine->process(Constants::IPV4_ADDRESS);
        }

        return (hrtime(true) - $start) /
            self::NANOSECONDS_PER_MICROSECOND / self::REQUESTS_PER_ROUND;
    }

    /**
     * The names of the data file's properties whose values are weighted
     * strings.
     *
     * @return array<string>
     */
    private static function weightedStringNames(IpIntelligenceOnPremise $engine): array
    {
        $names = [];
        foreach ($engine->getProperties() as $property) {
            if (strcasecmp((string) $property['type'], 'WeightedString') === 0) {
                $names[] = $property['name'];
            }
        }

        return $names;
    }

    /**
     * The names the native engine was created with, in its order, which is
     * the order of the positions.
     *
     * @return array<string>
     */
    private static function requiredNames(IpIntelligenceOnPremise $engine): array
    {
        $required = $engine->engine->getRequiredProperties();
        $names = [];
        for ($i = 0; $i < $required->size(); ++$i) {
            $names[] = $required->get($i);
        }

        return $names;
    }

    /**
     * @param array<float> $values an odd number of values
     */
    private static function median(array $values): float
    {
        sort($values);

        return $values[intdiv(count($values), 2)];
    }

    /**
     * In how many rounds the first figure was below the second.
     *
     * @param array<float> $first
     * @param array<float> $second
     */
    private static function roundsBelow(array $first, array $second): int
    {
        $below = 0;
        foreach ($first as $round => $figure) {
            $below += $figure < $second[$round] ? 1 : 0;
        }

        return $below;
    }
}
