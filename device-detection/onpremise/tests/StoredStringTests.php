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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ProvidesValuesAsString;
use fiftyone\pipeline\devicedetection\Messages;
use fiftyone\pipeline\devicedetection\SwigData;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigEngine;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigResult;
use fiftyone\pipeline\devicedetection\tests\classes\StubSwigValue;
use PHPUnit\Framework\TestCase;

/**
 * The pipeline specification's access-to-results.md, line 65 at
 * 51Degrees/specifications dbaf099c: "Where an Element Data instance holds
 * Property values as strings and parses them into the type each Property
 * declares, it SHOULD be possible to get the stored string for a Property as
 * well as the parsed value." The rules for doing so are in value-types.md,
 * "Values stored as strings", lines 36 to 61.
 *
 * The device detection data file holds every value as a string, which the
 * native results parse into the declared type. These tests use stubs for the
 * SWIG wrapped objects, so they run without the native extension.
 */
class StoredStringTests extends TestCase
{
    /**
     * The stored string is offered through a capability a caller can test
     * for at run time.
     */
    public function testTheStoredStringIsACapabilityACallerCanTestFor(): void
    {
        $data = self::data();

        $this->assertInstanceOf(ProvidesValuesAsString::class, $data);
    }

    /**
     * "Unknown" in a Boolean Property is read as false by the typed accessor,
     * and the stored string gives "Unknown", the text the data holds.
     */
    public function testTheStoredStringOfABooleanIsTheTextTheDataHolds(): void
    {
        $data = self::data();

        $this->assertFalse($data->get('javascriptgethighentropyvalues')->value);

        $stored = $data->getValueAsString('javascriptgethighentropyvalues');
        $this->assertInstanceOf(AspectPropertyValue::class, $stored);
        $this->assertTrue($stored->hasValue);
        $this->assertSame('Unknown', $stored->value);
    }

    /**
     * The stored string of a numeric Property is the text as stored, not a
     * string composed from the parsed number.
     */
    public function testTheStoredStringOfANumberIsNotComposedFromTheParsedValue(): void
    {
        $data = self::data();

        $this->assertSame(0, $data->get('screenpixelswidth')->value);
        $this->assertSame('N/A', $data->getValueAsString('screenpixelswidth')->value);
        $this->assertSame('1.50', $data->getValueAsString('pixelratio')->value);
    }

    /**
     * Offering the stored value does not change what the typed accessor
     * gives, whichever is read first.
     */
    public function testReadingTheStoredStringLeavesTheTypedValueAlone(): void
    {
        $data = self::data();

        $data->getValueAsString('javascriptgethighentropyvalues');
        $data->getValueAsString('pixelratio');

        $this->assertFalse($data->get('javascriptgethighentropyvalues')->value);
        $this->assertSame(1.5, $data->get('pixelratio')->value);
    }

    /**
     * A String Property's stored value and parsed value are the same.
     */
    public function testAStringPropertyGivesTheSameValueEitherWay(): void
    {
        $data = self::data();

        $this->assertSame(
            $data->get('browsername')->value,
            $data->getValueAsString('BrowserName')->value
        );
    }

    /**
     * A Property with no value is reported as it is by the typed accessor,
     * as an Aspect Property Value with no value and the same reason.
     */
    public function testAPropertyWithNoValueIsReportedAsTheTypedAccessorReportsIt(): void
    {
        $data = self::data();

        $typed = $data->get('ismobile');
        $stored = $data->getValueAsString('ismobile');

        $this->assertFalse($typed->hasValue);
        $this->assertFalse($stored->hasValue);
        $this->assertSame($typed->noValueMessage, $stored->noValueMessage);
    }

    /**
     * A Property the data does not hold is reported as it is by the typed
     * accessor, with the same missing property message.
     */
    public function testAMissingPropertyIsReportedAsTheTypedAccessorReportsIt(): void
    {
        $data = self::data();

        $typed = null;
        try {
            $data->get('nosuchproperty');
        } catch (\Exception $e) {
            $typed = $e->getMessage();
        }
        $this->assertNotNull($typed);

        $this->expectExceptionMessage($typed);
        $data->getValueAsString('nosuchproperty');
    }

    /**
     * Closed results cannot be read for the stored string either.
     */
    public function testClosedResultsGiveNoStoredString(): void
    {
        $data = self::data();
        $data->close();

        $this->expectExceptionMessage(Messages::RESULTS_CLOSED);
        $data->getValueAsString('browsername');
    }

    private static function data(): SwigData
    {
        $properties = [
            'javascriptgethighentropyvalues' => [
                'name' => 'JavascriptGetHighEntropyValues',
                'type' => 'Boolean',
                'category' => 'Supported Media'
            ],
            'screenpixelswidth' => ['name' => 'ScreenPixelsWidth', 'type' => 'Integer', 'category' => 'Screen'],
            'pixelratio' => ['name' => 'PixelRatio', 'type' => 'Double', 'category' => 'Screen'],
            'browsername' => ['name' => 'BrowserName', 'type' => 'String', 'category' => 'Browser'],
            'ismobile' => ['name' => 'IsMobile', 'type' => 'Boolean', 'category' => 'Device'],
        ];
        $result = new StubSwigResult(
            [
                'JavascriptGetHighEntropyValues' => new StubSwigValue(true, false),
                'ScreenPixelsWidth' => new StubSwigValue(true, 0),
                'PixelRatio' => new StubSwigValue(true, 1.5),
                'BrowserName' => new StubSwigValue(true, 'Chrome'),
                'IsMobile' => new StubSwigValue(false, null, 'The results contained a null profile for the component which the required property belongs to.'),
            ],
            null,
            [],
            [
                'JavascriptGetHighEntropyValues' => new StubSwigValue(true, 'Unknown'),
                'ScreenPixelsWidth' => new StubSwigValue(true, 'N/A'),
                'PixelRatio' => new StubSwigValue(true, '1.50'),
            ]
        );

        return new SwigData(new StubSwigEngine($properties), $result);
    }
}
