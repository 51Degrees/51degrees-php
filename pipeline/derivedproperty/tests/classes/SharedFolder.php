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

namespace fiftyone\pipeline\derivedproperty\tests\classes;

use Symfony\Component\Yaml\Yaml;

/**
 * Finds the derived-properties repository the shared cases come from, which
 * is the submodule at the root of this repository, by searching upward from
 * the tests for a folder of that name holding a tests folder.
 */
final class SharedFolder
{
    public const NAME = 'derived-properties';

    /**
     * @throws \RuntimeException the folder is not there
     */
    public static function path(): string
    {
        $directory = __DIR__;
        while (true) {
            $candidate = $directory . DIRECTORY_SEPARATOR . self::NAME;
            if (is_dir($candidate . DIRECTORY_SEPARATOR . 'tests')) {
                return $candidate;
            }
            $parent = dirname($directory);
            if ($parent === $directory) {
                throw new \RuntimeException(sprintf(
                    "No folder named '%s' with a tests folder was found " .
                        "above %s. Run 'git submodule update --init %s' at " .
                        'the repository root.',
                    self::NAME,
                    __DIR__,
                    self::NAME
                ));
            }
            $directory = $parent;
        }
    }

    /**
     * The files in a folder of the shared repository whose names match a
     * pattern, in ordinal order.
     *
     * @return array<string>
     */
    public static function files(string $folder, string $pattern): array
    {
        $directory = self::path() . DIRECTORY_SEPARATOR . $folder;
        $files = [];
        foreach (scandir($directory) ?: [] as $entry) {
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) && preg_match($pattern, $entry) === 1) {
                $files[] = $path;
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * A cases file, which is always YAML, with mappings as objects as the
     * element reads scripts.
     *
     * @return mixed
     */
    public static function read(string $path)
    {
        return Yaml::parse(
            (string) file_get_contents($path),
            Yaml::PARSE_OBJECT_FOR_MAP
        );
    }
}
