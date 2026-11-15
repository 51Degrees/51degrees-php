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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\JsonBundlerElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\SequenceElement;
use PHPUnit\Framework\TestCase;

class SharedBundlerFirstElement extends FlowElement
{
    public string $dataKey = 'first';

    public array $properties = [
        'Value' => ['type' => 'string'],
    ];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Value' => new AspectPropertyValue(null, 'one'),
        ]));
    }
}

/**
 * A Property marked for delayed execution, and another whose Evidence
 * comes from it.
 */
class SharedBundlerSecondElement extends FlowElement
{
    public string $dataKey = 'second';

    public array $properties = [
        'Script' => ['type' => 'javascript', 'delayexecution' => true],
        'Value' => ['type' => 'string', 'evidenceproperties' => ['Script']],
    ];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Script' => new AspectPropertyValue(null, 'void 0;'),
            'Value' => new AspectPropertyValue(null, 'two'),
        ]));
    }
}

/**
 * One JSON Bundler added to two Pipelines keeps what it learns about the
 * elements of each Pipeline apart, as multi-pipeline-elements.md in the
 * Pipeline specification asks.
 */
class MultiPipelineElementTests extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function json(Pipeline $pipeline): array
    {
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();

            return $flowData->get('jsonbundler')->get('json');
        } finally {
            $flowData->close();
        }
    }

    public function testTheSecondPipelineGetsTheJsonOfItsOwnElements()
    {
        $bundler = new JsonBundlerElement();
        $first = new Pipeline([
            new SequenceElement(),
            new SharedBundlerFirstElement(),
            $bundler
        ], []);
        $second = new Pipeline([
            new SequenceElement(),
            new SharedBundlerFirstElement(),
            new SharedBundlerSecondElement(),
            $bundler
        ], []);

        self::json($first);
        $json = self::json($second);

        $this->assertSame('one', $json['first']['value']);
        $this->assertSame('two', $json['second']['value']);
        $this->assertSame('void 0;', $json['second']['script']);
        $this->assertTrue($json['second']['scriptdelayexecution']);
        $this->assertSame(
            ['second.script'],
            $json['second']['valueevidenceproperties']
        );
    }

    public function testEachPipelineGetsOnlyTheJsonOfItsOwnElements()
    {
        $bundler = new JsonBundlerElement();
        $first = new Pipeline([
            new SequenceElement(),
            new SharedBundlerFirstElement(),
            $bundler
        ], []);
        $second = new Pipeline([
            new SequenceElement(),
            new SharedBundlerSecondElement(),
            $bundler
        ], []);

        self::json($second);
        $firstJson = self::json($first);
        $secondJson = self::json($second);

        $this->assertArrayHasKey('first', $firstJson);
        $this->assertArrayNotHasKey('second', $firstJson);
        $this->assertArrayNotHasKey('first', $secondJson);
        $this->assertTrue($secondJson['second']['scriptdelayexecution']);
    }
}
