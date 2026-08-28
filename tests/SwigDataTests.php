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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\ipintelligence\SwigData;
use fiftyone\pipeline\ipintelligence\tests\classes\StubLegacyWeightedValue;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigEngine;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigList;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigResult;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigValue;
use fiftyone\pipeline\ipintelligence\tests\classes\StubWeightedValue;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SwigData's conversion of native results into
 * AspectPropertyValues.
 *
 * The on-premise integration tests only run when the native extension and a
 * data file are both present, which leaves the weighted list conversion and
 * the getInternal type switch - the data integrity paths that decide the
 * country dropdown ordering - uncovered on the default CI path. The stubs
 * under tests/classes stand in for the SWIG wrapped objects so that these
 * paths are exercised everywhere.
 */
class SwigDataTests extends TestCase
{
    /**
     * The raw weighting must be the native 0 to 65535 ushort, read straight
     * from getShortRawWeight() so that it matches ip-intelligence-dotnet's
     * IWeightedValue.RawWeighting exactly.
     */
    public function testWeightedListUsesNativeShortRawWeight(): void
    {
        $data = $this->buildData(
            ['mcc' => ['name' => 'Mcc', 'type' => 'string']],
            ['Mcc' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue('234', 52428),
                new StubWeightedValue('235', 13107),
            ]))]
        );

        $value = $data->getInternal('mcc');

        $this->assertInstanceOf(AspectPropertyValue::class, $value);
        $this->assertTrue($value->hasValue);
        $this->assertSame([
            ['value' => '234', 'rawweighting' => 52428],
            ['value' => '235', 'rawweighting' => 13107],
        ], $value->value);
    }

    /**
     * A wrapper generated before getShortRawWeight() was added to
     * WeightedValue.i exposes only the normalised 0 to 1 float, so the
     * fallback has to scale it back to the same ushort. The cases are inlined
     * rather than supplied by a data provider so that the test behaves the
     * same on every PHPUnit major version the suite may run against.
     */
    public function testWeightedListFallsBackToScaledNormalisedWeight(): void
    {
        $expectations = [
            ['normalised' => 0.0, 'rawweighting' => 0],
            ['normalised' => 0.5, 'rawweighting' => 32768],
            ['normalised' => 1.0, 'rawweighting' => 65535],
        ];

        foreach ($expectations as $expectation) {
            $data = $this->buildData(
                ['mcc' => ['name' => 'Mcc', 'type' => 'string']],
                ['Mcc' => new StubSwigValue(true, new StubSwigList([
                    new StubLegacyWeightedValue('234', $expectation['normalised']),
                ]))]
            );

            $value = $data->getInternal('mcc');

            $this->assertSame(
                [['value' => '234', 'rawweighting' => $expectation['rawweighting']]],
                $value->value,
                sprintf('normalised weight %.1f', $expectation['normalised'])
            );
        }
    }

    public function testWeightedListWithNoValueCarriesTheNoValueMessage(): void
    {
        $data = $this->buildData(
            ['mcc' => ['name' => 'Mcc', 'type' => 'string']],
            ['Mcc' => new StubSwigValue(false, null, 'No MCC for this IP.')]
        );

        $value = $data->getInternal('mcc');

        $this->assertFalse($value->hasValue);
        $this->assertSame('No MCC for this IP.', $value->noValueMessage);
    }

    /**
     * Every weighted metadata type must route to the matching native weighted
     * list accessor, and every scalar type to its own accessor.
     */
    public function testGetInternalRoutesEachTypeToItsAccessor(): void
    {
        $properties = [
            'countrycodesgeographical' => ['name' => 'CountryCodesGeographical', 'type' => 'WeightedString'],
            'averagelocation' => ['name' => 'AverageLocation', 'type' => 'WeightedWKB'],
            'accuracyradiusmin' => ['name' => 'AccuracyRadiusMin', 'type' => 'WeightedInt'],
            'latitude' => ['name' => 'Latitude', 'type' => 'WeightedDouble'],
            'isproxy' => ['name' => 'IsProxy', 'type' => 'WeightedBool'],
            'registeredname' => ['name' => 'RegisteredName', 'type' => 'String'],
            'timezoneoffset' => ['name' => 'TimeZoneOffset', 'type' => 'Int'],
            'longitude' => ['name' => 'Longitude', 'type' => 'Double'],
            'iscrawler' => ['name' => 'IsCrawler', 'type' => 'Bool'],
            'areas' => ['name' => 'Areas', 'type' => 'WKB'],
            'countries' => ['name' => 'Countries', 'type' => 'String[]'],
        ];

        $result = new StubSwigResult([
            'CountryCodesGeographical' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue('GB', 65535)])),
            'AverageLocation' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue('POINT(1 2)', 65535)])),
            'AccuracyRadiusMin' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue(25, 65535)])),
            'Latitude' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue(51.5, 65535)])),
            'IsProxy' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue(true, 65535)])),
            'RegisteredName' => new StubSwigValue(true, 'An ISP'),
            'TimeZoneOffset' => new StubSwigValue(true, 60),
            'Longitude' => new StubSwigValue(true, -0.12),
            'IsCrawler' => new StubSwigValue(true, false),
            'Areas' => new StubSwigValue(true, 'POLYGON((0 0,1 0,1 1,0 0))'),
            'Countries' => new StubSwigValue(true, new StubSwigList(['GB', 'FR'])),
        ]);

        $data = new SwigData(new StubSwigEngine($properties), $result);

        $this->assertSame([['value' => 'GB', 'rawweighting' => 65535]], $data->getInternal('countrycodesgeographical')->value);
        $this->assertSame([['value' => 'POINT(1 2)', 'rawweighting' => 65535]], $data->getInternal('averagelocation')->value);
        $this->assertSame([['value' => 25, 'rawweighting' => 65535]], $data->getInternal('accuracyradiusmin')->value);
        $this->assertSame([['value' => 51.5, 'rawweighting' => 65535]], $data->getInternal('latitude')->value);
        $this->assertSame([['value' => true, 'rawweighting' => 65535]], $data->getInternal('isproxy')->value);
        $this->assertSame('An ISP', $data->getInternal('registeredname')->value);
        $this->assertSame(60, $data->getInternal('timezoneoffset')->value);
        $this->assertSame(-0.12, $data->getInternal('longitude')->value);
        $this->assertFalse($data->getInternal('iscrawler')->value);
        $this->assertSame('POLYGON((0 0,1 0,1 1,0 0))', $data->getInternal('areas')->value);
        $this->assertSame(['GB', 'FR'], $data->getInternal('countries')->value);

        // Mcc aside, only the Weighted* metadata types may take the weighted
        // list accessors - a scalar type reaching them would silently change
        // the returned shape.
        $this->assertSame(
            ['CountryCodesGeographical', 'AverageLocation', 'AccuracyRadiusMin', 'Latitude', 'IsProxy'],
            $result->weightedRequests
        );
    }

    /**
     * Mcc is weighted even though its metadata type is "string"
     * (ip-intelligence-dotnet parity).
     */
    public function testMccIsTreatedAsWeightedDespiteItsStringType(): void
    {
        $result = new StubSwigResult([
            'Mcc' => new StubSwigValue(true, new StubSwigList([new StubWeightedValue('234', 65535)])),
        ]);
        $data = new SwigData(
            new StubSwigEngine(['mcc' => ['name' => 'Mcc', 'type' => 'string']]),
            $result
        );

        $this->assertSame([['value' => '234', 'rawweighting' => 65535]], $data->getInternal('mcc')->value);
        $this->assertSame(['Mcc'], $result->weightedRequests);
        $this->assertSame([], $result->scalarRequests);
    }

    /**
     * The IP addresses supplied as evidence are echoed back rather than read
     * from the results (ip-intelligence-dotnet parity).
     */
    public function testEchoPropertiesComeFromTheEvidence(): void
    {
        $engine = new StubSwigEngine([]);
        $result = new StubSwigResult([]);

        $supplied = new SwigData($engine, $result, '1.2.3.4', '::1');
        $this->assertSame('1.2.3.4', $supplied->getInternal('ip')->value);
        $this->assertSame('::1', $supplied->getInternal('IPv6')->value);

        $absent = new SwigData($engine, $result);
        $this->assertFalse($absent->getInternal('ip')->hasValue);
        $this->assertFalse($absent->getInternal('ipv6')->hasValue);
    }

    public function testUnknownPropertyReturnsNull(): void
    {
        $data = new SwigData(new StubSwigEngine([]), new StubSwigResult([]));

        $this->assertNull($data->getInternal('notaproperty'));
    }

    /**
     * @param array<string, array<string, string>> $properties
     * @param array<string, StubSwigValue> $values
     */
    private function buildData(array $properties, array $values): SwigData
    {
        return new SwigData(new StubSwigEngine($properties), new StubSwigResult($values));
    }
}
