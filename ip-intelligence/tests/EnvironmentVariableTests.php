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

use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\translation\CountriesTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\CountryCodeTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\TranslationPaths;
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
        foreach ($this->folders as $folder) {
            $files = [
                '/Translations/OSM/countries.fr_FR.yml',
                '/Translations/countrycodes.en_GB.yml',
            ];
            foreach (array_merge($files, glob($folder . '/*.ipi') ?: []) as $file) {
                $file = strpos($file, $folder) === 0 ? $file : $folder . $file;
                if (is_file($file)) {
                    unlink($file);
                }
            }
            foreach (['/Translations/OSM', '/Translations', ''] as $inner) {
                if (is_dir($folder . $inner)) {
                    rmdir($folder . $inner);
                }
            }
        }
        $this->folders = [];
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

        $this->assertNull(ExampleUtils::getResourceKey());
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
            ExampleConstants::class,
            'RESOURCE_KEY_VARIABLE',
            '_51DEGREES_RESOURCE_KEY'
        );
        $this->assertVariableConstant(
            ExampleConstants::class,
            'IPI_PATH_VARIABLE',
            '_51DEGREES_IPI_PATH'
        );
        $this->assertVariableConstant(
            Constants::class,
            'RESOURCE_KEY_VARIABLE',
            '_51DEGREES_RESOURCE_KEY_BESPOKE'
        );
        $this->assertVariableConstant(
            Constants::class,
            'IPI_PATH_VARIABLE',
            '_51DEGREES_IPI_PATH'
        );
    }

    /**
     * A Translations folder beside a data file, holding one country in
     * English and in French.
     */
    private function translations(string $folder): string
    {
        $translations = $folder . '/Translations';
        mkdir($translations . '/OSM', 0777, true);
        file_put_contents(
            $translations . '/countrycodes.en_GB.yml',
            "GB: United Kingdom\n"
        );
        file_put_contents(
            $translations . '/OSM/countries.fr_FR.yml',
            "United Kingdom: Royaume-Uni\n"
        );

        return $translations;
    }

    /**
     * An empty data file in a folder of its own, named for the variable
     * that will give it.
     */
    private function dataFile(string $label): string
    {
        $folder = sys_get_temp_dir() . '/ipi-' . $label . '-' . uniqid();
        mkdir($folder);
        $path = $folder . '/' . $label . '.ipi';
        touch($path);
        $this->folders[] = $folder;

        return $path;
    }

    /** @var string[] The folders made by dataFile */
    private $folders = [];

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
