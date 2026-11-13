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
use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\ScriptValidationException;
use fiftyone\pipeline\derivedproperty\tests\classes\ConfiguredSourceElement;
use fiftyone\pipeline\derivedproperty\tests\classes\RecordingLogger;
use fiftyone\pipeline\derivedproperty\tests\classes\StubSourceElement;
use PHPUnit\Framework\TestCase;

/**
 * The element in a pipeline, being how it reads the values PHP elements
 * give, what it writes, what it logs and how it is configured.
 */
class ElementTests extends TestCase
{
    /**
     * A script producing yes where src.N is below 2, and no otherwise. The
     * line ends are written out, so that the tests that edit the text work
     * on a checkout that turns them into CR LF.
     */
    private const DEMO = "Format: 1\nName: Demo\nVersion: 1.0.0\nOutput:\n" .
        "  Name: Demo\n  Description: A demonstration.\n  ValueType: string\n" .
        "  IsList: false\nRules:\n  - When: { Property: src.N, Lt: 2 }\n" .
        "    Then: \"yes\"\n  - Else: \"no\"\n";

    private static function demo(): DerivedPropertyElement
    {
        return (new DerivedPropertyElementBuilder())
            ->addScript('Demo', self::DEMO)
            ->build();
    }

    /**
     * Processes one request through the elements given, after a builder
     * that adds no other element.
     *
     * @param array<FlowElement> $elements
     */
    private static function process(array $elements, ?RecordingLogger $logger = null): FlowData
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]);
        if ($logger !== null) {
            $builder->addLogger($logger);
        }
        foreach ($elements as $element) {
            $builder->add($element);
        }
        $flowData = $builder->build()->createFlowData();
        $flowData->process();

        return $flowData;
    }

    /**
     * @param mixed $value
     */
    private static function source($value): StubSourceElement
    {
        return new StubSourceElement('src', ['N' => $value], ['N']);
    }

    public function testAValueIsReadUnderTheDerivedKeyWhateverTheCase(): void
    {
        $flowData = self::process([
            self::source(StubSourceElement::value(1)),
            self::demo(),
        ]);

        $this->assertSame('yes', $flowData->derived->demo->value);
        $this->assertSame('yes', $flowData->get('derived')->get('Demo')->value);
        $this->assertSame(
            ['demo'],
            array_keys($flowData->get('derived')->asDictionary())
        );
    }

    public function testAPlainValueIsReadAsWellAsAnAspectPropertyValue(): void
    {
        $flowData = self::process([self::source(5), self::demo()]);

        $this->assertSame('no', $flowData->derived->demo->value);
    }

    public function testAPropertyThatCannotBeReadRaisesTheMessageOnRead(): void
    {
        $flowData = self::process([
            new StubSourceElement('src', [], ['N']),
            self::demo(),
        ]);
        $value = $flowData->derived->demo;

        $this->assertFalse($value->hasValue);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "Derived property 'Demo' has no value because 1 source property " .
                "was not available. 'src.N' (element 'src' has no value for " .
                "'N': property not present on this request). Usual causes are"
        );
        $value->value;
    }

    public function testAPropertyTheElementDoesNotProduceIsRefused(): void
    {
        $flowData = self::process([self::source(1), self::demo()]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "There is no derived property 'Nothing'. The derived properties are Demo."
        );
        $flowData->derived->get('Nothing');
    }

    /**
     * An engine raises an exception for a property it does not have, and
     * the message names why, so the derived property carries it.
     */
    public function testTheMessageOfASourceThatRaisesIsCarried(): void
    {
        $source = new class() extends FlowElement {
            public string $dataKey = 'src';

            public array $properties = ['n' => ['name' => 'N', 'type' => 'Integer']];

            public function processInternal(FlowData $flowData): void
            {
                $flowData->setElementData(new class($this) extends ElementData {
                    protected function getInternal(string $key)
                    {
                        throw new \Exception('N was excluded from the engine.');
                    }
                });
            }
        };
        $flowData = self::process([$source, self::demo()]);

        $this->assertStringContainsString(
            "'src.N' (element 'src' has no value for 'N': N was excluded from the engine.)",
            (string) $flowData->derived->demo->noValueMessage
        );
    }

    /**
     * IP Intelligence gives a weighted value as a list of value and
     * rawweighting pairs, and the heaviest is read.
     */
    public function testAWeightedListGivesTheHeaviestValue(): void
    {
        $flowData = self::process([
            self::source(StubSourceElement::value([
                ['value' => 9, 'rawweighting' => 100],
                ['value' => 1, 'rawweighting' => 65435],
            ])),
            self::demo(),
        ]);
        $this->assertSame('yes', $flowData->derived->demo->value);

        // The first of two equal weights wins.
        $flowData = self::process([
            self::source(StubSourceElement::value([
                ['value' => 9, 'rawweighting' => 5],
                ['value' => 1, 'rawweighting' => 5],
            ])),
            self::demo(),
        ]);
        $this->assertSame('no', $flowData->derived->demo->value);
    }

    public function testAListThatIsNotWeightedCannotBeRead(): void
    {
        $flowData = self::process([
            self::source(StubSourceElement::value([1, 2])),
            self::demo(),
        ]);

        $this->assertStringContainsString(
            "'src.N' (held a list where a single value is needed).",
            (string) $flowData->derived->demo->noValueMessage
        );
    }

    public function testElementDataNotThereIsLoggedOnce(): void
    {
        $logger = new RecordingLogger('error');
        $element = self::demo();
        $source = new StubSourceElement('src', null, ['N']);
        self::process([$source, $element], $logger);
        $flowData = self::process([$source, $element], $logger);

        $this->assertFalse($flowData->derived->demo->hasValue);
        $this->assertSame([
            "The script 'Demo' reads 'src.N', and the request carried no " .
                "element data 'src' at all, so the script has no value. The " .
                'usual cause is the element that supplies it running after ' .
                'this element rather than before it. This is reported once.',
        ], $logger->messages('error'));
    }

    public function testBuildingThePipelineLogsWhatEachScriptProduces(): void
    {
        $logger = new RecordingLogger();
        self::process([self::source(1), self::demo()], $logger);

        $this->assertContains(
            "Derived property script 'Demo' version 1.0.0, format 1, from " .
                "code, produces 'derived.Demo' as string.",
            $logger->messages('info')
        );
        $debug = array_values(array_filter(
            $logger->messages('debug'),
            fn (string $message) => strpos($message, "Derived property script 'Demo' compiled to ") === 0
        ));
        $this->assertCount(1, $debug);
        $this->assertStringContainsString(
            "\"Properties\": {\n    \"src.N\": {\n      \"Type\": \"int\"\n    }\n  }",
            $debug[0]
        );
    }

    public function testADeprecatedScriptLogsAWarningWithItsNote(): void
    {
        $text = str_replace(
            "Version: 1.0.0\n",
            "Version: 1.0.0\nDeprecated: true\nDeprecationNote: Use Other instead.\n",
            self::DEMO
        );
        $logger = new RecordingLogger();
        $element = (new DerivedPropertyElementBuilder())->addScript('Demo', $text)->build();
        self::process([self::source(1), $element], $logger);

        $this->assertSame(
            ["Derived property script 'Demo' is deprecated. Use Other instead."],
            $logger->messages('warning')
        );
    }

    public function testALaterScriptReadsWhatAnEarlierOneWrote(): void
    {
        $band = str_replace('Demo', 'Band', self::DEMO);
        $high = <<<'YAML'
            Format: 1
            Name: High
            Version: 1.0.0
            Output:
              Name: High
              Description: Whether the band is yes.
              ValueType: bool
              IsList: false
            Rules:
              - When: { Property: derived.Band, Eq: "yes" }
                Then: true
              - Else: false
            YAML;
        $element = (new DerivedPropertyElementBuilder())
            ->addScript('Band', $band)
            ->addScript('High', $high)
            ->build();
        $flowData = self::process([self::source(1), $element]);

        $this->assertTrue($flowData->derived->high->value);
    }

    public function testTheSettingsNameBuiltInScriptsAndFiles(): void
    {
        $folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('derived', true);
        mkdir($folder);
        try {
            file_put_contents($folder . DIRECTORY_SEPARATOR . 'Zed.yaml', str_replace('Demo', 'Zed', self::DEMO));
            file_put_contents($folder . DIRECTORY_SEPARATOR . 'Alpha.yaml', str_replace('Demo', 'Alpha', self::DEMO));
            file_put_contents($folder . DIRECTORY_SEPARATOR . 'Notes.txt', 'not a script');

            $element = new DerivedPropertyElement([
                'Scripts' => 'IsGdpr, "HumanConfidence"',
                'ScriptFiles' => $folder . DIRECTORY_SEPARATOR . '*.yaml',
            ]);

            $this->assertSame(
                ['IsGdpr', 'HumanConfidence', 'Alpha', 'Zed'],
                array_map(fn ($script) => $script->name, $element->getScripts())
            );
            $this->assertSame(
                $folder . DIRECTORY_SEPARATOR . 'Alpha.yaml',
                $element->getScripts()[2]->source
            );

            // The same settings as lists, and in camelCase.
            $element = new DerivedPropertyElement([
                'scripts' => ['IsGdpr'],
                'scriptFiles' => [$folder . DIRECTORY_SEPARATOR . 'Zed.yaml'],
            ]);
            $this->assertSame(
                ['IsGdpr', 'Zed'],
                array_map(fn ($script) => $script->name, $element->getScripts())
            );
        } finally {
            array_map('unlink', glob($folder . DIRECTORY_SEPARATOR . '*') ?: []);
            rmdir($folder);
        }
    }

    public function testAPipelineConfigurationFileBuildsTheElement(): void
    {
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [
                    [
                        'BuilderName' => ConfiguredSourceElement::class,
                        'BuildParameters' => [
                            'DataKey' => 'ip',
                            'Values' => ['CountryCode' => 'FR'],
                        ],
                    ],
                    [
                        'BuilderName' => DerivedPropertyElement::class,
                        'BuildParameters' => ['Scripts' => 'IsGdpr'],
                    ],
                ],
            ],
        ]);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertTrue($flowData->derived->isgdpr->value);
    }

    public function testFaultsInEveryScriptAreReportedTogether(): void
    {
        try {
            (new DerivedPropertyElementBuilder())
                ->addScript('One', str_replace('Demo', 'One', str_replace('Lt: 2', 'Lt: "2"', self::DEMO)))
                ->addScript('Two', str_replace('Name: Demo', 'Name: Other', self::DEMO))
                ->addScriptFile('no-such-folder/missing.yaml')
                ->addBuiltInScript('')
                ->build();
            $this->fail('the scripts were accepted');
        } catch (ScriptValidationException $e) {
            $messages = array_map(fn ($fault) => $fault->message, $e->getFaults());
            $this->assertContains(
                "operator 'Lt' is not allowed on type string. It is allowed on int, double",
                $messages
            );
            $this->assertContains("script name 'Other' must equal the file name 'Two'", $messages);
            $this->assertContains("the file 'no-such-folder/missing.yaml' does not exist", $messages);
            $this->assertContains('a built in script name is empty', $messages);
        }
    }

    public function testAnElementWithNoScriptIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one script must be configured.');
        new DerivedPropertyElement();
    }

    public function testASettingTheElementDoesNotHaveIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "The derived property element has no setting 'Script'. Its " .
                'settings are Scripts and ScriptFiles.'
        );
        new DerivedPropertyElement(['Script' => 'HumanConfidence']);
    }

    public function testTwoScriptsInOneElementCannotWriteOneProperty(): void
    {
        $this->expectException(ScriptValidationException::class);
        $this->expectExceptionMessage(
            "two scripts in the same element write the property 'Demo', being " .
                "'Demo' and 'Copy'"
        );
        (new DerivedPropertyElementBuilder())
            ->addScript('Demo', self::DEMO)
            ->addScript('Copy', (string) preg_replace('/^Name: Demo$/m', 'Name: Copy', self::DEMO))
            ->build();
    }

    public function testThePropertyMetadataIsTheOutputBlock(): void
    {
        $element = new DerivedPropertyElement(['Scripts' => 'HumanConfidence']);
        $metadata = $element->getProperties()['humanconfidence'];

        $this->assertSame('HumanConfidence', $metadata['name']);
        $this->assertSame('String', $metadata['type']);
        $this->assertSame('Human Detection', $metadata['category']);
        $this->assertTrue($metadata['available']);
        $this->assertFalse($metadata['isList']);
        $this->assertTrue($metadata['isPopular']);
        $this->assertSame(
            ['High', 'Medium', 'Low', 'Unknown'],
            array_map(fn (array $value) => $value['name'], $metadata['values'])
        );
        $this->assertContains('device.IsCrawler', $metadata['dependencies']);
        $this->assertContains('ip.HumanProbability', $metadata['dependencies']);
    }

    public function testTheJsonBundlerCarriesTheDerivedValues(): void
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilderElement' => false,
            'useSetHeaderProperties' => false,
        ]);
        $flowData = $builder
            ->add(self::source(StubSourceElement::value(1)))
            ->add(self::demo())
            ->build()
            ->createFlowData();
        $flowData->process();

        $this->assertSame('yes', $flowData->jsonbundler->json['derived']['demo']);
    }

    public function testASourceValueWithNoMessageReadsAsNotPresent(): void
    {
        $flowData = self::process([
            self::source(new AspectPropertyValue()),
            self::demo(),
        ]);

        $this->assertStringContainsString(
            "element 'src' has no value for 'N': property not present on this request",
            (string) $flowData->derived->demo->noValueMessage
        );
    }
}
