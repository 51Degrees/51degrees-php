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
 * Prints a script as canonical JSON, which is what the element writes to
 * the debug log when a pipeline is built, so anyone holding the log can see
 * exactly what was evaluated without the file.
 *
 * The form is PascalCase keys in the order of the format reference, two
 * space indent, the inferred type of every source property and the
 * computed Dependencies, as tools/canonical.mjs in the derived-properties
 * repository prints it. A YAML script and the JSON that mirrors it print
 * the same text.
 */
final class CanonicalJson
{
    /**
     * @return string canonical JSON, without a trailing newline
     */
    public static function write(DerivedScript $script): string
    {
        $text = json_encode(
            self::document($script),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
        );

        // PHP indents by four spaces and the canonical form by two.
        return (string) preg_replace_callback(
            '/^( +)/m',
            fn (array $match) => str_repeat(' ', intdiv(strlen($match[1]), 2)),
            $text
        );
    }

    private static function document(DerivedScript $script): \stdClass
    {
        $result = new \stdClass();
        $result->Format = $script->format;
        $result->Name = $script->name;
        $result->Version = $script->version;
        if ($script->deprecated) {
            $result->Deprecated = true;
            $result->DeprecationNote = $script->deprecationNote;
        }
        $result->Output = self::output($script->output);

        // The inferred type of every source property, which the file does
        // not carry because it comes from the literals compared against.
        $result->Properties = new \stdClass();
        foreach ($script->properties as $property) {
            $result->Properties->{$property['name']} = (object) ['Type' => $property['type']];
        }

        if ($script->checks) {
            $result->Checks = new \stdClass();
            foreach ($script->checks as $check) {
                $result->Checks->{$check['name']} =
                    self::condition($check['condition'], $script);
            }
        }

        $result->Rules = array_map(
            fn (array $rule) => $rule['when'] === null
                ? (object) ['Else' => self::literal($rule['value'])]
                : (object) [
                    'When' => self::condition($rule['when'], $script),
                    'Then' => self::literal($rule['value']),
                ],
            $script->rules
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $output
     */
    private static function output(array $output): \stdClass
    {
        $result = new \stdClass();
        foreach (ScriptValidator::OUTPUT_KEYS as $key) {
            if ($key === 'Values' || !isset($output[$key])) {
                continue;
            }
            $result->{$key} = $output[$key];
        }
        if (isset($output['Values'])) {
            $result->Values = array_map(
                fn (array $value) => $value['Description'] === null
                    ? (object) ['Name' => self::literal($value['Name'])]
                    : (object) [
                        'Name' => self::literal($value['Name']),
                        'Description' => $value['Description'],
                    ],
                $output['Values']
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $condition
     */
    private static function condition(array $condition, DerivedScript $script): \stdClass
    {
        switch ($condition['kind']) {
            case 'compare':
                return (object) [
                    'Property' => $condition['propertyName'],
                    $condition['op'] => self::literal($condition['operand']),
                ];
            case 'check':
                return (object) ['Check' => $condition['name']];
            case 'aggregate':
                return (object) [
                    $condition['agg'] => $condition['group'] === null
                        ? 'Checks'
                        : array_map(
                            fn (int $index) => $script->checks[$index]['name'],
                            $condition['group']
                        ),
                    $condition['op'] => self::literal($condition['operand']),
                ];
            case 'all':
            case 'any':
                return (object) [
                    ucfirst($condition['kind']) => array_map(
                        fn (array $item) => self::condition($item, $script),
                        $condition['items']
                    ),
                ];
            case 'not':
                return (object) ['Not' => self::condition($condition['item'], $script)];
            default:
                throw new \LogicException(
                    "unknown condition kind '" . $condition['kind'] . "'"
                );
        }
    }

    /**
     * A whole number prints without a decimal point, as the reference
     * tools print it, because JSON has one number type.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function literal($value)
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::literal($item), $value);
        }
        if (is_float($value) && ValueConverter::isWhole($value) && abs($value) < 9.0e15) {
            return (int) $value;
        }

        return $value;
    }
}
