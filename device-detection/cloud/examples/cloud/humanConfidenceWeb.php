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

/**
 * Human Confidence demo server. PHP's built-in web server. Run, from the
 * examples folder:
 *   php -S localhost:5151 cloud/humanConfidenceWeb.php
 * then open http://localhost:5151/
 *
 * Human Confidence says how likely it is that a person is looking at the
 * page. It comes in two ways, and this demo shows both.
 *
 * 1. Passive. This server asks the cloud itself, sending the visitor's
 *    request headers and IP address. HumanConfidenceSource is Passive and
 *    HumanConfidence is a plain level. The server asks for it rather than
 *    reading it from the page, because a level a browser sends can be
 *    forged.
 * 2. Active. The page loads the 51Degrees client script, which runs the
 *    browser tests and sends the answers to the cloud. When the script
 *    completes, HumanConfidenceSource is Active and HumanConfidence holds
 *    a sealed result the browser can't read or change.
 * 3. Redeem. The page hands the sealed result to this server, which calls
 *    hc/redeem with it as sealedResult and the account's License Key, and
 *    gets the Value back. It also sends the sealed result as value, the
 *    older name, so the redeem works with a cloud service that reads
 *    either. The answer's status says what happened: success, expired,
 *    replayed, unconfirmed or unreadable. A sealed result redeems once,
 *    within ten seconds. This server is the only party holding the
 *    License Key, which the browser never sees.
 *
 * Environment variables:
 *   _51DEGREES_RESOURCE_KEY    the Resource Key, required
 *   _51DEGREES_LICENSE_KEY     a License Key of the same account, required
 *                              to redeem
 *   _51DEGREES_CLOUD_ENDPOINT  the API base including the /api/v4/
 *                              segment, defaults to
 *                              https://cloud.51degrees.com/api/v4/
 *
 * The port is the php -S argument.
 */

require_once(__DIR__ . '/../../vendor/autoload.php');

use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\HumanConfidenceWeb;

function main()
{
    $settings = HumanConfidenceWeb::getSettingsFromEnv();

    if (empty($settings['resourceKey'])) {
        $message = 'Set ' . ExampleUtils::RESOURCE_KEY_ENV_VAR .
            ' to the Resource Key of the page. Create a Resource Key at ' .
            ExampleUtils::getTaggedLink('configure', __FILE__, 'resource-key-required');

        ExampleUtils::endWithoutResourceKey($message, __FILE__);
    }

    (new HumanConfidenceWeb())->run($settings, function ($message) { echo $message; });
}

main();
