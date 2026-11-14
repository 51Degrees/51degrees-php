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
 * Stands for a key a mapping does not have, which is different from a key
 * written with a null value.
 *
 * @internal
 */
final class Missing
{
    private static ?Missing $instance = null;

    private function __construct()
    {
    }

    public static function get(): Missing
    {
        return self::$instance ??= new Missing();
    }

    /**
     * Reads a key from a mapping without regard to case.
     *
     * @param mixed $mapping
     * @return mixed the value, or the Missing instance where the mapping
     *     has no such key or is not a mapping
     */
    public static function key($mapping, string $name)
    {
        if (!($mapping instanceof \stdClass)) {
            return self::get();
        }
        foreach (get_object_vars($mapping) as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return self::get();
    }

    /**
     * Whether a mapping has a key, compared without regard to case.
     *
     * @param mixed $mapping
     */
    public static function has($mapping, string $name): bool
    {
        return !(self::key($mapping, $name) instanceof Missing);
    }

    /**
     * The keys of a mapping as written.
     *
     * @return array<string>
     */
    public static function keys(\stdClass $mapping): array
    {
        return array_map('strval', array_keys(get_object_vars($mapping)));
    }

    /**
     * Whether a value is a list rather than a mapping or a scalar.
     *
     * @param mixed $value
     */
    public static function isList($value): bool
    {
        return is_array($value) &&
            ($value === [] || array_keys($value) === range(0, count($value) - 1));
    }
}
