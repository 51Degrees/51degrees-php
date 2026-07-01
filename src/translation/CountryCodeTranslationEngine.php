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

use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngine;
use fiftyone\pipeline\translation\YamlMap;

/**
 * Engine 1: translates the weighted ISO country codes emitted by the IP
 * intelligence engine (CountryCodesGeographical / CountryCodesPopulation)
 * into weighted English country names (CountryNamesGeographical /
 * CountryNamesPopulation), using countrycodes.en_GB.yml.
 *
 * Mirrors ip-intelligence-dotnet's CountryCodeTranslationEngine: it is just
 * the property map below passed to the base translation engine with a fixed
 * en_GB language and the Original missing behaviour.
 */
class CountryCodeTranslationEngine extends TranslationEngine
{
    public function __construct(?string $translationsDir = null)
    {
        $dir = $translationsDir ?? TranslationPaths::resolveTranslationsDir();
        $codeFile = $dir . '/' . Constants::COUNTRY_CODES_FILE;

        $localeMaps = [
            Constants::BASE_LOCALE => YamlMap::parseFile($codeFile),
        ];

        parent::__construct(
            Constants::COUNTRY_NAMES_KEY,
            Constants::IP_INTELLIGENCE_KEY,
            [
                'CountryCodesGeographical' => 'CountryNamesGeographical',
                'CountryCodesPopulation' => 'CountryNamesPopulation',
            ],
            $localeMaps,
            Constants::BASE_LOCALE,
            MissingTranslationBehavior::ORIGINAL
        );
    }
}
