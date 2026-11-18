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

namespace fiftyone\pipeline\ipintelligence\translation;

use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\translation\Languages;
use fiftyone\pipeline\translation\YamlMap;

/**
 * Locates the country translation YAML files, which ship in the
 * ip-intelligence folder of the data repository, and loads them.
 */
class TranslationPaths
{
    // Environment variable that names the IP Intelligence data file.
    private const IPI_PATH_VARIABLE = '_51DEGREES_IPI_PATH';

    // The name IPI_PATH_VARIABLE replaced, read when that one is not set
    // or is empty.
    private const OLD_IPI_PATH_VARIABLE = 'IPINTELLIGENCEDATAFILE';

    // Logged when a value was read from the deprecated environment
    // variable, which is named first, then the one that replaces it.
    private const OLD_IPI_PATH_VARIABLE_MESSAGE =
        'A value was read from the environment variable %s, which is ' .
        'deprecated. %s replaces it, so set that to the same value.';

    // Whether a value has been read from the deprecated variable.
    private static bool $oldIpiPathVariableRead = false;

    // Whether that has been logged since PHP started the script, which
    // under a web server is since the request began.
    private static bool $oldIpiPathVariableReported = false;

    /**
     * Find the Translations directory. Searches the ip-intelligence folder of
     * the data submodule inside the cxx submodule at the repository root,
     * then the _51DEGREES_IPI_PATH directory as a fallback.
     */
    public static function resolveTranslationsDir(): string
    {
        $candidates = [
            __DIR__ . '/../../../cxx/data/ip-intelligence/Translations',
        ];

        $env = self::dataFileFromEnvironment();
        if ($env) {
            $candidates[] = dirname($env) . '/Translations';
            $candidates[] = dirname($env) . '/data/ip-intelligence/Translations';
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new \Exception(
            'Could not find the Translations directory. It is in ' .
            'cxx/data/ip-intelligence once the submodules are initialised ' .
            'with "git submodule update --init --recursive". Otherwise pass ' .
            'its path to the translation engines.'
        );
    }

    /**
     * Logs, as a warning through the pipeline's logger, that a value was
     * read from the deprecated environment variable. It is logged once for
     * each time PHP runs the script, by the first translation engine added
     * to a pipeline after the variable was read.
     */
    public static function reportDeprecatedVariable(Pipeline $pipeline): void
    {
        if (!self::$oldIpiPathVariableRead || self::$oldIpiPathVariableReported) {
            return;
        }

        self::$oldIpiPathVariableReported = true;
        $pipeline->log('warning', sprintf(
            self::OLD_IPI_PATH_VARIABLE_MESSAGE,
            self::OLD_IPI_PATH_VARIABLE,
            self::IPI_PATH_VARIABLE
        ));
    }

    /**
     * The data file named in the environment, or an empty string. The
     * deprecated variable is read only when the new one gives nothing.
     */
    private static function dataFileFromEnvironment(): string
    {
        $new = (string) getenv(self::IPI_PATH_VARIABLE);
        if ($new !== '') {
            return $new;
        }

        $old = (string) getenv(self::OLD_IPI_PATH_VARIABLE);
        if ($old !== '') {
            self::$oldIpiPathVariableRead = true;
        }

        return $old;
    }

    /**
     * Load the countries.<locale>.yml files (English name to localized name).
     *
     * @return array<string, array<string, string>> locale => translation map
     */
    public static function loadCountriesLocaleMaps(string $translationsDir): array
    {
        $maps = [];
        $dir = $translationsDir . '/' . Constants::COUNTRIES_SUBDIR;
        foreach (glob($dir . '/countries.*.yml') ?: [] as $file) {
            $locale = Languages::localeFromFilename(basename($file));
            if ($locale !== null) {
                $maps[$locale] = YamlMap::parseFile($file);
            }
        }

        return $maps;
    }

    /**
     * Load countrycodes.en_GB.yml as an ordered list of [code, EnglishName]
     * pairs. The file order defines the order of all known countries.
     *
     * @return array<array{0: string, 1: string}>
     */
    public static function loadAllCountries(string $translationsDir): array
    {
        $map = YamlMap::parseFile($translationsDir . '/' . Constants::COUNTRY_CODES_FILE);
        $all = [];
        foreach ($map as $code => $englishName) {
            $all[] = [$code, $englishName];
        }

        return $all;
    }
}
