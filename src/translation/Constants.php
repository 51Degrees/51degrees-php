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

class Constants
{
    // Element data key of the IP intelligence engine that emits the weighted
    // country codes. Matches IpIntelligenceCloud / IpIntelligenceOnPremise.
    public const IP_INTELLIGENCE_KEY = 'ip';

    // Element data key produced by the country code translation engine.
    public const COUNTRY_NAMES_KEY = 'countrynames';

    // Element data key produced by the countries translation engine.
    public const COUNTRY_NAMES_TRANSLATED_KEY = 'countrynamestranslated';

    // The base language of the source data (country code file is en_GB).
    public const BASE_LOCALE = 'en_GB';

    // Directory (relative to the data root) holding the translation files.
    public const TRANSLATIONS_DIR = 'Translations';
    public const COUNTRIES_SUBDIR = 'OSM';
    public const COUNTRY_CODES_FILE = 'countrycodes.en_GB.yml';
}
