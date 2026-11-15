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
use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
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
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/cloud-aspect-engine.md
 */
class CloudEngine extends CloudEngineBase
{
    public string $dataKey = 'CloudEngineBase'; // This should be overridden

    /**
     * The Property types, in lower case, whose values are shapes in
     * well-known text (WKT).
     */
    private const WKT_TYPES = ['wkt', 'wktstring', 'wkb', 'wkbr'];

    /**
     * The keys of each item of a weighted value, in the order the
     * on-premise Engines give them.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/weighted-values.md#serializing
     */
    private const WEIGHTED_VALUE_KEYS = ['value', 'rawweighting'];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $aspectProperties;

    /**
     * Whether the missing product has been logged for this engine.
     */
    private bool $missingProductLogged = false;

    /**
     * Callback called when an engine is added to a pipeline. The engine
     * takes its properties from the Cloud Request Engine, which normally
     * has them once it is built, and logs a missing product then. Where it
     * has none yet, processInternal does both on first use.
     *
     * @throws \Exception when the pipeline has no Cloud Request Engine
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        $cloudRequestEngine = self::cloudRequestEngine($pipeline);
        if ($cloudRequestEngine !== null) {
            if (isset($cloudRequestEngine->flowElementProperties[$this->dataKey])) {
                $this->properties = $cloudRequestEngine->flowElementProperties[$this->dataKey];
            } elseif (
                $cloudRequestEngine instanceof CloudRequestEngine &&
                $cloudRequestEngine->propertiesLoaded()
            ) {
                $this->logMissingProductIn($pipeline);
            }

            return;
        }

        throw new \Exception(Constants::CLOUDREQUESTENGINE_EXCEPTION_MESSAGE);
    }

    /**
     * The Cloud Request Engine in a pipeline, found by its Data Key, or by
     * the deprecated key for an element still registered under that one.
     * Its data is read by its own Data Key, so neither logs a deprecation.
     */
    protected static function cloudRequestEngine(Pipeline $pipeline): ?FlowElement
    {
        return $pipeline->flowElementsList[Constants::DATA_KEY]
            ?? $pipeline->flowElementsList[Constants::DEPRECATED_DATA_KEY]
            ?? null;
    }

    /**
     * The raw JSON response the Cloud Request Engine got for the FlowData.
     *
     * @return mixed
     * @throws \Exception when the FlowData holds no response
     */
    protected static function cloudResponse(FlowData $flowData)
    {
        $cloudRequestEngine = self::cloudRequestEngine($flowData->pipeline);

        return CloudRequestData::jsonResponseOf($flowData->get(
            $cloudRequestEngine->dataKey ?? Constants::DATA_KEY
        ));
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
        $cloudRequestEngine = self::cloudRequestEngine($flowData->pipeline);
        if (isset($cloudRequestEngine->flowElementProperties[$this->dataKey])) {
            // set up properties list for the element from data in the CloudRequestEngine
            $this->properties = $cloudRequestEngine->flowElementProperties[$this->dataKey];
        }

        if ($this->setDataForFailedRequest($flowData)) {
            return;
        }

        $cloudData = self::cloudResponse($flowData);

        $this->logMissingProduct($flowData);

        if ($cloudData) {
            $cloudData = json_decode($cloudData, true);

            $result = [];

            // A response with no portion for this engine gives empty data,
            // and is not an error.
            if (!isset($cloudData[$this->dataKey]) || !is_array($cloudData[$this->dataKey])) {
                $flowData->setElementData(new AspectDataDictionary($this, $result));

                return;
            }

            foreach ($cloudData[$this->dataKey] as $key => $value) {
                if (isset($cloudData[$this->dataKey][$key . 'nullreason'])) {
                    $result[$key] = new AspectPropertyValue($cloudData[$this->dataKey][$key . 'nullreason']);
                } else {
                    $result[$key] = new AspectPropertyValue(
                        null,
                        self::weightedValues($this->wktText((string) $key, $value))
                    );
                }
            }

            $data = new AspectDataDictionary($this, $result);

            $flowData->setElementData($data);
        }
    }

    /**
     * The text of a shape in well-known text (WKT), which the cloud service
     * gives as an object with one member, value, holding the text, so that
     * the value is the text the on-premise Engine gives. Any other value is
     * given as it is.
     *
     * @param mixed $value
     * @return mixed
     */
    private function wktText(string $key, $value)
    {
        $property = $this->properties[strtolower($key)] ?? [];
        $type = $property['type'] ?? $property['Type'] ?? null;
        if (!is_string($type) || !in_array(strtolower($type), self::WKT_TYPES, true)) {
            return $value;
        }

        if (is_array($value) && array_keys($value) === ['value'] && is_string($value['value'])) {
            return $value['value'];
        }

        return $value;
    }

    /**
     * A weighted value, being a list whose every item holds only the keys
     * value and rawweighting, with each item's keys in the order the
     * on-premise Engines give them, whatever order the JSON holds them in.
     * The two Engines then give identical arrays. Any other value is given
     * as it is.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/README.md#engine
     *
     * @param mixed $value
     * @return mixed
     */
    private static function weightedValues($value)
    {
        if (!is_array($value) || $value === [] ||
            array_keys($value) !== range(0, count($value) - 1)) {
            return $value;
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item) || count($item) !== count(self::WEIGHTED_VALUE_KEYS)) {
                return $value;
            }

            $ordered = [];
            foreach (self::WEIGHTED_VALUE_KEYS as $key) {
                if (!array_key_exists($key, $item)) {
                    return $value;
                }
                $ordered[$key] = $item[$key];
            }
            $items[] = $ordered;
        }

        return $items;
    }

    /**
     * Log once, at warning level, when the products the Cloud Request Engine
     * found hold nothing for this engine's Data Key. A Resource Key with no
     * product for the engine is not an error. Call this after reading the
     * Cloud Request Engine's Element Data, because the products are found
     * before that data is set.
     */
    protected function logMissingProduct(FlowData $flowData): void
    {
        $this->logMissingProductIn($flowData->pipeline);
    }

    private function logMissingProductIn(Pipeline $pipeline): void
    {
        $products = self::cloudRequestEngine($pipeline)->flowElementProperties ?? [];
        if ($this->missingProductLogged || isset($products[$this->dataKey])) {
            return;
        }

        $this->missingProductLogged = true;
        $pipeline->log('warning', sprintf(
            "The Resource Key includes no product for '%s', so the engine gives no data.",
            $this->dataKey
        ));
    }

    /**
     * Where the Cloud Request Engine's request for the FlowData failed, so
     * that it has an error and no response, sets Element Data with no
     * values whose every property gives the CloudRequestFailed reason, and
     * returns true. The Cloud Request Engine's error stays the FlowData's
     * error.
     */
    protected function setDataForFailedRequest(FlowData $flowData): bool
    {
        $cloudRequestEngine = self::cloudRequestEngine($flowData->pipeline);
        if (
            $cloudRequestEngine === null ||
            isset($flowData->data[$cloudRequestEngine->dataKey]) ||
            !isset($flowData->errors[$cloudRequestEngine->dataKey])
        ) {
            return false;
        }

        $flowData->setElementData(new CloudRequestFailedData($this));

        return true;
    }

    /**
     * Element Data set for a failed cloud request is not cached, so that
     * the next request with the same Evidence asks the cloud service again.
     */
    protected function holdsValueForOneRequest(ElementData $data): bool
    {
        return $data instanceof CloudRequestFailedData;
    }
}
