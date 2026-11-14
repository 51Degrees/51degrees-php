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
 * The types format 1 knows, how a literal in a script infers one, and how a
 * source value is read as one.
 *
 * Values are never coerced loosely, so `N/A`, `Unknown` and the empty
 * string never become false or zero.
 */
final class ValueConverter
{
    public const BOOL = 'bool';
    public const INT = 'int';
    public const DOUBLE = 'double';
    public const STRING = 'string';

    /**
     * `int` is a signed 32 bit whole number in every language, so a script
     * gives one answer everywhere.
     */
    public const INT_MIN = -2147483648;
    public const INT_MAX = 2147483647;

    /**
     * The type a literal in a script infers, or null for a literal format 1
     * does not know. A number infers from its value rather than from how it
     * was written, so 8, 8.0 and 8e0 all infer `int`, and a whole number
     * outside the `int` range infers `double`.
     *
     * @param mixed $literal
     */
    public static function inferType($literal): ?string
    {
        if (is_bool($literal)) {
            return self::BOOL;
        }
        if (is_int($literal) || is_float($literal)) {
            return self::isInt32($literal) ? self::INT : self::DOUBLE;
        }
        if (is_string($literal)) {
            return self::STRING;
        }

        return null;
    }

    /**
     * Whether a number is a whole number the type `int` can hold.
     *
     * @param mixed $value
     */
    public static function isInt32($value): bool
    {
        if (is_int($value)) {
            return $value >= self::INT_MIN && $value <= self::INT_MAX;
        }

        return is_float($value) &&
            is_finite($value) &&
            floor($value) === $value &&
            $value >= self::INT_MIN &&
            $value <= self::INT_MAX;
    }

    /**
     * Whether a number is a whole number of any size.
     *
     * @param mixed $value
     */
    public static function isWhole($value): bool
    {
        return is_int($value) ||
            (is_float($value) && is_finite($value) && floor($value) === $value);
    }

    /**
     * A literal of the inferred type in the form the comparisons use, so an
     * `int` is a PHP int and a `double` a PHP float.
     *
     * @param mixed $literal
     * @return mixed
     */
    public static function normalise($literal, string $type)
    {
        switch ($type) {
            case self::INT:
                return (int) $literal;
            case self::DOUBLE:
                return (float) $literal;
            default:
                return $literal;
        }
    }

    /**
     * Reads a source value, which arrives either as its native type or as
     * a string, as the type the script inferred.
     *
     * @param mixed $raw
     * @param mixed $value set to the value read where the result is true
     */
    public static function convert($raw, string $type, &$value): bool
    {
        if (is_string($raw)) {
            return self::convertString($raw, $type, $value);
        }
        switch ($type) {
            case self::BOOL:
                if (is_bool($raw)) {
                    $value = $raw;

                    return true;
                }

                return false;
            case self::INT:
                if (self::isInt32($raw)) {
                    $value = (int) $raw;

                    return true;
                }

                return false;
            case self::DOUBLE:
                if (is_int($raw) || (is_float($raw) && is_finite($raw))) {
                    $value = (float) $raw;

                    return true;
                }

                return false;
            case self::STRING:
                if (is_bool($raw)) {
                    $value = $raw ? 'True' : 'False';

                    return true;
                }
                if (is_int($raw) || is_float($raw)) {
                    $value = self::text($raw);

                    return true;
                }

                return false;
            default:
                return false;
        }
    }

    /**
     * Reads the string form of a value as the type given.
     *
     * @param mixed $value set to the value read where the result is true
     */
    public static function convertString(string $raw, string $type, &$value): bool
    {
        switch ($type) {
            case self::BOOL:
                $trimmed = strtolower(trim($raw));
                if ($trimmed === 'true' || $trimmed === 'false') {
                    $value = $trimmed === 'true';

                    return true;
                }

                return false;
            case self::INT:
                $trimmed = trim($raw);
                if (preg_match('/^[+-]?[0-9]+$/', $trimmed) !== 1) {
                    return false;
                }
                // Compared as a float first, so a string too long for a
                // PHP int cannot wrap round into the range.
                $number = (float) $trimmed;
                if ($number < self::INT_MIN || $number > self::INT_MAX) {
                    return false;
                }
                $value = (int) $trimmed;

                return true;
            case self::DOUBLE:
                $trimmed = trim($raw);
                if (preg_match(
                    '/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/',
                    $trimmed
                ) !== 1) {
                    return false;
                }
                $number = (float) $trimmed;
                if (!is_finite($number)) {
                    return false;
                }
                $value = $number;

                return true;
            case self::STRING:
                $value = $raw;

                return true;
            default:
                return false;
        }
    }

    /**
     * The plain text of a value, where a boolean is `true` or `false` and a
     * whole number has no decimal point. This is how values are compared
     * by their text, as in `Output.Values` and in the cases.
     *
     * @param mixed $value
     */
    public static function text($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_float($value) && self::isWhole($value) && abs($value) < 1e15) {
            return (string) (int) $value;
        }
        if ($value === null) {
            return 'null';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return self::json($value);
    }

    /**
     * A source value as a message shows it, where a boolean is `True` or
     * `False`.
     *
     * @param mixed $raw
     */
    public static function display($raw): string
    {
        if (is_bool($raw)) {
            return $raw ? 'True' : 'False';
        }

        return self::text($raw);
    }

    /**
     * A value written as JSON, as a fault message quotes a literal.
     *
     * @param mixed $value
     */
    public static function json($value): string
    {
        if (is_float($value) && self::isWhole($value) && abs($value) < 1e15) {
            return (string) (int) $value;
        }
        $text = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
                JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $text === false ? 'null' : $text;
    }

    /**
     * How a fault message describes a value that has the wrong type.
     *
     * @param mixed $value
     */
    public static function describe($value): string
    {
        if ($value instanceof Missing) {
            return 'nothing';
        }
        if ($value === null) {
            return 'a null literal';
        }
        if (is_array($value)) {
            return 'a list';
        }
        if (is_bool($value)) {
            return 'a boolean';
        }
        if (is_int($value) || is_float($value)) {
            return self::isWhole($value) ? 'an integer' : 'a number';
        }
        if (is_string($value)) {
            return 'a string (' . self::json($value) . ')';
        }
        if (is_object($value)) {
            return 'a mapping';
        }

        return gettype($value);
    }
}
