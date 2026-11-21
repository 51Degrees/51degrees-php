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

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\EvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * Accepts the User-Agent and adds ElementData holding its data key.
 */
class AddingElement extends FlowElement
{
    public function __construct(string $dataKey)
    {
        parent::__construct();
        $this->dataKey = $dataKey;
    }

    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter(['header.user-agent']);
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'value' => $this->dataKey,
        ]));
    }
}

/**
 * Sets a field of the ElementData under another key.
 */
class WritingElement extends FlowElement
{
    private string $target;

    public function __construct(string $dataKey, string $target)
    {
        parent::__construct();
        $this->dataKey = $dataKey;
        $this->target = $target;
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->get($this->target)->written = $this->dataKey;
    }
}

/**
 * A FlowData is read only once processing has finished, so adding Evidence,
 * ElementData or an error to it then throws, and so does processing it
 * again. ElementData is written only by the FlowElements that write it,
 * while they process, so a write through it once they have finished throws.
 */
class FinishedWritesTests extends TestCase
{
    private const FINISHED = 'the FlowData has finished processing';
    private const ELEMENT_FINISHED = 'has finished processing';

    public function testAddingEvidenceToAFinishedFlowDataThrows(): void
    {
        $flowData = self::pipeline([new AddingElement('first')])->createFlowData();
        $flowData->evidence->set('header.user-agent', 'before');
        $flowData->process();

        foreach ([
            function () use ($flowData) {
                $flowData->evidence->set('header.user-agent', 'after');
            },
            function () use ($flowData) {
                $flowData->evidence->setArray(['header.user-agent' => 'after']);
            },
        ] as $write) {
            try {
                $write();
                $this->fail('Adding evidence after processing did not throw.');
            } catch (\Exception $e) {
                $this->assertStringContainsString(self::FINISHED, $e->getMessage());
            }
        }
        $this->assertSame('before', $flowData->evidence->get('header.user-agent'));
    }

    public function testAddingElementDataToAFinishedFlowDataThrows(): void
    {
        $element = new AddingElement('first');
        $flowData = self::processed([$element]);

        $this->expectExceptionMessage(self::FINISHED);
        $flowData->setElementData(new ElementDataDictionary($element, []));
    }

    public function testAddingAnErrorToAFinishedFlowDataThrows(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $this->expectExceptionMessage(self::FINISHED);
        $flowData->setError('first', new \Exception('late'));
    }

    public function testAFinishedFlowDataIsNotProcessedAgain(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $this->expectExceptionMessage('FlowData already processed');
        $flowData->process();
    }

    public function testElementDataThrowsOnAWriteOnceItsFlowElementHasFinished(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $this->expectExceptionMessage(self::ELEMENT_FINISHED);
        $flowData->get('first')->written = 'after';
    }

    public function testAWriteByALaterFlowElementIsThatElementsError(): void
    {
        $flowData = self::processed([
            new AddingElement('first'),
            new WritingElement('later', 'first'),
        ]);

        $this->assertSame(['later'], array_keys($flowData->errors));
        $this->assertStringContainsString(
            self::ELEMENT_FINISHED,
            $flowData->errors['later']->getMessage()
        );
    }

    public function testFlowElementsThatShareAKeyEachWriteItInTurn(): void
    {
        $flowData = self::processed([
            new AddingElement('shared'),
            new WritingElement('shared', 'shared'),
        ]);

        $this->assertSame([], $flowData->errors);
        $this->assertSame('shared', $flowData->get('shared')->written);
    }

    public function testElementDataTheCallerAddsThrowsOnceProcessingHasFinished(): void
    {
        $caller = new AddingElement('caller');
        $flowData = self::pipeline([new AddingElement('first')])->createFlowData();
        $data = new ElementDataDictionary($caller, []);
        $flowData->setElementData($data);
        $data->before = true;
        $flowData->process();

        $this->expectExceptionMessage(self::ELEMENT_FINISHED);
        $data->after = true;
    }

    public function testElementDataThrowsOnASecondWriteToAPropertyOnceFinished(): void
    {
        $flowData = self::processed([
            new AddingElement('shared'),
            new WritingElement('shared', 'shared'),
        ]);

        $this->assertWriteThrows(function () use ($flowData) {
            $flowData->get('shared')->written = 'after';
        }, self::ELEMENT_FINISHED);
        $this->assertSame('shared', $flowData->get('shared')->written);
    }

    public function testSettingTheDataArrayOfAFinishedFlowDataThrows(): void
    {
        $element = new AddingElement('first');
        $flowData = self::processed([$element]);

        $this->assertWriteThrows(function () use ($flowData, $element) {
            $flowData->data = ['other' => new ElementDataDictionary($element, [])];
        }, self::FINISHED);
        $this->assertSame(['first'], array_keys($flowData->data));
    }

    public function testSettingAnyOtherPropertyOfAFinishedFlowDataThrows(): void
    {
        $element = new AddingElement('first');
        $flowData = self::processed([$element]);
        $first = $flowData->get('first');

        // A property named as a data key would be read in place of the
        // ElementData under that key.
        $this->assertWriteThrows(function () use ($flowData, $element) {
            $flowData->first = new ElementDataDictionary($element, ['value' => 'after']);
        }, self::FINISHED);
        $this->assertSame($first, $flowData->first);

        $this->assertWriteThrows(function () use ($flowData, $element) {
            $flowData->other = new ElementDataDictionary($element, []);
        }, self::FINISHED);
        $this->assertFalse(isset($flowData->other));

        // A value that is not an array would be read in place of the data
        // array.
        $this->assertWriteThrows(function () use ($flowData) {
            $flowData->data = 'after';
        }, self::FINISHED);
        $this->assertSame(['first'], array_keys($flowData->data));
    }

    public function testSettingTheErrorsOfAFinishedFlowDataThrows(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $this->assertWriteThrows(function () use ($flowData) {
            $flowData->errors = ['late' => new \Exception('late')];
        }, self::FINISHED);
        $this->assertSame([], $flowData->errors);
    }

    public function testChangingAnErrorOfAFinishedFlowDataHasNoEffect(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $notices = self::notices(function () use ($flowData) {
            $flowData->errors['late'] = new \Exception('late');
        });
        $this->assertSame([], $flowData->errors);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('has no effect', $notices[0]);
    }

    public function testSettingTheContentsOfFinishedElementDataThrows(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $this->assertWriteThrows(function () use ($flowData) {
            $flowData->get('first')->contents = ['value' => 'after'];
        }, self::ELEMENT_FINISHED);
        $this->assertSame(['value' => 'first'], $flowData->get('first')->contents);
    }

    public function testChangingAValueInTheContentsOfFinishedElementDataHasNoEffect(): void
    {
        $flowData = self::processed([new AddingElement('first')]);

        $notices = self::notices(function () use ($flowData) {
            $flowData->get('first')->contents['value'] = 'after';
        });
        $this->assertSame(['value' => 'first'], $flowData->get('first')->contents);
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('has no effect', $notices[0]);
    }

    /**
     * Expects the write to throw an exception whose message holds the text.
     */
    private function assertWriteThrows(callable $write, string $message): void
    {
        $thrown = null;
        try {
            $write();
        } catch (\Exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'The write did not throw.');
        $this->assertStringContainsString($message, $thrown->getMessage());
    }

    /**
     * The messages of the notices and warnings PHP raises while the action
     * runs.
     *
     * @return array<int, string>
     */
    private static function notices(callable $action): array
    {
        $messages = [];
        set_error_handler(function (int $level, string $message) use (&$messages) {
            $messages[] = $message;

            return true;
        });
        try {
            $action();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }

    private static function pipeline(array $elements): Pipeline
    {
        return new Pipeline($elements, [
            'logger' => new Logger(null),
            'suppressProcessExceptions' => true,
        ]);
    }

    private static function processed(array $elements): FlowData
    {
        $flowData = self::pipeline($elements)->createFlowData();
        $flowData->process();

        return $flowData;
    }
}
