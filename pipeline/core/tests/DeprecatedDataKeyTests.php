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

use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * An element whose Data Key is new-key, and which had old-key before.
 */
class RenamedKeyElement extends FlowElement
{
    public string $dataKey = 'new-key';

    /**
     * @var array<string>
     */
    public array $deprecatedDataKeys = ['old-key'];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(
            new ElementDataDictionary($this, ['value' => 'renamed'])
        );
    }
}

/**
 * An element whose own Data Key is old-key.
 */
class OldKeyElement extends FlowElement
{
    public string $dataKey = 'old-key';

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(
            new ElementDataDictionary($this, ['value' => 'own'])
        );
    }
}

/**
 * A Data Key that changes is a public name, so for at least one release the
 * key an element had before still finds the element and its Element Data,
 * and is logged once as deprecated.
 */
class DeprecatedDataKeyTests extends TestCase
{
    public function testFlowDataGivesTheElementDataUnderTheDeprecatedKey(): void
    {
        $flowData = self::pipeline([new RenamedKeyElement()])->createFlowData();
        $flowData->process();

        $this->assertSame($flowData->get('new-key'), $flowData->get('old-key'));
        $this->assertSame('renamed', $flowData->get('old-key')->get('value'));
    }

    public function testThePipelineGivesTheElementUnderTheDeprecatedKey(): void
    {
        $element = new RenamedKeyElement();
        $pipeline = self::pipeline([$element]);

        $this->assertSame($element, $pipeline->getElement('old-key'));
        $this->assertSame($element, $pipeline->flowElementsList['old-key']);
    }

    public function testTheDeprecatedKeyIsLoggedOnceAsAWarning(): void
    {
        $logger = self::logger();
        $pipeline = self::pipeline([new RenamedKeyElement()], $logger);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $flowData->get('old-key');
        $flowData->get('old-key');
        $pipeline->getElement('old-key');

        $warnings = self::warnings($logger);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("'old-key'", $warnings[0]);
        $this->assertStringContainsString("'new-key'", $warnings[0]);
    }

    public function testTheCurrentKeyLogsNothing(): void
    {
        $logger = self::logger();
        $pipeline = self::pipeline([new RenamedKeyElement()], $logger);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $flowData->get('new-key');
        $pipeline->getElement('new-key');

        $this->assertSame([], self::warnings($logger));
    }

    /**
     * An element whose own Data Key is another element's deprecated one
     * keeps it, whichever of the two is added first.
     */
    public function testAnElementWithTheKeyAsItsOwnKeepsIt(): void
    {
        foreach ([true, false] as $renamedFirst) {
            $renamed = new RenamedKeyElement();
            $own = new OldKeyElement();
            $logger = self::logger();
            $pipeline = self::pipeline(
                $renamedFirst ? [$renamed, $own] : [$own, $renamed],
                $logger
            );
            $flowData = $pipeline->createFlowData();
            $flowData->process();

            $this->assertSame($own, $pipeline->getElement('old-key'));
            $this->assertSame('own', $flowData->get('old-key')->get('value'));
            $this->assertSame('renamed', $flowData->get('new-key')->get('value'));
            $this->assertSame([], self::warnings($logger));
        }
    }

    public function testAKeyNoElementHasStillThrows(): void
    {
        $flowData = self::pipeline([new RenamedKeyElement()])->createFlowData();
        $flowData->process();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("There is no element data for 'none'");
        $flowData->get('none');
    }

    /**
     * @param array<\fiftyone\pipeline\core\FlowElement> $elements
     */
    private static function pipeline(array $elements, ?Logger $logger = null): Pipeline
    {
        return new Pipeline($elements, ['logger' => $logger ?? self::logger()]);
    }

    /**
     * A logger that keeps every entry.
     */
    private static function logger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<array<string, string>> */
            public array $entries = [];

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };
    }

    /**
     * The messages logged as warnings.
     *
     * @return array<string>
     */
    private static function warnings(Logger $logger): array
    {
        $warnings = [];
        foreach ($logger->entries as $entry) {
            if ($entry['level'] === 'warning') {
                $warnings[] = $entry['message'];
            }
        }

        return $warnings;
    }
}
