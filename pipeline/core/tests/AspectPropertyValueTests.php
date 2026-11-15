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

use fiftyone\pipeline\core\AspectPropertyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reading the value of an AspectPropertyValue that has no value throws, with
 * the reason given or, when none was given, a message saying there is no
 * value. A value that is set, whatever it is, is read back as it was set.
 */
class AspectPropertyValueTests extends TestCase
{
    private const NO_VALUE = 'This instance does not have a set value';

    public static function provider_noValue(): array
    {
        return [
            'no reason' => [new AspectPropertyValue(), self::NO_VALUE],
            'a null reason' => [new AspectPropertyValue(null), self::NO_VALUE],
            'an empty reason' => [new AspectPropertyValue(''), self::NO_VALUE],
            'a reason' => [new AspectPropertyValue('No match'), 'No match'],
            'a reason and a value' => [
                new AspectPropertyValue('No match', 'Value'),
                'No match'
            ]
        ];
    }

    /**
     * @dataProvider provider_noValue
     */
    #[DataProvider('provider_noValue')]
    public function testReadingNoValueThrows(
        AspectPropertyValue $apv,
        string $message
    ): void {
        $this->assertFalse($apv->hasValue);
        try {
            $value = $apv->value;
            $this->fail('Read ' . var_export($value, true) . ' with no value.');
        } catch (\Exception $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function testNoReasonLeavesTheMessageUnset(): void
    {
        $this->assertNull((new AspectPropertyValue())->noValueMessage);
    }

    public static function provider_value(): array
    {
        return [
            'text' => ['Value'],
            'null' => [null],
            'false' => [false],
            'zero' => [0],
            'empty text' => [''],
            'a list' => [['a', 'b']]
        ];
    }

    /**
     * @dataProvider provider_value
     *
     * @param mixed $value
     */
    #[DataProvider('provider_value')]
    public function testAValueIsReadAsSet($value): void
    {
        $apv = new AspectPropertyValue(null, $value);
        $this->assertTrue($apv->hasValue);
        $this->assertSame($value, $apv->value);
    }
}
