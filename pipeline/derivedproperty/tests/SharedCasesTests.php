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
use fiftyone\pipeline\derivedproperty\DerivedPropertyElement;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;
use fiftyone\pipeline\derivedproperty\DerivedScript;
use fiftyone\pipeline\derivedproperty\Missing;
use fiftyone\pipeline\derivedproperty\ScriptValidationException;
use fiftyone\pipeline\derivedproperty\ScriptValidator;
use fiftyone\pipeline\derivedproperty\tests\classes\SharedFolder;
use fiftyone\pipeline\derivedproperty\tests\classes\StubSourceElement;
use fiftyone\pipeline\derivedproperty\ValueConverter;
use PHPUnit\Framework\TestCase;

/**
 * Runs every case file in the tests folder of the derived-properties
 * submodule, as .NET and Rust do, so the answers match theirs case for
 * case. Each value case runs the script this package carries through a
 * real pipeline, with a source element giving the case's properties. Each
 * rejection case in tests/invalid must be refused with a fault at every
 * path, and a fault mentioning every fragment, the case expects.
 *
 * Every failure is collected and reported together, so one run shows all
 * of them.
 */
class SharedCasesTests extends TestCase
{
    public function testEveryValueCaseGivesTheSharedAnswer(): void
    {
        $files = SharedFolder::files('tests', '/\.cases\.ya?ml$/i');
        $this->assertNotEmpty($files, 'the submodule has no case files');

        $failures = [];
        $count = 0;
        foreach ($files as $file) {
            $count += $this->runCaseFile($file, $failures);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThan(0, $count, 'no value case was run');
    }

    public function testEveryRejectionCaseIsRefused(): void
    {
        $files = SharedFolder::files('tests' . DIRECTORY_SEPARATOR . 'invalid', '/\.ya?ml$/i');
        $this->assertNotEmpty($files, 'the submodule has no rejection cases');

        $failures = [];
        foreach ($files as $file) {
            $this->runRejectionCase($file, $failures);
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * @param array<string> $failures
     * @return int the number of cases run
     */
    private function runCaseFile(string $file, array &$failures): int
    {
        $name = basename($file);
        $document = SharedFolder::read($file);
        $scriptName = Missing::key($document, 'Script');
        if (!is_string($scriptName)) {
            $failures[] = "$name: Script must name the script the file proves";

            return 0;
        }
        try {
            $element = (new DerivedPropertyElementBuilder())
                ->addBuiltInScript($scriptName)
                ->build();
        } catch (\Throwable $e) {
            $failures[] = "$name: the built in script '$scriptName' could not " .
                'be built. ' . $e->getMessage();

            return 0;
        }
        $script = $element->getScripts()[0];
        if ($script->name !== $scriptName) {
            $failures[] = "$name: Script is '$scriptName' but the built in " .
                "script is '{$script->name}'";

            return 0;
        }

        $cases = Missing::key($document, 'Cases');
        if (!is_array($cases) || count($cases) === 0) {
            $failures[] = "$name: Cases must list at least one case";

            return 0;
        }
        foreach ($cases as $case) {
            $this->runCase($scriptName, $script, $case, $failures);
        }

        return count($cases);
    }

    /**
     * @param mixed $case
     * @param array<string> $failures
     */
    private function runCase(
        string $scriptName,
        DerivedScript $script,
        $case,
        array &$failures
    ): void {
        $caseName = Missing::key($case, 'Name');
        $prefix = sprintf(
            "%s: '%s' ",
            $scriptName,
            is_string($caseName) ? $caseName : '(unnamed)'
        );
        $expect = Missing::key($case, 'Expect');
        if (!($expect instanceof \stdClass)) {
            $failures[] = $prefix . 'has no Expect';

            return;
        }

        try {
            $sources = $this->sources($script, Missing::key($case, 'Properties'));
            $produced = $this->produce($script, $sources);
        } catch (\Throwable $e) {
            $failures[] = $prefix . 'raised ' . get_class($e) . ': ' . $e->getMessage();

            return;
        }

        $wanted = Missing::key($expect, 'Value');
        if (!($wanted instanceof Missing)) {
            if (!$produced->hasValue) {
                $failures[] = sprintf(
                    "%sexpected the value '%s' but there was no value. %s",
                    $prefix,
                    ValueConverter::text($wanted),
                    $produced->noValueMessage
                );
            } elseif (ValueConverter::text($produced->value) !== ValueConverter::text($wanted)) {
                $failures[] = sprintf(
                    "%sexpected '%s' but got '%s'",
                    $prefix,
                    ValueConverter::text($wanted),
                    ValueConverter::text($produced->value)
                );
            }

            return;
        }

        $missing = Missing::key($expect, 'Missing');
        if (!($missing instanceof Missing)) {
            $this->compareMissing($prefix, $script, $missing, $produced, $failures);

            return;
        }

        $failures[] = $prefix . 'has an Expect that is not Value or Missing';
    }

    /**
     * Every property that could not be read has to be named, so the count
     * the message gives must equal the number expected, and each expected
     * property must be named, with letter case ignored.
     *
     * @param mixed $missing
     * @param array<string> $failures
     */
    private function compareMissing(
        string $prefix,
        DerivedScript $script,
        $missing,
        AspectPropertyValue $produced,
        array &$failures
    ): void {
        if ($produced->hasValue) {
            $failures[] = sprintf(
                "%sexpected no value because a source property was not " .
                    "available, but got '%s'",
                $prefix,
                ValueConverter::text($produced->value)
            );

            return;
        }
        if (!is_array($missing) || count($missing) === 0) {
            $failures[] = $prefix . 'Missing must list the properties expected ' .
                'to be absent';

            return;
        }
        $message = (string) $produced->noValueMessage;
        $opening = sprintf(
            "Derived property '%s' has no value because ",
            $script->outputName()
        );
        if (strpos($message, $opening) !== 0 ||
            substr($message, -strlen(DerivedScript::USUAL_CAUSES)) !==
                DerivedScript::USUAL_CAUSES) {
            $failures[] = $prefix . 'gave a message that is not in the shape the ' .
                "format gives: $message";

            return;
        }
        $count = preg_match('/^1 source property was not available\./', substr($message, strlen($opening))) === 1
            ? 1
            : (preg_match('/^(\d+) source properties were not available\./', substr($message, strlen($opening)), $match) === 1
                ? (int) $match[1]
                : -1);
        $expected = array_unique(array_map(
            fn ($name) => strtolower((string) $name),
            $missing
        ));
        if ($count !== count($expected)) {
            $failures[] = sprintf(
                '%sexpected %d missing properties, being %s, but the message ' .
                    'said: %s',
                $prefix,
                count($expected),
                implode(', ', $expected),
                $message
            );

            return;
        }
        foreach ($expected as $name) {
            if (stripos($message, "'" . $name . "' (") === false) {
                $failures[] = sprintf(
                    "%sexpected the message to name '%s' but it said: %s",
                    $prefix,
                    $name,
                    $message
                );
            }
        }
    }

    /**
     * One source element for each element key the script or the case
     * names. Each gives the case's values for its key and says it gives
     * every property the script reads under that key, so that a property
     * the case leaves out is one the request lacks rather than one the
     * pipeline cannot supply.
     *
     * @param mixed $properties
     * @return array<StubSourceElement>
     */
    private function sources(DerivedScript $script, $properties): array
    {
        $byKey = [];
        foreach ($script->properties as $property) {
            $key = strtolower($property['elementKey']);
            $byKey[$key]['names'][] = $property['propertyName'];
            $byKey[$key]['values'] = $byKey[$key]['values'] ?? [];
        }
        if ($properties instanceof \stdClass) {
            foreach (Missing::keys($properties) as $name) {
                $dot = strpos($name, '.');
                if ($dot === false) {
                    throw new \UnexpectedValueException(
                        "'$name' is not written as elementKey.PropertyName"
                    );
                }
                $key = strtolower(substr($name, 0, $dot));
                $byKey[$key]['names'] = $byKey[$key]['names'] ?? [];
                $byKey[$key]['values'][substr($name, $dot + 1)] =
                    self::sourceValue($name, $properties->{$name});
            }
        } elseif (!($properties instanceof Missing) && $properties !== null) {
            throw new \UnexpectedValueException(
                'Properties must be a mapping of source properties to values'
            );
        }

        $sources = [];
        ksort($byKey, SORT_STRING);
        foreach ($byKey as $key => $entry) {
            $sources[] = new StubSourceElement(
                (string) $key,
                $entry['values'],
                $entry['names'] ?? []
            );
        }

        return $sources;
    }

    /**
     * A value in one of the four forms a case can write it, as a PHP
     * element would hold it.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function sourceValue(string $name, $value)
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \stdClass) {
            $string = Missing::key($value, 'String');
            if (!($string instanceof Missing)) {
                if ($string === null) {
                    return null;
                }

                return StubSourceElement::value(
                    is_string($string) ? $string : ValueConverter::text($string)
                );
            }
            $noValue = Missing::key($value, 'NoValue');
            if (!($noValue instanceof Missing)) {
                return new AspectPropertyValue(ValueConverter::text($noValue));
            }
            throw new \UnexpectedValueException(
                "'$name' is written as a mapping that is neither String nor NoValue"
            );
        }
        if (is_array($value)) {
            // A weighted list in the shape IP Intelligence gives one.
            $weighted = [];
            foreach ($value as $member) {
                $memberValue = Missing::key($member, 'Value');
                $weight = Missing::key($member, 'Weight');
                if ($memberValue instanceof Missing || $weight instanceof Missing) {
                    throw new \UnexpectedValueException(
                        "'$name' is written as a list, so every member needs a " .
                            'Value and a Weight'
                    );
                }
                $weighted[] = ['value' => $memberValue, 'rawweighting' => $weight];
            }

            return StubSourceElement::value($weighted);
        }

        return StubSourceElement::value($value);
    }

    /**
     * Runs the script in a pipeline of its own, after the source elements.
     *
     * @param array<StubSourceElement> $sources
     */
    private function produce(DerivedScript $script, array $sources): AspectPropertyValue
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]);
        foreach ($sources as $source) {
            $builder->add($source);
        }
        $builder->add(new DerivedPropertyElement([], [$script]));
        $flowData = $builder->build()->createFlowData();
        $flowData->process();
        $value = $flowData->get(DerivedPropertyElement::DATA_KEY)
            ->get($script->outputName());
        if (!($value instanceof AspectPropertyValue)) {
            throw new \UnexpectedValueException(
                'the element wrote something that is not an AspectPropertyValue'
            );
        }

        return $value;
    }

    /**
     * @param array<string> $failures
     */
    private function runRejectionCase(string $file, array &$failures): void
    {
        $name = basename($file);
        $document = SharedFolder::read($file);
        $text = Missing::key($document, 'Script');
        if (!is_string($text)) {
            $failures[] = "$name: Script must be the text of the script to reject";

            return;
        }
        $scriptName = Missing::key($document, 'Name');
        $expect = Missing::key($document, 'Expect');

        try {
            ScriptValidator::read(
                $text,
                is_string($scriptName) ? $scriptName : null,
                $name
            );
            $failures[] = "$name: the script was expected to be rejected but it validated";

            return;
        } catch (ScriptValidationException $e) {
            $faults = $e->getFaults();
        }
        $described = implode("\n", array_map('strval', $faults));

        $paths = Missing::key($expect, 'Paths');
        foreach (is_array($paths) ? $paths : [] as $path) {
            $found = false;
            foreach ($faults as $fault) {
                $found = $found || $fault->path === (string) $path;
            }
            if (!$found) {
                $failures[] = "$name: expected a fault at '$path'. Faults were:\n$described";
            }
        }
        $mentions = Missing::key($expect, 'Mentions');
        foreach (is_array($mentions) ? $mentions : [] as $fragment) {
            $found = false;
            foreach ($faults as $fault) {
                $found = $found || strpos($fault->message, (string) $fragment) !== false;
            }
            if (!$found) {
                $failures[] = "$name: expected a fault mentioning '$fragment'. " .
                    "Faults were:\n$described";
            }
        }
    }
}
