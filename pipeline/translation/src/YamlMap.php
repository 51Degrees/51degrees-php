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

namespace fiftyone\pipeline\translation;

/**
 * Minimal parser for the flat "key: value" YAML maps used by the country
 * translation files. The files contain one mapping per line and no nesting,
 * so a full YAML library is not required. Insertion order is preserved
 * (PHP associative arrays are ordered), which matters because the country
 * code file order defines the order of all known countries.
 */
class YamlMap
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $result = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $key = self::unquote($key);
            $value = self::unquote($value);
            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Parse a file, returning an empty map if it cannot be read.
     *
     * @return array<string, string>
     */
    public static function parseFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $content = file_get_contents($path);

        return $content === false ? [] : self::parse($content);
    }

    private static function unquote(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $len - 2);
            }
        }

        return $value;
    }
}
