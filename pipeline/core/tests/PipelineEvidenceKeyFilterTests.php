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

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\EvidenceKeyFilter;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\SequenceElement;
use fiftyone\pipeline\core\tests\classes\DeclaresNothingElement;
use fiftyone\pipeline\core\tests\classes\ListedEvidenceElement;
use PHPUnit\Framework\TestCase;

/**
 * A Pipeline describes the Evidence it accepts, which is every key that at
 * least one of its elements accepts.
 */
class PipelineEvidenceKeyFilterTests extends TestCase
{
    /**
     * The Pipeline accepts each key one of its elements lists, in any
     * letter case, and the keys an element reads through a filter function,
     * and nothing else.
     */
    public function testThePipelineAcceptsWhatAnyElementAccepts()
    {
        $headers = new ListedEvidenceElement(
            ['header.user-agent', 'header.sec-ch-ua']
        );
        $queries = new ListedEvidenceElement(['query.user-agent']);
        $queries->dataKey = 'queries';
        $pipeline = $this->pipelineOf(
            [$headers, $queries, new SequenceElement()]
        );

        $filter = $pipeline->getEvidenceKeyFilter();

        foreach ([
            'header.user-agent',
            'Header.User-Agent',
            'HEADER.SEC-CH-UA',
            'query.user-agent',
            'Query.User-Agent',
            'query.sequence',
            'query.session-id',
        ] as $key) {
            $this->assertTrue(
                $filter->filterEvidenceKey($key),
                "The Pipeline does not accept {$key}"
            );
        }
        foreach ([
            'header.if-none-match',
            'cookie.user-agent',
            'server.client-ip',
            'query.x-test',
        ] as $key) {
            $this->assertFalse(
                $filter->filterEvidenceKey($key),
                "The Pipeline accepts {$key}"
            );
        }
    }

    /**
     * An element that declares nothing accepts nothing, so it adds no key
     * to the Pipeline's, and a Pipeline of such elements accepts nothing.
     */
    public function testAnElementThatDeclaresNothingAddsNothing()
    {
        $listed = $this->pipelineOf([
            new ListedEvidenceElement(['header.user-agent']),
            new DeclaresNothingElement(),
        ])->getEvidenceKeyFilter();
        $this->assertTrue($listed->filterEvidenceKey('header.user-agent'));
        $this->assertFalse($listed->filterEvidenceKey('header.x-test'));

        $nothing = $this->pipelineOf([new DeclaresNothingElement()])
            ->getEvidenceKeyFilter();
        $this->assertFalse($nothing->filterEvidenceKey('header.user-agent'));

        $empty = $this->pipelineOf([])->getEvidenceKeyFilter();
        $this->assertFalse($empty->filterEvidenceKey('header.user-agent'));
    }

    /**
     * The filter asks the elements when a key is checked, so it follows an
     * element whose accepted Evidence changes after the filter was taken,
     * as an engine's does when its data is refreshed.
     */
    public function testTheFilterFollowsAnElementWhoseKeysChange()
    {
        $element = new class () extends FlowElement {
            public string $dataKey = 'changing';

            /**
             * @var array<string>
             */
            public array $keys = ['header.user-agent'];

            public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
            {
                return new BasicListEvidenceKeyFilter($this->keys);
            }
        };
        $filter = $this->pipelineOf([$element])->getEvidenceKeyFilter();
        $this->assertTrue($filter->filterEvidenceKey('header.user-agent'));
        $this->assertFalse($filter->filterEvidenceKey('header.sec-ch-ua'));

        $element->keys = ['header.sec-ch-ua'];

        $this->assertFalse($filter->filterEvidenceKey('header.user-agent'));
        $this->assertTrue($filter->filterEvidenceKey('header.sec-ch-ua'));
    }

    /**
     * Filtering Evidence with the Pipeline's filter keeps the values the
     * Pipeline keeps as accepted Evidence, which are the values its
     * elements read.
     */
    public function testTheFilterKeepsWhatTheFlowDataKeeps()
    {
        $pipeline = $this->pipelineOf([
            new ListedEvidenceElement(['header.user-agent']),
            new SequenceElement(),
        ]);
        $evidence = [
            'Header.User-Agent' => 'agent',
            'header.x-test' => 'test',
            'query.sequence' => '1',
            'cookie.51d_test' => 'cookie',
        ];

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setArray($evidence);

        $this->assertSame(
            ['Header.User-Agent' => 'agent', 'query.sequence' => '1'],
            $pipeline->getEvidenceKeyFilter()->filterEvidence($evidence)
        );
        $this->assertSame(
            $flowData->evidence->getAll(),
            $pipeline->getEvidenceKeyFilter()->filterEvidence($evidence)
        );
    }

    /**
     * The filter is always needed and does not change once the Pipeline is
     * built, so the Pipeline builds it when it is built and every call gives
     * that same filter, rather than a new one on each call.
     */
    public function testTheFilterIsBuiltWithThePipelineAndKept()
    {
        $pipeline = $this->pipelineOf([
            new ListedEvidenceElement(['header.user-agent']),
        ]);

        // Read before any call, so the filter must come from the build.
        $kept = (function () {
            return $this->evidenceKeyFilter ?? null;
        })->call($pipeline);
        $this->assertInstanceOf(
            EvidenceKeyFilter::class,
            $kept,
            'The Pipeline has no filter once it is built'
        );

        $first = $pipeline->getEvidenceKeyFilter();
        $this->assertSame($kept, $first);
        $this->assertSame($first, $pipeline->getEvidenceKeyFilter());
        $this->assertTrue($first->filterEvidenceKey('header.user-agent'));
        $this->assertFalse($first->filterEvidenceKey('header.x-test'));
    }

    /**
     * The Evidence and the request line decision ask the Pipeline's filter,
     * so the Pipeline decides what it accepts in one place.
     */
    public function testEvidenceAndTheRequestLineAskThePipelineFilter()
    {
        $pipeline = $this->pipelineOf([
            new ListedEvidenceElement(['header.user-agent']),
        ]);
        $asked = [];
        $spy = new EvidenceKeyFilter();
        $spy->filterEvidenceKey = function (string $key) use (&$asked): bool {
            $asked[] = $key;

            return true;
        };
        (function () use ($spy) {
            $this->evidenceKeyFilter = $spy;
        })->call($pipeline);

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.x-test', 'test');

        $this->assertContains('header.x-test', $asked);
        $this->assertSame(
            ['header.x-test' => 'test'],
            $flowData->evidence->getAll()
        );

        $this->assertTrue($pipeline->acceptsRequestLine());
        $this->assertNotEmpty(array_diff($asked, ['header.x-test']));
    }

    /**
     * Every caller shares the Pipeline's filter, so it cannot be given a
     * filter function of its own, which would also stop a session cache
     * serializing data that refers to the Pipeline.
     */
    public function testTheSharedFilterCannotBeGivenAFunction()
    {
        $pipeline = $this->pipelineOf([
            new ListedEvidenceElement(['header.user-agent']),
        ]);
        $filter = $pipeline->getEvidenceKeyFilter();

        $refused = false;
        try {
            $filter->filterEvidenceKey = function (string $key): bool {
                return false;
            };
        } catch (\Exception $e) {
            $refused = strpos($e->getMessage(), 'filterEvidenceKey') !== false;
        }
        $this->assertTrue($refused, 'The Pipeline filter took a filter function');

        $this->assertTrue($filter->filterEvidenceKey('header.user-agent'));
        $this->assertIsString(serialize($pipeline));
    }

    /**
     * @param array<FlowElement> $elements
     */
    private function pipelineOf(array $elements): Pipeline
    {
        return new Pipeline($elements, []);
    }
}
