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

namespace fiftyone\pipeline\devicedetection\tests\classes;

/**
 * Reads a YAML evidence file, such as 20000 Evidence Records.yml, which is a
 * stream of YAML documents each holding one flat map of evidence key to
 * value. A value is single quoted, or double quoted, where YAML needs it.
 */
final class EvidenceRecords
{
    /**
     * The records in the file, each as its evidence keys and values, in the
     * order the file gives them. An empty record is left out.
     *
     * @param string $path The YAML evidence file
     * @return array<int, array<string, string>>
     */
    public static function read(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $records = [];
        $record = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '---' || $line === '...') {
                    if ($record !== []) {
                        $records[] = $record;
                    }
                    $record = [];
                    continue;
                }
                // A key never holds a space, so the first ': ' ends it. A
                // line with none, such as '{}' for an empty record, holds no
                // evidence.
                $at = strpos($line, ': ');
                if ($at !== false) {
                    $record[substr($line, 0, $at)] = self::scalar(
                        substr($line, $at + 2)
                    );
                }
            }
        } finally {
            fclose($handle);
        }
        if ($record !== []) {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * The values of one evidence key, from the records that have it, up to
     * the number given.
     *
     * @param string $path The YAML evidence file
     * @param string $key The evidence key, such as header.user-agent
     * @param int $limit The most values to give
     * @return array<int, string>
     */
    public static function values(string $path, string $key, int $limit): array
    {
        $values = [];
        foreach (self::read($path) as $record) {
            if (count($values) >= $limit) {
                break;
            }
            if (isset($record[$key]) && $record[$key] !== '') {
                $values[] = $record[$key];
            }
        }

        return $values;
    }

    private static function scalar(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }
}
