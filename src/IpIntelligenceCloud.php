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

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\core\FlowData;

/**
 * The IP intelligence cloud engine requires the 51Degrees
 * CloudRequestEngine to be placed in a pipeline before it. It takes the
 * raw JSON response produced by the CloudRequestEngine and parses out the
 * IP intelligence segment, which is keyed "ip" - matching
 * ip-intelligence-dotnet's IpiCloudEngine.ElementDataKey and the cloud
 * service JSON.
 *
 * Weighted properties (for example Mcc, CountryCodesGeographical and
 * CountryCodesPopulation) are returned by the cloud service as a list of
 * {"value": ..., "rawweighting": ...} objects, where "rawweighting" is the
 * 0 to 65535 integer that .NET exposes as IWeightedValue.RawWeighting. The
 * base CloudEngine wraps these nested structures verbatim in an
 * AspectPropertyValue, so callers receive a plain PHP array of those
 * objects, the same shape the on-premise engine produces (see SwigData).
 */
class IpIntelligenceCloud extends CloudEngine
{
    public string $dataKey = Constants::ELEMENT_DATA_KEY;

    /**
     * Process the cloud response for IP intelligence.
     *
     * Guards against a pipeline assembled without a CloudRequestEngine, and
     * against a resource key that has not been authorised for IP intelligence
     * data, before delegating to the base implementation.
     *
     * @throws \Exception
     */
    public function processInternal(FlowData $flowData): void
    {
        // IpIntelligencePipelineBuilder always places a CloudRequestEngine
        // first, but a manually assembled pipeline need not, and dereferencing
        // a missing 'cloud' element would raise a bare Error instead of this
        // message.
        $cloudRequestEngine = $flowData->pipeline->flowElementsList['cloud'] ?? null;
        if ($cloudRequestEngine === null) {
            throw new \Exception('IP intelligence data was not available. The IP intelligence cloud engine requires a CloudRequestEngine to be placed in the pipeline before it.');
        }

        if (!array_key_exists($this->dataKey, $cloudRequestEngine->flowElementProperties)) {
            throw new \Exception('IP intelligence data was not available. Check that the resource key being used is authorised for IP intelligence data.');
        }

        parent::processInternal($flowData);
    }
}
