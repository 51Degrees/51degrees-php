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

namespace fiftyone\pipeline\translation\tests;

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngine;
use fiftyone\pipeline\translation\tests\classes\SourceElement;
use PHPUnit\Framework\TestCase;

/**
 * Building the Translation Engine from a pipeline configuration, as the
 * Translation Engine specification's Configuration section describes, and
 * from code with the constructor's arguments.
 */
class TranslationEngineConfigTests extends TestCase
{
    /**
     * Builds a pipeline from a configuration holding one Translation Engine
     * with the given build parameters, and gives the engine to the check.
     *
     * @param array<string, mixed> $parameters
     */
    private function withConfigured(array $parameters, callable $check): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig(
            self::configuration($parameters)
        );
        try {
            $this->assertCount(1, $pipeline->flowElements);
            $engine = $pipeline->flowElements[0];
            $this->assertInstanceOf(TranslationEngine::class, $engine);
            $check($engine);
        } finally {
            $pipeline->close();
        }
    }

    /**
     * A configuration holding one Translation Engine.
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private static function configuration(array $parameters): array
    {
        return [
            'PipelineOptions' => [
                'Elements' => [
                    [
                        'BuilderName' => TranslationEngine::class,
                        'BuildParameters' => $parameters,
                    ],
                ],
            ],
        ];
    }

    /** A protected field of the engine, which has no accessor for it. */
    private static function field(TranslationEngine $engine, string $name)
    {
        return (fn () => $this->{$name})->call($engine);
    }

    public function testAConfigurationBuildsTheEngine(): void
    {
        $this->withConfigured(
            ['SourceElementDataKey' => 'ip'],
            function (TranslationEngine $engine): void {
                $this->assertSame(TranslationEngine::DEFAULT_ELEMENT_DATA_KEY, $engine->dataKey);
                $this->assertSame('ip', self::field($engine, 'sourceElementDataKey'));
                $this->assertNull(self::field($engine, 'fixedLanguage'));
                $this->assertSame(MissingTranslationBehavior::ORIGINAL, self::field($engine, 'behavior'));
            }
        );
    }

    public function testEachOptionByTheSpecificationName(): void
    {
        $this->withConfigured(
            [
                'SourceElementDataKey' => 'device',
                'FixedLanguage' => 'en_GB',
                'MissingTranslationBehavior' => 'FlowError',
            ],
            function (TranslationEngine $engine): void {
                $this->assertSame('device', self::field($engine, 'sourceElementDataKey'));
                $this->assertSame('en_GB', self::field($engine, 'fixedLanguage'));
                $this->assertSame(MissingTranslationBehavior::FLOW_ERROR, self::field($engine, 'behavior'));
            }
        );
    }

    public function testEachOptionByTheCamelCaseName(): void
    {
        $this->withConfigured(
            [
                'sourceElementDataKey' => 'device',
                'fixedLanguage' => 'fr_FR',
                'missingTranslationBehavior' => 'EmptyString',
            ],
            function (TranslationEngine $engine): void {
                $this->assertSame('device', self::field($engine, 'sourceElementDataKey'));
                $this->assertSame('fr_FR', self::field($engine, 'fixedLanguage'));
                $this->assertSame(MissingTranslationBehavior::EMPTY_STRING, self::field($engine, 'behavior'));
            }
        );
    }

    public function testEachSpellingOfTheBehavior(): void
    {
        $spellings = [
            'Original' => MissingTranslationBehavior::ORIGINAL,
            'original' => MissingTranslationBehavior::ORIGINAL,
            'ORIGINAL' => MissingTranslationBehavior::ORIGINAL,
            'EmptyString' => MissingTranslationBehavior::EMPTY_STRING,
            'emptystring' => MissingTranslationBehavior::EMPTY_STRING,
            'EMPTYSTRING' => MissingTranslationBehavior::EMPTY_STRING,
            'FlowError' => MissingTranslationBehavior::FLOW_ERROR,
            'flowerror' => MissingTranslationBehavior::FLOW_ERROR,
            'FLOWERROR' => MissingTranslationBehavior::FLOW_ERROR,
        ];
        foreach ($spellings as $spelling => $behavior) {
            $this->withConfigured(
                [
                    'SourceElementDataKey' => 'ip',
                    'MissingTranslationBehavior' => $spelling,
                ],
                function (TranslationEngine $engine) use ($spelling, $behavior): void {
                    $this->assertSame($behavior, self::field($engine, 'behavior'), $spelling);
                }
            );
        }
    }

    public function testAnUnknownBehaviorIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "'Ignore' is not a missing translation behavior. Use Original, EmptyString or FlowError."
        );
        (new PipelineBuilder())->buildFromConfig(self::configuration([
            'SourceElementDataKey' => 'ip',
            'MissingTranslationBehavior' => 'Ignore',
        ]));
    }

    public function testAConfigurationWithoutASourceIsRefused(): void
    {
        foreach ([[], ['FixedLanguage' => 'en_GB'], ['SourceElementDataKey' => '']] as $parameters) {
            $message = null;
            try {
                new TranslationEngine($parameters);
            } catch (\InvalidArgumentException $e) {
                $message = $e->getMessage();
            }
            $this->assertSame(
                'The Translation Engine needs a source element data key, the SourceElementDataKey setting.',
                $message,
                (string) json_encode($parameters)
            );
        }
    }

    public function testASettingThatIsNotTextIsRefused(): void
    {
        $cases = [
            'SourceElementDataKey' => ['SourceElementDataKey' => 5],
            'FixedLanguage' => [
                'SourceElementDataKey' => 'ip',
                'FixedLanguage' => ['en_GB'],
            ],
        ];
        foreach ($cases as $name => $parameters) {
            $message = null;
            try {
                new TranslationEngine($parameters);
            } catch (\InvalidArgumentException $e) {
                $message = $e->getMessage();
            }
            $this->assertSame(
                "The Translation Engine's {$name} setting must be text.",
                $message,
                (string) json_encode($parameters)
            );
        }
    }

    /**
     * An engine built from a configuration has no sources or translations,
     * so it processes without error and gives no properties.
     */
    public function testAConfiguredEngineReturnsNoProperties(): void
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [
                    ['BuilderName' => SourceElement::class],
                    [
                        'BuilderName' => TranslationEngine::class,
                        'BuildParameters' => ['SourceElementDataKey' => 'ip'],
                    ],
                ],
            ],
        ]);
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();
            $this->assertSame([], $flowData->errors);
            $this->assertSame(
                [],
                $flowData->get(TranslationEngine::DEFAULT_ELEMENT_DATA_KEY)
                    ->asDictionary()
            );
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    /**
     * FlowError records an error under the engine's data key for a value
     * with no translation, and the engine still gives its properties.
     */
    public function testFlowErrorRecordsAMissingTranslation(): void
    {
        $pipeline = (new PipelineBuilder())
            ->add(new SourceElement())
            ->add(new TranslationEngine(
                'translated',
                'ip',
                ['Country' => 'CountryTranslated'],
                ['fr_FR' => ['Scotland' => 'Ecosse']],
                'fr_FR',
                MissingTranslationBehavior::FLOW_ERROR
            ))
            ->build();
        // Keep the error in the FlowData rather than throw it from process.
        $pipeline->suppressProcessExceptions = true;
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();
            $this->assertSame(['translated'], array_keys($flowData->errors));
            $this->assertSame(
                "There was no translation found for the value 'England'.",
                $flowData->errors['translated']->getMessage()
            );
            $value = $flowData->get('translated')->get('countrytranslated');
            $this->assertFalse($value->hasValue);
            $this->assertSame(
                'Translation produced no value.',
                $value->noValueMessage
            );
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    /**
     * The constructor's arguments, which code written before the
     * configuration route passes, still build an engine that translates.
     */
    public function testThePositionalConstructorStillWorks(): void
    {
        $engine = new TranslationEngine(
            'translated',
            'ip',
            ['Country' => 'CountryTranslated'],
            ['fr_FR' => ['England' => 'Angleterre']],
            'fr_FR',
            MissingTranslationBehavior::EMPTY_STRING
        );
        $this->assertSame(
            MissingTranslationBehavior::EMPTY_STRING,
            self::field($engine, 'behavior')
        );
        $this->assertTranslates($engine, 'translated');
    }

    /**
     * PHP 8 named arguments with the deprecated name behavior still set the
     * missing translation behavior.
     */
    public function testNamedArgumentsStillWork(): void
    {
        $engine = new TranslationEngine(
            elementDataKey: 'translated',
            sourceElementDataKey: 'ip',
            propertyMap: ['Country' => 'CountryTranslated'],
            localeMaps: ['fr_FR' => ['England' => 'Angleterre']],
            fixedLanguage: 'fr_FR',
            behavior: MissingTranslationBehavior::EMPTY_STRING
        );
        $this->assertSame(
            MissingTranslationBehavior::EMPTY_STRING,
            self::field($engine, 'behavior')
        );
        $this->assertTranslates($engine, 'translated');
    }

    /**
     * The named argument takes the specification's name,
     * missingTranslationBehavior.
     */
    public function testNamedArgumentsUseTheSpecificationName(): void
    {
        $engine = new TranslationEngine(
            elementDataKey: 'translated',
            sourceElementDataKey: 'ip',
            propertyMap: ['Country' => 'CountryTranslated'],
            localeMaps: ['fr_FR' => ['England' => 'Angleterre']],
            fixedLanguage: 'fr_FR',
            missingTranslationBehavior: MissingTranslationBehavior::FLOW_ERROR
        );
        $this->assertSame(
            MissingTranslationBehavior::FLOW_ERROR,
            self::field($engine, 'behavior')
        );
        $this->assertTranslates($engine, 'translated');
    }

    public function testThePositionalConstructorStillNeedsASource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TranslationEngine('translated');
    }

    private function assertTranslates(TranslationEngine $engine, string $dataKey): void
    {
        $pipeline = (new PipelineBuilder())
            ->add(new SourceElement())
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();
            $this->assertSame(
                'Angleterre',
                $flowData->get($dataKey)->get('countrytranslated')->value
            );
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }
}
