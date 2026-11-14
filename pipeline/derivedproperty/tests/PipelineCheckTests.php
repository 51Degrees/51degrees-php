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

use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\PipelineConfigurationException;
use fiftyone\pipeline\derivedproperty\tests\classes\StubSourceElement;
use PHPUnit\Framework\TestCase;

/**
 * When a pipeline is built, the element refuses it where no element
 * supplies a property a script reads, or where another element already
 * writes a property a script writes. The same checks, with the same
 * messages, are made by the .NET and Rust elements.
 */
class PipelineCheckTests extends TestCase
{
    private const REFUSED = 'The derived property element cannot be used in this pipeline.';

    private static function script(string $name, string $source): string
    {
        $text = "Format: 1\nName: $name\nVersion: 1.0.0\nOutput:\n" .
            "  Name: $name\n  Description: A demonstration.\n" .
            "  ValueType: string\n  IsList: false\nRules:\n";
        if ($source !== '') {
            $text .= "  - When: { Property: $source, Lt: 2 }\n    Then: \"yes\"\n";
        }

        return $text . "  - Else: \"no\"\n";
    }

    /**
     * @param array<string, string> $scripts each script's name and the one
     *     source property it reads, or an empty string for none
     */
    private static function derived(array $scripts): DerivedPropertyElement
    {
        $builder = new DerivedPropertyElementBuilder();
        foreach ($scripts as $name => $source) {
            $builder->addScript($name, self::script($name, $source));
        }

        return $builder->build();
    }

    private static function humanConfidence(): DerivedPropertyElement
    {
        return (new DerivedPropertyElementBuilder())
            ->addBuiltInScript('HumanConfidence')
            ->build();
    }

    /**
     * The names HumanConfidence reads under one element key.
     *
     * @return array<string>
     */
    private static function readByHumanConfidence(string $key): array
    {
        $names = [];
        foreach (self::humanConfidence()->getScripts()[0]->properties as $property) {
            if ($property['elementKey'] === $key) {
                $names[] = $property['propertyName'];
            }
        }

        return $names;
    }

    /**
     * @param array<string> $names
     */
    private static function supplier(string $key, array $names): StubSourceElement
    {
        return new StubSourceElement($key, [], $names);
    }

    private static function needs(string $script, string $property): string
    {
        return "The script '$script' needs the property '$property', and no " .
            "element in the pipeline supplies it. Either add the element that " .
            "supplies '$property', or change the script so that it does not " .
            "name '$property'.";
    }

    private static function writes(string $script, string $property): string
    {
        return "The script '$script' writes the derived property '$property', " .
            'and another element in the pipeline already writes a property of ' .
            'that name. One pipeline writes each derived property once.';
    }

    /**
     * @param array<string> $lines
     */
    private static function refused(array $lines): string
    {
        return self::REFUSED . "\n" . implode("\n", $lines);
    }

    /**
     * @param array<FlowElement> $elements
     */
    private static function message(array $elements): string
    {
        try {
            new Pipeline($elements, []);
        } catch (PipelineConfigurationException $e) {
            return $e->getMessage();
        }

        return 'the pipeline was built';
    }

    public function testAPipelineSupplyingNothingNamesEveryProperty(): void
    {
        $lines = array_map(
            fn (array $property) => self::needs('HumanConfidence', $property['name']),
            self::humanConfidence()->getScripts()[0]->properties
        );

        $this->assertNotEmpty($lines);
        $this->assertSame(
            self::refused($lines),
            self::message([self::humanConfidence()])
        );
    }

    public function testSuppliersMaySitAnywhereInThePipeline(): void
    {
        new Pipeline([
            self::supplier('device', self::readByHumanConfidence('device')),
            self::humanConfidence(),
            self::supplier('ip', self::readByHumanConfidence('ip')),
        ], []);
        new Pipeline([
            self::humanConfidence(),
            self::supplier('device', self::readByHumanConfidence('device')),
            self::supplier('ip', self::readByHumanConfidence('ip')),
        ], []);

        $this->addToAssertionCount(1);
    }

    public function testKeysAndNamesAreComparedIgnoringCase(): void
    {
        new Pipeline([
            self::supplier('DEVICE', array_map(
                'strtolower',
                self::readByHumanConfidence('device')
            )),
            self::supplier('Ip', array_map(
                'strtoupper',
                self::readByHumanConfidence('ip')
            )),
            self::humanConfidence(),
        ], []);

        $this->addToAssertionCount(1);
    }

    public function testAPropertyListedUnderAnotherKeyIsNotSupplied(): void
    {
        $lines = array_map(
            fn (string $name) => self::needs('HumanConfidence', "ip.$name"),
            self::readByHumanConfidence('ip')
        );

        $this->assertSame(self::refused($lines), self::message([
            self::supplier('device', self::readByHumanConfidence('device')),
            self::supplier('location', self::readByHumanConfidence('ip')),
            self::humanConfidence(),
        ]));
    }

    /**
     * A cloud engine learns its properties from the first response, so an
     * element listing none may supply any property under its own key.
     */
    public function testAnElementListingNoPropertiesMaySupplyAnyOfItsOwn(): void
    {
        new Pipeline([
            self::supplier('device', self::readByHumanConfidence('device')),
            self::supplier('ip', []),
            self::humanConfidence(),
        ], []);

        $lines = array_map(
            fn (string $name) => self::needs('HumanConfidence', "device.$name"),
            self::readByHumanConfidence('device')
        );
        $this->assertSame(self::refused($lines), self::message([
            self::supplier('ip', []),
            self::humanConfidence(),
        ]));
    }

    public function testTwoElementsWritingOneDerivedPropertyAreRefused(): void
    {
        $this->assertSame(self::refused([self::writes('Demo', 'Demo')]), self::message([
            self::supplier('src', ['N']),
            self::derived(['Demo' => 'src.N']),
            self::derived(['Demo' => 'src.N']),
        ]));

        // Letter case does not make two properties of one.
        $this->assertSame(self::refused([self::writes('Demo', 'Demo')]), self::message([
            self::derived(['Demo' => '']),
            self::derived(['DEMO' => '']),
        ]));

        new Pipeline([
            self::supplier('src', ['N']),
            self::derived(['Demo' => 'src.N']),
            self::derived(['Other' => 'src.N']),
        ], []);

        // Only an element with the data key derived writes a derived
        // property.
        new Pipeline([
            self::supplier('src', ['N', 'Demo']),
            self::derived(['Demo' => 'src.N']),
        ], []);
    }

    public function testAScriptMayReadWhatAnEarlierScriptOfItsElementWrites(): void
    {
        new Pipeline([
            self::supplier('src', ['N']),
            self::derived(['Band' => 'src.N', 'BandIsHigh' => 'derived.Band']),
        ], []);

        // A later script's property has no value when an earlier one reads
        // it.
        $this->assertSame(
            self::refused([self::needs('BandIsHigh', 'derived.Band')]),
            self::message([
                self::supplier('src', ['N']),
                self::derived(['BandIsHigh' => 'derived.Band', 'Band' => 'src.N']),
            ])
        );

        // A script's own property is not there when it runs either.
        $this->assertSame(
            self::refused([self::needs('Demo', 'derived.Demo')]),
            self::message([self::derived(['Demo' => 'derived.Demo'])])
        );
    }

    public function testAnotherDerivedElementSuppliesWhatItWritesWhereverItSits(): void
    {
        new Pipeline([
            self::supplier('src', ['N']),
            self::derived(['BandIsHigh' => 'derived.Band']),
            self::derived(['Band' => 'src.N']),
        ], []);

        $this->addToAssertionCount(1);
    }

    public function testPropertiesWrittenTwiceComeBeforeThoseNotSupplied(): void
    {
        $this->assertSame(
            self::refused([
                self::writes('Demo', 'Demo'),
                self::needs('Demo', 'gone.N'),
            ]),
            self::message([
                self::derived(['Demo' => 'gone.N']),
                self::derived(['Demo' => '']),
            ])
        );
    }

    /**
     * PHP builds the pipeline on every request, so an element that can find
     * one property is asked for that one, rather than for the table of
     * every property it has.
     */
    public function testAnElementThatFindsAPropertyIsNotAskedForEveryProperty(): void
    {
        $engine = new class() extends FlowElement {
            public string $dataKey = 'src';

            public int $asked = 0;

            /**
             * @var array<string>
             */
            public array $found = [];

            public function getProperties(): array
            {
                ++$this->asked;

                return [];
            }

            /**
             * @return null|array<string, string>
             */
            public function findProperty(string $name): ?array
            {
                $this->found[] = $name;

                return strcasecmp($name, 'N') === 0
                    ? ['name' => 'N', 'type' => 'Integer']
                    : null;
            }
        };

        new Pipeline([$engine, self::derived(['Demo' => 'src.N'])], []);
        $this->assertSame(0, $engine->asked);
        $this->assertSame(['N'], $engine->found);

        $this->assertSame(
            self::refused([self::needs('Demo', 'src.M')]),
            self::message([$engine, self::derived(['Demo' => 'src.M'])])
        );
    }
}
