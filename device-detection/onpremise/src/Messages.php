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

namespace fiftyone\pipeline\devicedetection;

/**
 * Messages which may be reused by the API.
 */
class Messages
{
    /**
     * Error message returned when a cache is configured for the on-premise
     * engine.
     */
    public const CACHE_ERROR =
        'A results cache cannot be configured in the on-premise Hash ' .
        'engine. The overhead of having to manage native object ' .
        'lifetimes when a cache is enabled outweighs the benefit of the ' .
        'cache.';

    /**
     * Error message returned when a performance profile other than
     * MaxPerformance is configured for the on-premise engine. The placeholder
     * is the profile that was configured.
     */
    public const PERFORMANCE_PROFILE_ERROR =
        "The on-premise Hash engine for PHP can only run with the in-memory " .
        "'MaxPerformance' performance profile, but '%s' was configured. PHP is " .
        "typically run under a process manager (Apache MPM prefork, php-fpm) " .
        "that forks the worker process, and any other profile keeps the data " .
        "file open and shares those file handles across the fork, which " .
        "corrupts detection. Remove the " .
        "'FiftyOneDegreesHashEngine.performance_profile' setting, or set it to " .
        "'MaxPerformance'.";

    /**
     * Error message thrown when a property is read from results that have
     * been closed.
     */
    public const RESULTS_CLOSED =
        'These device detection results have been closed, so their ' .
        'properties cannot be read. Read the values needed before closing ' .
        'the FlowData.';

    /**
     * Error message thrown when the extension is not loaded.
     */
    public const EXTENSION_NOT_LOADED =
        'The FiftyOneDegreesHashEngine extension is not loaded. Build it and ' .
        'add it to php.ini, as readme.md says.';

    /**
     * Error message thrown when PHP started without building the engine. The
     * placeholder is DATA_FILE_NOT_SET, DATA_FILE_MISSING or
     * DATA_FILE_NOT_LOADED.
     */
    public const NO_ENGINE =
        'No device detection engine was built when PHP started, because %s ' .
        'Set FiftyOneDegreesHashEngine.data_file in php.ini to the path of a ' .
        '.hash data file, so that PHP builds the engine once when it starts. ' .
        'The Data file section of readme.md says where to get the free Lite ' .
        'data file.';

    public const DATA_FILE_NOT_SET =
        'FiftyOneDegreesHashEngine.data_file is not set.';

    /**
     * The placeholder is the path the setting names.
     */
    public const DATA_FILE_MISSING =
        "there is no data file at '%s', which " .
        'FiftyOneDegreesHashEngine.data_file names.';

    /**
     * The placeholder is the path the setting names.
     */
    public const DATA_FILE_NOT_LOADED =
        "the engine could not be built from '%s', which " .
        'FiftyOneDegreesHashEngine.data_file names. PHP gave the reason in a ' .
        'warning when it started.';
}
