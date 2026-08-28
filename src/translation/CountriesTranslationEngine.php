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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\ipintelligence\Constants as IpIntelligenceConstants;
use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngine;

/**
 * Engine 2: translates the weighted English country names from Engine 1 into
 * the browser language, and builds the complete ordered "All" lists used by
 * the country dropdown.
 *
 * Mirrors ip-intelligence-dotnet's CountriesTranslationEngine. The base engine
 * produces the weighted translated names; this engine then appends every other
 * known country (alphabetically) to form the ordered All lists, with the most
 * probable country first.
 */
class CountriesTranslationEngine extends TranslationEngine
{
    // Dimensions: [translated names key, IP codes property, all-names key,
    // all-codes key].
    private const DIMENSIONS = [
        [
            'countrynamesgeographicaltranslated',
            'CountryCodesGeographical',
            'countrynamesgeographicalalltranslated',
            'countrycodesgeographicalall',
        ],
        [
            'countrynamespopulationtranslated',
            'CountryCodesPopulation',
            'countrynamespopulationalltranslated',
            'countrycodespopulationall',
        ],
    ];

    /**
     * @var array<array{0: string, 1: string}> ordered [code, EnglishName]
     */
    private array $allCountries;

    /**
     * @var array<string, bool> set of valid codes, keyed lower-case
     */
    private array $validCodes;

    public function __construct(?string $translationsDir = null)
    {
        $dir = $translationsDir ?? TranslationPaths::resolveTranslationsDir();

        $localeMaps = TranslationPaths::loadCountriesLocaleMaps($dir);

        parent::__construct(
            Constants::COUNTRY_NAMES_TRANSLATED_KEY,
            Constants::COUNTRY_NAMES_KEY,
            [
                'CountryNamesGeographical' => 'CountryNamesGeographicalTranslated',
                'CountryNamesPopulation' => 'CountryNamesPopulationTranslated',
            ],
            $localeMaps,
            null,
            MissingTranslationBehavior::ORIGINAL
        );

        $this->allCountries = TranslationPaths::loadAllCountries($dir);
        $this->validCodes = [];
        foreach ($this->allCountries as [$code]) {
            $this->validCodes[strtolower($code)] = true;
        }

        // Declare the additional output properties.
        foreach (self::DIMENSIONS as [$namesKey, $codesProp, $allNamesKey, $allCodesKey]) {
            $this->properties[$allNamesKey] = ['name' => $allNamesKey, 'type' => 'Array'];
            $this->properties[$allCodesKey] = ['name' => $allCodesKey, 'type' => 'Array'];
        }
        $this->properties['sortingcultureused'] = ['name' => 'SortingCultureUsed', 'type' => 'String'];
    }

    public function processInternal(FlowData $flowData): void
    {
        $resolved = $this->resolveTranslator($flowData);
        $translator = $resolved['translator'];
        $locale = $resolved['locale'];

        // The base engine produces the weighted translated names.
        $result = $this->buildTranslatedProperties($flowData, $translator);

        $comparer = $this->makeComparer($locale);
        // Report the culture in hyphenated form (fr-FR), as the .NET engine
        // does, rather than the underscore locale form used by the files.
        $culture = $locale === null ? '' : str_replace('_', '-', $locale);
        $result['sortingcultureused'] = new AspectPropertyValue(null, $culture);

        // The weighted codes are read directly from the IP intelligence engine.
        $ip = $flowData->get(Constants::IP_INTELLIGENCE_KEY);

        foreach (self::DIMENSIONS as [$namesKey, $codesProp, $allNamesKey, $allCodesKey]) {
            $translatedNames = $this->weightedListOf($result[$namesKey] ?? null);
            $weightedCodes = $this->weightedListOf($this->safeGet($ip, strtolower($codesProp)));

            [$allCodes, $allNames] = $this->buildAllLists($translatedNames, $weightedCodes, $translator, $comparer);

            $result[$allCodesKey] = new AspectPropertyValue(null, $allCodes);
            $result[$allNamesKey] = new AspectPropertyValue(null, $allNames);
        }

        $flowData->setElementData(new AspectDataDictionary($this, $result));
    }

    /**
     * Build the ordered code and name lists for one dimension.
     *
     * Weighted countries come first (highest weight first, ties broken by
     * translated name), then every remaining known country alphabetically by
     * translated name. The two returned lists are index-aligned.
     *
     * @param array<array<string, mixed>> $translatedWeightedNames
     * @param array<array<string, mixed>> $weightedCodes
     * @param callable $comparer
     * @return array{0: array<string>, 1: array<string>} [codes, names]
     */
    private function buildAllLists(array $translatedWeightedNames, array $weightedCodes, $translator, callable $comparer): array
    {
        // 1. The weighted countries, zipped by index, real codes only.
        $count = min(count($translatedWeightedNames), count($weightedCodes));
        $weighted = [];
        for ($i = 0; $i < $count; ++$i) {
            $code = (string) ($weightedCodes[$i][IpIntelligenceConstants::WEIGHTED_VALUE_KEY] ?? '');
            if (!isset($this->validCodes[strtolower($code)])) {
                continue;
            }
            $weighted[] = [
                'code' => $code,
                'name' => (string) ($translatedWeightedNames[$i][IpIntelligenceConstants::WEIGHTED_VALUE_KEY] ?? ''),
                'weight' => $translatedWeightedNames[$i][IpIntelligenceConstants::WEIGHTED_RAW_WEIGHTING_KEY] ?? 0,
            ];
        }
        usort($weighted, static function ($a, $b) use ($comparer) {
            if ($a['weight'] === $b['weight']) {
                return $comparer($a['name'], $b['name']);
            }

            return $b['weight'] <=> $a['weight'];
        });

        // 2. Every remaining known country, alphabetical by translated name.
        $usedCodes = [];
        foreach ($weighted as $tuple) {
            $usedCodes[strtolower($tuple['code'])] = true;
        }

        $errors = [];
        $remaining = [];
        foreach ($this->allCountries as [$code, $englishName]) {
            if (isset($usedCodes[strtolower($code)])) {
                continue;
            }
            $name = $englishName;
            $translated = $translator->translate($englishName, $errors);
            if (is_string($translated) && $translated !== '') {
                $name = $translated;
            }
            $remaining[] = ['code' => $code, 'name' => $name];
        }
        usort($remaining, static fn ($a, $b) => $comparer($a['name'], $b['name']));

        // 3. Concatenate and split into the two parallel lists.
        $all = array_merge($weighted, $remaining);

        $codes = array_map(static fn ($t) => $t['code'], $all);
        $names = array_map(static fn ($t) => $t['name'], $all);

        return [$codes, $names];
    }

    /**
     * Build a locale-aware string comparer.
     *
     * When the intl extension is available a Collator for the resolved locale
     * is used, which matches .NET's culture-aware ordering (correct for
     * languages such as Turkish, Swedish and Polish). When intl is not
     * available the comparer falls back to transliterating diacritics to
     * ASCII and comparing case-insensitively, which orders accented Latin
     * names close to their unaccented form but is not fully locale-correct.
     *
     * @param null|string $locale resolved locale (e.g. fr_FR) or null
     */
    private function makeComparer(?string $locale): callable
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator($locale !== null ? str_replace('_', '-', $locale) : 'en-US');

            return static fn (string $a, string $b): int => $collator->compare($a, $b);
        }

        return static function (string $a, string $b): int {
            $normalize = static function (string $s): string {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

                return strtolower($t === false ? $s : $t);
            };

            return strcmp($normalize($a), $normalize($b));
        };
    }

    /**
     * Extract a weighted list ([['value'=>.., 'rawweighting'=>..], ...]) from
     * an AspectPropertyValue, or an empty array if there is no value.
     *
     * @param mixed $apv
     * @return array<array<string, mixed>>
     */
    private function weightedListOf($apv): array
    {
        if ($apv instanceof AspectPropertyValue && $apv->hasValue && is_array($apv->value)) {
            return $apv->value;
        }

        return [];
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function safeGet($data, string $key)
    {
        try {
            return $data->get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
