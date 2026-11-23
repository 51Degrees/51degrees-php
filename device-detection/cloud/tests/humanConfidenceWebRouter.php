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

// Serves the Human Confidence web example for the tests built on
// classes/HumanConfidenceWebTestCase.php, as
// examples/cloud/humanConfidenceWeb.php serves it, with one more setting,
// the timeout the tests give a request to the cloud service. It is far
// above anything measured on a runner, because a test must not fail when
// the service or the network is slow. The example's own default is left
// as it is.

require_once __DIR__ . '/../vendor/autoload.php';

use fiftyone\pipeline\devicedetection\examples\cloud\classes\HumanConfidenceWeb;

$settings = HumanConfidenceWeb::getSettingsFromEnv();
$settings['timeoutSeconds'] = 30;

(new HumanConfidenceWeb())->run($settings, function ($message) { echo $message; });
