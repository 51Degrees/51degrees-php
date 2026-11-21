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

namespace fiftyone\pipeline\translation\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngine;
use PHPUnit\Framework\TestCase;

/**
 * The ElementData of a TranslationEngine is read only once it has finished.
 */
class ReadOnlyTests extends TestCase
{
    public function testATranslationEnginesElementDataIsReadOnlyOnceProcessed(): void
    {
        $source = new class () extends FlowElement {
            public string $dataKey = 'ip';

            public array $properties = [
                'country' => ['name' => 'Country', 'type' => 'String'],
            ];

            public function processInternal(FlowData $flowData): void
            {
                $flowData->setElementData(new ElementDataDictionary($this, [
                    'country' => new AspectPropertyValue(null, 'Scotland'),
                ]));
            }

            public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
            {
                return new BasicListEvidenceKeyFilter([]);
            }
        };
        $flowData = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($source)->add(new TranslationEngine(
            'translated',
            'ip',
            ['Country' => 'CountryFrench'],
            ['fr_FR' => ['Scotland' => 'Ecosse']],
            'fr_FR',
            MissingTranslationBehavior::ORIGINAL
        ))->build()->createFlowData();
        $flowData->process();

        $this->expectExceptionMessage('has finished processing');
        $flowData->get('translated')->probe = 1;
    }
}
