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

namespace fiftyone\pipeline\core;

/**
 * The JSONBundler aggregates all properties from FlowElements into a JSON object
 * It is used for retrieving via an endpoint from the client
 * side via the JavaScriptBuilder and also used inside the
 * JavaScriptBuilder itself to pass properties to the client side.
 * Both this and the JavaScriptBuilder element are automatically
 * added to a Pipeline unless specifically omitted in the PipelineBuilder.
 */
class JsonBundlerElement extends FlowElement
{
    /**
     * The Data Keys of the elements whose data is left out of the JSON,
     * being this element, the JavaScript builder, the Set Headers Element
     * and the Cloud Request Engine, whose data is the cloud service's raw
     * response.
     */
    private const EXCLUDED_DATA_KEYS = [
        'jsonbundler', 'javascriptbuilder', 'set-headers', 'cloud'
    ];

    /**
     * The Property types, in lower case, whose values are shapes in
     * well-known text (WKT).
     */
    private const WKT_TYPES = ['wkt', 'wktstring', 'wkb', 'wkbr'];

    public string $dataKey = 'jsonbundler';

    /**
     * From this sequence number on, the JavaScript properties are not listed,
     * so the script stops asking for them.
     */
    private const MAX_SEQUENCE_WITH_JAVASCRIPT = 10;

    /**
     * @var array<string, mixed>
     */
    public array $json;

    /**
     * What the JSON needs from the Property metadata of the elements of each
     * Pipeline the bundler is in, worked out once for each Pipeline, because
     * an element can be in Pipelines with different elements.
     */
    private ?\SplObjectStorage $propertyCaches = null;

    /**
     * The JSONBundler accepts no Evidence. It reads the Element Data of the
     * other elements, and the sequence number the Sequence Element adds to
     * Evidence, which the Sequence Element's own filter keeps.
     */
    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter([]);
    }

    /**
     * The integer under query.sequence in Evidence, which the Sequence
     * Element adds. A value from the query string is text, so it is not a
     * sequence number.
     *
     * @throws \Exception when Evidence holds no integer sequence number
     */
    private function getSequence(FlowData $flowData): int
    {
        $sequence = $flowData->evidence->get('query.sequence');

        if (!is_int($sequence)) {
            throw new \Exception(Messages::NO_SEQUENCE);
        }

        return $sequence;
    }

    /**
     * Whether a value is a shape in well-known text, being a string whose
     * Property type is one of WKT_TYPES. The JSON holds such a value as an
     * object with one member, value, holding the text.
     *
     * @param mixed $property The Property's metadata
     * @param mixed $value
     */
    private static function isWkt($property, $value): bool
    {
        if (!is_string($value) || !is_array($property)) {
            return false;
        }

        $type = $property['type'] ?? $property['Type'] ?? null;

        return is_string($type) &&
            in_array(strtolower($type), self::WKT_TYPES, true);
    }

    /**
     * The JSONBundler extracts all properties from a FlowData and serializes them into JSON.
     */
    public function processInternal(FlowData $flowData): void
    {
        // The sequence number decides whether the JavaScript properties are
        // listed. Without one from a Sequence Element the JSON is not built.
        $sequence = $this->getSequence($flowData);

        // Get every property on every FlowElement
        // Storing JavaScript properties in an extra section

        $output = [
            'javascriptProperties' => []
        ];

        $this->propertyCaches = $this->propertyCaches ?? new \SplObjectStorage();
        $pipeline = $flowData->pipeline;
        $propertyCacheSet = $this->propertyCaches->contains($pipeline);
        $pipelineCache = $propertyCacheSet ? $this->propertyCaches[$pipeline] : [];

        foreach ($flowData->pipeline->flowElements as $flowElement) {
            if (in_array($flowElement->dataKey, self::EXCLUDED_DATA_KEYS, true)) {
                continue;
            }

            $properties = $flowElement->getProperties();

            if (!$propertyCacheSet) {
                $delayExecutionList = [];
                $delayedEvidenceProperties = [];

                // Loop over all properties and see if any have delay execution set to true
                foreach ($properties as $propertyKey => $propertyMeta) {
                    if (isset($propertyMeta['delayexecution']) && $propertyMeta['delayexecution']) {
                        $delayExecutionList[] = $propertyKey;
                    }
                }

                // Loop over all properties again and see if any have evidenceproperties which
                // have delayedExecution set to true
                foreach ($properties as $propertyKey => $propertyMeta) {
                    if (isset($propertyMeta['evidenceproperties'])) {
                        $delayedEvidencePropertiesList = array_filter(
                            $propertyMeta['evidenceproperties'],
                            function ($evidenceProperty) use ($delayExecutionList) {
                                return in_array($evidenceProperty, $delayExecutionList);
                            }
                        );

                        // Each name is lower case, as the keys are, and the
                        // names form a list, so the filter's gaps are closed.
                        if (count($delayedEvidencePropertiesList)) {
                            $delayedEvidenceProperties[$propertyKey] = array_values(array_map(
                                function ($property) use ($flowElement) {
                                    return strtolower($flowElement->dataKey . '.' . $property);
                                },
                                $delayedEvidencePropertiesList
                            ));
                        }
                    }
                }

                $pipelineCache[$flowElement->dataKey] = [
                    'delayExecutionList' => $delayExecutionList,
                    'evidenceProperties' => $delayedEvidenceProperties
                ];
            }

            $propertyCache = $pipelineCache[$flowElement->dataKey];

            // Create empty area for FlowElement properties to go. Every key in
            // the JSON is lower case.
            $dataKey = strtolower($flowElement->dataKey);
            $output[$dataKey] = [];

            foreach ($properties as $propertyKey => $property) {
                $value = null;
                $nullReason = null;

                // Check if property has any delayed execution evidence properties and set in JSON if yes
                if (isset($propertyCache['evidenceProperties'][$propertyKey])) {
                    $output[$dataKey][strtolower($propertyKey) . 'evidenceproperties'] = $propertyCache['evidenceProperties'][$propertyKey];
                }

                try {
                    /** @phpstan-var null|\fiftyone\pipeline\core\AspectPropertyValue $valueContainer */
                    $valueContainer = $flowData->get($flowElement->dataKey)->get($propertyKey);

                    // Check if value is of the aspect property value type
                    if (is_object($valueContainer) && property_exists($valueContainer, 'hasValue')) {
                        // Check if it has a value
                        if ($valueContainer->hasValue) {
                            $value = $valueContainer->value;
                        } else {
                            $nullReason = !empty($valueContainer->noValueMessage)
                                ? $valueContainer->noValueMessage
                                : AspectPropertyValue::NO_VALUE_MESSAGE;
                        }
                    } else {
                        // Standard value
                        $value = $valueContainer;
                    }
                } catch (\Exception $e) {
                    // Catching missing property exceptions and other errors
                    continue;
                }

                // Each item of a list of Element Data, such as the hardware
                // profiles, is written by the same rules as a Property.
                if (self::isElementDataList($value)) {
                    $value = self::listJson($value);
                }

                // A Property assigned no value is written as null with its
                // reason. Any other null, such as a plain null from an
                // element that is not an Engine, has no entry. Only a Property
                // with a value is marked for delayed execution.
                if ($nullReason !== null) {
                    $output[$dataKey][strtolower($propertyKey)] = null;
                    $output[$dataKey][strtolower($propertyKey) . 'nullreason'] = $nullReason;
                } elseif ($value !== null) {
                    $output[$dataKey][strtolower($propertyKey)] = self::isWkt($property, $value)
                        ? ['value' => $value]
                        : $value;
                    if (in_array($propertyKey, $propertyCache['delayExecutionList'])) {
                        $output[$dataKey][strtolower($propertyKey) . 'delayexecution'] = true;
                    }
                }

                if ($sequence < self::MAX_SEQUENCE_WITH_JAVASCRIPT) {
                    // Cloud properties come back as capitalized
                    // TODO change this, but for now
                    if (isset($property['Type'])) {
                        $property['type'] = $property['Type'];
                    }

                    if (isset($property['type']) && strtolower($property['type']) === 'javascript') {
                        $output['javascriptProperties'][] = $dataKey . '.' . strtolower($propertyKey);
                    }
                }
            }
        }

        if (!$propertyCacheSet) {
            $this->propertyCaches[$pipeline] = $pipelineCache;
        }

        $data = new ElementDataDictionary($this, ['json' => $output]);

        $flowData->setElementData($data);
    }

    /**
     * Whether a value is a list of Element Data, being a list whose items
     * are each Element Data or an array keyed by Property name, as the
     * hardware profiles are, with at least one Property among them. A list
     * of plain values, such as a list of names, is not.
     *
     * @param mixed $value
     */
    private static function isElementDataList($value): bool
    {
        if (!is_array($value) || $value === [] ||
            array_keys($value) !== range(0, count($value) - 1)) {
            return false;
        }

        $hasProperty = false;
        foreach ($value as $item) {
            if ($item instanceof ElementData) {
                $hasProperty = true;
                continue;
            }

            if (!is_array($item)) {
                return false;
            }

            foreach (array_keys($item) as $name) {
                if (!is_string($name)) {
                    return false;
                }

                $hasProperty = true;
            }
        }

        return $hasProperty;
    }

    /**
     * Each item of a list of Element Data as a JSON object.
     *
     * @param array<int, ElementData|array<string, mixed>> $items
     * @return array<int, array<string, mixed>|\stdClass>
     */
    private static function listJson(array $items): array
    {
        $json = [];
        foreach ($items as $item) {
            $json[] = self::itemJson($item);
        }

        return $json;
    }

    /**
     * One item of a list of Element Data, written by the rules for a
     * Property. A Property assigned no value is null with its nullreason, a
     * null has no entry, and every name is lower case. An item with nothing
     * to write is an empty object, so that it is still an object in the
     * JSON.
     *
     * @param ElementData|array<string, mixed> $item
     * @return array<string, mixed>|\stdClass
     */
    private static function itemJson($item)
    {
        $properties = $item instanceof ElementData
            ? $item->asDictionary()
            : $item;
        $json = [];

        foreach ($properties as $name => $property) {
            $name = strtolower((string) $name);
            $value = $property;

            if (is_object($property) && property_exists($property, 'hasValue')) {
                if (!$property->hasValue) {
                    $json[$name] = null;
                    $json[$name . 'nullreason'] = !empty($property->noValueMessage)
                        ? $property->noValueMessage
                        : AspectPropertyValue::NO_VALUE_MESSAGE;

                    continue;
                }

                $value = $property->value;
            }

            if ($value === null) {
                continue;
            }

            if (self::isElementDataList($value)) {
                $value = self::listJson($value);
            }

            $json[$name] = $value;
        }

        return $json === [] ? new \stdClass() : $json;
    }
}
