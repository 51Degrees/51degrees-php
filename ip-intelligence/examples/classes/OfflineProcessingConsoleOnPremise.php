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

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;

/**
 * Offline processing (on-premise) example. Processes a list of IP addresses
 * (as would be read from a log file) and writes a YAML-like document for
 * each, containing the IP and a selection of looked-up properties.
 */
class OfflineProcessingConsoleOnPremise
{
    // Where to ask for a copy of the Enterprise data file, tagged with this
    // example.
    private const CONTACT_US = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-classes-offlineprocessingconsoleonpremise.php&utm_term=enterprise-data-file';

    /**
     * @param array<string> $ipAddresses
     * @param resource $output
     */
    public function run(string $dataFile, array $ipAddresses, $output): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => $dataFile,
            // The example uses the Enterprise data file when it finds one,
            // which is several gigabytes, and LowMemory keeps the memory it
            // needs small.
            'performanceProfile' => 'LowMemory'
        ]))->build();

        // The pipeline is closed in the finally block, which releases the
        // native data it holds.
        try {
            $engine = $pipeline->getElement('ip');
            $needsEnterprise = false;
            foreach ($ipAddresses as $ipAddress) {
                // Each FlowData is closed in its finally block, which
                // releases the results of the lookup straight away, rather
                // than when the garbage collector next runs.
                $flowData = $pipeline->createFlowData();
                try {
                    $flowData->evidence->set('query.client-ip', $ipAddress);
                    $result = $flowData->process();

                    $ip = $result->ip;
                    fwrite($output, "---\n");
                    fwrite($output, sprintf("query.client-ip: %s\n", $ipAddress));
                    foreach (['RegisteredName', 'Country', 'CountryCode'] as $name) {
                        $needsEnterprise =
                            ExampleUtils::outputEnterpriseProperty($engine, $ip, $name, $output) ||
                            $needsEnterprise;
                    }
                } finally {
                    $flowData->close();
                }
            }
            if ($needsEnterprise) {
                fwrite($output, '# ' . ExampleUtils::getEnterpriseNote(self::CONTACT_US) . "\n");
            }
        } finally {
            $pipeline->close();
        }
    }
}
