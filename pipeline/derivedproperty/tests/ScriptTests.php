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

use fiftyone\pipeline\derivedproperty\CanonicalJson;
use fiftyone\pipeline\derivedproperty\DerivedScript;
use fiftyone\pipeline\derivedproperty\ScriptValidationException;
use fiftyone\pipeline\derivedproperty\ScriptValidator;
use fiftyone\pipeline\derivedproperty\ValueConverter;
use PHPUnit\Framework\TestCase;

/**
 * Parts of the format the shared cases do not reach on their own, being
 * how YAML is read, how a literal infers its type and how a value is
 * converted.
 */
class ScriptTests extends TestCase
{
    /**
     * A script whose one rule compares src.N with the condition given.
     */
    private static function script(string $condition, string $valueType = 'string'): string
    {
        $then = $valueType === 'bool' ? 'true' : '"yes"';
        $else = $valueType === 'bool' ? 'false' : '"no"';

        return "Format: 1\nName: Demo\nVersion: 1.0.0\nOutput:\n" .
            "  Name: Demo\n  Description: A demonstration.\n" .
            "  ValueType: $valueType\n  IsList: false\nRules:\n" .
            "  - When: { Property: src.N, $condition }\n    Then: $then\n" .
            "  - Else: $else\n";
    }

    /**
     * The messages of the faults a script is refused with.
     *
     * @return array<string, string> each fault's message by its path
     */
    private static function faults(string $text): array
    {
        try {
            ScriptValidator::read($text);
        } catch (ScriptValidationException $e) {
            $faults = [];
            foreach ($e->getFaults() as $fault) {
                $faults[$fault->path] = $fault->message;
            }

            return $faults;
        }

        return [];
    }

    /**
     * Runs a script with every source property read as the value given.
     *
     * @param mixed $value
     */
    private static function answer(DerivedScript $script, $value): string
    {
        $slots = [];
        foreach ($script->properties as $property) {
            $read = null;
            $slots[] = [
                'available' => ValueConverter::convert($value, $property['type'], $read),
                'value' => $read,
                'reason' => 'not read',
            ];
        }
        $result = $script->evaluate($slots);

        return $result->hasValue
            ? ValueConverter::text($result->value)
            : (string) $result->noValueMessage;
    }

    public function testYesAndNoAreStringsAsTheReferenceToolsReadThem(): void
    {
        $script = ScriptValidator::read(self::script('Eq: no'));

        $this->assertSame('string', $script->properties[0]['type']);
        $this->assertSame('yes', self::answer($script, 'no'));
    }

    public function testKeysThatDifferOnlyInCaseAreRefused(): void
    {
        $faults = self::faults(str_replace(
            "  IsList: false\n",
            "  IsList: false\n  islist: false\n",
            self::script('Eq: 1')
        ));

        $this->assertArrayHasKey('', $faults);
        $this->assertStringContainsString(
            "the keys 'IsList' and 'islist' differ only in case",
            $faults['']
        );
    }

    public function testAKeyWrittenTwiceIsRefused(): void
    {
        $faults = self::faults(str_replace(
            "  IsList: false\n",
            "  IsList: false\n  IsList: false\n",
            self::script('Eq: 1')
        ));

        $this->assertStringContainsString('not valid YAML', $faults['']);
    }

    public function testKeysAreMatchedWithoutRegardToCase(): void
    {
        $script = ScriptValidator::read(str_replace(
            ['Format:', 'Rules:', 'ValueType:'],
            ['format:', 'RULES:', 'valuetype:'],
            self::script('lt: 2')
        ));

        $this->assertSame('int', $script->properties[0]['type']);
        $this->assertSame('Lt', $script->rules[0]['when']['op']);
    }

    public function testAJsonScriptGivesTheSameScriptAsItsYaml(): void
    {
        $json = <<<'JSON'
            {
              "Format": 1,
              "Name": "Demo",
              "Version": "1.0.0",
              "Output": {
                "Name": "Demo",
                "Description": "A demonstration.",
                "ValueType": "string",
                "IsList": false
              },
              "Rules": [
                { "When": { "Property": "src.N", "Lt": 2 }, "Then": "yes" },
                { "Else": "no" }
              ]
            }
            JSON;

        $this->assertSame(
            CanonicalJson::write(ScriptValidator::read(self::script('Lt: 2'))),
            CanonicalJson::write(ScriptValidator::read($json))
        );
    }

    public function testAWholeNumberWrittenWithAPointStillInfersInt(): void
    {
        $whole = ScriptValidator::read(self::script('Lt: 2.0'));
        $fraction = ScriptValidator::read(self::script('Lt: 1.999'));

        $this->assertSame('int', $whole->properties[0]['type']);
        $this->assertSame('double', $fraction->properties[0]['type']);
        // 1.5 cannot be read as an int, so the script has no value.
        $this->assertStringContainsString('has no value', self::answer($whole, '1.5'));
        $this->assertSame('yes', self::answer($fraction, '1.5'));
    }

    public function testAWholeNumberBeyondAnIntInfersDouble(): void
    {
        $script = ScriptValidator::read(self::script('Gt: 3000000000'));

        $this->assertSame('double', $script->properties[0]['type']);
        $this->assertSame('yes', self::answer($script, 3000000001));
    }

    public function testAnIntIsASigned32BitWholeNumber(): void
    {
        $value = null;
        $this->assertTrue(ValueConverter::convertString('2147483647', 'int', $value));
        $this->assertSame(2147483647, $value);
        $this->assertTrue(ValueConverter::convertString(' -2147483648 ', 'int', $value));
        $this->assertSame(-2147483648, $value);
        $this->assertFalse(ValueConverter::convertString('3000000000', 'int', $value));
        $this->assertFalse(ValueConverter::convertString('99999999999999999999', 'int', $value));
        $this->assertFalse(ValueConverter::convert(3000000000, 'int', $value));
        $this->assertTrue(ValueConverter::convert(3000000000, 'double', $value));
    }

    public function testValuesThatMeanNothingNeverBecomeFalseOrZero(): void
    {
        $value = null;
        foreach (['bool', 'int', 'double'] as $type) {
            foreach (['N/A', 'Unknown', '', ' '] as $text) {
                $this->assertFalse(
                    ValueConverter::convertString($text, $type, $value),
                    "'$text' was read as $type"
                );
            }
        }
        $this->assertFalse(ValueConverter::convertString('1', 'bool', $value));
        $this->assertFalse(ValueConverter::convertString('yes', 'bool', $value));
        $this->assertFalse(ValueConverter::convertString('1.0', 'int', $value));
        $this->assertTrue(ValueConverter::convertString(' TRUE ', 'bool', $value));
        $this->assertTrue($value);
        $this->assertTrue(ValueConverter::convertString('-1.5e2', 'double', $value));
        $this->assertSame(-150.0, $value);
    }

    public function testANativeValueReadAsAStringTakesItsPrintedForm(): void
    {
        $value = null;
        $this->assertTrue(ValueConverter::convert(true, 'string', $value));
        $this->assertSame('True', $value);
        $this->assertTrue(ValueConverter::convert(false, 'string', $value));
        $this->assertSame('False', $value);
        $this->assertTrue(ValueConverter::convert(8, 'string', $value));
        $this->assertSame('8', $value);
        $this->assertFalse(ValueConverter::convert(1, 'bool', $value));
    }

    public function testStringsCompareOrdinallyWithRegardToCase(): void
    {
        $equal = ScriptValidator::read(self::script('Eq: "Chrome"'));
        $starts = ScriptValidator::read(self::script('StartsWith: "Chr"'));

        $this->assertSame('yes', self::answer($equal, 'Chrome'));
        $this->assertSame('no', self::answer($equal, 'chrome'));
        $this->assertSame('yes', self::answer($starts, 'Chrome'));
        $this->assertSame('no', self::answer($starts, 'CHROME'));
    }

    public function testAMixedNumberListReadsAsDouble(): void
    {
        $script = ScriptValidator::read(self::script('In: [1, 2.5]'));

        $this->assertSame('double', $script->properties[0]['type']);
        $this->assertSame('yes', self::answer($script, 1));
        $this->assertSame('yes', self::answer($script, '2.5'));
        $this->assertSame('no', self::answer($script, 2));
    }

    public function testEveryFaultInAScriptIsReportedTogether(): void
    {
        $faults = self::faults(str_replace(
            "Version: 1.0.0\n",
            "Version: one\nInputs: [src.N]\n",
            self::script('Gt: "2"')
        ));

        $this->assertStringContainsString('Version expected a semantic version', $faults['Version']);
        $this->assertStringContainsString("unknown key 'Inputs' at the top level", $faults['Inputs']);
        $this->assertStringContainsString("operator 'Gt' is not allowed on type string", $faults['Rules[0].When']);
    }

    public function testTheMessageNamesEveryMissingPropertyInOrder(): void
    {
        $script = ScriptValidator::read(str_replace(
            '{ Property: src.N, Lt: 2 }',
            '{ All: [ { Property: src.N, Lt: 2 }, { Property: other.M, Eq: true } ] }',
            self::script('Lt: 2')
        ));
        $result = $script->evaluate([
            ['available' => false, 'value' => null, 'reason' => 'first reason'],
            ['available' => false, 'value' => null, 'reason' => 'second reason'],
        ]);

        $this->assertFalse($result->hasValue);
        $this->assertSame(
            "Derived property 'Demo' has no value because 2 source properties " .
                "were not available. 'src.N' (first reason). 'other.M' " .
                '(second reason). ' . DerivedScript::USUAL_CAUSES,
            $result->noValueMessage
        );
    }

    public function testABoolOutputIsABoolean(): void
    {
        $script = ScriptValidator::read(self::script('Lt: 2', 'bool'));
        $result = $script->evaluate([['available' => true, 'value' => 1, 'reason' => null]]);

        $this->assertTrue($result->value);
    }
}
