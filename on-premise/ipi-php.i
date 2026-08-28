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

/*
 * PHP entry point for the SWIG interface to the IP intelligence on-premise
 * engine. It simply includes the language-agnostic interface shipped in the
 * ip-intelligence-cxx submodule (src/ipi.i). The generated module is named
 * FiftyOneDegreesIpIntelligenceEngine via the -module flag passed to SWIG by
 * config.m4 / config.w32, mirroring device-detection-php-onpremise.
 *
 * On PHP 7+ the SWIG generated constructors accept the required arguments
 * directly, so unlike the historic device-detection PHP 5 workaround there is
 * no need to redefine the engine constructor here - the EngineIpiSwig class is
 * constructed directly from the PHP layer (IpIntelligenceOnPremise.php) with a
 * data file path, a ConfigIpiSwig and a RequiredPropertiesConfigSwig.
 */

%include "ip-intelligence-cxx/src/ipi.i"

/*
 * Optional global engine.
 *
 * Constructing the engine on every request re-opens the (potentially multi
 * gigabyte) data file each time. To allow a long-running SAPI (for example the
 * PHP built-in server or php-fpm) to load the data file once and reuse it, the
 * module can build a single global engine at startup from php.ini settings and
 * expose it via ipi_engine_get().
 *
 * This is entirely OPTIONAL and OFF by default: the global engine is only built
 * when FiftyOneDegreesIpIntelligenceEngine.data_file is set to an existing file.
 * When it is not set the module loads normally and callers construct
 * EngineIpiSwig directly (as IpIntelligenceOnPremise.php and the tests do), so
 * existing behaviour is unchanged. The accessor is named ipi_engine_get (not
 * engine_get) so it does not clash with the device detection extension's global
 * engine when both extensions are loaded in the same process (mixed examples).
 */

%{
    #include <string.h>
    #include <sys/stat.h>

    EngineIpi *ipi_engine = NULL;
    ConfigIpi *ipi_engine_config = NULL;
    RequiredPropertiesConfig *ipi_engine_properties = NULL;

    PHP_INI_BEGIN()
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.data_file", "", PHP_INI_ALL, NULL)
    /* Keep this default in step with Constants::DEFAULT_PERFORMANCE_PROFILE in
       src/Constants.php - the PHP layer applies that default when it builds its
       own engine, and this one governs the shared global engine, so they must
       agree for an identical configuration to behave the same either way. */
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.performance_profile", "Balanced", PHP_INI_ALL, NULL)
    PHP_INI_ENTRY("FiftyOneDegreesIpIntelligenceEngine.required_properties", "", PHP_INI_ALL, NULL)
    PHP_INI_END()
%}

%immutable ipi_engine;
EngineIpi *ipi_engine;

%minit {
    REGISTER_INI_ENTRIES();
    char *filePath = INI_STR("FiftyOneDegreesIpIntelligenceEngine.data_file");
    struct stat sb;
    if (filePath != NULL && strlen(filePath) > 0 && stat(filePath, &sb) == 0) {
        char *profile = INI_STR("FiftyOneDegreesIpIntelligenceEngine.performance_profile");
        char *propertyList = INI_STR("FiftyOneDegreesIpIntelligenceEngine.required_properties");
        ipi_engine_config = new ConfigIpi();
        if (profile != NULL && strcmp(profile, "MaxPerformance") == 0) {
            ipi_engine_config->setMaxPerformance();
        } else if (profile != NULL && strcmp(profile, "HighPerformance") == 0) {
            ipi_engine_config->setHighPerformance();
        } else if (profile != NULL && strcmp(profile, "BalancedTemp") == 0) {
            ipi_engine_config->setBalancedTemp();
        } else if (profile != NULL && strcmp(profile, "LowMemory") == 0) {
            ipi_engine_config->setLowMemory();
        } else {
            /* "Balanced" and anything unrecognised - the documented default. */
            ipi_engine_config->setBalanced();
        }
        ipi_engine_config->setUseUpperPrefixHeaders(false);
        if (propertyList != NULL && strlen(propertyList) > 0) {
            ipi_engine_properties = new RequiredPropertiesConfig(propertyList);
        } else {
            ipi_engine_properties = new RequiredPropertiesConfig();
        }
        ipi_engine = new EngineIpi(filePath, ipi_engine_config, ipi_engine_properties);
    }
}

%mshutdown {
    if (ipi_engine != NULL) { delete ipi_engine; ipi_engine = NULL; }
    if (ipi_engine_properties != NULL) { delete ipi_engine_properties; ipi_engine_properties = NULL; }
    if (ipi_engine_config != NULL) { delete ipi_engine_config; ipi_engine_config = NULL; }
}
