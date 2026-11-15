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

use fiftyone\pipeline\core\AggregateException;
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
 * With FlowError, a flow error is added for each value with no
 * translation, as the Translation Engine specification says, so every
 * missing translation is among the Flow Data's errors, whether one engine
 * misses several or several engines sharing a Data Key each miss one.
 */
class MissingTranslationErrorsTests extends TestCase
{
    // A language whose map translates neither source value.
    private const MAPS = ['fr_FR' => ['Scotland' => 'Ecosse']];

    public function testEveryMissingTranslationOfOneEngineIsKept(): void
    {
        $flowData = self::process(new TranslationEngine(
            'translated',
            'ip',
            ['Country' => 'CountryFrench', 'Region' => 'RegionFrench'],
            self::MAPS,
            'fr_FR',
            MissingTranslationBehavior::FLOW_ERROR
        ));

        $this->assertSame(['translated'], array_keys($flowData->errors));
        $this->assertSame(
            [
                "There was no translation found for the value 'England'.",
                "There was no translation found for the value 'Wessex'.",
            ],
            self::messages($flowData->errors['translated'])
        );
    }

    public function testEveryMissingTranslationOfEnginesSharingAKeyIsKept(): void
    {
        $flowData = self::process(
            new TranslationEngine(
                'translation',
                'ip',
                ['Country' => 'CountryFrench'],
                self::MAPS,
                'fr_FR',
                MissingTranslationBehavior::FLOW_ERROR
            ),
            new TranslationEngine(
                'translation',
                'ip',
                ['Region' => 'RegionFrench'],
                self::MAPS,
                'fr_FR',
                MissingTranslationBehavior::FLOW_ERROR
            )
        );

        $this->assertSame(['translation'], array_keys($flowData->errors));
        $this->assertSame(
            [
                "There was no translation found for the value 'England'.",
                "There was no translation found for the value 'Wessex'.",
            ],
            self::messages($flowData->errors['translation'])
        );
    }

    /**
     * Processes a Pipeline of a source with a Country and a Region and the
     * engines given, keeping the errors in the Flow Data.
     */
    private static function process(TranslationEngine ...$engines): FlowData
    {
        $builder = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true,
        ]))->add(new class () extends FlowElement {
            public string $dataKey = 'ip';

            public array $properties = [
                'country' => ['name' => 'Country', 'type' => 'String'],
                'region' => ['name' => 'Region', 'type' => 'String'],
            ];

            public function processInternal(FlowData $flowData): void
            {
                $flowData->setElementData(new ElementDataDictionary($this, [
                    'country' => new AspectPropertyValue(null, 'England'),
                    'region' => new AspectPropertyValue(null, 'Wessex'),
                ]));
            }

            public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
            {
                return new BasicListEvidenceKeyFilter([]);
            }
        });
        foreach ($engines as $engine) {
            $builder->add($engine);
        }
        $flowData = $builder->build()->createFlowData();
        $flowData->process();

        return $flowData;
    }

    /**
     * @return array<string> the message of each error kept under a key
     */
    private static function messages(\Throwable $kept): array
    {
        $errors = $kept instanceof AggregateException
            ? $kept->errors
            : [$kept];

        return array_map(
            static fn (\Throwable $error): string => $error->getMessage(),
            $errors
        );
    }
}
