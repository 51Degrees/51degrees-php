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

namespace fiftyone\pipeline\devicedetection;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ProvidesValuesAsString;
use fiftyone\pipeline\engines\AspectData;

/**
 * Extension of AspectData that reads each property from the results the
 * SWIG wrapper of the on-premise device detection engine gives.
 *
 * The data file holds every value as a string, which the native results
 * parse into the type the property declares. get() gives the parsed value
 * and getValueAsString() the string as stored.
 *
 * SwigData, and the native results it holds, is not safe to use from
 * several threads at once, because reading a value writes into the native
 * results, and SwigData takes no lock. The caller is responsible for
 * thread-safe access, so a caller that shares an instance between threads
 * coordinates that itself.
 */
class SwigData extends AspectData implements ProvidesValuesAsString
{
    /**
     * The names of the C library's match methods, in the order of its
     * fiftyoneDegreesHashMatchMethod values, which getMethod returns.
     */
    private const MATCH_METHODS = ['NONE', 'PERFORMANCE', 'COMBINED', 'PREDICTIVE'];

    private $result;

    public function __construct($engine, $result)
    {
        $this->result = $result;

        parent::__construct(...func_get_args());
    }

    /**
     * Release the native results, which keep the engine's data in memory
     * while they are held, even after the data has been refreshed. Values
     * already read are unaffected, and reading a property afterwards throws
     * an exception. Closing the FlowData closes its results.
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

    /**
     * Get the string the data file holds for a property, as well as the
     * parsed value get() gives. For example, "Unknown" in a Boolean property
     * is false through get() and "Unknown" here. A property with no value,
     * or one the data does not hold, is reported as get() reports it.
     * @throws \Exception
     */
    public function getValueAsString(string $key): AspectPropertyValue
    {
        if ($this->result === null) {
            throw new \Exception(Messages::RESULTS_CLOSED);
        }

        try {
            $result = $this->getStoredString($key);
        } catch (\Exception $e) {
            $result = null;
        }

        if ($result === null) {
            return $this->missingPropertyService->check($key, $this->flowElement);
        }

        return $result;
    }

    public function __isset($key)
    {
        return $this->flowElement->findProperty($key) !== null;
    }

    public function getInternal($key)
    {
        $property = $this->flowElement->findProperty($key);

        if ($property !== null) {
            if ($property['category'] === DeviceDetectionOnPremise::METRICS_CATEGORY) {
                switch ($property['name']) {
                    case 'MatchedNodes':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getMatchedNodes()
                        );
                        break;
                    case 'Difference':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getDifference()
                        );
                        break;
                    case 'Drift':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getDrift()
                        );
                        break;
                    case 'DeviceId':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getDeviceId()
                        );
                        break;
                    case 'UserAgents':
                        // Each matched User-Agent once, as more than one
                        // result can give the same one.
                        $useragents = [];
                        $count = $this->result->getUserAgents();
                        for ($i = 0; $i < $count; ++$i) {
                            $useragent = $this->result->getUserAgent($i);
                            if (!in_array($useragent, $useragents, true)) {
                                $useragents[] = $useragent;
                            }
                        }
                        $result = new AspectPropertyValue(
                            null,
                            $useragents
                        );
                        break;
                    case 'Method':
                        $result = new AspectPropertyValue(
                            null,
                            self::MATCH_METHODS[$this->result->getMethod()] ?? 'NONE'
                        );
                        break;
                    case 'Iterations':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getIterations()
                        );
                        break;
                }
            } else {
                switch ($property['type']) {
                    case 'Boolean':
                        $value = $this->result->getValueAsBool($property['name']);
                        break;
                    case 'String':
                        $value = $this->result->getValueAsString($property['name']);
                        break;
                    case 'JavaScript':
                        $value = $this->result->getValueAsString($property['name']);
                        break;
                    case 'Integer':
                        $value = $this->result->getValueAsInteger($property['name']);
                        break;
                    case 'Double':
                        $value = $this->result->getValueAsDouble($property['name']);
                        break;
                    case 'Array':
                        $value = $this->result->getValues($property['name']);
                        break;
                }

                if ($value->hasValue()) {
                    if ($property['type'] == 'Array') {
                        $result = new AspectPropertyValue(null, SwigHelpers::vectorToArray($value->getValue()));
                    } else {
                        $result = new AspectPropertyValue(null, $value->getValue());
                    }
                } else {
                    $result = new AspectPropertyValue($value->getNoValueMessage());
                }
            }

            return $result;
        }
    }

    /**
     * The stored string of a property, or null when the data does not hold
     * the property. A match metric is worked out for each request rather
     * than stored, so only a string metric, whose value is the same either
     * way, gives one.
     */
    private function getStoredString(string $key): ?AspectPropertyValue
    {
        $property = $this->flowElement->findProperty($key);

        if ($property === null) {
            return null;
        }

        if ($property['category'] === DeviceDetectionOnPremise::METRICS_CATEGORY) {
            if ($property['type'] === 'String') {
                return $this->getInternal($key);
            }

            return new AspectPropertyValue(
                sprintf(Messages::METRIC_NOT_STORED, $property['name'])
            );
        }

        $value = $this->result->getValueAsString($property['name']);

        return $value->hasValue()
            ? new AspectPropertyValue(null, $value->getValue())
            : new AspectPropertyValue($value->getNoValueMessage());
    }
}
