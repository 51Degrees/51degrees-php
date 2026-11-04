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

namespace fiftyone\pipeline\ipintelligence\examples\classes;

class ExampleConstants
{
    // Environment variable holding the cloud resource key.
    public const CLOUD_RESOURCE_KEY_ENV_VAR = 'resource_key';

    // Environment variable holding the path to an on-premise .ipi data file.
    public const IP_INTELLIGENCE_DATA_FILE_ENV_VAR = 'IPINTELLIGENCEDATAFILE';

    // The free Lite data file, as the data repository names it. The examples
    // download it into cxx/data/ip-intelligence when no other file is found.
    public const LITE_DATA_FILE_NAME = '51Degrees-LiteV41.ipi';

    // The default on-premise data file names searched for, in priority order.
    // The Asn file is left out, because it has only the Asn and AsnName
    // properties and the examples are written for the Lite or Enterprise file.
    public const IPI_DATA_FILE_NAMES = [
        '51Degrees-EnterpriseIpiV41.ipi',
        '51Degrees-LiteIpiV41.ipi',
        self::LITE_DATA_FILE_NAME
    ];

    // Sent with each download of a data file, so that downloads can be
    // counted by where they come from.
    public const DATA_DOWNLOAD_SOURCE = 'examples-51degrees-php';

    // Evidence (IP addresses) used by the getting started examples. These
    // match the values used by ip-intelligence-dotnet-examples.
    public const EVIDENCE_VALUES = [
        ['query.client-ip' => '116.154.188.222'],
        ['query.client-ip' => '1.3.32.31'],
        ['query.client-ip' => '45.236.48.61'],
        ['query.client-ip' => '2001:0db8:085a:0000:0000:8a2e:0370:7334']
    ];

    // Evidence used by the mixed (device detection + IP intelligence) examples.
    public const MIXED_EVIDENCE_VALUES = [
        [
            'header.user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1',
            'query.client-ip' => '62.61.32.31'
        ],
        [
            'header.user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36',
            'query.client-ip' => '116.154.188.222'
        ]
    ];

    // IP intelligence properties printed by the getting started examples.
    public const OUTPUT_PROPERTIES = [
        'RegisteredName',
        'RegisteredOwner',
        'RegisteredCountry',
        'IpRangeStart',
        'IpRangeEnd',
        'Country',
        'CountryCode',
        'CountryCode3',
        'Region',
        'State',
        'Town',
        'Latitude',
        'Longitude',
        'Areas',
        'AccuracyRadiusMin',
        'TimeZoneOffset'
    ];

    // Device detection properties printed by the mixed examples.
    public const DEVICE_OUTPUT_PROPERTIES = [
        'IsMobile',
        'PlatformName',
        'PlatformVersion',
        'BrowserName',
        'BrowserVersion',
        'HardwareName',
        'HardwareVendor',
        'DeviceType'
    ];
}
