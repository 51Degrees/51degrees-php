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

use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * An element with one JavaScript Property, so that the script carries a
 * snippet, as it does with Device Detection.
 */
class ScriptPropertyElement extends FlowElement
{
    public string $dataKey = 'scripted';

    public array $properties = [
        'Script' => ['type' => 'javascript'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Script' => 'document.cookie = "51D_Width=" + screen.width;',
        ]));
    }
}

/**
 * Measures what minification adds to the time a Pipeline with the
 * JavaScript Builder takes for one request, by timing the same Pipeline with
 * minification on and off in alternate rounds, and checks the median round
 * against a limit.
 */
class JavaScriptBuilderPerformanceTests extends TestCase
{
    /**
     * Requests made on each Pipeline before timing, so that start-up work
     * is not counted.
     */
    private const WARM_UP_REQUESTS = 5;

    /**
     * Rounds timed. The cost reported and checked is the median round's.
     */
    private const ROUNDS = 9;

    /**
     * Requests timed on each Pipeline in a round.
     */
    private const REQUESTS_PER_ROUND = 10;

    /**
     * The most minification may add to one request, in milliseconds. It is
     * twice the slowest round measured on the runners the test runs on,
     * rounded up, so that only a real change in the cost fails the test.
     */
    private const MAX_MINIFICATION_MILLISECONDS = 30;

    private const NANOSECONDS_PER_MILLISECOND = 1000000;

    /**
     * Debugger and coverage extensions slow the code they watch, so a time
     * taken with one loaded measures them rather than the code.
     */
    private const INSTRUMENTING_EXTENSIONS = ['xdebug', 'pcov'];

    /**
     * Skips each timing when an instrumenting extension is loaded, naming
     * the extension, so that the limit is only checked against the code.
     */
    protected function setUp(): void
    {
        foreach (self::INSTRUMENTING_EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $this->markTestSkipped(
                    "The $extension extension is loaded, so this time " .
                    'would measure the instrumentation, not the code.'
                );
            }
        }
    }

    public function testMinificationCostIsWithinTheLimit()
    {
        $minified = self::pipeline(true);
        $plain = self::pipeline(false);

        // Minification ran, or the measurement would be of nothing.
        $minifiedBytes = strlen(self::script($minified));
        $plainBytes = strlen(self::script($plain));
        $this->assertLessThan($plainBytes, $minifiedBytes);

        self::millisecondsPerRequest($minified, self::WARM_UP_REQUESTS);
        self::millisecondsPerRequest($plain, self::WARM_UP_REQUESTS);

        $withTimes = [];
        $withoutTimes = [];
        $costs = [];
        for ($round = 0; $round < self::ROUNDS; $round++) {
            $with = self::millisecondsPerRequest(
                $minified,
                self::REQUESTS_PER_ROUND
            );
            $without = self::millisecondsPerRequest(
                $plain,
                self::REQUESTS_PER_ROUND
            );
            $withTimes[] = $with;
            $withoutTimes[] = $without;
            $costs[] = $with - $without;
        }

        $cost = self::median($costs);
        $summary = sprintf(
            'JavaScript Builder minification on PHP %s, %s: ' .
            'cost %.3f ms per request (lowest %.3f, highest %.3f), ' .
            '%.3f ms with and %.3f ms without, over %d rounds of %d ' .
            'requests. Script %d bytes minified, %d bytes not.',
            PHP_VERSION,
            PHP_OS_FAMILY,
            $cost,
            min($costs),
            max($costs),
            self::median($withTimes),
            self::median($withoutTimes),
            self::ROUNDS,
            self::REQUESTS_PER_ROUND,
            $minifiedBytes,
            $plainBytes
        );
        fwrite(STDERR, PHP_EOL . $summary . PHP_EOL);

        $this->assertLessThanOrEqual(
            self::MAX_MINIFICATION_MILLISECONDS,
            $cost,
            $summary
        );
    }

    /**
     * A builder keeps the templates it has minified for its own life, so a
     * Pipeline built for every request, as a PHP web page often builds it,
     * minifies the whole template in its first request. This times that
     * first request, on a new minifying Pipeline and a new plain one in
     * each round, and checks the median cost against the same limit.
     */
    public function testFirstRequestCostIsWithinTheLimit()
    {
        // One of each first, so that loading the classes and the template
        // file is not counted.
        self::millisecondsPerRequest(self::pipeline(true), 1);
        self::millisecondsPerRequest(self::pipeline(false), 1);

        $costs = [];
        for ($round = 0; $round < self::ROUNDS; $round++) {
            $minified = self::pipeline(true);
            $plain = self::pipeline(false);
            $costs[] = self::millisecondsPerRequest($minified, 1) -
                self::millisecondsPerRequest($plain, 1);
        }

        $cost = self::median($costs);
        $summary = sprintf(
            'JavaScript Builder minification on PHP %s, %s, first request ' .
            'on a new Pipeline: cost %.3f ms (lowest %.3f, highest %.3f) ' .
            'over %d rounds.',
            PHP_VERSION,
            PHP_OS_FAMILY,
            $cost,
            min($costs),
            max($costs),
            self::ROUNDS
        );
        fwrite(STDERR, PHP_EOL . $summary . PHP_EOL);

        $this->assertLessThanOrEqual(
            self::MAX_MINIFICATION_MILLISECONDS,
            $cost,
            $summary
        );
    }

    private static function pipeline(bool $minify): Pipeline
    {
        return (new PipelineBuilder([
            'javascriptBuilderSettings' => ['minify' => $minify]
        ]))
            ->add(new ScriptPropertyElement())
            ->build();
    }

    private static function script(Pipeline $pipeline): string
    {
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }

    /**
     * The mean time of one request over the given number of requests.
     */
    private static function millisecondsPerRequest(
        Pipeline $pipeline,
        int $requests
    ): float {
        $start = hrtime(true);
        for ($request = 0; $request < $requests; $request++) {
            $pipeline->createFlowData()->process();
        }

        return (hrtime(true) - $start) /
            self::NANOSECONDS_PER_MILLISECOND / $requests;
    }

    /**
     * @param float[] $values an odd number of values
     */
    private static function median(array $values): float
    {
        sort($values);

        return $values[intdiv(count($values), 2)];
    }
}
