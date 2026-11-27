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
use PHPUnit\Framework\TestCase;

/**
 * A variable is read by its name only.
 * Each constant that names a variable holds that name.
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

    public function testAValueOnlyInTheEnvArrayIsNotRead(): void
    {
        $this->set('_51DEGREES_TEST_UNSET', null);
        $_ENV['_51DEGREES_TEST_UNSET'] = 'not-in-the-environment';

        $this->assertSame('', EnvironmentVariable::read('_51DEGREES_TEST_UNSET'));
    }

    public function testConstantsHoldTheVariableNames(): void
    {
        $this->assertVariableConstant(
            ExampleUtils::class,
            'RESOURCE_KEY_ENV_VAR',
            '_51DEGREES_RESOURCE_KEY'
        );
        $this->assertVariableConstant(
            ExampleUtils::class,
            'CLOUD_ENDPOINT_VARIABLE',
            '_51DEGREES_CLOUD_ENDPOINT'
        );
        $this->assertVariableConstant(
            Constants::class,
            'RESOURCE_ENV_VAR',
            '_51DEGREES_RESOURCE_KEY_BESPOKE'
        );
        $this->assertVariableConstant(
            Constants::class,
            'LICENSE_ENV_VAR',
            '_51DEGREES_LICENSE_KEY_BESPOKE'
        );
        $this->assertVariableConstant(
            Constants::class,
            'BASIC_RESOURCE_ENV_VAR',
            '_51DEGREES_RESOURCE_KEY_BASIC'
        );
        // The two names only CI sets, for the staging cloud service.
        $this->assertVariableConstant(
            Constants::class,
            'STAGING_ENDPOINT_ENV_VAR',
            'CLOUD_STAGING_ENDPOINT'
        );
        $this->assertVariableConstant(
            Constants::class,
            'STAGING_RESOURCE_ENV_VAR',
            'RESOURCE_KEY_BESPOKE_STAGING'
        );
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
