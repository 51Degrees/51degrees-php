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
use fiftyone\pipeline\core\tests\classes\ConfiguredElement;
use fiftyone\pipeline\core\tests\classes\ConfiguredElementBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The pipeline usage examples say "Creating Flow Elements SHOULD always be
 * done using a consistent mechanism."
 *
 * An element with a builder is created by that builder in code, so a
 * configuration file names the same builder, and its build parameters
 * reach the builder's methods of the same names. An element with no
 * builder is still created by its constructor, from the build parameters,
 * in code and from a configuration alike.
 */
class ElementBuilderConfigurationTests extends TestCase
{
    public function testABuilderNamedInConfigurationCreatesTheElementAsItDoesInCode(): void
    {
        $inCode = (new ConfiguredElementBuilder())
            ->setDataKey('fromcode')
            ->setAnswer(42)
            ->build();
        $codePipeline = (new PipelineBuilder())->add($inCode)->build();

        $configPipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElementBuilder::class,
                    'BuildParameters' => ['DataKey' => 'fromconfig', 'Answer' => 42]
                ]]
            ]
        ]);

        $this->assertInstanceOf(
            ConfiguredElement::class,
            $configPipeline->getElement('fromconfig')
        );
        $this->assertSame(
            $codePipeline->createFlowData()->process()->fromcode->answer,
            $configPipeline->createFlowData()->process()->fromconfig->answer
        );
    }

    public function testAListParameterCallsTheBuildersAddMethodForEachItem(): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElementBuilder::class,
                    'BuildParameters' => ['ToAnswer' => [40, 2]]
                ]]
            ]
        ]);

        $this->assertSame(42, $pipeline->createFlowData()->process()->configured->answer);
    }

    /**
     * The pipeline configuration feature says "Configuration files MUST be
     * human-readable and consistent between languages." and lists among the
     * types supported in configuration files for all languages a "list of
     * strings (represented as a comma-separated string)". So a list written
     * as a configuration file for another language writes it reaches the
     * builder's add method once for each item, as a JSON list does.
     */
    public function testACommaSeparatedListCallsTheBuildersAddMethodForEachItem(): void
    {
        $fromText = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElementBuilder::class,
                    'BuildParameters' => ['Name' => 'first, second,third']
                ]]
            ]
        ]);
        $fromList = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElementBuilder::class,
                    'BuildParameters' => ['Name' => ['first', 'second', 'third']]
                ]]
            ]
        ]);

        $this->assertSame(
            ['first', 'second', 'third'],
            $fromText->getElement('configured')->names
        );
        $this->assertSame(
            $fromList->getElement('configured')->names,
            $fromText->getElement('configured')->names
        );
    }

    public function testABuilderNamedWithoutParametersBuildsWithItsDefaults(): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [['BuilderName' => ConfiguredElementBuilder::class]]
            ]
        ]);

        $this->assertSame(0, $pipeline->createFlowData()->process()->configured->answer);
    }

    public function testAParameterTheBuilderHasNoMethodForIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Colour.*ConfiguredElementBuilder/');

        (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElementBuilder::class,
                    'BuildParameters' => ['Colour' => 'blue']
                ]]
            ]
        ]);
    }

    public function testAnElementNamedInConfigurationIsStillCreatedByItsConstructor(): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => ConfiguredElement::class,
                    'BuildParameters' => ['dataKey' => 'direct', 'answer' => 7]
                ]]
            ]
        ]);

        $this->assertSame(7, $pipeline->createFlowData()->process()->direct->answer);
    }
}
