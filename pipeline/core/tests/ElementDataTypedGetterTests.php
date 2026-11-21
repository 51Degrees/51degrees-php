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
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The methods of Element Data and of the Evidence key filter that no other
 * test calls, each tested with valid data and with data that is not valid,
 * as automated-testing.md in the Pipeline specification asks.
 *
 * Engines give each Property as an AspectPropertyValue, so a typed getter
 * reads the value it holds, and throws the reason when it holds none. A
 * Property that is not there, or a value that is not a single value,
 * throws, rather than reading as 0, 1 or an empty string.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/automated-testing.md#test-cases
 */
class ElementDataTypedGetterTests extends TestCase
{
    private static function data(): ElementDataDictionary
    {
        return new ElementDataDictionary(new ExampleFlowElement1(), [
            'name' => 'Example',
            'count' => '42',
            'ratio' => 1.5,
            'aspectcount' => new AspectPropertyValue(null, 42),
            'aspectname' => new AspectPropertyValue(null, 'Example'),
            'aspectratio' => new AspectPropertyValue(null, '1.5'),
            'novalue' => new AspectPropertyValue('No match for this.'),
            'list' => ['a', 'b'],
        ]);
    }

    public function testAPlainValueIsReadAsEachType(): void
    {
        $data = self::data();

        $this->assertSame('Example', $data->getAsString('name'));
        $this->assertSame(42, $data->getAsInteger('count'));
        $this->assertSame(42.0, $data->getAsFloat('count'));
        $this->assertSame(1.5, $data->getAsFloat('ratio'));
        $this->assertSame('1.5', $data->getAsString('ratio'));
    }

    public function testTheValueOfAnAspectPropertyValueIsReadAsEachType(): void
    {
        $data = self::data();

        $this->assertSame(42, $data->getAsInteger('aspectcount'));
        $this->assertSame(42.0, $data->getAsFloat('aspectcount'));
        $this->assertSame('42', $data->getAsString('aspectcount'));
        $this->assertSame('Example', $data->getAsString('aspectname'));
        $this->assertSame(1.5, $data->getAsFloat('aspectratio'));
    }

    public function testANameInOtherLettersIsRead(): void
    {
        $this->assertSame(42, self::data()->getAsInteger('AspectCount'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function getters(): array
    {
        return [
            'string' => ['getAsString'],
            'integer' => ['getAsInteger'],
            'float' => ['getAsFloat'],
        ];
    }

    /**
     * @dataProvider getters
     */
    #[DataProvider('getters')]
    public function testNoValueThrowsTheReason(string $getter): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No match for this.');

        self::data()->{$getter}('novalue');
    }

    /**
     * @dataProvider getters
     */
    #[DataProvider('getters')]
    public function testAPropertyThatIsNotThereThrows(string $getter): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'missing'");

        self::data()->{$getter}('missing');
    }

    /**
     * @dataProvider getters
     */
    #[DataProvider('getters')]
    public function testAListThrows(string $getter): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'list'");

        self::data()->{$getter}('list');
    }

    public function testAsDictionaryGivesEveryValue(): void
    {
        $data = self::data();

        $this->assertSame(
            ['name', 'count', 'ratio', 'aspectcount', 'aspectname',
                'aspectratio', 'novalue', 'list'],
            array_keys($data->asDictionary())
        );
        $this->assertSame('Example', $data->asDictionary()['name']);
    }

    public function testTheKeyFilterGivesItsList(): void
    {
        $keys = ['header.user-agent', 'query.User-Agent'];
        $filter = new BasicListEvidenceKeyFilter($keys);

        $this->assertSame($keys, $filter->getList());
        $this->assertSame([], (new BasicListEvidenceKeyFilter([]))->getList());
    }
}
