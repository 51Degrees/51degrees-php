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

use fiftyone\pipeline\devicedetection\DeviceDetectionPipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceCloud;

/**
 * Mixed (cloud) example. Combines device detection and IP intelligence in a
 * single cloud pipeline: one CloudRequestEngine serves both the device
 * detection and IP intelligence cloud engines.
 *
 * Mirrors ip-intelligence-dotnet-examples Cloud/Mixed/GettingStarted-Console.
 */
class MixedConsoleCloud
{
    /**
     * @param array<string, mixed> $settings Must contain a 'resourceKey'
     *   authorised for both device detection and IP intelligence.
     * @param resource $output
     */
    public function run(array $settings, $output): void
    {
        // The device detection builder wires up the shared CloudRequestEngine
        // and the device detection cloud engine. We then append the IP
        // intelligence cloud engine so both run from the same cloud response.
        $builder = new DeviceDetectionPipelineBuilder($settings);
        $builder->add(new IpIntelligenceCloud());
        $pipeline = $builder->build();

        foreach (ExampleConstants::MIXED_EVIDENCE_VALUES as $evidence) {
            $this->analyseEvidence($evidence, $pipeline, $output);
        }
    }

    /**
     * @param array<string, string> $evidence
     * @param resource $output
     */
    private function analyseEvidence(array $evidence, $pipeline, $output): void
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setArray($evidence);
        $result = $flowData->process();

        fwrite($output, "Input values:\n");
        foreach ($evidence as $key => $value) {
            fwrite($output, sprintf("\t%s: %s\n", $key, $value));
        }

        fwrite($output, "Device Detection Results:\n");
        $device = $result->device;
        foreach (ExampleConstants::DEVICE_OUTPUT_PROPERTIES as $name) {
            ExampleUtils::outputProperty($device, $name, $output);
        }

        fwrite($output, "IP Intelligence Results:\n");
        $ip = $result->ip;
        foreach (ExampleConstants::OUTPUT_PROPERTIES as $name) {
            ExampleUtils::outputProperty($ip, $name, $output);
        }
        fwrite($output, "\n");
    }
}
