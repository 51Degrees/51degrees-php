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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

/**
 * Shared constants and canned cloud responses used by the IP intelligence
 * tests. The fixtures mirror the equivalent ones in 51degrees-python
 * so that the PHP cloud engine is exercised against the same JSON shapes.
 */
class Constants
{
    // The environment variables the tests read a resource key and a data
    // file from.
    public const RESOURCE_KEY_VARIABLE = '_51DEGREES_RESOURCE_KEY';
    public const IPI_PATH_VARIABLE = '_51DEGREES_IPI_PATH';

    /**
     * Read for the resource key when RESOURCE_KEY_VARIABLE is not set.
     *
     * @deprecated use {@see Constants::RESOURCE_KEY_VARIABLE}
     */
    public const RESOURCE_KEY_ENV_VAR = 'resource_key';

    /**
     * Read for the data file when IPI_PATH_VARIABLE is not set.
     *
     * @deprecated use {@see Constants::IPI_PATH_VARIABLE}
     */
    public const IP_INTELLIGENCE_DATA_FILE_ENV_VAR = 'IPINTELLIGENCEDATAFILE';

    public const IPV4_ADDRESS = '185.28.167.78';
    public const IPV6_ADDRESS = '2001:4860:4860::8888';

    // The JSON response segment for IP intelligence is keyed "ip"
    // (same as ip-intelligence-dotnet's IpiCloudEngine.ElementDataKey).
    public const JSON_RESPONSE = <<<'JSON'
{
    "ip": {
        "registeredname": "51 Degrees Mobile Experts Limited",
        "registeredcountry": "GB",
        "countrycode": "GB",
        "latitude": 51.4578,
        "longitude": -0.9732,
        "accuracyradiusmin": 25,
        "iseu": false,
        "mcc": [
            { "value": "234", "rawweighting": 52428 },
            { "value": "235", "rawweighting": 13107 }
        ],
        "ip": null,
        "ipnullreason": "The results are empty. This is probably because we don't have this data in our database."
    },
    "javascriptProperties": []
}
JSON;

    public const EVIDENCE_KEYS_RESPONSE = '["query.client-ip-51d", "server.client-ip-51d", "query.true-client-ip-51d"]';

    /**
     * @return array<string, mixed>
     */
    public static function accessiblePropertiesResponse(): array
    {
        return [
            'Products' => [
                'ip' => [
                    'DataTier' => 'CloudV4Free',
                    'Properties' => [
                        ['Name' => 'RegisteredName', 'Type' => 'String', 'Category' => 'IP Metrics'],
                        ['Name' => 'RegisteredCountry', 'Type' => 'String', 'Category' => 'IP Metrics'],
                        ['Name' => 'CountryCode', 'Type' => 'String', 'Category' => 'Geography'],
                        ['Name' => 'Latitude', 'Type' => 'Single', 'Category' => 'Geography'],
                        ['Name' => 'Longitude', 'Type' => 'Single', 'Category' => 'Geography'],
                        ['Name' => 'AccuracyRadiusMin', 'Type' => 'Int32', 'Category' => 'Geography'],
                        ['Name' => 'IsEu', 'Type' => 'Boolean', 'Category' => 'Geography'],
                        ['Name' => 'Mcc', 'Type' => 'WeightedString', 'Category' => 'Network'],
                        ['Name' => 'Ip', 'Type' => 'IpAddress', 'Category' => 'IP Metrics'],
                    ],
                ],
            ],
        ];
    }
}
