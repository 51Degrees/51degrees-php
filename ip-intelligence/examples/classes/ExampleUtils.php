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

namespace fiftyone\pipeline\ipintelligence\examples\classes;

use fiftyone\pipeline\core\AspectPropertyValue;

class ExampleUtils
{
    /**
     * Resolve the cloud resource key from a CLI argument or environment
     * variable.
     *
     * @param array<string> $args
     */
    public static function getResourceKey(array $args = []): ?string
    {
        if (count($args) > 0 && !empty($args[0])) {
            return $args[0];
        }

        $key = getenv(ExampleConstants::CLOUD_RESOURCE_KEY_ENV_VAR);
        if ($key && $key !== '!!YOUR_RESOURCE_KEY!!') {
            return $key;
        }

        return null;
    }

    /**
     * Resolve an on-premise data file path from a CLI argument, environment
     * variable, or by searching known repository locations. When none is
     * found, the free Lite file is downloaded into cxx/data/ip-intelligence.
     *
     * @param array<string> $args
     */
    public static function getDataFile(array $args = []): ?string
    {
        if (count($args) > 0 && !empty($args[0]) && file_exists($args[0])) {
            return $args[0];
        }

        $env = getenv(ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR);
        if ($env && file_exists($env)) {
            return $env;
        }

        $assets = __DIR__ . '/../../assets';
        $data = __DIR__ . '/../../../cxx/data/ip-intelligence';

        foreach ([$assets, $data] as $dir) {
            foreach (ExampleConstants::IPI_DATA_FILE_NAMES as $name) {
                $candidate = $dir . '/' . $name;
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
            // Any other .ipi file in assets was put there to be used. The
            // data folder is searched by name only, as it can also hold the
            // Asn file.
            if ($dir === $assets) {
                $any = glob($dir . '/*.ipi') ?: [];
                if (!empty($any)) {
                    return $any[0];
                }
            }
        }

        $lite = $data . '/' . ExampleConstants::LITE_DATA_FILE_NAME;

        return self::downloadMissingDataFile($lite) ? $lite : null;
    }

    /**
     * Download a free data file that is missing, with the data repository's
     * script, when the file's link is in the folder where it goes.
     *
     * The data repository keeps each free data file as <file>.link, holding
     * the address it is downloaded from, and the repository is the folder
     * above the link's folder. The command is printed before it runs, and
     * again if the file is still missing.
     *
     * @param string $path Where the data file goes
     * @return bool True when the data file is there
     */
    public static function downloadMissingDataFile(string $path): bool
    {
        if (file_exists($path)) {
            return true;
        }
        $command = self::getDataFileCommand($path);
        if ($command === null) {
            return false;
        }

        $name = basename($path);
        self::writeError(
            "{$name} is missing, so it is being downloaded by running\n{$command}"
        );
        $status = 1;
        if (function_exists('exec')) {
            $lines = [];
            exec($command, $lines, $status);
            if (count($lines) > 0) {
                self::writeError(implode("\n", $lines));
            }
        }
        clearstatcache(true, $path);
        if ($status === 0 && file_exists($path)) {
            return true;
        }
        self::writeError(
            "{$name} could not be downloaded. Run this command to try " .
            "again.\n{$command}"
        );

        return false;
    }

    /**
     * The command that downloads a free data file with the data repository's
     * script, or null when the file's link is not in the folder where it
     * goes.
     *
     * @param string $path Where the data file goes
     */
    public static function getDataFileCommand(string $path): ?string
    {
        $link = realpath($path . '.link');
        if ($link === false || !is_file($link)) {
            return null;
        }
        $data = dirname($link, 2);
        $name = escapeshellarg(basename($path));
        $source = ExampleConstants::DATA_DOWNLOAD_SOURCE;
        if (PHP_OS_FAMILY === 'Windows') {
            return 'powershell -NoProfile -ExecutionPolicy Bypass -File ' .
                escapeshellarg($data . '\\get-data-files.ps1') .
                " {$name} -Source {$source}";
        }

        return 'sh ' . escapeshellarg($data . '/get-data-files.sh') .
            " -s {$source} {$name}";
    }

    /**
     * Format an AspectPropertyValue (or a missing value) for printing.
     *
     * @param mixed $apv
     */
    public static function formatValue($apv): string
    {
        if (!($apv instanceof AspectPropertyValue)) {
            return (string) $apv;
        }

        if (!$apv->hasValue) {
            return '(' . $apv->noValueMessage . ')';
        }

        $value = $apv->value;
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            // Weighted values are arrays of ['value' => ..., 'rawweighting' => ...].
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item) && array_key_exists('value', $item)) {
                    $weight = $item['rawweighting'] ?? '';
                    $parts[] = $item['value'] . ' (' . $weight . ')';
                } else {
                    $parts[] = (string) $item;
                }
            }

            return implode(', ', $parts);
        }

        return (string) $value;
    }

    /**
     * Write a single property to the output stream.
     *
     * @param mixed $data An ElementData instance
     * @param resource $output
     */
    public static function outputProperty($data, string $name, $output): void
    {
        try {
            $apv = $data->get(strtolower($name));
            fwrite($output, sprintf("\t%s: %s\n", $name, self::formatValue($apv)));
        } catch (\Throwable $e) {
            // The property is not present for this engine / resource key /
            // data file. Report it rather than aborting the whole example.
            fwrite($output, sprintf("\t%s: (not available - %s)\n", $name, $e->getMessage()));
        }
    }

    /**
     * Write a message to standard error. A web example's server shows it in
     * its console, and it never reaches the page.
     */
    private static function writeError(string $message): void
    {
        file_put_contents('php://stderr', $message . "\n");
    }
}
