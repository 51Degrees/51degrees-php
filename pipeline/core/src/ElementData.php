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
 * Stores information created by a FlowElement based on FlowData.
 * Stored in FlowData.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/conceptual-overview.md#element-data
 */
class ElementData
{
    public FlowElement $flowElement;

    public function __construct(FlowElement $flowElement)
    {
        $this->flowElement = $flowElement;
    }

    /**
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * Get a value from the ElementData contents
     * This calls the ElementData class' (often overridden) getInternal method.
     *
     * @param string $key Property name
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->getInternal($key);
    }

    /**
     * Get the values contained in the ElementData instance as a dictionary
     * of keys and values.
     *
     * @return array<string, mixed>
     */
    public function asDictionary(): array
    {
        return [];
    }

    /**
     * The value of a Property as a string. For an AspectPropertyValue it is
     * the value held.
     *
     * @throws \Exception when the Property is not there, has no value, in
     *   which case the message is the reason, or is not a single value
     */
    public function getAsString(string $key): string
    {
        return strval($this->singleValue($key, 'a string'));
    }

    /**
     * The value of a Property as a float. For an AspectPropertyValue it is
     * the value held.
     *
     * @throws \Exception when the Property is not there, has no value, in
     *   which case the message is the reason, or is not a single value
     */
    public function getAsFloat(string $key): float
    {
        return floatval($this->singleValue($key, 'a float'));
    }

    /**
     * The value of a Property as an int. For an AspectPropertyValue it is
     * the value held.
     *
     * @throws \Exception when the Property is not there, has no value, in
     *   which case the message is the reason, or is not a single value
     */
    public function getAsInteger(string $key): int
    {
        return intval($this->singleValue($key, 'an integer'));
    }

    /**
     * The single value of a Property, from inside an AspectPropertyValue
     * where it is one, for the typed getters to convert.
     *
     * @param string $type The type asked for, for the message
     * @return bool|float|int|string
     * @throws \Exception when the Property is not there, has no value or is
     *   not a single value
     */
    private function singleValue(string $key, string $type)
    {
        $value = $this->get($key);
        if ($value === null) {
            throw new \Exception(sprintf(
                Messages::PROPERTY_NOT_FOUND,
                $key,
                $this->flowElement->dataKey
            ));
        }

        if ($value instanceof AspectPropertyValue) {
            $value = $value->value;
        }

        if (!is_scalar($value)) {
            throw new \Exception(sprintf(Messages::PROPERTY_NOT_SCALAR, $key, $type));
        }

        return $value;
    }

    /**
     * Called by the get() method
     * Returns the requested property from the data.
     *
     * @param string $key Property name
     * @return mixed
     */
    protected function getInternal(string $key)
    {
        return;
    }
}
