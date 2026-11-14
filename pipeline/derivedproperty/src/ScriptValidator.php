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

namespace fiftyone\pipeline\derivedproperty;

/**
 * Checks a script against format 1 and turns it into a DerivedScript.
 * Every fault is collected rather than stopping at the first, so an author
 * sees everything wrong with a file at once.
 *
 * The checks and their messages follow tools/validate.mjs in the
 * derived-properties repository, which is the reference the shared cases
 * are written against.
 */
final class ScriptValidator
{
    /**
     * The value types format 1 allows for an output property.
     */
    public const VALUE_TYPES = ['string', 'bool', 'int', 'double'];

    /**
     * The comparison operators, and the inferred types each is allowed on.
     */
    public const OPERATORS = [
        'Eq' => ['bool', 'int', 'double', 'string'],
        'Ne' => ['bool', 'int', 'double', 'string'],
        'Gt' => ['int', 'double'],
        'Ge' => ['int', 'double'],
        'Lt' => ['int', 'double'],
        'Le' => ['int', 'double'],
        'In' => ['bool', 'int', 'double', 'string'],
        'NotIn' => ['bool', 'int', 'double', 'string'],
        'StartsWith' => ['string'],
        'EndsWith' => ['string'],
        'Contains' => ['string'],
    ];

    /**
     * The two counts a rule can compare a group of checks by.
     */
    public const AGGREGATES = ['Passed', 'Failed'];

    /**
     * The Output keys, in the order the canonical form prints them.
     */
    public const OUTPUT_KEYS = ['Name', 'Description', 'ValueType',
        'StoredValueType', 'DefaultValue', 'IsList', 'IsMandatory',
        'IsObsolete', 'Category', 'IsPopular', 'ExportValues', 'Url',
        'DisplayOrder', 'PropertyId', 'VendorIds', 'Dependencies', 'Values'];

    private const TOP_LEVEL_KEYS = ['Format', 'Name', 'Version', 'Deprecated',
        'DeprecationNote', 'Output', 'Checks', 'Rules'];

    private const OUTPUT_BOOLEANS = ['IsMandatory', 'IsObsolete', 'IsPopular',
        'ExportValues'];

    private const OUTPUT_FIELDS = [
        'Category' => 'string',
        'Url' => 'string',
        'StoredValueType' => 'string',
        'DisplayOrder' => 'integer',
        'PropertyId' => 'integer',
        'VendorIds' => 'array',
        'Dependencies' => 'array',
    ];

    private const IDENTIFIER = '^[A-Za-z][A-Za-z0-9]*$';

    private const SOURCE_PROPERTY =
        '/^[A-Za-z][A-Za-z0-9]*\.[A-Za-z][A-Za-z0-9]*$/D';

    private const SEMANTIC_VERSION =
        '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+(?:\.[0-9A-Za-z.-]+)*)?(?:\+[0-9A-Za-z.-]+)?$/D';

    /**
     * What a condition that could not be read becomes. A fault has been
     * recorded by then, so the script is never built.
     */
    private const FAULTED = ['kind' => 'all', 'items' => []];

    private ?string $script;

    private string $source;

    private \stdClass $document;

    /**
     * @var array<ScriptFault>
     */
    private array $faults = [];

    /**
     * Each source property by its lower case name, with the type it was
     * first inferred as and the path that inferred it.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $seen = [];

    /**
     * @var array<int, array<string, string>>
     */
    private array $properties = [];

    private function __construct(?string $script, string $source)
    {
        $this->script = $script;
        $this->source = $source;
    }

    /**
     * Parses and validates script text.
     *
     * @param string $text the script, as YAML or as JSON
     * @param null|string $name the file name without its extension, which
     *     the script's Name must equal, or null where there is none
     * @param string $source where the script came from, for the messages
     * @throws ScriptValidationException listing every fault in the script
     */
    public static function read(
        string $text,
        ?string $name = null,
        string $source = 'code'
    ): DerivedScript {
        $validator = new ScriptValidator($name, $source);
        try {
            $validator->document = ScriptParser::parse($text);
        } catch (ScriptParseException $e) {
            throw new ScriptValidationException([new ScriptFault(
                $name,
                $source,
                '',
                $e->parsedLine,
                $e->getMessage()
            )]);
        }
        $script = $validator->build();
        if ($validator->faults) {
            throw new ScriptValidationException($validator->faults);
        }

        return $script;
    }

    private function fault(string $path, string $message): void
    {
        $this->faults[] = new ScriptFault(
            $this->script,
            $this->source,
            $path,
            null,
            $message
        );
    }

    /**
     * The keys of a mapping that are not in the allowed list.
     *
     * @param array<string> $allowed
     * @return array<string>
     */
    private static function unknownKeys(\stdClass $mapping, array $allowed): array
    {
        $lower = array_map('strtolower', $allowed);

        return array_values(array_filter(
            Missing::keys($mapping),
            fn (string $key) => !in_array(strtolower($key), $lower, true)
        ));
    }

    private static function isIdentifier(string $value): bool
    {
        return preg_match('/' . self::IDENTIFIER . '/D', $value) === 1;
    }

    private function build(): DerivedScript
    {
        foreach (self::unknownKeys($this->document, self::TOP_LEVEL_KEYS) as $key) {
            $this->fault($key, sprintf(
                "unknown key '%s' at the top level. Expected one of %s",
                $key,
                implode(', ', self::TOP_LEVEL_KEYS)
            ));
        }

        $script = new DerivedScript();
        $this->readFormat();
        $name = $this->readName();
        $version = $this->readVersion();
        $this->readDeprecation($script);
        $script->output = $this->readOutput();

        // Checks are read before rules so that a rule can name one.
        $checkNames = $this->readCheckNames();
        $script->checks = $this->readChecks($checkNames);
        $script->rules = $this->readRules($script->output, $checkNames);

        $script->properties = $this->properties;
        if (!isset($script->output['Dependencies'])) {
            $script->output['Dependencies'] = array_map(
                fn (array $property) => $property['name'],
                $this->properties
            );
        }
        $script->name = $name ?? '';
        $script->version = $version ?? '';
        $script->source = $this->source;

        return $script;
    }

    private function readFormat(): void
    {
        $value = Missing::key($this->document, 'Format');
        if ($value instanceof Missing) {
            $this->fault('Format', "required key 'Format' is missing");

            return;
        }
        if ($value !== 1 && $value !== 1.0) {
            $this->fault(
                'Format',
                'Format must be 1, found ' . ValueConverter::json($value)
            );
        }
    }

    private function readName(): ?string
    {
        $value = Missing::key($this->document, 'Name');
        if ($value instanceof Missing) {
            $this->fault('Name', "required key 'Name' is missing");

            return null;
        }
        if (!is_string($value)) {
            $this->fault(
                'Name',
                'Name expected a string, found ' . ValueConverter::describe($value)
            );

            return null;
        }
        if (!self::isIdentifier($value)) {
            $this->fault('Name', sprintf(
                "script name '%s' does not match the pattern %s",
                $value,
                self::IDENTIFIER
            ));

            return $value;
        }
        if ($this->script !== null && $this->script !== $value) {
            $this->fault('Name', sprintf(
                "script name '%s' must equal the file name '%s'",
                $value,
                $this->script
            ));
        }

        return $value;
    }

    private function readVersion(): ?string
    {
        $value = Missing::key($this->document, 'Version');
        if ($value instanceof Missing) {
            $this->fault('Version', "required key 'Version' is missing");

            return null;
        }
        if (!is_string($value) || preg_match(self::SEMANTIC_VERSION, $value) !== 1) {
            $this->fault(
                'Version',
                'Version expected a semantic version such as 1.0.0, found ' .
                    ValueConverter::json($value)
            );

            return null;
        }

        return $value;
    }

    private function readDeprecation(DerivedScript $script): void
    {
        $deprecated = Missing::key($this->document, 'Deprecated');
        if (!($deprecated instanceof Missing)) {
            if (!is_bool($deprecated)) {
                $this->fault(
                    'Deprecated',
                    'Deprecated expected a boolean, found ' .
                        ValueConverter::describe($deprecated)
                );
            } else {
                $script->deprecated = $deprecated;
            }
        }
        $note = Missing::key($this->document, 'DeprecationNote');
        if (!($note instanceof Missing) && !is_string($note)) {
            $this->fault(
                'DeprecationNote',
                'DeprecationNote expected a string, found ' .
                    ValueConverter::describe($note)
            );
        } elseif (!($note instanceof Missing)) {
            $script->deprecationNote = $note;
        }
        $hasNote = $script->deprecationNote !== null && $script->deprecationNote !== '';
        if ($script->deprecated && !$hasNote) {
            $this->fault(
                'DeprecationNote',
                'a deprecated script must say what to use instead in DeprecationNote'
            );
        }
        if (!$script->deprecated && $hasNote) {
            $this->fault(
                'DeprecationNote',
                'DeprecationNote is only allowed when Deprecated is true'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readOutput(): array
    {
        $raw = Missing::key($this->document, 'Output');
        $fallback = [
            'Name' => $this->script ?? 'unknown',
            'ValueType' => null,
            'IsList' => false,
            'Values' => null,
        ];
        if ($raw instanceof Missing) {
            $this->fault('Output', "required key 'Output' is missing");

            return $fallback;
        }
        if (!($raw instanceof \stdClass)) {
            $this->fault(
                'Output',
                'Output expected a mapping, found ' . ValueConverter::describe($raw)
            );

            return $fallback;
        }
        foreach (self::unknownKeys($raw, self::OUTPUT_KEYS) as $key) {
            $this->fault("Output.$key", sprintf(
                "unknown key '%s' under Output. A typo in a metadata field is " .
                'a fault rather than a value that is quietly dropped',
                $key
            ));
        }

        $output = [];

        $name = Missing::key($raw, 'Name');
        if ($name instanceof Missing) {
            $this->fault('Output.Name', "required key 'Name' is missing");
        } elseif (!is_string($name) || !self::isIdentifier($name)) {
            $this->fault('Output.Name', sprintf(
                'Output.Name %s does not match the pattern %s',
                ValueConverter::json($name),
                self::IDENTIFIER
            ));
        } else {
            $output['Name'] = $name;
        }
        if (!isset($output['Name'])) {
            $output['Name'] = $this->script ?? 'unknown';
        }

        $description = Missing::key($raw, 'Description');
        if ($description instanceof Missing) {
            $this->fault(
                'Output.Description',
                "required key 'Description' is missing. Say what the property " .
                    'asserts, not how far to trust it'
            );
        } elseif (!is_string($description) || trim($description) === '') {
            $this->fault(
                'Output.Description',
                'Output.Description expected a non empty string, found ' .
                    ValueConverter::describe($description)
            );
        } else {
            $output['Description'] = $description;
        }

        $valueType = Missing::key($raw, 'ValueType');
        $output['ValueType'] = null;
        if ($valueType instanceof Missing) {
            $this->fault('Output.ValueType', "required key 'ValueType' is missing");
        } elseif (!is_string($valueType) ||
            !in_array(strtolower($valueType), self::VALUE_TYPES, true)) {
            $this->fault('Output.ValueType', sprintf(
                "Output.ValueType '%s' is not allowed in format 1. Expected one of %s",
                ValueConverter::text($valueType),
                implode(', ', self::VALUE_TYPES)
            ));
        } else {
            $output['ValueType'] = strtolower($valueType);
        }

        $isList = Missing::key($raw, 'IsList');
        if ($isList instanceof Missing) {
            $this->fault('Output.IsList', "required key 'IsList' is missing");
        } elseif (!is_bool($isList)) {
            $this->fault(
                'Output.IsList',
                'Output.IsList expected a boolean, found ' .
                    ValueConverter::describe($isList)
            );
        } elseif ($isList) {
            $this->fault(
                'Output.IsList',
                'Output.IsList must be false in format 1. List outputs are deferred'
            );
        }
        $output['IsList'] = false;

        $output['Values'] = $this->readValues($raw, $output['ValueType']);

        foreach (self::OUTPUT_BOOLEANS as $key) {
            $value = Missing::key($raw, $key);
            if ($value instanceof Missing) {
                continue;
            }
            if (!is_bool($value)) {
                $this->fault(
                    "Output.$key",
                    "Output.$key expected a boolean, found " .
                        ValueConverter::describe($value)
                );
                continue;
            }
            $output[$key] = $value;
        }

        foreach (self::OUTPUT_FIELDS as $key => $kind) {
            $value = Missing::key($raw, $key);
            if ($value instanceof Missing) {
                continue;
            }
            if ($kind === 'string' && !is_string($value)) {
                $this->fault(
                    "Output.$key",
                    "Output.$key expected a string, found " .
                        ValueConverter::describe($value)
                );
                continue;
            }
            if ($kind === 'integer' && !ValueConverter::isWhole($value)) {
                $this->fault(
                    "Output.$key",
                    "Output.$key expected an integer, found " .
                        ValueConverter::describe($value)
                );
                continue;
            }
            if ($kind === 'array' && !is_array($value)) {
                $this->fault(
                    "Output.$key",
                    "Output.$key expected a list, found " .
                        ValueConverter::describe($value)
                );
                continue;
            }
            $output[$key] = $value;
        }

        // DefaultValue is the string form of the value, so it is checked
        // against the value type and the value list.
        $default = Missing::key($raw, 'DefaultValue');
        if (!($default instanceof Missing)) {
            if (!is_string($default)) {
                $this->fault(
                    'Output.DefaultValue',
                    'Output.DefaultValue expected a string holding the string ' .
                        'form of the value, found ' .
                        ValueConverter::describe($default)
                );
            } else {
                $output['DefaultValue'] = $default;
                $converted = null;
                if ($output['ValueType'] !== null &&
                    !ValueConverter::convertString(
                        $default,
                        $output['ValueType'],
                        $converted
                    )) {
                    $this->fault('Output.DefaultValue', sprintf(
                        "Output.DefaultValue '%s' cannot be read as %s",
                        $default,
                        $output['ValueType']
                    ));
                }
                if ($output['Values'] !== null &&
                    !self::isListedValue($output['Values'], $default)) {
                    $this->fault('Output.DefaultValue', sprintf(
                        "Output.DefaultValue '%s' is not one of the values " .
                            'listed under Output.Values',
                        $default
                    ));
                }
            }
        }

        return $output;
    }

    /**
     * @return null|array<int, array{Name: mixed, Description: ?string}>
     */
    private function readValues(\stdClass $raw, ?string $valueType): ?array
    {
        $values = Missing::key($raw, 'Values');
        if ($values instanceof Missing) {
            return null;
        }
        if (!is_array($values)) {
            $this->fault(
                'Output.Values',
                'Output.Values expected a list, found ' .
                    ValueConverter::describe($values)
            );

            return null;
        }
        if ($valueType !== null && $valueType !== 'string' && $valueType !== 'int') {
            $this->fault(
                'Output.Values',
                'Output.Values is only allowed where ValueType is string or ' .
                    "int, not $valueType"
            );

            return null;
        }
        $list = [];
        foreach ($values as $index => $entry) {
            $path = "Output.Values[$index]";
            if (!($entry instanceof \stdClass)) {
                $this->fault(
                    $path,
                    'a value expected a mapping of Name and Description, found ' .
                        ValueConverter::describe($entry)
                );
                continue;
            }
            foreach (self::unknownKeys($entry, ['Name', 'Description']) as $key) {
                $this->fault("$path.$key", sprintf(
                    "unknown key '%s' in a value. Expected Name and Description",
                    $key
                ));
            }
            $name = Missing::key($entry, 'Name');
            if (!is_string($name) && !is_int($name) && !is_float($name)) {
                $this->fault("$path.Name", 'a value must have a Name');
                continue;
            }
            $description = Missing::key($entry, 'Description');
            if (!($description instanceof Missing) && !is_string($description)) {
                $this->fault(
                    "$path.Description",
                    'a value Description expected a string, found ' .
                        ValueConverter::describe($description)
                );
                $description = null;
            }
            $list[] = [
                'Name' => $name,
                'Description' => $description instanceof Missing ? null : $description,
            ];
        }
        $seen = [];
        foreach ($list as $entry) {
            $key = ValueConverter::text($entry['Name']);
            if (isset($seen[$key])) {
                $this->fault(
                    'Output.Values',
                    "the value '$key' is listed more than once"
                );
            }
            $seen[$key] = true;
        }

        return $list;
    }

    /**
     * @param array<int, array{Name: mixed, Description: ?string}> $values
     * @param mixed $value
     */
    private static function isListedValue(array $values, $value): bool
    {
        $text = ValueConverter::text($value);
        foreach ($values as $entry) {
            if (ValueConverter::text($entry['Name']) === $text) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function readCheckNames(): array
    {
        $raw = Missing::key($this->document, 'Checks');
        if ($raw instanceof Missing) {
            return [];
        }
        if (!($raw instanceof \stdClass)) {
            $this->fault(
                'Checks',
                'Checks expected a mapping of names to conditions, found ' .
                    ValueConverter::describe($raw)
            );

            return [];
        }
        $names = [];
        foreach (Missing::keys($raw) as $name) {
            if (!self::isIdentifier($name)) {
                $this->fault("Checks.$name", sprintf(
                    "check name '%s' does not match the pattern %s",
                    $name,
                    self::IDENTIFIER
                ));
                continue;
            }
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param array<string> $checkNames
     * @return array<int, array{name: string, condition: array<string, mixed>}>
     */
    private function readChecks(array $checkNames): array
    {
        $raw = Missing::key($this->document, 'Checks');
        if (!($raw instanceof \stdClass)) {
            return [];
        }
        $checks = [];
        foreach ($checkNames as $name) {
            $checks[] = [
                'name' => $name,
                'condition' => $this->readCondition(
                    $raw->{$name},
                    "Checks.$name",
                    $checkNames
                ),
            ];
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $output
     * @param array<string> $checkNames
     * @return array<int, array{when: ?array<string, mixed>, value: mixed}>
     */
    private function readRules(array $output, array $checkNames): array
    {
        $raw = Missing::key($this->document, 'Rules');
        if ($raw instanceof Missing) {
            $this->fault('Rules', "required key 'Rules' is missing");

            return [];
        }
        if (!is_array($raw)) {
            $this->fault(
                'Rules',
                'Rules expected a list, found ' . ValueConverter::describe($raw)
            );

            return [];
        }
        if (count($raw) === 0) {
            $this->fault('Rules', 'Rules must hold at least one rule');

            return [];
        }
        // Every script ends in an Else, so a script always chooses a value
        // once its source properties have been read.
        if (!Missing::has($raw[count($raw) - 1], 'Else')) {
            $this->fault(
                'Rules',
                'the last rule must be an Else, which is what a script falls ' .
                    'back to when no earlier rule matched'
            );
        }
        $rules = [];
        foreach ($raw as $index => $entry) {
            $path = "Rules[$index]";
            if (!($entry instanceof \stdClass)) {
                $this->fault(
                    $path,
                    'a rule expected a mapping, found ' .
                        ValueConverter::describe($entry)
                );
                continue;
            }
            foreach (self::unknownKeys($entry, ['When', 'Then', 'Else']) as $key) {
                $this->fault("$path.$key", sprintf(
                    "unknown key '%s' in a rule. Expected When and Then, or Else",
                    $key
                ));
            }
            $hasWhen = Missing::has($entry, 'When');
            $hasThen = Missing::has($entry, 'Then');
            $hasElse = Missing::has($entry, 'Else');
            $isLast = $index === count($raw) - 1;

            if ($hasElse && $hasWhen) {
                $this->fault(
                    $path,
                    'a rule has both When and Else. A rule is either When ' .
                        'with Then, or Else on its own'
                );
                continue;
            }
            if ($hasElse && !$isLast) {
                $this->fault($path, 'Else is only allowed on the last rule');
                continue;
            }
            if (!$hasElse && !$hasWhen) {
                $this->fault($path, 'a rule needs a When, or an Else on the last rule');
                continue;
            }
            if ($hasWhen && !$hasThen) {
                $this->fault($path, 'a rule with When needs a Then');
                continue;
            }

            $value = $this->readRuleValue(
                $output,
                Missing::key($entry, $hasElse ? 'Else' : 'Then'),
                $hasElse ? "$path.Else" : "$path.Then"
            );
            $when = $hasWhen
                ? $this->readCondition(
                    Missing::key($entry, 'When'),
                    "$path.When",
                    $checkNames
                )
                : null;
            $rules[] = ['when' => $when, 'value' => $value];
        }

        return $rules;
    }

    /**
     * Reads a Then or an Else, which is a literal of Output.ValueType and
     * must be one of Output.Values where that list is given.
     *
     * @param array<string, mixed> $output
     * @param mixed $raw
     * @return mixed the value as the output type
     */
    private function readRuleValue(array $output, $raw, string $path)
    {
        $valueType = $output['ValueType'];
        if ($raw === null || $raw instanceof Missing) {
            $this->fault(
                $path,
                'a rule value is a null literal, which format 1 does not allow'
            );

            return null;
        }
        if (is_array($raw) || is_object($raw)) {
            $this->fault(
                $path,
                'a rule value is a literal of the output value type, found ' .
                    ValueConverter::describe($raw)
            );

            return null;
        }
        if ($valueType !== null) {
            $literalType = ValueConverter::inferType($raw);
            // A whole number written without a decimal point reads as a
            // double.
            if ($literalType !== $valueType &&
                !($literalType === 'int' && $valueType === 'double')) {
                $this->fault($path, sprintf(
                    'expected a %s to match Output.ValueType, found %s',
                    $valueType,
                    ValueConverter::describe($raw)
                ));

                return null;
            }
        }
        if ($output['Values'] !== null && !self::isListedValue($output['Values'], $raw)) {
            $this->fault($path, sprintf(
                "'%s' is not one of the values listed under Output.Values (%s)",
                ValueConverter::text($raw),
                implode(', ', array_map(
                    fn (array $entry) => ValueConverter::text($entry['Name']),
                    $output['Values']
                ))
            ));

            return null;
        }

        return $valueType === null ? $raw : ValueConverter::normalise($raw, $valueType);
    }

    /**
     * @param mixed $raw
     * @param array<string> $checkNames
     * @return array<string, mixed>
     */
    private function readCondition($raw, string $path, array $checkNames): array
    {
        if (!($raw instanceof \stdClass)) {
            $this->fault(
                $path,
                'a condition expected a mapping, found ' .
                    ValueConverter::describe($raw)
            );

            return self::FAULTED;
        }
        $keys = Missing::keys($raw);
        if (count($keys) === 0) {
            $this->fault($path, 'a condition is empty');

            return self::FAULTED;
        }
        $lower = array_map('strtolower', $keys);

        if (in_array('property', $lower, true)) {
            return $this->readComparison($raw, $path, $keys);
        }
        if (in_array('check', $lower, true)) {
            return $this->readCheckReference($raw, $path, $keys, $checkNames);
        }
        if (in_array('passed', $lower, true) || in_array('failed', $lower, true)) {
            return $this->readAggregate($raw, $path, $keys, $checkNames);
        }
        if (in_array('all', $lower, true) || in_array('any', $lower, true)) {
            $which = in_array('all', $lower, true) ? 'All' : 'Any';
            if (count($keys) !== 1) {
                $this->fault($path, sprintf(
                    '%s must be the only key of its condition, found %s',
                    $which,
                    implode(', ', $keys)
                ));
            }
            $items = Missing::key($raw, $which);
            if (!is_array($items)) {
                $this->fault("$path.$which", sprintf(
                    '%s expected a list of conditions, found %s',
                    $which,
                    ValueConverter::describe($items)
                ));

                return self::FAULTED;
            }
            if (count($items) === 0) {
                $this->fault(
                    "$path.$which",
                    "$which must list at least one condition"
                );

                return self::FAULTED;
            }
            $read = [];
            foreach ($items as $index => $item) {
                $read[] = $this->readCondition(
                    $item,
                    "$path.{$which}[$index]",
                    $checkNames
                );
            }

            return ['kind' => strtolower($which), 'items' => $read];
        }
        if (in_array('not', $lower, true)) {
            if (count($keys) !== 1) {
                $this->fault(
                    $path,
                    'Not must be the only key of its condition, found ' .
                        implode(', ', $keys)
                );
            }

            return [
                'kind' => 'not',
                'item' => $this->readCondition(
                    Missing::key($raw, 'Not'),
                    "$path.Not",
                    $checkNames
                ),
            ];
        }
        $this->fault(
            $path,
            'a condition must be a comparison, a Check reference, an aggregate, ' .
                'All, Any or Not. Found the keys ' . implode(', ', $keys)
        );

        return self::FAULTED;
    }

    /**
     * The canonical name of an operator, matched without regard to case.
     */
    private static function operatorName(string $key): ?string
    {
        foreach (array_keys(self::OPERATORS) as $operator) {
            if (strcasecmp($operator, $key) === 0) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    private function readComparison(\stdClass $raw, string $path, array $keys): array
    {
        $propertyKey = null;
        foreach ($keys as $key) {
            if (strtolower($key) === 'property') {
                $propertyKey = $key;
                break;
            }
        }
        $property = $raw->{$propertyKey};
        $operatorKeys = array_values(array_filter(
            $keys,
            fn (string $key) => $key !== $propertyKey
        ));
        $operators = implode(', ', array_keys(self::OPERATORS));

        if (!is_string($property) || preg_match(self::SOURCE_PROPERTY, $property) !== 1) {
            $this->fault("$path.Property", sprintf(
                "'%s' is not a source property. Write it as " .
                    'elementKey.PropertyName, for example device.IsCrawler',
                ValueConverter::text($property)
            ));

            return self::FAULTED;
        }
        if (count($operatorKeys) === 0) {
            $this->fault($path, sprintf(
                "a comparison on '%s' has no operator. Expected exactly one of %s",
                $property,
                $operators
            ));

            return self::FAULTED;
        }
        $known = [];
        foreach ($operatorKeys as $key) {
            if (self::operatorName($key) === null) {
                $this->fault(
                    $path,
                    "unknown operator '$key', expected one of $operators"
                );
            } else {
                $known[] = $key;
            }
        }
        if (count($known) === 0) {
            return self::FAULTED;
        }
        if (count($operatorKeys) > 1) {
            $this->fault(
                $path,
                'a condition takes exactly one operator, found ' .
                    implode(', ', $operatorKeys)
            );

            return self::FAULTED;
        }
        $op = (string) self::operatorName($known[0]);
        $operand = $raw->{$known[0]};

        if ($operand === null) {
            $this->fault(
                "$path.$op",
                'a null literal is not allowed. Give the value to compare against'
            );

            return self::FAULTED;
        }

        if ($op === 'In' || $op === 'NotIn') {
            if (!is_array($operand)) {
                $this->fault(
                    "$path.$op",
                    "$op expects a list of values, found " .
                        ValueConverter::describe($operand)
                );

                return self::FAULTED;
            }
            if (count($operand) === 0) {
                $this->fault("$path.$op", "$op expects a non empty list");

                return self::FAULTED;
            }
            if (in_array(null, $operand, true)) {
                $this->fault("$path.$op", 'a null literal is not allowed in a list');

                return self::FAULTED;
            }
            $memberTypes = array_map(
                fn ($member) => ValueConverter::inferType($member),
                $operand
            );
            $first = $memberTypes[0];
            // int and double may sit together, and the list reads as double.
            $numeric = true;
            $same = true;
            foreach ($memberTypes as $type) {
                $numeric = $numeric && ($type === 'int' || $type === 'double');
                $same = $same && $type === $first;
            }
            if (!$numeric && !$same) {
                $this->fault(
                    "$path.$op",
                    'every member of a list must be of the same type, found ' .
                        implode(', ', array_map(
                            fn (?string $type) => $type ?? 'null',
                            $memberTypes
                        ))
                );

                return self::FAULTED;
            }
            $type = $numeric
                ? (in_array('double', $memberTypes, true) ? 'double' : 'int')
                : $first;
        } else {
            $type = ValueConverter::inferType($operand);
            if ($type === null) {
                $this->fault(
                    "$path.$op",
                    'the literal ' . ValueConverter::json($operand) .
                        ' has no type format 1 knows'
                );

                return self::FAULTED;
            }
        }

        if ($type === null || !in_array($type, self::OPERATORS[$op], true)) {
            $this->fault($path, sprintf(
                "operator '%s' is not allowed on type %s. It is allowed on %s",
                $op,
                $type ?? 'null',
                implode(', ', self::OPERATORS[$op])
            ));

            return self::FAULTED;
        }

        $value = is_array($operand)
            ? array_map(
                fn ($member) => ValueConverter::normalise($member, $type),
                $operand
            )
            : ValueConverter::normalise($operand, $type);

        return [
            'kind' => 'compare',
            'slot' => $this->useProperty($property, $type, $path),
            'propertyName' => $property,
            'op' => $op,
            'operand' => $operand,
            'value' => $value,
            'type' => $type,
        ];
    }

    /**
     * @param array<string> $keys
     * @param array<string> $checkNames
     * @return array<string, mixed>
     */
    private function readCheckReference(
        \stdClass $raw,
        string $path,
        array $keys,
        array $checkNames
    ): array {
        if (count($keys) !== 1) {
            $this->fault(
                $path,
                'Check must be the only key of its condition, found ' .
                    implode(', ', $keys)
            );
        }
        $name = Missing::key($raw, 'Check');
        if (!is_string($name)) {
            $this->fault(
                "$path.Check",
                'Check expected the name of a check, found ' .
                    ValueConverter::describe($name)
            );

            return self::FAULTED;
        }
        // A check name is matched exactly as written.
        $index = array_search($name, $checkNames, true);
        if ($index === false) {
            $this->fault("$path.Check", sprintf(
                "check '%s' is not defined. The checks are %s",
                $name,
                count($checkNames) === 0 ? '(none)' : implode(', ', $checkNames)
            ));

            return self::FAULTED;
        }

        return ['kind' => 'check', 'name' => $name, 'index' => $index];
    }

    /**
     * @param array<string> $keys
     * @param array<string> $checkNames
     * @return array<string, mixed>
     */
    private function readAggregate(
        \stdClass $raw,
        string $path,
        array $keys,
        array $checkNames
    ): array {
        $aggregateKeys = array_values(array_filter(
            $keys,
            fn (string $key) => in_array(strtolower($key), ['passed', 'failed'], true)
        ));
        if (count($aggregateKeys) > 1) {
            $this->fault(
                $path,
                'an aggregate condition takes one of Passed, Failed, found ' .
                    implode(', ', $aggregateKeys)
            );

            return self::FAULTED;
        }
        $agg = strtolower($aggregateKeys[0]) === 'passed' ? 'Passed' : 'Failed';
        $group = $this->readGroup(
            $raw->{$aggregateKeys[0]},
            "$path.$agg",
            $checkNames
        );

        $operatorKeys = array_values(array_filter(
            $keys,
            fn (string $key) => $key !== $aggregateKeys[0]
        ));
        if (count($operatorKeys) === 0) {
            $this->fault(
                $path,
                'an aggregate condition has no operator. Expected exactly one ' .
                    'of Eq, Ne, Gt, Ge, Lt, Le'
            );

            return self::FAULTED;
        }
        if (count($operatorKeys) > 1) {
            $this->fault(
                $path,
                'a condition takes exactly one operator, found ' .
                    implode(', ', $operatorKeys)
            );

            return self::FAULTED;
        }
        $op = self::operatorName($operatorKeys[0]);
        if ($op === null) {
            $this->fault($path, sprintf(
                "unknown operator '%s', expected one of Eq, Ne, Gt, Ge, Lt, Le",
                $operatorKeys[0]
            ));

            return self::FAULTED;
        }
        if (!in_array('int', self::OPERATORS[$op], true)) {
            $this->fault(
                $path,
                "operator '$op' is not allowed on a count, which is an int"
            );

            return self::FAULTED;
        }

        // A count is compared with a whole number and with nothing else.
        $operand = $raw->{$operatorKeys[0]};
        if (!ValueConverter::isWhole($operand)) {
            $this->fault(
                "$path.$op",
                'an aggregate is compared with a whole number, found ' .
                    ValueConverter::describe($operand)
            );

            return self::FAULTED;
        }

        return [
            'kind' => 'aggregate',
            'agg' => $agg,
            'group' => $group,
            'op' => $op,
            'operand' => $operand,
            // A count is a PHP int, so the operand is one too wherever it
            // fits, which keeps Eq and Ne exact.
            'value' => is_float($operand) && abs($operand) < 9.0e15
                ? (int) $operand
                : $operand,
        ];
    }

    /**
     * A group is the word `Checks`, meaning every check, or a list of check
     * names. It becomes a list of check indexes, or null for every check.
     *
     * @param mixed $raw
     * @param array<string> $checkNames
     * @return null|array<int, int>
     */
    private function readGroup($raw, string $path, array $checkNames): ?array
    {
        if (is_string($raw)) {
            if (strtolower($raw) === 'checks') {
                return null;
            }
            $this->fault($path, sprintf(
                'a group is the word Checks, meaning every check, or a list of ' .
                    "check names. Found '%s'",
                $raw
            ));

            return [];
        }
        if (is_array($raw)) {
            $indexes = [];
            foreach ($raw as $name) {
                if (!is_string($name)) {
                    $this->fault(
                        $path,
                        'a group lists check names, found ' .
                            ValueConverter::describe($name)
                    );
                    continue;
                }
                $index = array_search($name, $checkNames, true);
                if ($index === false) {
                    $this->fault($path, sprintf(
                        "check '%s' is not defined. The checks are %s",
                        $name,
                        count($checkNames) === 0 ? '(none)' : implode(', ', $checkNames)
                    ));
                    continue;
                }
                $indexes[] = $index;
            }

            return $indexes;
        }
        $this->fault(
            $path,
            'a group is the word Checks or a list of check names, found ' .
                ValueConverter::describe($raw)
        );

        return [];
    }

    /**
     * Records that a condition names a source property and the type its
     * literal infers, and returns the slot the property is read into.
     */
    private function useProperty(string $name, string $type, string $path): int
    {
        $key = strtolower($name);
        if (!isset($this->seen[$key])) {
            $slot = count($this->properties);
            $this->seen[$key] = ['type' => $type, 'path' => $path, 'slot' => $slot];
            $dot = strpos($name, '.');
            $this->properties[] = [
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'elementKey' => substr($name, 0, (int) $dot),
                'propertyName' => substr($name, (int) $dot + 1),
            ];

            return $slot;
        }
        $entry = $this->seen[$key];
        if ($entry['type'] !== $type) {
            $this->fault($path, sprintf(
                "'%s' is inferred as %s here but was already inferred as %s at " .
                    '%s. Every use of a property must infer the same type',
                $name,
                $type,
                $entry['type'],
                $entry['path']
            ));
        }

        return $entry['slot'];
    }
}
