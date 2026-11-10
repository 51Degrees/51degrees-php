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

    /**
     * @dataProvider provider_testSwitch
     */
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

    public function testSequenceElementTreatsAnArrayAsNoSessionId()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', ['x', 'y']);
        $flowData->process();

        $this->assertIsString($flowData->sequence->get('session-id'));
        $this->assertNotEmpty($flowData->sequence->get('session-id'));
        $this->assertSame(1, $flowData->sequence->get('sequence'));
    }

    public function testSequenceElementTreatsAnArrayAsNoSequence()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', 'abc');
        $flowData->evidence->set('query.sequence', ['7']);
        $flowData->process();

        $this->assertSame('abc', $flowData->sequence->get('session-id'));
        $this->assertSame(1, $flowData->sequence->get('sequence'));
    }

    public function testSequenceElementKeepsASessionIdOfZero()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', '0');
        $flowData->evidence->set('query.sequence', 2);
        $flowData->process();

        $this->assertSame('0', $flowData->sequence->get('session-id'));
        $this->assertSame(3, $flowData->sequence->get('sequence'));
    }

    public function testSequenceElementCountsASequenceWithNoSessionId()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.sequence', 4);
        $flowData->process();

        $this->assertNotEmpty($flowData->sequence->get('session-id'));
        $this->assertSame(5, $flowData->sequence->get('sequence'));
    }

    public function testSequenceIsInTheJsonSentToTheBrowser()
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', 'abc');
        $flowData->evidence->set('query.sequence', 2);
        $flowData->process();

        $this->assertSame(
            ['session-id' => 'abc', 'sequence' => 3],
            $flowData->jsonbundler->json['sequence']
        );
    }

    public static function provider_sequenceInput(): array
    {
        return [
            'zero' => ['0', 1],
            'leading zeros' => ['000000000007', 8],
            'largest text' => ['2147483646', 2147483647],
            'largest number' => [2147483646, 2147483647],
            'past largest text' => ['2147483647', 1],
            'past largest number' => [2147483647, 1],
            'plus sign' => ['+5', 1],
            'leading space' => [' 5', 1],
            'trailing space' => ['5 ', 1],
            'trailing new line' => ["5\n", 1],
            'negative number' => [-3, 1],
        ];
    }

    /**
     * A query.sequence counts only when it is a whole number, or a string of
     * the digits 0 to 9 with no sign or spaces, from 0 up to 2147483646, so
     * that adding one stays within a 32 bit integer. Any other value counts
     * as absent, and 0 and an absent sequence both give 1.
     * @dataProvider provider_sequenceInput
     */
    #[DataProvider('provider_sequenceInput')]
    public function testSequenceElementCountsOnlyAUsableSequence($value, int $expected)
    {
        $flowData = (new PipelineBuilder([]))->build()->createFlowData();
        $flowData->evidence->set('query.session-id', 'abc');
        $flowData->evidence->set('query.sequence', $value);
        $flowData->process();

        $this->assertSame($expected, $flowData->sequence->get('sequence'));
        $this->assertSame($expected, $flowData->evidence->get('query.sequence'));
    }
}
