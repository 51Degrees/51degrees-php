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

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\EnvironmentVariable;
use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use fiftyone\pipeline\tests\PhpMessage;
use PHPUnit\Framework\TestCase;

/**
 * A variable is read by its name only.
 * Each constant that names a variable holds that name.
 */
class EnvironmentVariableTests extends TestCase
{
    /** @var array<string, array{0: string|false, 1: mixed}> */
    private $previous = [];

    /** @var array<string> Files a test wrote, removed after it */
    private $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $file) {
            PhpMessage::take(function () use (&$file) {
                return unlink($file);
            });
        }
        $this->written = [];

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

    public function testNoDataFileNamedLeavesTheEngineAlone(): void
    {
        $this->set('_51DEGREES_DD_PATH', null);

        $this->assertNull(ExampleUtils::useDataFileFromEnv(null, null));
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
            'DD_PATH_VARIABLE',
            '_51DEGREES_DD_PATH'
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
