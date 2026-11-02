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

use fiftyone\pipeline\cloudrequestengine\HttpClient;

/**
 * Mock of the cloud HttpClient so that cloud engine unit tests can run
 * without a resource key or any network access. The PHP analogue of
 * ip-intelligence-python's MockRequestClient.
 */
class MockHttpClient extends HttpClient
{
    public function makeCloudRequest(string $type, string $url, ?string $content, ?string $originHeader): string
    {
        if (strpos($url, 'accessibleProperties') !== false) {
            return json_encode(Constants::accessiblePropertiesResponse());
        }

        if (strpos($url, 'evidencekeys') !== false) {
            return Constants::EVIDENCE_KEYS_RESPONSE;
        }

        if (strpos($url, '.json') !== false) {
            return Constants::JSON_RESPONSE;
        }

        throw new \Exception("MockHttpClient was not expecting a request to the URL '{$url}'");
    }
}
