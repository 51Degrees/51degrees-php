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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;

/**
 * Mixed (on-premise) example. Combines device detection and IP intelligence
 * in a single on-premise pipeline.
 *
 * Mirrors ip-intelligence-dotnet-examples OnPremise/Mixed/GettingStarted-Console.
 *
 * This requires BOTH native engines:
 *  - the device detection Hash engine (51degrees/fiftyone.devicedetection
 *    on-premise package and its FiftyOneDegreesHashEngine extension, with its
 *    data file configured via php.ini), and
 *  - the IP intelligence engine (FiftyOneDegreesIpIntelligenceEngine extension)
 *    with an .ipi data file.
 */
class MixedConsoleOnPremise
{
    public const DEVICE_DETECTION_ONPREMISE_CLASS =
        'fiftyone\\pipeline\\devicedetection\\DeviceDetectionOnPremise';

    /**
     * @param resource $output
     * @throws \Exception when the device detection on-premise engine is not
     *   available in the environment.
     */
    public function run(string $ipDataFile, $output): void
    {
        $ddClass = self::DEVICE_DETECTION_ONPREMISE_CLASS;
        if (!class_exists($ddClass)) {
            throw new \Exception(
                'The device detection on-premise package is not installed. Install ' .
                '51degrees/fiftyone.devicedetection (on-premise) and build its native ' .
                'extension to run the mixed on-premise example.'
            );
        }

        // The device detection on-premise engine reads its data file from the
        // FiftyOneDegreesHashEngine.data_file php.ini setting.
        $deviceEngine = new $ddClass();

        $ipEngine = new IpIntelligenceOnPremise([
            'dataFile' => $ipDataFile,
            'performanceProfile' => 'MaxPerformance'
        ]);

        $pipeline = (new PipelineBuilder([]))
            ->add($deviceEngine)
            ->add($ipEngine)
            ->build();

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
