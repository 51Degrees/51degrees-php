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
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\engines\AspectDataDictionary;

/**
 * The cloud engine for the `derived` member of the cloud response, which
 * holds HumanConfidence and HumanConfidenceSource. On a request that
 * carries a sealed Human Confidence result, the Cloud Request Engine
 * redeems it through its call, and this engine gives the two Properties as
 * the specification's table says, so a consumer reads them and never
 * redeems. On any other request it is a cloud engine like the others.
 *
 * Unlike the other cloud engines, it can be added to a Pipeline that has
 * no Cloud Request Engine. It then gives only the two Properties: Medium
 * and Active on a request that carries a sealed result, and no value, with
 * the reason, on any other request.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#when-humanconfidence-arrives-sealed
 */
class DerivedCloudEngine extends CloudEngine
{
    public string $dataKey = SealedHumanConfidence::DATA_KEY;

    /**
     * Why the two Properties have no value on a request with no sealed
     * result, in a Pipeline with no Cloud Request Engine.
     */
    public const NO_CLOUD_REQUEST_ENGINE_MESSAGE =
        'The Pipeline has no Cloud Request Engine, so %s has a value only ' .
        'on a request that carries a sealed Human Confidence result, in ' .
        'the Evidence query.humanconfidence. Add a Cloud Request Engine ' .
        'before this engine to get the value from the cloud service.';

    /**
     * Takes the Properties from the Cloud Request Engine, as any cloud
     * engine does. In a Pipeline with no Cloud Request Engine it lists only
     * HumanConfidence and HumanConfidenceSource, and is not refused.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#where-the-sealed-result-is-not-redeemed
     *
     * @throws \Exception as CloudEngine::onRegistration does, where the
     *   Pipeline has a Cloud Request Engine
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        if (self::cloudRequestEngine($pipeline) !== null) {
            parent::onRegistration($pipeline);

            return;
        }

        $this->properties = [];
        foreach ([SealedHumanConfidence::VALUE_PROPERTY => 'HumanConfidence',
            SealedHumanConfidence::SOURCE_PROPERTY => 'HumanConfidenceSource'] as $key => $name) {
            $this->properties[$key] = ['name' => $name, 'type' => 'String'];
        }
        $this->propertyMetadataLoaded = true;
        $this->propertyListRequestFailed = false;
    }

    /**
     * A request that carries a sealed Human Confidence result is neither
     * served from nor stored in a cache set on this engine, as the Cloud
     * Request Engine's own cache isn't for one. Its Value was redeemed for
     * that request alone, and the engine's cache key holds no Evidence, so
     * a cached Value would be served to every later request. Nor is one
     * that carries a humanconfidence under another key. Every other
     * request is processed as before.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/caching.md#values-created-for-one-request
     *
     * @throws \Exception as processInternal does
     */
    public function process(FlowData $flowData): void
    {
        if (SealedHumanConfidence::in($flowData) !== null || SealedHumanConfidence::elsewhereIn($flowData)) {
            $this->processInternal($flowData);

            return;
        }

        parent::process($flowData);
    }

    /**
     * Reads the `derived` member, and on a request that carries a sealed
     * result gives HumanConfidenceSource Active and HumanConfidence the
     * redeemed Value, or Medium wherever the sealed result was not
     * redeemed. Neither read throws then, even after a failed request.
     *
     * @throws \Exception for a response that can't be read, as any cloud
     *   engine does, on a request that carries no sealed result
     */
    public function processInternal(FlowData $flowData): void
    {
        if (self::cloudRequestEngine($flowData->pipeline) === null) {
            $this->processWithoutCloudRequestEngine($flowData);

            return;
        }

        if (SealedHumanConfidence::in($flowData) === null) {
            parent::processInternal($flowData);

            return;
        }

        try {
            parent::processInternal($flowData);
        } catch (\Throwable $e) {
            // The two Properties are given below whatever the response
            // held, and the read adds no error of its own.
        }

        $given = [
            SealedHumanConfidence::VALUE_PROPERTY =>
                new AspectPropertyValue(null, $this->redeemedValue($flowData)),
            SealedHumanConfidence::SOURCE_PROPERTY =>
                new AspectPropertyValue(null, SealedHumanConfidence::SOURCE),
        ];

        $data = $flowData->tryGet($this->dataKey);
        if ($data instanceof CloudRequestFailedData) {
            $flowData->setElementData(new SealedResultFailedData($this, $given));

            return;
        }

        $contents = $data instanceof AspectDataDictionary ? $data->asDictionary() : [];
        foreach (array_keys($contents) as $name) {
            $lower = strtolower((string) $name);
            foreach (array_keys($given) as $property) {
                if ($lower === $property || $lower === $property . 'nullreason') {
                    unset($contents[$name]);
                }
            }
        }
        $flowData->setElementData(new AspectDataDictionary($this, $contents + $given));
    }

    /**
     * With no Cloud Request Engine nothing is redeemed. A request that
     * carries a sealed result gives Medium and Active, and any other gives
     * the two Properties with no value and the reason. Neither adds an
     * error.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#where-the-sealed-result-is-not-redeemed
     */
    private function processWithoutCloudRequestEngine(FlowData $flowData): void
    {
        if (SealedHumanConfidence::in($flowData) !== null) {
            $given = [
                SealedHumanConfidence::VALUE_PROPERTY =>
                    new AspectPropertyValue(null, SealedHumanConfidence::NOT_REDEEMED),
                SealedHumanConfidence::SOURCE_PROPERTY =>
                    new AspectPropertyValue(null, SealedHumanConfidence::SOURCE),
            ];
        } else {
            $given = [];
            foreach ($this->properties as $key => $property) {
                $given[$key] = new AspectPropertyValue(
                    sprintf(self::NO_CLOUD_REQUEST_ENGINE_MESSAGE, $property['name'])
                );
            }
        }
        $flowData->setElementData(new AspectDataDictionary($this, $given));
    }

    /**
     * The Value the response gives for the sealed result, or Medium where
     * the call didn't carry it or the response gives none.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#where-the-sealed-result-is-not-redeemed
     */
    private function redeemedValue(FlowData $flowData): string
    {
        $cloudRequestEngine = self::cloudRequestEngine($flowData->pipeline);
        $request = $cloudRequestEngine === null
            ? null
            : $flowData->tryGet($cloudRequestEngine->dataKey);
        if (!($request instanceof CloudRequestData) || !$request->sealedResultSent()) {
            return SealedHumanConfidence::NOT_REDEEMED;
        }

        return SealedHumanConfidence::valueFrom(
            (string) CloudRequestData::jsonResponseOf($request)
        );
    }
}
