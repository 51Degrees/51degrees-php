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

use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\KeyCountingElement;
use PHPUnit\Framework\TestCase;

/**
 * Whether the request line is added depends only on the Pipeline, so the
 * Pipeline decides it once and each request reads the answer, rather than
 * asking every element again on every request.
 */
class RequestLineDecisionTests extends TestCase
{
    private const KEYS = [
        'server.request-method',
        'server.request-path',
        'server.request-query',
    ];

    private const SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/page?x=1',
        'HTTP_USER_AGENT' => 'agent',
    ];

    private const REQUESTS = 5;

    /**
     * @param array<FlowElement> $elements
     */
    private static function pipeline(array $elements): Pipeline
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]);
        foreach ($elements as $element) {
            $builder->add($element);
        }

        return $builder->build();
    }

    /**
     * Every value set on a Flow Data made from the Pipeline with Evidence
     * read from the same request.
     *
     * @return array<string, int|string>
     */
    private static function request(Pipeline $pipeline): array
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest(self::SERVER, [], []);

        return $flowData->evidence->getAllIncludingUnaccepted();
    }

    /**
     * No element accepts a request line key, so no request from the
     * Pipeline carries any of the three.
     */
    public function testAbsentWhenNoElementAccepts()
    {
        $pipeline = self::pipeline([
            new KeyCountingElement(['header.user-agent'], 'first'),
            new KeyCountingElement(['query.x'], 'second'),
        ]);

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $all = self::request($pipeline);
            $this->assertSame('agent', $all['header.user-agent'] ?? null);
            foreach (self::KEYS as $key) {
                $this->assertArrayNotHasKey($key, $all);
            }
        }
    }

    /**
     * One element, not the first, accepts one of the keys, so every
     * request from the Pipeline carries all three.
     */
    public function testPresentWhenOneElementAccepts()
    {
        $pipeline = self::pipeline([
            new KeyCountingElement(['header.user-agent'], 'first'),
            new KeyCountingElement(['Server.Request-Query'], 'second'),
        ]);

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $all = self::request($pipeline);
            $this->assertSame(
                ['GET', '/page', 'x=1'],
                array_map(fn ($key) => $all[$key] ?? null, self::KEYS)
            );
        }
    }

    /**
     * Where no element accepts the request line, the elements are asked
     * about its keys for the first request at most, and never again for a
     * later request from the same Pipeline.
     */
    public function testElementsAreNotAskedAgainWhenNoneAccepts()
    {
        $first = new KeyCountingElement(['header.user-agent'], 'first');
        $second = new KeyCountingElement(['query.x'], 'second');
        $pipeline = self::pipeline([$first, $second]);

        self::request($pipeline);
        $firstChecks = $first->checksOf(self::KEYS);
        $secondChecks = $second->checksOf(self::KEYS);
        $this->assertGreaterThan(0, $secondChecks);

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            self::request($pipeline);
        }

        $this->assertSame($firstChecks, $first->checksOf(self::KEYS));
        $this->assertSame($secondChecks, $second->checksOf(self::KEYS));
    }

    /**
     * Where an element accepts the request line, a later request asks each
     * element about each of the three keys once, which is Evidence::set
     * checking every value it is given, and no more. Before the decision
     * was kept on the Pipeline, every request also asked the elements
     * whether any of them accepted a request line key.
     */
    public function testElementsAreAskedOnlyAsEachValueIsSetWhenOneAccepts()
    {
        $first = new KeyCountingElement(['header.user-agent'], 'first');
        $second = new KeyCountingElement(['server.request-path'], 'second');
        $pipeline = self::pipeline([$first, $second]);

        self::request($pipeline);

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $firstChecks = $first->checksOf(self::KEYS);
            $secondChecks = $second->checksOf(self::KEYS);

            self::request($pipeline);

            $this->assertSame(
                count(self::KEYS),
                $first->checksOf(self::KEYS) - $firstChecks
            );
            $this->assertSame(
                count(self::KEYS),
                $second->checksOf(self::KEYS) - $secondChecks
            );
        }
    }

    /**
     * Each Pipeline decides for itself, so an element in two Pipelines
     * gives the request line only where an element of that Pipeline
     * accepts it.
     */
    public function testEachPipelineDecidesForItself()
    {
        $shared = new KeyCountingElement(['header.user-agent'], 'shared');
        $without = self::pipeline([$shared]);
        $with = self::pipeline([
            $shared,
            new KeyCountingElement(['server.request-method'], 'signature'),
        ]);

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $this->assertSame('GET', self::request($with)['server.request-method'] ?? null);
            $this->assertArrayNotHasKey(
                'server.request-method',
                self::request($without)
            );
        }
    }

    /**
     * Without a request target, as when PHP runs from the command line,
     * there is no request line to add, so the elements are not asked.
     */
    public function testNotDecidedWithoutARequestTarget()
    {
        $element = new KeyCountingElement(self::KEYS);
        $flowData = self::pipeline([$element])->createFlowData();
        $flowData->evidence->setFromWebRequest(['REQUEST_METHOD' => 'GET'], [], []);

        $this->assertSame(0, $element->checksOf(self::KEYS));
    }
}
