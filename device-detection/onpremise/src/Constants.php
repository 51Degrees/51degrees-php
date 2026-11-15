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
 * Constants which may be reused by the API.
 */
class Constants
{
    // Match metrics descriptions, as the specification's table of match
    // metric properties gives them.
    public const MATCHED_NODES_DESCRIPTION =
        'Indicates the number of hash nodes matched within the Evidence.';
    public const DIFFERENCE_DESCRIPTION =
        'Used when detection method is not Exact or None. The larger the ' .
        'value the less confident the detector is in this result.';
    public const DRIFT_DESCRIPTION =
        "The drift of every component's result added together, each " .
        "result's drift being how far, in character positions, the " .
        "substring's hashes were found from where they were expected. " .
        'Never the largest or smallest result alone.';
    public const DEVICE_ID_DESCRIPTION =
        'Contains the profile ids of the matching profiles, separated by a ' .
        'hyphen symbol. For example ' .
        '[HardwareId]-[PlatformId]-[BrowserId]-[CrawlerId]. By convention, ' .
        'these will be in component Id order. There will often be 4 ids ' .
        'present, but this is not guaranteed.';
    public const USER_AGENTS_DESCRIPTION =
        'The User-Agent values that were matched, each listed once across ' .
        'every result. Populated when the Update Matched User Agent ' .
        'configuration option is on, which it is by default.';
    public const ITERATIONS_DESCRIPTION =
        'The number of iterations carried out in order to find a match. This ' .
        'is the number of nodes in the graph which have been visited.';
    public const METHOD_DESCRIPTION =
        'The method used to determine the match result.';

    // The php.ini setting naming the data file the extension builds its
    // engine from when PHP starts.
    public const DATA_FILE_SETTING = 'FiftyOneDegreesHashEngine.data_file';
}
