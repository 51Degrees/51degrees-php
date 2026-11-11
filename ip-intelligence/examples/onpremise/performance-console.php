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

require_once __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\examples\classes\PerformanceConsoleOnPremise;

// Any data file will do for a benchmark, so without one the far smaller free
// ASN file is downloaded rather than the Lite file.
$dataFile = ExampleUtils::getDataFile(
    array_slice($argv, 1),
    ExampleConstants::ASN_DATA_FILE_NAME
);

if (!$dataFile) {
    fwrite(STDERR, "An IP intelligence data file (.ipi) is required. Supply its path as the first argument or via the _51DEGREES_IPI_PATH environment variable.\n");
    exit(1);
}

// Wall-clock time cap in seconds (the benchmark samples throughput over this
// window rather than running a fixed - and potentially very long - number of
// lookups against the multi-million-profile enterprise data file).
$maxSeconds = (count($argv) > 2 && is_numeric($argv[2])) ? (float) $argv[2] : 10.0;

// The performance profile, LowMemory unless the third argument is
// MaxPerformance.
$profile = $argv[3] ?? 'LowMemory';

(new PerformanceConsoleOnPremise())->run($dataFile, $maxSeconds, STDOUT, $profile);
