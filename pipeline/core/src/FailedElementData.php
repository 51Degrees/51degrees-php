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
 * The Element Data FlowData::get gives for an element that failed during
 * processing and added none, so that asking for it does not throw. It
 * holds no values. Each Property the element lists reads as an
 * AspectPropertyValue with no value, whose reason names the element, and
 * any other name reads as null, as it does from other Element Data.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/exception-handling.md#flow-data-and-derived-accessors
 */
class FailedElementData extends ElementData
{
    /**
     * An AspectPropertyValue with no value for a Property the element lists,
     * or null for any other name.
     *
     * @return null|AspectPropertyValue
     */
    protected function getInternal(string $key)
    {
        $name = $this->listedName($key);
        if ($name === null) {
            return null;
        }

        return new AspectPropertyValue(sprintf(
            Messages::ELEMENT_FAILED,
            $name,
            $this->flowElement->dataKey
        ));
    }

    /**
     * Each Property the element lists, by its lower case name, with no
     * value.
     *
     * @return array<string, AspectPropertyValue>
     */
    public function asDictionary(): array
    {
        $values = [];
        foreach (array_keys($this->flowElement->getProperties()) as $key) {
            $value = $this->getInternal((string) $key);
            if ($value !== null) {
                $values[strtolower((string) $key)] = $value;
            }
        }

        return $values;
    }

    /**
     * The name of the Property the element lists under the key, compared
     * ignoring case with both the key it is listed under and its name, or
     * null when it lists none.
     */
    private function listedName(string $key): ?string
    {
        foreach ($this->flowElement->getProperties() as $listedKey => $property) {
            $name = is_array($property) && isset($property['name'])
                ? (string) $property['name']
                : (string) $listedKey;
            if (strcasecmp((string) $listedKey, $key) === 0 ||
                strcasecmp($name, $key) === 0) {
                return $name;
            }
        }

        return null;
    }
}
