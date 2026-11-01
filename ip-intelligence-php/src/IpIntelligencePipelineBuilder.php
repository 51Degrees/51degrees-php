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

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\PipelineBuilder;

/**
 * The IpIntelligencePipelineBuilder builds a Pipeline that uses the
 * 51Degrees cloud service to look up IP intelligence data. It wires up a
 * CloudRequestEngine followed by an IpIntelligenceCloud engine.
 *
 * Mirrors device-detection-php's DeviceDetectionPipelineBuilder and
 * location-php's GeoLocationPipelineBuilder.
 */
class IpIntelligencePipelineBuilder extends PipelineBuilder
{
    /**
     * @param array<string, mixed> $settings
     *   resourceKey         - (required) 51Degrees cloud resource key.
     *   cloudEndPoint       - (optional) override the cloud service base URL.
     *   cloudRequestOrigin  - (optional) value for the Origin header.
     *   restrictedProperties- (optional) list of properties to request.
     *   httpClient          - (optional) HttpClient instance (used for tests).
     * @throws \Exception
     */
    public function __construct(array $settings)
    {
        parent::__construct($settings);

        if (array_key_exists('cloudEndPoint', $settings)) {
            $settings['baseURL'] = $settings['cloudEndPoint'];
        }

        $cloud = new CloudRequestEngine($settings);

        $flowElements = [];
        $flowElements[] = $cloud;

        $ipIntelligence = new IpIntelligenceCloud();

        if (isset($settings['restrictedProperties'])) {
            $ipIntelligence->setRestrictedProperties($settings['restrictedProperties']);
        }

        $flowElements[] = $ipIntelligence;

        $flowElements = array_merge($flowElements, $this->flowElements);
        $this->flowElements = $flowElements;
    }
}
