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
use fiftyone\pipeline\engines\AspectData;

class SwigData extends AspectData
{
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

    public function __isset($key)
    {
        return $this->flowElement->findProperty($key) !== null;
    }

    /**
     * What to hand a native value accessor for a property.
     *
     * An engine built with a list of names gives the property's index in
     * that list, which is fixed at build and so names the same property
     * after any reload, and null for a name outside the list. An engine
     * built with no names has no index table and gives the name back, which
     * is the behaviour it had before indexes were used.
     *
     * @return null|int|string
     */
    private function readKey(string $name)
    {
        if ($this->flowElement->propertyIndexes === []) {
            return $name;
        }

        return $this->flowElement->getPropertyIndex($name);
    }

    public function getInternal($key)
    {
        $property = $this->flowElement->findProperty($key);

        if ($property !== null) {
            if ($property['category'] == 'DeviceMetrics') {
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
                        $useragents = [];
                        $count = $this->result->getUserAgents();
                        for ($i = 0; $i < $count; ++$i) {
                            $useragents[] = $this->result->getUserAgent($i);
                        }
                        $result = new AspectPropertyValue(
                            null,
                            $useragents
                        );
                        break;
                    case 'Method':
                        $result = new AspectPropertyValue(
                            null,
                            $this->result->getDifference()
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
                // Read by the property's index in the list the engine was
                // built with, which saves the native code a name lookup on
                // every read.
                //
                // An engine built with no names has no index table, so the
                // read falls back to the name. A property that is in the
                // data set but outside a build-time list has no index and so
                // carries no value.
                $readKey = $this->readKey($property['name']);
                if ($readKey === null) {
                    return new AspectPropertyValue(sprintf(
                        Messages::PROPERTY_NOT_BUILT,
                        $property['name']
                    ));
                }

                switch ($property['type']) {
                    case 'Boolean':
                        $value = $this->result->getValueAsBool($readKey);
                        break;
                    case 'String':
                        $value = $this->result->getValueAsString($readKey);
                        break;
                    case 'JavaScript':
                        $value = $this->result->getValueAsString($readKey);
                        break;
                    case 'Integer':
                        $value = $this->result->getValueAsInteger($readKey);
                        break;
                    case 'Double':
                        $value = $this->result->getValueAsDouble($readKey);
                        break;
                    case 'Array':
                        $value = $this->result->getValues($readKey);
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
}
