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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\Engine;
use fiftyone\pipeline\ipintelligence\Constants;

/**
 * A stand-in for IpIntelligenceOnPremise carrying only the property metadata
 * SwigData reads, so SwigData can be built without the native extension.
 */
class StubSwigEngine extends Engine
{
    public string $dataKey = Constants::ELEMENT_DATA_KEY;

    /**
     * @param array<string, array<string, string>> $properties metadata keyed
     *   by lowercase property name, as the real engine builds it
     */
    public function __construct(array $properties)
    {
        $this->pipelines = [];
        $this->properties = $properties;

        parent::__construct();
    }

    /**
     * The metadata of a property, as the real engine gives it to a read.
     *
     * @return null|array<string, string>
     */
    public function findProperty(string $name): ?array
    {
        return $this->properties[strtolower($name)] ?? null;
    }

    public function processInternal(FlowData $flowData): void
    {
        // Never processed - SwigData is built directly by the tests.
    }
}
