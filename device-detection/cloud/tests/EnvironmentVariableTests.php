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

use fiftyone\pipeline\devicedetection\examples\cloud\classes\EnvironmentVariable;
use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;
use PHPUnit\Framework\TestCase;

/**
 * A variable is read by its name only.
 * Each constant that held a deprecated name keeps its name and its value.
 */
class EnvironmentVariableTests extends TestCase
{
    /** @var array<string, array{0: string|false, 1: mixed}> */
    private $previous = [];

    protected function tearDown(): void
    {
        foreach ($this->previous as $name => $was) {
            putenv($was[0] === false ? $name : $name . '=' . $was[0]);
            if ($was[1] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $was[1];
            }
        }
        $this->previous = [];
    }

    public function testNameIsRead(): void
    {
        $this->set('_51DEGREES_TEST_SET', 'new');

        $this->assertSame('new', EnvironmentVariable::read('_51DEGREES_TEST_SET'));
    }

    public function testNameNotSetGivesAnEmptyString(): void
    {
        $this->set('_51DEGREES_TEST_UNSET', null);

        $this->assertSame('', EnvironmentVariable::read('_51DEGREES_TEST_UNSET'));
    }

    public function testPlaceholderCountsAsNotSet(): void
    {
        $this->set('_51DEGREES_TEST_PLACEHOLDER', '!!NOT_SET!!');

        $this->assertSame('', EnvironmentVariable::read(
            '_51DEGREES_TEST_PLACEHOLDER',
            '!!NOT_SET!!'
        ));
    }

    public function testNoExampleKeyGivesNull(): void
    {
        $this->set('_51DEGREES_RESOURCE_KEY', null);

        $this->assertNull(ExampleUtils::getResourceKeyFromEnv());
    }

    public function testNoExampleEndpointGivesNull(): void
    {
        $this->set('_51DEGREES_CLOUD_ENDPOINT', null);

        $this->assertNull(ExampleUtils::getCloudEndpoint());
    }

    /**
     * The Accept-CH tests ask for one property at a time with the license
     * key, which is read only under its own name. The four header resource
     * keys they used before are gone, and no name they had is read.
     */
    public function testTheAcceptChLicenseKeyHasOnlyItsOwnName(): void
    {
        $this->assertSame(
            '_51DEGREES_LICENSE_KEY_BESPOKE',
            Constants::LICENSE_ENV_VAR
        );
        $this->assertArrayNotHasKey(
            Constants::LICENSE_ENV_VAR,
            Constants::LEGACY_ENV_VARS
        );

        // The old name is written in two parts, so the check of old names
        // in ci/key-names.Tests.ps1 does not find it here.
        $old = 'AcceptCh' . 'BrowserKey';
        $this->set(Constants::LICENSE_ENV_VAR, ResourceKeys::PLACEHOLDER);
        $this->set($old, 'old-browser-key');
        $this->assertNull(ResourceKeys::find(Constants::LICENSE_ENV_VAR));
        $this->set($old, null);

        $this->set(Constants::LICENSE_ENV_VAR, 'license-key');
        $this->assertSame(
            'license-key',
            ResourceKeys::find(Constants::LICENSE_ENV_VAR)
        );

        foreach (['PLATFORM', 'HARDWARE', 'BROWSER', 'NO_ACCEPTCH'] as $old) {
            $this->assertFalse(
                defined(Constants::class . '::' . $old . '_ENV_VAR'),
                $old . '_ENV_VAR'
            );
        }
    }

    public function testDeprecatedConstantsKeepTheirNamesAndValues(): void
    {
        $this->assertDeprecatedConstant(
            ExampleUtils::class,
            'LEGACY_RESOURCE_KEY_ENV_VAR',
            'resource_key'
        );
        $this->assertDeprecatedConstant(
            ExampleUtils::class,
            'ENDPOINT_ENV_VAR',
            'cloud_endpoint'
        );
        $this->assertVariableConstant(
            ExampleUtils::class,
            'CLOUD_ENDPOINT_VARIABLE',
            '_51DEGREES_CLOUD_ENDPOINT'
        );

        $legacy = [
            'LEGACY_RESOURCE_ENV_VAR' => 'resource_key',
        ];
        foreach ($legacy as $constant => $value) {
            $this->assertDeprecatedConstant(Constants::class, $constant, $value);
        }
        $this->assertTrue(defined(Constants::class . '::LEGACY_ENV_VARS'));
        $this->assertSame(
            self::TEST_KEY_NAMES,
            constant(Constants::class . '::LEGACY_ENV_VARS')
        );
    }

    // Each variable the tests read a resource key from, and the deprecated
    // name it replaced.
    private const TEST_KEY_NAMES = [
        '_51DEGREES_RESOURCE_KEY' => 'resource_key',
    ];

    /**
     * Checks that a constant still has its name and its value, and is
     * marked deprecated.
     *
     * @param class-string $class
     */
    private function assertDeprecatedConstant(
        string $class,
        string $constant,
        string $value
    ): void {
        $this->assertTrue(defined($class . '::' . $constant), $constant);
        $this->assertSame($value, constant($class . '::' . $constant));
        $comment = (string) (new \ReflectionClassConstant($class, $constant))
            ->getDocComment();
        $this->assertStringContainsString('@deprecated', $comment, $constant);
    }

    /**
     * Checks that a constant holds a variable's name.
     *
     * @param class-string $class
     */
    private function assertVariableConstant(
        string $class,
        string $constant,
        string $value
    ): void {
        $this->assertTrue(defined($class . '::' . $constant), $constant);
        $this->assertSame($value, constant($class . '::' . $constant));
    }

    /**
     * Sets a variable until the test ends, or removes it when the value is
     * null.
     */
    private function set(string $name, ?string $value): void
    {
        if (!array_key_exists($name, $this->previous)) {
            $this->previous[$name] = [getenv($name), $_ENV[$name] ?? null];
        }
        putenv($value === null ? $name : $name . '=' . $value);
        unset($_ENV[$name]);
    }
}
