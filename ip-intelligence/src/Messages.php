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

namespace fiftyone\pipeline\ipintelligence;

class Messages
{
    public const CACHE_ERROR =
        'The on-premise IP intelligence engine has its own internal cache ' .
        'and does not support an external cache.';

    public const INVALID_PERFORMANCE_PROFILE =
        "'%s' is not a valid performance profile. Valid profiles are: %s.";

    // Raised when the php.ini settings name a performance profile other than
    // MaxPerformance for the shared global engine. The placeholder is the
    // profile that was configured.
    public const PERFORMANCE_PROFILE_ERROR =
        "The IP intelligence engine the extension builds when PHP starts can " .
        "only run with the in-memory 'MaxPerformance' performance profile, " .
        "but '%s' was configured. PHP is typically run under a process " .
        "manager (Apache MPM prefork, php-fpm) that forks the worker process " .
        "after that engine is built, and any other profile keeps the data " .
        "file open and shares those file handles across the fork, which " .
        "corrupts the results. Remove the " .
        "'FiftyOneDegreesIpIntelligenceEngine.performance_profile' setting, " .
        "or set it to 'MaxPerformance'.";

    // Raised when no settings are given and PHP started without building an
    // engine.
    public const NO_STARTUP_ENGINE =
        'No IP intelligence engine was built when PHP started. Set ' .
        'FiftyOneDegreesIpIntelligenceEngine.data_file in php.ini to the ' .
        'path of an .ipi data file, so that PHP builds the engine once when ' .
        'it starts. See OnPremise.md.';

    // Raised when the "dataFile" setting is given outside the command line.
    public const DATA_FILE_SETTING_REFUSED =
        'The "dataFile" setting builds a new IP intelligence engine from the ' .
        'data file every time, so under a web server the data file would be ' .
        'loaded on every request, and it is refused outside the command ' .
        'line. Set FiftyOneDegreesIpIntelligenceEngine.data_file in php.ini ' .
        'to the data file and leave the "dataFile" setting out, so that PHP ' .
        'builds the engine once, when it starts.';

    public const DATA_FILE_NOT_FOUND =
        'There is no IP intelligence data file at %s';

    public const DATA_FILE_EXTENSION_ERROR =
        'The IP intelligence data file name must end in %s';

    public const GLOBAL_ENGINE_SETTINGS_REJECTED =
        'The shared global IP intelligence engine is built once at module ' .
        'load from the FiftyOneDegreesIpIntelligenceEngine.* php.ini ' .
        'settings and cannot be reconfigured per instance, so these ' .
        'setting(s) would have no effect: %s. Remove them, and set the data ' .
        'file and the required properties in php.ini (data_file, ' .
        'required_properties).';

    public const EXTENSION_NOT_LOADED =
        'The FiftyOneDegreesIpIntelligenceEngine extension is not loaded. The ' .
        'on-premise IP intelligence engine requires the native extension to be ' .
        'built and enabled (see OnPremise.md). Cloud operation does not require it.';

    public const RESULTS_CLOSED =
        'These IP intelligence results have been closed, so their ' .
        'properties cannot be read. Read the values needed before closing ' .
        'the FlowData.';

    public const ENGINE_CLOSED =
        'The on-premise IP intelligence engine has been closed, so it cannot ' .
        'process evidence. Build a new pipeline.';
}
