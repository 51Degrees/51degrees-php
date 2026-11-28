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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\engines\Engine;

/**
 * The Element Data of the derived cloud engine where the cloud request
 * failed on a request that carries a sealed Human Confidence result.
 * HumanConfidence and HumanConfidenceSource read as Medium and Active, and
 * any other property throws as {@see CloudRequestFailedData} does.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#where-the-sealed-result-is-not-redeemed
 */
class SealedResultFailedData extends CloudRequestFailedData
{
    /** @var array<string, AspectPropertyValue> */
    private array $given;

    /**
     * @param array<string, AspectPropertyValue> $given The two Properties,
     *   keyed by lower case name
     */
    public function __construct(Engine $flowElement, array $given)
    {
        $this->given = $given;

        parent::__construct($flowElement);
    }

    public function getInternal(string $key)
    {
        return $this->given[strtolower($key)] ?? parent::getInternal($key);
    }

    /**
     * @return array<string, AspectPropertyValue>
     */
    public function asDictionary(): array
    {
        return $this->given;
    }
}
