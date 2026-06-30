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

use fiftyone\pipeline\translation\Languages;
use fiftyone\pipeline\translation\YamlMap;

/**
 * Locates the country translation YAML files, which ship with the IP
 * intelligence data submodule, and loads them.
 */
class TranslationPaths
{
    /**
     * Find the Translations directory. Searches the in-repo data submodule
     * locations, then the IPINTELLIGENCEDATAFILE directory as a fallback.
     */
    public static function resolveTranslationsDir(): string
    {
        $candidates = [
            __DIR__ . '/../../ip-intelligence-data/Translations',
            __DIR__ . '/../../on-premise/ip-intelligence-cxx/ip-intelligence-data/Translations',
        ];

        $env = getenv('IPINTELLIGENCEDATAFILE');
        if ($env) {
            $candidates[] = dirname($env) . '/Translations';
            $candidates[] = dirname($env) . '/ip-intelligence-data/Translations';
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new \Exception(
            'Could not find the Translations directory. It ships with the ' .
            'ip-intelligence-data submodule; initialise submodules or pass an ' .
            'explicit path to the translation engines.'
        );
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
