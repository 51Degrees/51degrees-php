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

namespace fiftyone\pipeline\cloudrequestengine;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\CloudEngineBase;

/**
 * This is a template for all 51Degrees cloud engines.
 * It requires the 51Degrees cloudRequestEngine to be placed in a
 *  pipeline before it. It takes that raw JSON response and
 * parses it to extract the device part.
 * It also uses this data to generate a list of properties. It reads no
 * Evidence, so it accepts none.
 */
class CloudEngine extends CloudEngineBase
{
    public string $dataKey = 'CloudEngineBase'; // This should be overridden

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $aspectProperties;

    /**
     * Whether the missing product has been logged for this engine.
     */
    private bool $missingProductLogged = false;

    /**
     * Callback called when an engine is added to a pipeline.
     *
     * @throws \Exception
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        if (isset($pipeline->flowElementsList['cloud'])) {
            return;
        }

        throw new \Exception(Constants::CLOUDREQUESTENGINE_EXCEPTION_MESSAGE);
    }

    /**
     * A cloud engine reads only the Cloud Request Engine's response, so it
     * accepts no Evidence.
     */
    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter([]);
    }

    public function processInternal(FlowData $flowData): void
    {
        if (isset($flowData->pipeline->flowElementsList['cloud']->flowElementProperties[$this->dataKey])) {
            // set up properties list for the element from data in the CloudRequestEngine
            $this->properties = $flowData->pipeline->flowElementsList['cloud']->flowElementProperties[$this->dataKey];
        }

        $cloudData = $flowData->get('cloud')->get('cloud');

        if ($cloudData) {
            $cloudData = json_decode($cloudData, true);

            $result = [];

            // A Resource Key with no product for this engine is not an error.
            // The engine gives empty data, and says so once.
            if (!isset($cloudData[$this->dataKey]) || !is_array($cloudData[$this->dataKey])) {
                $this->logMissingProduct($flowData);
                $flowData->setElementData(new AspectDataDictionary($this, $result));

                return;
            }

            foreach ($cloudData[$this->dataKey] as $key => $value) {
                if (isset($cloudData[$this->dataKey][$key . 'nullreason'])) {
                    $result[$key] = new AspectPropertyValue($cloudData[$this->dataKey][$key . 'nullreason']);
                } else {
                    $result[$key] = new AspectPropertyValue(null, $value);
                }
            }

            $data = new AspectDataDictionary($this, $result);

            $flowData->setElementData($data);
        }
    }

    /**
     * Log, on the first request only, that the Resource Key has no product
     * for this engine.
     */
    private function logMissingProduct(FlowData $flowData): void
    {
        if ($this->missingProductLogged) {
            return;
        }

        $this->missingProductLogged = true;
        $flowData->pipeline->log('error', sprintf(
            "The Resource Key includes no product for '%s', so the engine gives no data.",
            $this->dataKey
        ));
    }
}
