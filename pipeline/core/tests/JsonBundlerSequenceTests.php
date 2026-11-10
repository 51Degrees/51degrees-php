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
use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\JsonBundlerElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Writes a sequence to the Sequence Element's Element Data only, leaving
 * Evidence without one.
 */
class SequenceDataOnlyElement extends FlowElement
{
    public string $dataKey = 'sequence';

    public array $properties = [
        'sequence' => ['name' => 'sequence', 'type' => 'int'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'sequence' => 1,
        ]));
    }
}

/**
 * Has one JavaScript Property, which the JSON Bundler lists below a sequence
 * of 10.
 */
class ScriptPropertyElement extends FlowElement
{
    public string $dataKey = 'scripted';

    public array $properties = [
        'snippet' => ['name' => 'snippet', 'type' => 'javascript'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'snippet' => 'void 0;',
        ]));
    }
}

/**
 * The JSON Bundler needs the sequence number a Sequence Element produced.
 * Without one it fails with an error and sets no JSON.
 */
class JsonBundlerSequenceTests extends TestCase
{
    private function withoutSequenceElement(?FlowElement $first = null)
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true,
        ]);
        if ($first !== null) {
            $builder->add($first);
        }
        $builder->add(new JsonBundlerElement());
        $builder->add(new JavascriptBuilderElement([]));
        $pipeline = $builder->build();
        $pipeline->suppressProcessExceptions = true;

        return $pipeline->createFlowData();
    }

    public function testFailsWithoutASequenceElement()
    {
        $flowData = $this->withoutSequenceElement();
        $flowData->process();

        $this->assertArrayHasKey('jsonbundler', $flowData->errors);
        $this->assertStringContainsString(
            'Sequence Element',
            $flowData->errors['jsonbundler']->getMessage()
        );
        $this->assertStringContainsString(
            'before',
            $flowData->errors['jsonbundler']->getMessage()
        );
    }

    public function testSetsNoJsonWithoutASequenceElement()
    {
        $flowData = $this->withoutSequenceElement();
        $flowData->process();

        $this->expectException(\Exception::class);
        $flowData->get('jsonbundler');
    }

    public function testAQueryStringSequenceIsNotASequenceNumber()
    {
        $flowData = $this->withoutSequenceElement();
        $flowData->evidence->set('query.sequence', '3');
        $flowData->process();

        $this->assertArrayHasKey('jsonbundler', $flowData->errors);
    }

    public function testAnIntegerInEvidenceIsTheSequenceNumber()
    {
        $flowData = $this->withoutSequenceElement(new ScriptPropertyElement());
        $flowData->evidence->set('query.sequence', 9);
        $flowData->process();

        $this->assertArrayNotHasKey('jsonbundler', $flowData->errors);
        $this->assertSame(
            ['scripted.snippet'],
            $flowData->jsonbundler->json['javascriptProperties']
        );
    }

    public function testFromTenTheJavaScriptPropertiesAreNotListed()
    {
        $flowData = $this->withoutSequenceElement(new ScriptPropertyElement());
        $flowData->evidence->set('query.sequence', 10);
        $flowData->process();

        $this->assertArrayNotHasKey('jsonbundler', $flowData->errors);
        $this->assertSame(
            [],
            $flowData->jsonbundler->json['javascriptProperties']
        );
    }

    public function testElementDataAloneIsNotASequenceNumber()
    {
        $flowData = $this->withoutSequenceElement(
            new SequenceDataOnlyElement()
        );
        $flowData->process();

        $this->assertArrayHasKey('jsonbundler', $flowData->errors);
    }

    public function testSucceedsWithASequenceElement()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->process();

        $this->assertArrayNotHasKey('jsonbundler', $flowData->errors);
        $this->assertArrayHasKey('javascriptProperties', $flowData->jsonbundler->json);
    }

    public function testAcceptsNoEvidence()
    {
        $this->assertFalse(
            (new JsonBundlerElement())->filterEvidenceKey('query.sequence')
        );
    }
}
