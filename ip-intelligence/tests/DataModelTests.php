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

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectData;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\OnPremiseTestCase;

/**
 * The on-premise engine's Element Data, with the data file, is Aspect Data.
 */
class DataModelTests extends OnPremiseTestCase
{
    public function testTheElementDataIsAspectData(): void
    {
        $this->withResult(function (FlowData $flowData): void {
            $this->assertInstanceOf(AspectData::class, $flowData->ip);
        });
    }

    /**
     * Processes an address and hands the result to the check.
     */
    private function withResult(callable $check): void
    {
        $engine = self::buildEngine();
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $flowData->process();
            $check($flowData);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }
}
