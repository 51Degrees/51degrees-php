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
 * ********************************************************************* */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\examples\classes\GetAllPropertiesConsoleCloud;

$resourceKey = ExampleUtils::getResourceKey(array_slice($argv, 1));

if (!$resourceKey) {
    fwrite(STDERR, "A resource key is required. Supply it as the first argument or via the resource_key environment variable. Obtain one from https://configure.51degrees.com.\n");
    exit(1);
}

(new GetAllPropertiesConsoleCloud())->run(['resourceKey' => $resourceKey], STDOUT);
