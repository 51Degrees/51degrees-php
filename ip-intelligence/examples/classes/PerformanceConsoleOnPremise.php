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

namespace fiftyone\pipeline\ipintelligence\examples\classes;

/**
 * Performance benchmark (on-premise). Mirrors
 * ip-intelligence-dotnet-examples OnPremise/Performance-Console: it builds the
 * engine once, performs a warm-up pass, then a timed pass over a set of IP
 * addresses and reports throughput (detections per second and average
 * milliseconds per detection).
 *
 * PHP has no shared-memory threading in a plain CLI, so this is a
 * single-threaded benchmark (the .NET example also exposes a single-thread
 * mode); the figures therefore reflect per-core performance.
 */
class PerformanceConsoleOnPremise
{
    // Where to ask for a copy of the Enterprise data file, tagged with this
    // example.
    private const CONTACT_US = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-classes-performanceconsoleonpremise.php&utm_term=enterprise-data-file';

    // Hard upper bound on the number of detections, regardless of the time
    // cap. A safety net so the benchmark can never run away on a large
    // enterprise data file (which contains millions of profiles).
    private const MAX_DETECTIONS = 2000000;

    /**
     * Run a time-bounded benchmark.
     *
     * The benchmark runs for at most $maxSeconds of wall-clock time (and at
     * most MAX_DETECTIONS lookups). This matters because an enterprise .ipi
     * data file contains millions of profiles, so an uncapped loop could run
     * for hours; instead we sample throughput over a fixed, short window.
     *
     * @param resource $output
     * @param string $profile LowMemory, or MaxPerformance to hold the whole
     *   data file in memory
     */
    public function run(
        string $dataFile,
        float $maxSeconds,
        $output,
        string $profile = 'LowMemory'
    ): void {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            fwrite($output, "The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.\n");
            return;
        }

        $ipAddresses = [];
        foreach (ExampleConstants::EVIDENCE_VALUES as $evidence) {
            $ipAddresses[] = $evidence['query.client-ip'];
        }

        // The timed loop below only exits from inside the inner foreach.
        if ($ipAddresses === []) {
            fwrite($output, "No IP addresses to benchmark.\n");

            return;
        }

        // Build the engine once and time how long that takes.
        $startupStart = microtime(true);
        $config = new \ConfigIpiSwig();
        // LowMemory by default, because the example uses the Enterprise data
        // file when it finds one. Pass MaxPerformance as the third argument
        // of performance-console.php to measure the fastest profile where
        // there is memory for the whole file.
        $maxPerformance = strcasecmp($profile, 'MaxPerformance') === 0;
        if ($maxPerformance) {
            $config->setMaxPerformance();
        } else {
            $config->setLowMemory();
        }
        $config->setUseUpperPrefixHeaders(false);
        $engine = new \EngineIpiSwig($dataFile, $config, new \RequiredPropertiesConfigSwig(new \VectorStringSwig()));
        $startupMs = (microtime(true) - $startupStart) * 1000.0;

        // The free ASN data file has AsnName in place of RegisteredName, so
        // the benchmark reads whichever the data file has.
        $property = ExampleUtils::hasProperty($engine, 'RegisteredName')
            ? 'RegisteredName'
            : 'AsnName';

        fwrite($output, "Performance benchmark (on-premise IP intelligence)\n");
        fwrite($output, sprintf("\tData file: %s\n", $dataFile));
        fwrite($output, sprintf(
            "\tProfile: %s\n",
            $maxPerformance ? 'MaxPerformance' : 'LowMemory'
        ));
        fwrite($output, sprintf("\tEngine startup: %.0f ms\n", $startupMs));
        fwrite($output, sprintf("\tIP addresses: %d, time cap: %.1f s\n", count($ipAddresses), $maxSeconds));

        // Warm-up pass (not timed) so the data file pages and caches are hot.
        foreach ($ipAddresses as $ip) {
            $engine->process($ip);
        }

        // Timed pass, bounded by wall-clock time and a detection ceiling.
        $count = 0;
        $checksum = 0;
        $deadline = microtime(true) + $maxSeconds;
        $start = microtime(true);
        $stop = false;
        while (!$stop) {
            foreach ($ipAddresses as $ip) {
                $results = $engine->process($ip);
                // Read a property so the lookup cannot be optimised away.
                $value = $results->getValueAsString($property);
                if ($value->hasValue()) {
                    $checksum += strlen($value->getValue());
                }
                ++$count;
                // Respect the wall-clock cap and detection ceiling. Checked
                // every detection: lookups against a large enterprise file can
                // each take hundreds of milliseconds, so a coarser check could
                // overshoot the cap badly.
                if (microtime(true) >= $deadline || $count >= self::MAX_DETECTIONS) {
                    $stop = true;
                    break;
                }
            }
        }
        $elapsed = microtime(true) - $start;

        $detectionsPerSecond = $elapsed > 0 ? $count / $elapsed : 0;
        $msPerDetection = $count > 0 ? ($elapsed * 1000.0) / $count : 0;

        fwrite($output, "Results:\n");
        fwrite($output, sprintf("\tDetections: %d\n", $count));
        fwrite($output, sprintf("\tElapsed: %.3f s\n", $elapsed));
        fwrite($output, sprintf("\tDetections per second: %.0f\n", $detectionsPerSecond));
        fwrite($output, sprintf("\tAvg ms per detection: %.4f\n", $msPerDetection));
        fwrite($output, sprintf("\t(checksum: %d)\n", $checksum));
        if (ExampleUtils::isFreeDataFile($engine)) {
            fwrite($output, "\n" . ExampleUtils::getEnterpriseOffer(self::CONTACT_US) . "\n");
        }
    }
}
