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

use fiftyone\pipeline\core\PipelineBuilder;

/**
 * Builds a Pipeline that uses the on-premise IP intelligence engine to
 * look up IP intelligence data from a local data file.
 *
 * Mirrors device-detection-php-onpremise usage and
 * ip-intelligence-dotnet's IpiOnPremisePipelineBuilder.
 */
class IpIntelligenceOnPremisePipelineBuilder extends PipelineBuilder
{
    /**
     * The settings are passed straight through to IpIntelligenceOnPremise.
     * With none, the pipeline uses the engine the extension builds once,
     * when PHP starts, from the FiftyOneDegreesIpIntelligenceEngine.data_file
     * php.ini setting. A "dataFile" setting, for command-line tools only,
     * builds a new engine from the data file every time, and is refused
     * outside the command line, as IpIntelligenceOnPremise describes.
     *
     * @param array<string, mixed> $settings
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);

        $engine = new IpIntelligenceOnPremise($settings);

        $flowElements = [$engine];
        $flowElements = array_merge($flowElements, $this->flowElements);
        $this->flowElements = $flowElements;
    }
}
