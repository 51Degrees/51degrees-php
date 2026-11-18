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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ErrorFlowData;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use PHPUnit\Framework\TestCase;

/**
 * The Flow Data accessors throw where the caller has done something wrong,
 * and not because an element failed, as the Flow Data and derived accessors
 * section of exception-handling.md in the Pipeline specification asks.
 */
class FlowDataAccessorTests extends TestCase
{
    private static function flowData(bool $withFailingElement): FlowData
    {
        $builder = (new PipelineBuilder(['suppressProcessExceptions' => true]))
            ->add(new ExampleFlowElement1());
        if ($withFailingElement) {
            $builder->add(new ErrorFlowData());
        }
        $flowData = $builder->build()->createFlowData();
        $flowData->evidence->set('header.user-agent', 'agent');

        return $flowData;
    }

    /**
     * Asking for results before processing is the caller's mistake, which
     * the specification gives as its example, and the message names the
     * element asked for.
     */
    public function testReadingResultsBeforeProcessingThrows(): void
    {
        $flowData = self::flowData(false);

        try {
            $flowData->get('example1');
            $this->fail('Reading results before processing should throw.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('example1', $e->getMessage());
        }
    }

    /**
     * A data key is mandatory, so leaving it out is refused.
     */
    public function testAMissingDataKeyIsRefused(): void
    {
        $flowData = self::flowData(false);
        $flowData->process();

        $this->expectException(\TypeError::class);
        $flowData->get(null);
    }

    /**
     * An element that fails does so through no fault of the caller. With
     * errors kept in the Flow Data, the other results, the search by
     * metadata and the Evidence can all still be read.
     */
    public function testAFailedElementLeavesTheOtherAccessorsWorking(): void
    {
        $flowData = self::flowData(true);
        $flowData->process();

        $this->assertArrayHasKey('error', $flowData->errors);
        $this->assertSame(5, $flowData->get('example1')->get('integer'));
        $this->assertSame(5, $flowData->example1->integer);
        $this->assertSame(5, $flowData->getWhere('type', 'int')['integer']);
        $this->assertSame('agent', $flowData->evidence->get('header.user-agent'));
        $this->assertSame(
            'agent',
            $flowData->getEvidenceDataKey()['header.user-agent']
        );
    }
}
