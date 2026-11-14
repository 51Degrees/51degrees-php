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

namespace fiftyone\pipeline\engines\tests;

use fiftyone\pipeline\engines\CloudEngineBase;
use fiftyone\pipeline\engines\Engine;
use fiftyone\pipeline\engines\MissingPropertyMessages;
use fiftyone\pipeline\engines\MissingPropertyService;
use PHPUnit\Framework\TestCase;

class MissingPropertyTests extends TestCase
{
    /**
     * Check that when an engine contains the property, but in another data
     * tier, an "upgrade required" exception message is returned.
     */
    public function testUpgradeRequired()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(Engine::class);

        $properties = [
            'testProperty' => [
                'name' => 'testProperty',
                'type' => 'string',
                'datatierswherepresent' => ['premium']
            ]
        ];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('lite');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'testProperty', 'testElement');
        $message .= sprintf(MissingPropertyMessages::DATA_UPGRADE_REQUIRED, 'premium', get_class($engine));
        $this->expectExceptionMessage($message);
        $service->check('testProperty', $engine);
    }

    /**
     * Check that when an engine contains the property, but it is not marked as
     * available, an "excluded" exception message is returned.
     */
    public function testExcluded()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(Engine::class);

        $properties = [
            'testProperty' => [
                'name' => 'testProperty',
                'type' => 'string',
                'datatierswherepresent' => ['lite'],
                'available' => false
            ]
        ];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('lite');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'testProperty', 'testElement');
        $message .= MissingPropertyMessages::PROPERTY_EXCLUDED;
        $this->expectExceptionMessage($message);
        $service->check('testProperty', $engine);
    }

    /**
     * Check that when an engine does not contain the property, an "unknown"
     * exception message is returned.
     */
    public function testNotInEngine()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(Engine::class);

        $properties = [
            'testProperty' => [
                'name' => 'testProperty',
                'type' => 'string',
                'datatierswherepresent' => ['premium'],
                'available' => false
            ]
        ];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('lite');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'otherProperty', 'testElement');
        $message .= MissingPropertyMessages::UNKNOWN;
        $this->expectExceptionMessage($message);
        $service->check('otherProperty', $engine);
    }

    /**
     * Check that when a cloud engine does not contain the product, a "product
     * not in resource" exception message is returned.
     */
    public function testProductNotInResource()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(CloudEngineBase::class);

        $properties = [];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('lite');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'testProperty', 'testElement');
        $message .= sprintf(MissingPropertyMessages::PRODUCT_NOT_IN_CLOUD_RESOURCE, 'testElement');
        $this->expectExceptionMessage($message);
        $service->check('testProperty', $engine);
    }

    /**
     * Check that when a cloud engine does not contain the property, a "property
     * not in resource" exception message is returned.
     */
    public function testPropertyNotInResource()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(CloudEngineBase::class);

        $properties = [
            'testProperty' => [
                'name' => 'testProperty',
                'type' => 'string',
                'datatierswherepresent' => ['premium'],
                'available' => false
            ]
        ];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('lite');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'otherProperty', 'testElement');
        $message .= sprintf(MissingPropertyMessages::PROPERTY_NOT_IN_CLOUD_RESOURCE, 'testElement', 'testProperty');
        $this->expectExceptionMessage($message);
        $service->check('otherProperty', $engine);
    }

    /**
     * Check that when none of the above are true, an "unknown" exception
     * message is returned.
     */
    public function testUnknown()
    {
        $service = new MissingPropertyService();

        $engine = $this->createMock(Engine::class);

        $properties = [
            'testProperty' => [
                'name' => 'testProperty',
                'type' => 'string',
                'datatierswherepresent' => ['premium'],
                'available' => true
            ]
        ];

        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn('premium');
        $engine->dataKey = 'testElement';

        $message = sprintf(MissingPropertyMessages::PREFIX, 'testProperty', 'testElement');
        $message .= MissingPropertyMessages::UNKNOWN;
        $this->expectExceptionMessage($message);
        $service->check('testProperty', $engine);
    }

    // ----- The text and the order the specification gives -----
    // https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/properties.md#missing-properties

    private const PREFIX =
        "Property 'testProperty' not found in data for element 'testElement'. ";

    /**
     * An engine mock with the properties, data tier and Data Key given.
     *
     * @param array<string, array<string, mixed>> $properties
     */
    private function engine(
        string $class,
        array $properties,
        string $tier = 'Lite'
    ): Engine {
        $engine = $this->createMock($class);
        $engine->method('getProperties')->willReturn($properties);
        $engine->method('getDataSourceTier')->willReturn($tier);
        $engine->dataKey = 'testElement';

        return $engine;
    }

    /**
     * The message for the property, with the campaign parameters a link
     * may carry taken out, so it compares with the specification's text.
     */
    private static function messageFor(Engine $engine, string $name): string
    {
        try {
            (new MissingPropertyService())->check($name, $engine);
        } catch (\Exception $e) {
            return preg_replace(
                '/\?utm_[A-Za-z0-9_=&.-]*[A-Za-z0-9_-]/',
                '',
                $e->getMessage()
            );
        }

        throw new \LogicException('No exception for ' . $name);
    }

    public function testDataFileUpgradeRequiredIsTheSpecificationText()
    {
        $engine = $this->engine(Engine::class, [
            'testproperty' => [
                'name' => 'testProperty',
                'datatierswherepresent' => ['Premium', 'Enterprise'],
                'available' => true
            ]
        ]);

        $this->assertSame(
            self::PREFIX . 'This is because your license or data file does '
            . 'not include this property. It is available with the '
            . 'Premium,Enterprise license/data for the ' . get_class($engine)
            . '. To upgrade, see https://51degrees.com/pricing.',
            self::messageFor($engine, 'testProperty')
        );
    }

    /**
     * An empty list of data tiers gives the message for tiers that are not
     * known, which leaves out the sentence naming them.
     */
    public function testDataFileUpgradeRequiredWithNoTiersIsTheSpecificationText()
    {
        $engine = $this->engine(Engine::class, [
            'testproperty' => [
                'name' => 'testProperty',
                'datatierswherepresent' => [],
                'available' => true
            ]
        ]);

        $this->assertSame(
            self::PREFIX . 'This is because your license or data file does '
            . 'not include this property. To see which licenses include it, '
            . 'see https://51degrees.com/pricing.',
            self::messageFor($engine, 'testProperty')
        );
    }

    public function testProductNotAccessibleIsTheSpecificationText()
    {
        $engine = $this->engine(CloudEngineBase::class, []);

        $this->assertSame(
            self::PREFIX . 'This is because your resource key does not '
            . "include access to any properties under 'testElement'. Check "
            . 'or create a resource key with the properties you need in the '
            . 'configurator at https://configure.51degrees.com.',
            self::messageFor($engine, 'testProperty')
        );
    }

    public function testPropertyNotAccessibleIsTheSpecificationText()
    {
        $engine = $this->engine(CloudEngineBase::class, [
            'ismobile' => ['name' => 'IsMobile', 'available' => true],
            'platformname' => ['name' => 'PlatformName', 'available' => true]
        ]);

        $this->assertSame(
            self::PREFIX . 'This is because your resource key does not '
            . 'include access to this property. The properties included for '
            . "your key under 'testElement' are IsMobile,PlatformName. Check "
            . 'or change your key in the configurator at '
            . 'https://configure.51degrees.com.',
            self::messageFor($engine, 'testProperty')
        );
    }

    /**
     * A listed property that is not available is excluded, whether or not
     * its metadata gives the data tiers.
     */
    public function testAPropertyThatIsNotAvailableIsExcluded()
    {
        $engine = $this->engine(Engine::class, [
            'testproperty' => ['name' => 'testProperty', 'available' => false]
        ]);

        $this->assertSame(
            self::PREFIX . 'This is because the property has been excluded '
            . 'when configuring the engine.',
            self::messageFor($engine, 'testProperty')
        );
    }

    /**
     * The data tier is compared before the property's availability, so a
     * property the data file does not hold is an upgrade even where it is
     * also not available.
     */
    public function testTheDataTierIsComparedBeforeAvailability()
    {
        $engine = $this->engine(Engine::class, [
            'testproperty' => [
                'name' => 'testProperty',
                'datatierswherepresent' => ['Enterprise'],
                'available' => false
            ]
        ]);

        $this->assertStringContainsString(
            'It is available with the Enterprise license/data',
            self::messageFor($engine, 'testProperty')
        );
    }

    /**
     * A cloud engine compares no data tier. A property it lists that is not
     * available is excluded, and one it lists that is available but missing
     * is not accessible with the resource key.
     */
    public function testACloudEngineListingThePropertyGivesTheResourceKeyOrExclusion()
    {
        $available = $this->engine(CloudEngineBase::class, [
            'testproperty' => [
                'name' => 'testProperty',
                'datatierswherepresent' => ['Enterprise'],
                'available' => true
            ]
        ]);
        $this->assertSame(
            self::PREFIX . 'This is because your resource key does not '
            . 'include access to this property. The properties included for '
            . "your key under 'testElement' are testProperty. Check or change "
            . 'your key in the configurator at https://configure.51degrees.com.',
            self::messageFor($available, 'testProperty')
        );

        $excluded = $this->engine(CloudEngineBase::class, [
            'testproperty' => ['name' => 'testProperty', 'available' => false]
        ]);
        $this->assertSame(
            self::PREFIX . 'This is because the property has been excluded '
            . 'when configuring the engine.',
            self::messageFor($excluded, 'testProperty')
        );
    }

    /**
     * Names are compared ignoring case, so a property is given the same
     * reason however the caller spells it, and the message names it as the
     * caller did.
     */
    public function testNamesAreComparedIgnoringCase()
    {
        $engine = $this->engine(Engine::class, [
            'testproperty' => ['name' => 'testProperty', 'available' => false]
        ]);

        $this->assertSame(
            "Property 'TESTPROPERTY' not found in data for element "
            . "'testElement'. This is because the property has been excluded "
            . 'when configuring the engine.',
            self::messageFor($engine, 'TESTPROPERTY')
        );
    }

    /**
     * An on-premise engine that does not list the property gives the
     * unknown reason.
     */
    public function testAnUnlistedPropertyOfAnOnPremiseEngineIsUnknown()
    {
        $engine = $this->engine(Engine::class, [
            'otherproperty' => ['name' => 'otherProperty', 'available' => true]
        ]);

        $this->assertSame(
            self::PREFIX . 'The reason for this is unknown. Please check that '
            . 'the aspect and property name are correct.',
            self::messageFor($engine, 'testProperty')
        );
    }
}
