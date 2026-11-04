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
 * Getting started (on-premise) example. Looks up IP intelligence data for a
 * set of IP addresses using a local .ipi data file.
 */
class GettingStartedConsoleOnPremise
{
    /**
     * @param resource $output
     */
    public function run(string $dataFile, $output): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => $dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();

        // The pipeline is closed in the finally block, which releases the
        // native data it holds.
        try {
            foreach (ExampleConstants::EVIDENCE_VALUES as $evidence) {
                $this->analyseEvidence($evidence, $pipeline, $output);
            }
        } finally {
            $pipeline->close();
        }
    }

    /**
     * @param array<string, string> $evidence
     * @param resource $output
     */
    private function analyseEvidence(array $evidence, $pipeline, $output): void
    {
        // The FlowData is closed in the finally block, which releases the
        // results of the lookup straight away, rather than when the garbage
        // collector next runs.
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->setArray($evidence);
            $result = $flowData->process();

            fwrite($output, "Input values:\n");
            foreach ($evidence as $key => $value) {
                fwrite($output, sprintf("\t%s: %s\n", $key, $value));
            }

            fwrite($output, "Results:\n");
            $ip = $result->ip;
            foreach (ExampleConstants::OUTPUT_PROPERTIES as $name) {
                ExampleUtils::outputProperty($ip, $name, $output);
            }
            fwrite($output, "\n");
        } finally {
            $flowData->close();
        }
    }
}
