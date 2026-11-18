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

use fiftyone\pipeline\core\AggregateException;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * FlowData keeps every error set under a key, as the exception handling
 * specification says the errors are added to the Flow Data errors
 * collection.
 */
class FlowDataErrorsTests extends TestCase
{
    private static function flowData(): FlowData
    {
        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->build()->createFlowData();
    }

    public function testOneErrorUnderAKeyIsKeptAsItIs(): void
    {
        $flowData = self::flowData();
        $error = new \RuntimeException('first');

        $flowData->setError('element', $error);

        $this->assertSame(['element' => $error], $flowData->errors);
    }

    public function testEveryErrorUnderAKeyIsKeptInOrder(): void
    {
        $flowData = self::flowData();
        $errors = [
            new \RuntimeException('first'),
            new \InvalidArgumentException('second'),
            new \RuntimeException('third'),
        ];

        foreach ($errors as $error) {
            $flowData->setError('element', $error);
        }

        $kept = $flowData->errors['element'];
        $this->assertInstanceOf(AggregateException::class, $kept);
        $this->assertSame($errors, $kept->errors);
        $this->assertSame("first\nsecond\nthird", $kept->getMessage());
        $this->assertSame($errors[0], $kept->getPrevious());
    }

    public function testErrorsUnderOtherKeysAreKeptApart(): void
    {
        $flowData = self::flowData();
        $one = new \RuntimeException('one');
        $two = new \RuntimeException('two');

        $flowData->setError('one', $one);
        $flowData->setError('two', $two);

        $this->assertSame(['one' => $one, 'two' => $two], $flowData->errors);
    }
}
