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

namespace fiftyone\pipeline\did\tests;

use PHPUnit\Framework\TestCase;

/**
 * A variable is read by its name only.
 * The example server reads its keys the same way.
 */
class EnvironmentVariableTest extends TestCase
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
