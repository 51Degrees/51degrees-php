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

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\engines\AspectDataDictionary;

/**
 * The Cloud Request Engine's Element Data, holding the raw JSON response as
 * its one Property, json-response. The Property's deprecated name, cloud,
 * gives the same value, and the engine logs the first read by that name.
 *
 * @property CloudRequestEngine $flowElement
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/cloud-request-engine.md#element-data
 */
class CloudRequestData extends AspectDataDictionary
{
    public function __construct(CloudRequestEngine $engine, string $jsonResponse)
    {
        parent::__construct(
            $engine,
            [Constants::JSON_RESPONSE_PROPERTY => $jsonResponse]
        );
    }

    /**
     * @return mixed
     */
    public function getInternal(string $key)
    {
        if (strcasecmp($key, Constants::DEPRECATED_JSON_RESPONSE_PROPERTY) === 0) {
            $this->flowElement->reportDeprecatedProperty();
            $key = Constants::JSON_RESPONSE_PROPERTY;
        }

        return parent::getInternal($key);
    }

    /**
     * The raw JSON response in Element Data from the Cloud Request Engine or
     * from an element standing in for it. Element Data not made by this
     * class, such as a stand-in's, or a result an older version put in a
     * cache, can hold it under the Property's deprecated name, which is read
     * when the new one is not there.
     *
     * @return mixed
     */
    public static function jsonResponseOf(ElementData $data)
    {
        $contents = $data->asDictionary();
        if (array_key_exists(Constants::JSON_RESPONSE_PROPERTY, $contents)) {
            return $contents[Constants::JSON_RESPONSE_PROPERTY];
        }

        return $data->get(Constants::DEPRECATED_JSON_RESPONSE_PROPERTY);
    }
}
