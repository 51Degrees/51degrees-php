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
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\OnPremiseTestCase;

/**
 * The on-premise engine's Element Data, with the data file. It is Aspect
 * Data, and every property of the data file gives a list of weighted
 * values.
 */
class DataModelTests extends OnPremiseTestCase
{
    // The addresses the engine echoes from the Evidence, which are the
    // package's own properties and not the data file's.
    private const ECHOED = ['ip', 'ipv6'];

    private const MAX_RAW_WEIGHTING = 65535;

    public function testTheElementDataIsAspectData(): void
    {
        $this->withResult(function (FlowData $flowData): void {
            $this->assertInstanceOf(AspectData::class, $flowData->ip);
        });
    }

    public function testEveryPropertyOfTheDataFileIsAListOfWeightedValues(): void
    {
        $this->withResult(function (FlowData $flowData, IpIntelligenceOnPremise $engine): void {
            $types = [];
            $withValue = 0;
            $notWeighted = [];
            foreach ($engine->getProperties() as $key => $property) {
                if (in_array($key, self::ECHOED, true)) {
                    continue;
                }
                $label = $property['name'] . ' (' . $property['type'] . ')';
                $types[] = $label;
                $value = $flowData->ip->get($property['name']);
                if (!$value->hasValue) {
                    continue;
                }
                ++$withValue;
                if (!self::isWeightedList($value->value)) {
                    $notWeighted[] = $label;
                }
            }
            fwrite(STDERR, PHP_EOL . 'Data file properties: ' .
                implode(', ', $types) . PHP_EOL);

            $this->assertGreaterThan(0, $withValue);
            $this->assertSame([], $notWeighted);
        });
    }

    /**
     * Whether a value is a list of weighted values, each a value and a raw
     * weighting from 0 to 65535.
     *
     * @param mixed $value
     */
    private static function isWeightedList($value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_array($item) ||
                array_keys($item) !== ['value', 'rawweighting'] ||
                !is_int($item['rawweighting']) ||
                $item['rawweighting'] < 0 ||
                $item['rawweighting'] > self::MAX_RAW_WEIGHTING) {
                return false;
            }
        }

        return true;
    }

    /**
     * Processes an address and hands the result and the engine to the check.
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
            $check($flowData, $engine);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }
}
