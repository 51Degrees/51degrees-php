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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\DeclaresNothingElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The elements a Pipeline adds for the web integration each have a switch,
 * and the Sequence Element runs first and writes Element Data as well as
 * Evidence.
 */
class WebIntegrationElementsTests extends TestCase
{
    /**
     * @return array<string>
     */
    private static function dataKeys(array $settings): array
    {
        $builder = new PipelineBuilder($settings);
        $builder->add(new DeclaresNothingElement());

        return array_map(
            fn ($element) => $element->dataKey,
            $builder->build()->flowElements
        );
    }

    public function testDefaultOrder()
    {
        $this->assertSame(
            [
                'sequence',
                'declaresnothing',
                'jsonbundler',
                'javascriptbuilder',
                'set-headers'
            ],
            self::dataKeys([])
        );
    }

    public static function provider_testSwitch()
    {
        return [
            'sequence' => ['addSequenceElement', 'sequence'],
            'json bundler' => ['addJsonBundlerElement', 'jsonbundler'],
            'javascript builder' => [
                'addJavaScriptBuilderElement',
                'javascriptbuilder'
            ],
            'set headers' => ['addSetHeaderElement', 'set-headers'],
        ];
    }

    #[DataProvider('provider_testSwitch')]
    public function testSwitchRemovesOnlyItsElement(
        string $setting,
        string $removed
    ) {
        $all = self::dataKeys([]);
        $keys = self::dataKeys([$setting => false]);

        $this->assertSame(array_values(array_diff($all, [$removed])), $keys);
    }

    public function testJavaScriptSwitchStillRemovesTheThreeElements()
    {
        $this->assertSame(
            ['declaresnothing', 'set-headers'],
            self::dataKeys(['addJavaScriptBuilder' => false])
        );
    }

    public function testSetHeaderSwitchStillRemovesTheElement()
    {
        $this->assertSame(
            ['sequence', 'declaresnothing', 'jsonbundler', 'javascriptbuilder'],
            self::dataKeys(['useSetHeaderProperties' => false])
        );
    }

    public function testNothingAddedWhenEverySwitchIsOff()
    {
        $this->assertSame(
            ['declaresnothing'],
            self::dataKeys([
                'addSequenceElement' => false,
                'addJsonBundlerElement' => false,
                'addJavaScriptBuilderElement' => false,
                'addSetHeaderElement' => false,
            ])
        );
    }

    public function testSequenceElementDataForANewSession()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->process();

        $session = $flowData->evidence->get('query.session-id');
        $this->assertNotEmpty($session);
        $this->assertSame($session, $flowData->sequence->get('session-id'));
        $this->assertSame(1, $flowData->sequence->get('sequence'));
        $this->assertSame(1, $flowData->evidence->get('query.sequence'));
    }

    public function testSequenceElementDataForAnExistingSession()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', 'abc');
        $flowData->evidence->set('query.sequence', 4);
        $flowData->process();

        $this->assertSame('abc', $flowData->sequence->get('session-id'));
        $this->assertSame(5, $flowData->sequence->get('sequence'));
        $this->assertSame(5, $flowData->evidence->get('query.sequence'));
    }

    public function testSequenceIsNotInTheJsonSentToTheBrowser()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->process();

        $this->assertArrayNotHasKey('sequence', $flowData->jsonbundler->json);
        $this->assertArrayNotHasKey('session-id', $flowData->jsonbundler->json);
    }
}
