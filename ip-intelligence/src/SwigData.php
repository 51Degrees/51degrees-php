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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\engines\AspectData;

/**
 * Extension of AspectData for retrieving results created by the SWIG
 * wrapper of the on-premise IP intelligence engine.
 *
 * Property values (or the lack of one) are returned in an
 * AspectPropertyValue. Weighted properties (for example Mcc) are returned
 * as a list of ['value' => ..., 'rawweighting' => ...] arrays, matching the
 * shape returned by the cloud engine.
 */
class SwigData extends AspectData
{
    private $result;

    private ?string $echoIp;

    private ?string $echoIpv6;

    public function __construct($engine, $result, ?string $echoIp = null, ?string $echoIpv6 = null)
    {
        $this->result = $result;
        $this->echoIp = $echoIp;
        $this->echoIpv6 = $echoIpv6;

        parent::__construct($engine);
    }

    /**
     * Release the native results, which keep the engine's data in memory
     * while they are held, even after the engine is closed or its data is
     * refreshed. Values already read are unaffected, and reading a property
     * afterwards throws an exception. Closing the FlowData closes its
     * results.
     */
    public function close(): void
    {
        $this->result = null;
    }

    /**
     * Get a property value, once the results are known to be open.
     * Checked here, because AspectData::get turns an exception from
     * getInternal into a missing property message.
     * @throws \Exception
     */
    public function get(string $key)
    {
        if ($this->result === null) {
            throw new \Exception(Messages::RESULTS_CLOSED);
        }

        return parent::get($key);
    }

    public function __isset($key)
    {
        return isset($this->flowElement->properties[strtolower($key)]);
    }

    public function getInternal($key)
    {
        $key = strtolower($key);

        // Special echo properties - the IP address supplied as evidence is
        // echoed back in the results (ip-intelligence-dotnet parity).
        if ($key === 'ip') {
            return $this->echoIp !== null
                ? new AspectPropertyValue(null, $this->echoIp)
                : new AspectPropertyValue('IPv4 was not supplied as evidence.');
        }

        if ($key === 'ipv6') {
            return $this->echoIpv6 !== null
                ? new AspectPropertyValue(null, $this->echoIpv6)
                : new AspectPropertyValue('IPv6 was not supplied as evidence.');
        }

        if (!isset($this->flowElement->properties[$key])) {
            return null;
        }

        $property = $this->flowElement->properties[$key];
        $name = $property['name'];
        $type = strtolower((string) $property['type']);

        // Read by the property's index in the list the engine was built
        // with, which saves the native code a name lookup on every read. A
        // property that is in the data set but outside that list has no
        // index and so carries no value.
        $readKey = $this->readKey($name);
        if ($readKey === null) {
            return new AspectPropertyValue(sprintf(Messages::PROPERTY_NOT_BUILT, $name));
        }

        // Mcc is weighted even though its metadata type is "string"
        // (ip-intelligence-dotnet parity).
        if ($type === 'string' && strtolower($name) === 'mcc') {
            $type = 'weightedstring';
        }

        switch ($type) {
            case 'weightedstring':
            case 'weightedwkbr':
            case 'weightedwkb':
                return $this->weightedListToValue($this->result->getValuesAsWeightedStringList($readKey));
            case 'weightedint':
                return $this->weightedListToValue($this->result->getValuesAsWeightedIntegerList($readKey));
            case 'weighteddouble':
            case 'weightedsingle':
                return $this->weightedListToValue($this->result->getValuesAsWeightedDoubleList($readKey));
            case 'weightedbool':
                return $this->weightedListToValue($this->result->getValuesAsWeightedBoolList($readKey));
            case 'wkb':
            case 'wkbr':
            case 'ipaddress':
            case 'string':
            case 'javascript':
                // WKB geometry (e.g. Areas) and IP address properties are
                // exposed via their string form, matching the .NET binding.
                return $this->singleValue($this->result->getValueAsString($readKey));
            case 'int':
                return $this->singleValue($this->result->getValueAsInteger($readKey));
            case 'double':
            case 'single':
                return $this->singleValue($this->result->getValueAsDouble($readKey));
            case 'bool':
                return $this->singleValue($this->result->getValueAsBool($readKey));
            case 'string[]':
                $value = $this->result->getValues($readKey);
                if ($value->hasValue()) {
                    return new AspectPropertyValue(null, SwigHelpers::vectorToArray($value->getValue()));
                }

                return new AspectPropertyValue($value->getNoValueMessage());
            default:
                return $this->singleValue($this->result->getValueAsString($readKey));
        }
    }

    /**
     * What to hand a native value accessor for a property.
     *
     * The property's index in the list of names the engine was built with,
     * which is fixed at build and so names the same property after any
     * reload, or null for a name outside the list.
     *
     * @return null|int
     */
    private function readKey(string $name)
    {
        return $this->flowElement->getPropertyIndex($name);
    }

    /**
     * Convert a SWIG wrapped Value to an AspectPropertyValue.
     *
     * @param object $value SWIG Value wrapper with hasValue()/getValue()
     */
    private function singleValue($value): AspectPropertyValue
    {
        if ($value->hasValue()) {
            return new AspectPropertyValue(null, $value->getValue());
        }

        return new AspectPropertyValue($value->getNoValueMessage());
    }

    /**
     * Convert a SWIG wrapped weighted value list to a list of
     * ['value' => ..., 'rawweighting' => ...] arrays wrapped in an
     * AspectPropertyValue.
     *
     * @param object $value SWIG Value wrapper holding a weighted value list
     */
    private function weightedListToValue($value): AspectPropertyValue
    {
        if (!$value->hasValue()) {
            return new AspectPropertyValue($value->getNoValueMessage());
        }

        $items = [];
        $list = $value->getValue();
        for ($i = 0; $i < $list->size(); ++$i) {
            $weighted = $list->get($i);
            $items[] = [
                Constants::WEIGHTED_VALUE_KEY => $weighted->getValue(),
                Constants::WEIGHTED_RAW_WEIGHTING_KEY => self::rawWeighting($weighted),
            ];
        }

        return new AspectPropertyValue(null, $items);
    }

    /**
     * The 0-65535 raw weighting.
     *
     * The fallback covers a wrapper generated before getShortRawWeight()
     * existed: getWeight() is normalised against 65535^2, not 65535, so
     * scaling by 65535 is what recovers the ushort.
     *
     * @param object $weighted SWIG WeightedValue*Swig wrapper
     */
    private static function rawWeighting($weighted): int
    {
        if (method_exists($weighted, 'getShortRawWeight')) {
            return (int) $weighted->getShortRawWeight();
        }

        return (int) round($weighted->getWeight() * 65535);
    }
}
