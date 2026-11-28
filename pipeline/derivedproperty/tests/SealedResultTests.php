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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\DerivedScript;
use fiftyone\pipeline\derivedproperty\Missing;
use fiftyone\pipeline\derivedproperty\tests\classes\SharedFolder;
use PHPUnit\Framework\TestCase;

/**
 * This element can't redeem a sealed Human Confidence result. On a request
 * that carries one, HumanConfidence is Medium and HumanConfidenceSource is
 * Active, never the High or Low the script works out. Other requests are
 * unchanged.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#where-the-sealed-result-is-not-redeemed
 */
class SealedResultTests extends TestCase
{
    private const SEALED = 'SEALED-RESULT-abcdefghijklmnopqrstuvwxyz0123456789';

    private static function script(string $name): DerivedScript
    {
        return (new DerivedPropertyElementBuilder())
            ->addBuiltInScript($name)
            ->build()
            ->getScripts()[0];
    }

    /**
     * Every case of the shared HumanConfidence cases whose answer is High
     * or Low, by name, with its answer and source properties.
     *
     * @return array<string, array{0: string, 1: mixed}>
     */
    private static function highAndLowCases(): array
    {
        $file = SharedFolder::path() . DIRECTORY_SEPARATOR . 'tests' .
            DIRECTORY_SEPARATOR . 'HumanConfidence.cases.yaml';
        $cases = [];
        foreach (Missing::key(SharedFolder::read($file), 'Cases') as $case) {
            $value = Missing::key(Missing::key($case, 'Expect'), 'Value');
            if ($value === 'High' || $value === 'Low') {
                $cases[(string) Missing::key($case, 'Name')] = [$value, Missing::key($case, 'Properties')];
            }
        }

        return $cases;
    }

    /**
     * Runs the scripts after source elements giving a case's properties,
     * with the Evidence given, where exceptions aren't suppressed.
     *
     * @param array<DerivedScript> $scripts
     * @param mixed $properties
     * @param array<string, string> $evidence
     */
    private static function process(array $scripts, $properties, array $evidence): FlowData
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => false,
        ]);
        foreach (SharedCasesTests::sources($scripts[0], $properties) as $source) {
            $builder->add($source);
        }
        $builder->add(new DerivedPropertyElement([], $scripts));
        $flowData = $builder->build()->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData;
    }

    /**
     * @return array<string, mixed> each derived property's value, or its
     *   no value message, by lower case name
     */
    private static function values(FlowData $flowData): array
    {
        return array_map(
            static fn ($value) => $value->hasValue ? $value->value : $value->noValueMessage,
            $flowData->get(DerivedPropertyElement::DATA_KEY)->asDictionary()
        );
    }

    public function testEveryHighAndLowCaseGivesMediumAndActiveWithASealedResult(): void
    {
        $script = self::script('HumanConfidence');
        $cases = self::highAndLowCases();
        $this->assertContains('High', array_column($cases, 0));
        $this->assertContains('Low', array_column($cases, 0));

        foreach ($cases as $name => [$expected, $properties]) {
            $without = self::process([$script], $properties, []);
            $this->assertSame(['humanconfidence' => $expected], self::values($without), $name);

            foreach (['query.humanconfidence', 'Query.HumanConfidence', 'QUERY.HUMANCONFIDENCE'] as $key) {
                $flowData = self::process([$script], $properties, [$key => self::SEALED]);
                $this->assertSame([], $flowData->errors, "$name $key");
                $this->assertSame(
                    ['humanconfidence' => 'Medium', 'humanconfidencesource' => 'Active'],
                    self::values($flowData),
                    "$name $key"
                );
                $this->assertSame('Medium', $flowData->derived->humanconfidence->value, "$name $key");
                $this->assertSame('Active', $flowData->derived->HumanConfidenceSource->value, "$name $key");
            }
        }
    }

    public function testARequestWithoutASealedResultIsUnchanged(): void
    {
        $script = self::script('HumanConfidence');
        $requests = [
            'an empty one' => ['query.humanconfidence' => ''],
            'one under a header' => ['header.humanconfidence' => self::SEALED],
            'one under a cookie' => ['cookie.humanconfidence' => self::SEALED],
            'one with no prefix' => ['humanconfidence' => self::SEALED],
        ];
        foreach (self::highAndLowCases() as $name => [$expected, $properties]) {
            foreach ($requests as $request => $evidence) {
                $flowData = self::process([$script], $properties, $evidence);
                $this->assertSame(['humanconfidence' => $expected], self::values($flowData), "$name $request");
            }
        }
    }

    public function testAnElementThatWritesNoHumanConfidenceIsUnchanged(): void
    {
        $script = self::script('IsGdpr');
        $without = self::process([$script], null, []);
        $with = self::process([$script], null, ['query.humanconfidence' => self::SEALED]);
        $this->assertSame(['isgdpr'], array_keys(self::values($with)));
        $this->assertSame(self::values($without), self::values($with));
    }
}
