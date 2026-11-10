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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\SwigData;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigEngine;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigResult;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigValue;
use PHPUnit\Framework\TestCase;

/**
 * A property's index is its place in the list of names the engine was built
 * with, fixed at build, so the same index names the same property after any
 * reload. These tests use stubs for the SWIG wrapped objects, so they run
 * without the native extension or a data file.
 */
class SwigDataIndexTests extends TestCase
{
    /**
     * Every read goes by the property's index in the build-time list, so no
     * accessor is handed a name.
     */
    public function testEveryReadGoesByTheBuildTimeIndex(): void
    {
        $required = ['IsMobile', 'BrowserName', 'ScreenPixelsWidth'];
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
            'screenpixelswidth' => ['name' => 'ScreenPixelsWidth', 'type' => 'Integer', 'category' => 'Device'],
        ];
        $result = new StubSwigResult([
            'IsMobile' => new StubSwigValue(true, true),
            'BrowserName' => new StubSwigValue(true, 'Chrome'),
            'ScreenPixelsWidth' => new StubSwigValue(true, 1080),
        ], $required);
        $data = new SwigData(new StubSwigEngine($properties, $required), $result);

        $this->assertTrue($data->getInternal('ismobile')->value);
        $this->assertSame('Chrome', $data->getInternal('browsername')->value);
        $this->assertSame(1080, $data->getInternal('screenpixelswidth')->value);

        $this->assertSame([0, 1, 2], $result->requestedKeys);
    }

    /**
     * A data set loaded later may hold the properties in another order, or
     * hold more of them. The index still names the property the engine was
     * built with, because it is the place in the build-time list.
     */
    public function testIndexesFollowTheBuildListNotTheDataSetOrder(): void
    {
        // The engine was built with these names, in this order.
        $required = ['ScreenPixelsWidth', 'IsMobile'];

        // The reloaded data set sorts its properties by name and holds a
        // third property the engine was not built with.
        $properties = [
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'screenpixelswidth' => ['name' => 'ScreenPixelsWidth', 'type' => 'Integer', 'category' => 'Device'],
        ];
        $result = new StubSwigResult([
            'ScreenPixelsWidth' => new StubSwigValue(true, 1440),
            'IsMobile' => new StubSwigValue(true, false),
        ], $required);
        $data = new SwigData(new StubSwigEngine($properties, $required), $result);

        $this->assertSame(1440, $data->getInternal('screenpixelswidth')->value);
        $this->assertFalse($data->getInternal('ismobile')->value);

        // ScreenPixelsWidth is index 0 and IsMobile index 1 because that is
        // the build order, not the order the data set lists them in.
        $this->assertSame([0, 1], $result->requestedKeys);
    }

    /**
     * A read by name and a typed read give the same value, both going by the
     * same index.
     */
    public function testAReadByNameAndATypedReadAgree(): void
    {
        $required = ['IsMobile', 'BrowserName'];
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
        ];
        $result = new StubSwigResult([
            'IsMobile' => new StubSwigValue(true, true),
            'BrowserName' => new StubSwigValue(true, 'Safari'),
        ], $required);
        $engine = new StubSwigEngine($properties, $required);
        $data = new SwigData($engine, $result);

        $byName = $data->getInternal('ismobile')->value;
        $typed = $result->getValueAsBool($engine->getPropertyIndex('IsMobile'))->getValue();

        $this->assertSame($typed, $byName);
        $this->assertSame('Safari', $data->getInternal('BrowserName')->value);
    }

    /**
     * A property the engine was not built with has no index, so it carries no
     * value rather than being read by name.
     */
    public function testAPropertyOutsideTheBuildListCarriesNoValue(): void
    {
        $required = ['IsMobile'];
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
        ];
        $result = new StubSwigResult([
            'IsMobile' => new StubSwigValue(true, true),
            'BrowserName' => new StubSwigValue(true, 'Chrome'),
        ], $required);
        $data = new SwigData(new StubSwigEngine($properties, $required), $result);

        $this->assertTrue($data->getInternal('ismobile')->value);
        $this->assertFalse($data->getInternal('browsername')->hasValue);
    }

    /**
     * A metric property is read from the results directly, not through an
     * index, because the data set holds no property of that name.
     */
    public function testMetricPropertiesAreNotReadByIndex(): void
    {
        $required = ['IsMobile'];
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'difference' => ['name' => 'Difference', 'type' => 'Integer', 'category' => 'DeviceMetrics'],
        ];
        $result = new StubSwigResult(['IsMobile' => new StubSwigValue(true, true)], $required);
        $data = new SwigData(new StubSwigEngine($properties, $required), $result);

        $this->assertTrue($data->getInternal('ismobile')->value);
        $this->assertSame([0], $result->requestedKeys);
    }
}
