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

namespace fiftyone\pipeline\derivedproperty\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\tests\classes\StubSourceElement;
use PHPUnit\Framework\TestCase;

/**
 * The ElementData of a DerivedPropertyElement is read only once it has
 * finished.
 */
class ReadOnlyTests extends TestCase
{
    private const DEMO = "Format: 1\nName: Demo\nVersion: 1.0.0\nOutput:\n" .
        "  Name: Demo\n  Description: A demonstration.\n  ValueType: string\n" .
        "  IsList: false\nRules:\n  - When: { Property: src.N, Lt: 2 }\n" .
        "    Then: \"yes\"\n  - Else: \"no\"\n";

    public function testADerivedPropertyElementsElementDataIsReadOnlyOnceProcessed(): void
    {
        $flowData = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new StubSourceElement('src', ['N' => 1], ['N']))
            ->add((new DerivedPropertyElementBuilder())->addScript('Demo', self::DEMO)->build())
            ->build()->createFlowData();
        $flowData->process();

        $this->expectExceptionMessage('has finished processing');
        $flowData->get('derived')->probe = 1;
    }

    public function testSetValueThrowsOnceTheDerivedPropertyElementHasFinished(): void
    {
        $flowData = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new StubSourceElement('src', ['N' => 1], ['N']))
            ->add((new DerivedPropertyElementBuilder())->addScript('Demo', self::DEMO)->build())
            ->build()->createFlowData();
        $flowData->process();
        $data = $flowData->get('derived');

        $thrown = null;
        try {
            $data->setValue('Demo', new AspectPropertyValue(null, 'after'));
        } catch (\Exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'setValue after processing did not throw.');
        $this->assertStringContainsString('has finished processing', $thrown->getMessage());
        $this->assertSame('yes', $data->get('Demo')->value);
    }
}
