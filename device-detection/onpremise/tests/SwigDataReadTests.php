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
 * Every value is read by the property's name, because PHP builds the engine
 * class on every request, so a table from names to indexes would be built on
 * every request too. These tests use stubs for the SWIG wrapped objects, so
 * they run without the native extension or a data file.
 */
class SwigDataReadTests extends TestCase
{
    /**
     * Every accessor is handed the property's name.
     */
    public function testEveryReadGoesByName(): void
    {
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
            'screenpixelswidth' => ['name' => 'ScreenPixelsWidth', 'type' => 'Integer', 'category' => 'Device'],
        ];
        $result = new StubSwigResult([
            'IsMobile' => new StubSwigValue(true, true),
            'BrowserName' => new StubSwigValue(true, 'Chrome'),
            'ScreenPixelsWidth' => new StubSwigValue(true, 1080),
        ]);
        $data = new SwigData(new StubSwigEngine($properties), $result);

        $this->assertTrue($data->getInternal('ismobile')->value);
        $this->assertSame('Chrome', $data->getInternal('browsername')->value);
        $this->assertSame(1080, $data->getInternal('screenpixelswidth')->value);

        $this->assertSame(
            ['IsMobile', 'BrowserName', 'ScreenPixelsWidth'],
            $result->requestedKeys
        );
    }

    /**
     * A read through SwigData and a read of the native results by name give
     * the same value, whatever letter case the caller uses.
     */
    public function testAReadThroughSwigDataAndANativeReadAgree(): void
    {
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
        ];
        $result = new StubSwigResult([
            'IsMobile' => new StubSwigValue(true, true),
            'BrowserName' => new StubSwigValue(true, 'Safari'),
        ]);
        $data = new SwigData(new StubSwigEngine($properties), $result);

        $this->assertSame(
            $result->getValueAsBool('IsMobile')->getValue(),
            $data->getInternal('ismobile')->value
        );
        $this->assertSame('Safari', $data->getInternal('BrowserName')->value);
    }

    /**
     * A metric property is read from the results directly, so no value
     * accessor is called for it.
     */
    public function testMetricPropertiesAreNotReadThroughTheValueAccessors(): void
    {
        $properties = [
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
            'difference' => ['name' => 'Difference', 'type' => 'Integer', 'category' => 'DeviceMetrics'],
        ];
        $result = new StubSwigResult(['IsMobile' => new StubSwigValue(true, true)]);
        $data = new SwigData(new StubSwigEngine($properties), $result);

        $this->assertTrue($data->getInternal('ismobile')->value);
        $this->assertSame(0, $data->getInternal('difference')->value);
        $this->assertSame(['IsMobile'], $result->requestedKeys);
    }
}
