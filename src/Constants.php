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

class Constants
{
    public const IP_DESCRIPTION =
        'The IPv4 address that was used to perform the lookup. This is the ' .
        'IP address supplied as evidence, echoed back in the results.';

    public const IPV6_DESCRIPTION =
        'The IPv6 address that was used to perform the lookup. This is the ' .
        'IP address supplied as evidence, echoed back in the results.';

    // Performance profiles supported by the on-premise engine.
    public const PERFORMANCE_PROFILES = [
        'LowMemory',
        'MaxPerformance',
        'Balanced',
        'BalancedTemp',
        'HighPerformance'
    ];

    // The file extension expected for an on-premise IP intelligence data file.
    public const DATA_FILE_EXTENSION = '.ipi';
}
