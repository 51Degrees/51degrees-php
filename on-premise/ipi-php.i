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
