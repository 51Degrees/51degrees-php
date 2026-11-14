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

use fiftyone\pipeline\core\EvidenceKeyFilter;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\AcceptsEveryKeyElement;
use PHPUnit\Framework\TestCase;

/**
 * What each element the Pipeline builder adds tells the Pipeline about
 * itself, as the Flow Element section of conceptual-overview.md and the
 * Generation of keys section of caching.md in the Pipeline specification
 * require. Each element exposes its data key, lists the properties it
 * populates and accepts the Evidence it makes use of.
 */
class ElementContractTests extends TestCase
{
    /**
     * The data keys of the elements the Pipeline builder adds.
     */
    private const DATA_KEYS = [
        'sequence', 'jsonbundler', 'javascriptbuilder', 'set-headers'
    ];

    /**
     * Evidence that makes each of those elements give the same result every
     * time, being a session that already exists and the host the
     * JavaScript calls back to.
     */
    private const FIXED_EVIDENCE = [
        'query.session-id' => 'fixed-session',
        'query.sequence' => '1',
        'header.host' => 'www.example.com',
        'header.protocol' => 'https',
    ];

    /**
     * Evidence keys that the test changes one at a time.
     */
    private const CHANGED_KEYS = [
        'header.user-agent',
        'header.x-changed',
        'cookie.changed',
        'query.changed',
        'server.client-ip',
    ];

    /**
     * The Pipeline builder's elements, with an element that accepts every
     * key, so that the Pipeline keeps every value and any element could
     * read it.
     */
    private static function pipeline(): Pipeline
    {
        return (new PipelineBuilder())
            ->add(new AcceptsEveryKeyElement())
            ->build();
    }

    /**
     * Each element's data after processing the Evidence given.
     *
     * @param array<string, string> $evidence
     * @return array<string, array<string, mixed>> By data key
     */
    private function results(Pipeline $pipeline, array $evidence): array
    {
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->setArray($evidence);
            foreach ($evidence as $key => $value) {
                $this->assertSame($value, $flowData->evidence->get($key), $key);
            }
            $flowData->process();

            $results = [];
            foreach (self::DATA_KEYS as $dataKey) {
                $results[$dataKey] = $flowData->get($dataKey)->asDictionary();
            }

            return $results;
        } finally {
            $flowData->close();
        }
    }

    public function testEachElementExposesADataKeyAndAnEvidenceFilter(): void
    {
        $pipeline = self::pipeline();

        $dataKeys = [];
        foreach ($pipeline->flowElements as $element) {
            $this->assertNotSame('', $element->dataKey);
            $this->assertInstanceOf(
                EvidenceKeyFilter::class,
                $element->getEvidenceKeyFilter(),
                $element->dataKey
            );
            $dataKeys[] = $element->dataKey;
        }

        $this->assertEqualsCanonicalizing(
            array_merge(self::DATA_KEYS, ['acceptseverykey']),
            $dataKeys
        );
    }

    /**
     * Every value an element puts in its Element Data is a property it
     * lists, with the name and the type a consumer reads.
     */
    public function testEachElementListsThePropertiesItPopulates(): void
    {
        $pipeline = self::pipeline();
        $results = $this->results($pipeline, self::FIXED_EVIDENCE);

        foreach ($results as $dataKey => $values) {
            $this->assertNotSame([], $values, $dataKey);
            $properties = $pipeline->getElement($dataKey)->getProperties();
            foreach (array_keys($values) as $name) {
                $this->assertArrayHasKey(
                    $name,
                    $properties,
                    "$dataKey populates $name but does not list it"
                );
                $this->assertSame(
                    $name,
                    strtolower($properties[$name]['name'] ?? ''),
                    "$dataKey lists $name without its name"
                );
                $this->assertArrayHasKey(
                    'type',
                    $properties[$name],
                    "$dataKey lists $name without its type"
                );
            }
        }
    }

    /**
     * An element's result changes only with the Evidence it accepts. The
     * Pipeline keeps every value, so an element that read a value it does
     * not accept would give another result when only that value changed.
     */
    public function testEvidenceAnElementDoesNotAcceptLeavesItsResultAlone(): void
    {
        $pipeline = self::pipeline();

        $compared = 0;
        foreach (self::CHANGED_KEYS as $key) {
            $first = $this->results(
                $pipeline,
                self::FIXED_EVIDENCE + [$key => 'first value']
            );
            $second = $this->results(
                $pipeline,
                self::FIXED_EVIDENCE + [$key => 'second value']
            );

            foreach (self::DATA_KEYS as $dataKey) {
                if ($pipeline->getElement($dataKey)->filterEvidenceKey($key)) {
                    continue;
                }
                $this->assertSame(
                    $first[$dataKey],
                    $second[$dataKey],
                    "$dataKey changed with $key, which it does not accept"
                );
                ++$compared;
            }
        }

        $this->assertGreaterThan(0, $compared);
    }
}
