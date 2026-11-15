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

namespace fiftyone\pipeline\engines\tests;

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\EvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\DataKeyedCache;
use fiftyone\pipeline\engines\Engine;
use PHPUnit\Framework\TestCase;

/**
 * A cache held in memory, which keeps each result under the JSON of the
 * key it is given.
 */
class MemoryKeyedCache extends DataKeyedCache
{
    /** @var array<string, mixed> */
    private array $entries = [];

    public function get($cacheKey)
    {
        return $this->entries[json_encode($cacheKey)] ?? null;
    }

    public function set($cacheKey, $value): void
    {
        $this->entries[json_encode($cacheKey)] = $value;
    }
}

/**
 * An Engine that accepts two User-Agent keys and counts how many times it
 * processes a FlowData, so that a result served from the cache shows as a
 * count that did not move.
 */
class CountingEngine extends Engine
{
    public string $dataKey = 'counting';

    public array $properties = [
        'count' => ['type' => 'int'],
    ];

    public int $processed = 0;

    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter(
            ['header.user-agent', 'query.user-agent']
        );
    }

    public function processInternal(FlowData $flowData): void
    {
        ++$this->processed;
        $flowData->setElementData(new AspectDataDictionary(
            $this,
            ['count' => $this->processed]
        ));
    }
}

/**
 * The cache key an Engine builds from Evidence, as caching.md in the
 * Pipeline specification asks. It is the same whatever order the Evidence
 * was set in and whatever the letter case of its keys, and it differs when
 * a value differs in letter case.
 */
class CacheKeyTests extends TestCase
{
    /**
     * Processes a FlowData with the Evidence given, in the order given, and
     * returns how many times the Engine has then processed one itself.
     *
     * @param array<string, string> $evidence
     */
    private static function process(CountingEngine $engine, array $evidence): int
    {
        $flowData = (new Pipeline([$engine], []))->createFlowData();
        try {
            foreach ($evidence as $key => $value) {
                $flowData->evidence->set($key, $value);
            }
            $flowData->process();

            return $engine->processed;
        } finally {
            $flowData->close();
        }
    }

    private static function engine(): CountingEngine
    {
        $engine = new CountingEngine();
        $engine->setCache(new MemoryKeyedCache());

        return $engine;
    }

    public function testEvidenceSetInAnotherOrderGivesTheSameCacheKey()
    {
        $engine = self::engine();

        self::process($engine, [
            'header.user-agent' => 'Agent A',
            'query.user-agent' => 'Agent B',
        ]);
        $processed = self::process($engine, [
            'query.user-agent' => 'Agent B',
            'header.user-agent' => 'Agent A',
        ]);

        $this->assertSame(1, $processed);
    }

    public function testKeysInOtherLettersGiveTheSameCacheKey()
    {
        $engine = self::engine();

        self::process($engine, ['query.user-agent' => 'abc']);
        $processed = self::process($engine, ['query.User-Agent' => 'abc']);

        $this->assertSame(1, $processed);
    }

    public function testValuesInOtherLettersGiveAnotherCacheKey()
    {
        $engine = self::engine();

        self::process($engine, ['query.user-agent' => 'abc']);
        $processed = self::process($engine, ['query.user-agent' => 'ABC']);

        $this->assertSame(2, $processed);
    }

    public function testTheSameEvidenceIsServedFromTheCache()
    {
        $engine = self::engine();

        self::process($engine, ['header.user-agent' => 'Agent A']);
        $processed = self::process($engine, ['header.user-agent' => 'Agent A']);

        $this->assertSame(1, $processed);
    }
}
