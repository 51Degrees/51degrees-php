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
 * An element with the Data Key and deprecated Data Keys it is given, whose
 * Element Data holds that Data Key.
 */
class KeyedElement extends FlowElement
{
    /**
     * @param array<string> $deprecatedDataKeys
     */
    public function __construct(
        string $dataKey,
        array $deprecatedDataKeys = []
    ) {
        parent::__construct();
        $this->dataKey = $dataKey;
        $this->deprecatedDataKeys = $deprecatedDataKeys;
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'key' => $this->dataKey,
        ]));
    }
}

/**
 * The Pipeline finds an element under its Data Key in any letter case, as
 * access-to-results.md in the Pipeline specification asks of every string
 * key, trying the exact key first. It finds the element whose Element Data
 * FlowData::get gives for the same key.
 */
class ElementKeyTests extends TestCase
{
    public function testAnElementIsFoundUnderItsKeyInAnyCase(): void
    {
        $element = new KeyedElement('device');
        $pipeline = self::pipeline([$element]);

        $this->assertSame($element, $pipeline->getElement('device'));
        $this->assertSame($element, $pipeline->getElement('Device'));
        $this->assertSame($element, $pipeline->getElement('DEVICE'));
    }

    /**
     * Of two elements whose keys differ only in case, each is found under
     * its own key, and a key that is neither finds the first.
     */
    public function testTheExactKeyIsTriedFirst(): void
    {
        $lower = new KeyedElement('device');
        $upper = new KeyedElement('DEVICE');
        $pipeline = self::pipeline([$lower, $upper]);
        $flowData = self::processed($pipeline);

        $this->assertSame($lower, $pipeline->getElement('device'));
        $this->assertSame($upper, $pipeline->getElement('DEVICE'));
        $this->assertSame($lower, $pipeline->getElement('Device'));
        foreach (['device', 'DEVICE', 'Device'] as $key) {
            $this->assertSame(
                $pipeline->getElement($key)->dataKey,
                $flowData->get($key)->get('key'),
                $key
            );
        }
    }

    /**
     * An element's own Data Key in another case comes before another
     * element's deprecated key, whichever element is added first, as it
     * does in FlowData::get.
     */
    public function testAnOwnKeyInAnyCaseComesBeforeADeprecatedKey(): void
    {
        foreach ([true, false] as $renamedFirst) {
            $renamed = new KeyedElement('new-key', ['old-key']);
            $own = new KeyedElement('OLD-KEY');
            $logger = self::logger();
            $pipeline = self::pipeline(
                $renamedFirst ? [$renamed, $own] : [$own, $renamed],
                $logger
            );
            $flowData = self::processed($pipeline);

            $this->assertSame($own, $pipeline->getElement('old-key'));
            $this->assertSame(
                'OLD-KEY',
                $flowData->get('old-key')->get('key')
            );
            $this->assertSame([], self::warnings($logger));
        }
    }

    public function testAKeyNoElementHasThrows(): void
    {
        $pipeline = self::pipeline([new KeyedElement('device')]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'none'");
        $pipeline->getElement('none');
    }

    /**
     * @param array<\fiftyone\pipeline\core\FlowElement> $elements
     */
    private static function pipeline(
        array $elements,
        ?Logger $logger = null
    ): Pipeline {
        return new Pipeline($elements, ['logger' => $logger ?? self::logger()]);
    }

    private static function processed(Pipeline $pipeline): FlowData
    {
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        return $flowData;
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
