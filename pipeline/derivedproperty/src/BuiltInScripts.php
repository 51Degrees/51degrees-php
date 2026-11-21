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
 * The scripts this package carries, in its scripts folder. The folder is a
 * copy of the scripts folder of the derived-properties repository, because
 * Packagist serves an archive of a tag and an archive has no submodules.
 *
 * PHP builds the pipeline on every request, so each built in script is also
 * carried compiled, as a PHP file in the compiled folder that returns the
 * script's fields and the SHA-256 of the text they came from. opcache keeps
 * such a file in shared memory, so a request reads no YAML for a built in
 * script while the hash matches the script file beside it.
 */
final class BuiltInScripts
{
    private const EXTENSIONS = ['yaml', 'yml', 'json'];

    /**
     * The folder the built in scripts are read from.
     */
    public static function folder(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts';
    }

    /**
     * The folder of the compiled built in scripts, which
     * ci/compile-built-in-scripts.php writes.
     */
    public static function compiledFolder(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'compiled';
    }

    /**
     * The compiled form of the built in script in the file given, or null
     * where there is none or it was compiled from other text, in which case
     * the script is read from its file.
     */
    public static function compiled(string $path): ?DerivedScript
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $fields = self::readCompiled(
            self::compiledFolder() . DIRECTORY_SEPARATOR . $name . '.php',
            $path
        );
        if ($fields === null) {
            return null;
        }
        try {
            return DerivedScript::fromArray($fields, 'built in ' . $name);
        } catch (\TypeError $e) {
            return null;
        }
    }

    /**
     * The script fields in a compiled file, or null where the file is
     * missing, holds no compiled script, or was compiled from other text
     * than the script file holds now.
     *
     * @internal
     * @return null|array<string, mixed>
     */
    public static function readCompiled(string $compiledFile, string $scriptFile): ?array
    {
        if (!is_file($compiledFile)) {
            return null;
        }
        $compiled = include $compiledFile;
        if (!is_array($compiled) ||
            !is_string($compiled['sha256'] ?? null) ||
            !is_array($compiled['script'] ?? null)) {
            return null;
        }
        $text = @file_get_contents($scriptFile);
        if ($text === false || !hash_equals($compiled['sha256'], self::hash($text))) {
            return null;
        }

        return $compiled['script'];
    }

    /**
     * The SHA-256 of a script's text with its line ends as line feeds, as
     * a checkout on Windows may write the file with CR LF.
     */
    public static function hash(string $text): string
    {
        return hash('sha256', str_replace("\r\n", "\n", $text));
    }

    /**
     * The name of every built in script, being its file name without the
     * extension, in ordinal order.
     *
     * @return array<string>
     */
    public static function names(): array
    {
        return array_keys(self::files());
    }

    /**
     * The file of the built in script with the name given, matched without
     * regard to case, or null where the package has no such script.
     */
    public static function path(string $name): ?string
    {
        foreach (self::files() as $script => $path) {
            if (strcasecmp($script, $name) === 0) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> each script's name and file
     */
    private static function files(): array
    {
        $files = [];
        $entries = is_dir(self::folder()) ? scandir(self::folder()) : false;
        foreach ($entries === false ? [] : $entries as $entry) {
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, self::EXTENSIONS, true)) {
                continue;
            }
            $files[pathinfo($entry, PATHINFO_FILENAME)] =
                self::folder() . DIRECTORY_SEPARATOR . $entry;
        }
        ksort($files, SORT_STRING);

        return $files;
    }
}
