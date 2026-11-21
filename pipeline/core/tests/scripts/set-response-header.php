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

/*
 * Served by PHP's built-in web server from WebIntegrationFailureTests, which
 * reads the response headers, as PHP from the command line sends none. It
 * processes a request with a working Set Headers Element, or with one that
 * fails when the query string says element=failed, then sets the response
 * headers and writes DONE, or THROWN and the error when setting them throws.
 */

require __DIR__ . '/../../vendor/autoload.php';

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\core\tests\classes\FailingSetHeaderElement;
use fiftyone\pipeline\core\tests\classes\SetHeaderNamesElement;

$builder = (new PipelineBuilder(['suppressProcessExceptions' => true]))
    ->add(new SetHeaderNamesElement());
if (($_GET['element'] ?? '') === 'failed') {
    $builder->add(new FailingSetHeaderElement());
}
$flowData = $builder->build()->createFlowData();
$flowData->process();

try {
    Utils::setResponseHeader($flowData);
    echo 'DONE';
} catch (\Throwable $e) {
    echo 'THROWN ' . get_class($e) . ': ' . $e->getMessage();
}
