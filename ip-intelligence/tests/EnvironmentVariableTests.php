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
 * A variable is read by its name first and, when that is not set or is
 * empty, by the deprecated name it replaced, whose use is reported once.
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

    public function testNameIsReadBeforeTheDeprecatedName(): void
    {
        $this->set('_51DEGREES_TEST_BOTH', 'new');
        $this->set('OLD_TEST_BOTH', 'old');

        $logged = $this->logged(function (): void {
            $this->assertSame(
                'new',
                EnvironmentVariable::read('_51DEGREES_TEST_BOTH', 'OLD_TEST_BOTH')
            );
        });
        $this->assertSame([], $logged);
    }

    public function testDeprecatedNameIsReadWhenTheNameIsNotSet(): void
    {
        $this->set('_51DEGREES_TEST_UNSET', null);
        $this->set('OLD_TEST_UNSET', 'old');

        $this->logged(function (): void {
            $this->assertSame(
                'old',
                EnvironmentVariable::read('_51DEGREES_TEST_UNSET', 'OLD_TEST_UNSET')
            );
        });
    }

    public function testDeprecatedNameIsReadWhenTheNameIsEmpty(): void
    {
        $this->set('_51DEGREES_TEST_EMPTY', '');
        $this->set('OLD_TEST_EMPTY', 'old');

        $this->logged(function (): void {
            $this->assertSame(
                'old',
                EnvironmentVariable::read('_51DEGREES_TEST_EMPTY', 'OLD_TEST_EMPTY')
            );
        });
    }

    public function testNeitherNameSetGivesAnEmptyString(): void
    {
        $this->set('_51DEGREES_TEST_NEITHER', null);
        $this->set('OLD_TEST_NEITHER', null);

        $logged = $this->logged(function (): void {
            $this->assertSame(
                '',
                EnvironmentVariable::read('_51DEGREES_TEST_NEITHER', 'OLD_TEST_NEITHER')
            );
        });
        $this->assertSame([], $logged);
    }

    public function testPlaceholderCountsAsNotSet(): void
    {
        $this->set('_51DEGREES_TEST_PLACEHOLDER', '!!NOT_SET!!');
        $this->set('OLD_TEST_PLACEHOLDER', 'old');

        $this->logged(function (): void {
            $this->assertSame('old', EnvironmentVariable::read(
                '_51DEGREES_TEST_PLACEHOLDER',
                'OLD_TEST_PLACEHOLDER',
                '!!NOT_SET!!'
            ));
        });

        $this->set('OLD_TEST_PLACEHOLDER', '!!NOT_SET!!');
        $this->assertSame('', EnvironmentVariable::read(
            '_51DEGREES_TEST_PLACEHOLDER',
            'OLD_TEST_PLACEHOLDER',
            '!!NOT_SET!!'
        ));
    }

    public function testDeprecatedNameInUseIsReportedOnce(): void
    {
        $this->set('_51DEGREES_TEST_ONCE', null);
        $this->set('OLD_TEST_ONCE', 'old');

        $logged = $this->logged(function (): void {
            EnvironmentVariable::read('_51DEGREES_TEST_ONCE', 'OLD_TEST_ONCE');
            EnvironmentVariable::read('_51DEGREES_TEST_ONCE', 'OLD_TEST_ONCE');
        });

        $this->assertCount(1, $logged);
        $this->assertStringContainsString('OLD_TEST_ONCE', $logged[0]);
        $this->assertStringContainsString('deprecated', $logged[0]);
        $this->assertStringContainsString('_51DEGREES_TEST_ONCE', $logged[0]);
    }

    public function testExampleKeyIsReadFromItsDeprecatedName(): void
    {
        $this->set('_51DEGREES_RESOURCE_KEY', '!!YOUR_RESOURCE_KEY!!');
        $this->set('resource_key', 'old-key');

        $this->logged(function (): void {
            $this->assertSame('old-key', ExampleUtils::getResourceKey());
        });
    }

    public function testExampleKeyNameWinsOverItsDeprecatedName(): void
    {
        $this->set('_51DEGREES_RESOURCE_KEY', 'new-key');
        $this->set('resource_key', 'old-key');

        $this->assertSame('new-key', ExampleUtils::getResourceKey());
    }

    public function testNoExampleKeyGivesNull(): void
    {
        $this->set('_51DEGREES_RESOURCE_KEY', '!!YOUR_RESOURCE_KEY!!');
        $this->set('resource_key', null);

        $this->assertNull(ExampleUtils::getResourceKey());
    }

    public function testExampleDataFileIsReadFromItsDeprecatedName(): void
    {
        $old = $this->dataFile('old');
        $this->set('_51DEGREES_IPI_PATH', '');
        $this->set('IPINTELLIGENCEDATAFILE', $old);

        $this->logged(function () use ($old): void {
            $this->assertSame($old, ExampleUtils::getDataFile());
        });
    }

    public function testExampleDataFileNameWinsOverItsDeprecatedName(): void
    {
        $new = $this->dataFile('new');
        $this->set('_51DEGREES_IPI_PATH', $new);
        $this->set('IPINTELLIGENCEDATAFILE', $this->dataFile('old'));

        $this->assertSame($new, ExampleUtils::getDataFile());
    }

    public function testTranslationsAreFoundFromTheDeprecatedName(): void
    {
        $old = $this->dataFile('old');
        $this->translations(dirname($old));
        $this->set('_51DEGREES_IPI_PATH', '');
        $this->set('IPINTELLIGENCEDATAFILE', $old);
        $this->forgetDeprecatedVariable();

        // The folder in the repository is looked in first, whatever the
        // environment names, and is there once the submodules are.
        $inRepository = __DIR__ . '/../../cxx/data/ip-intelligence/Translations';
        $this->assertSame(
            realpath(is_dir($inRepository)
                ? $inRepository
                : dirname($old) . '/Translations'),
            realpath(TranslationPaths::resolveTranslationsDir())
        );
    }

    public function testDeprecatedDataFileNameIsLoggedOnceByThePipeline(): void
    {
        $old = $this->dataFile('old');
        $this->translations(dirname($old));
        $this->set('_51DEGREES_IPI_PATH', '');
        $this->set('IPINTELLIGENCEDATAFILE', $old);
        $this->forgetDeprecatedVariable();

        $logger = $this->memoryLogger();
        $this->buildTranslationPipeline($logger);
        $this->buildTranslationPipeline($logger);

        $about = $this->aboutTheDeprecatedName($logger);
        $this->assertCount(1, $about);
        $this->assertSame('warning', $about[0]['level']);
        $this->assertStringContainsString('deprecated', $about[0]['message']);
        $this->assertStringContainsString('_51DEGREES_IPI_PATH', $about[0]['message']);
    }

    public function testDataFileNameIsReadFirstAndNothingIsLogged(): void
    {
        $new = $this->dataFile('new');
        $this->translations(dirname($new));
        $this->set('_51DEGREES_IPI_PATH', $new);
        $this->set('IPINTELLIGENCEDATAFILE', $this->dataFile('old'));
        $this->forgetDeprecatedVariable();

        $inRepository = __DIR__ . '/../../cxx/data/ip-intelligence/Translations';
        $this->assertSame(
            realpath(is_dir($inRepository)
                ? $inRepository
                : dirname($new) . '/Translations'),
            realpath(TranslationPaths::resolveTranslationsDir())
        );

        $logger = $this->memoryLogger();
        $this->buildTranslationPipeline($logger);
        $this->assertSame([], $this->aboutTheDeprecatedName($logger));
    }

    public function testExplicitTranslationsFolderLogsNothing(): void
    {
        $old = $this->dataFile('old');
        $folder = $this->translations(dirname($old));
        $this->set('_51DEGREES_IPI_PATH', '');
        $this->set('IPINTELLIGENCEDATAFILE', $old);
        $this->forgetDeprecatedVariable();

        $logger = $this->memoryLogger();
        (new PipelineBuilder([]))
            ->addLogger($logger)
            ->add(new CountryCodeTranslationEngine($folder))
            ->add(new CountriesTranslationEngine($folder))
            ->build();

        $this->assertSame([], $this->aboutTheDeprecatedName($logger));
    }

    public function testDeprecatedConstantsKeepTheirNamesAndValues(): void
    {
        $this->assertDeprecatedConstant(
            ExampleConstants::class,
            'CLOUD_RESOURCE_KEY_ENV_VAR',
            'resource_key'
        );
        $this->assertDeprecatedConstant(
            ExampleConstants::class,
            'IP_INTELLIGENCE_DATA_FILE_ENV_VAR',
            'IPINTELLIGENCEDATAFILE'
        );
        $this->assertDeprecatedConstant(
            Constants::class,
            'RESOURCE_KEY_ENV_VAR',
            'resource_key'
        );
        $this->assertDeprecatedConstant(
            Constants::class,
            'IP_INTELLIGENCE_DATA_FILE_ENV_VAR',
            'IPINTELLIGENCEDATAFILE'
        );
        foreach ([ExampleConstants::class, Constants::class] as $class) {
            $this->assertVariableConstant(
                $class,
                'RESOURCE_KEY_VARIABLE',
                '_51DEGREES_RESOURCE_KEY'
            );
            $this->assertVariableConstant(
                $class,
                'IPI_PATH_VARIABLE',
                '_51DEGREES_IPI_PATH'
            );
        }
    }

    /**
     * Builds a pipeline of the two translation engines, each finding the
     * translation files for itself.
     */
    private function buildTranslationPipeline(Logger $logger): void
    {
        (new PipelineBuilder([]))
            ->addLogger($logger)
            ->add(new CountryCodeTranslationEngine())
            ->add(new CountriesTranslationEngine())
            ->build();
    }

    /**
     * A logger that keeps every entry.
     */
    private function memoryLogger(): Logger
    {
        return new class ('trace') extends Logger {
            /** @var array<int, array<string, string>> */
            public array $entries = [];

            public function logInternal(array $log): void
            {
                $this->entries[] = $log;
            }
        };
    }

    /**
     * The entries the logger kept that name the deprecated variable.
     *
     * @return array<int, array<string, string>>
     */
    private function aboutTheDeprecatedName(Logger $logger): array
    {
        return array_values(array_filter(
            $logger->entries,
            static fn (array $entry): bool => strpos(
                $entry['message'],
                'IPINTELLIGENCEDATAFILE'
            ) !== false
        ));
    }

    /**
     * Makes the package's once-only log entry possible again, as it is
     * when PHP starts a script.
     */
    private function forgetDeprecatedVariable(): void
    {
        \Closure::bind(static function (): void {
            self::$oldIpiPathVariableRead = false;
            self::$oldIpiPathVariableReported = false;
        }, null, TranslationPaths::class)();
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

    /**
     * Runs the action and returns the lines it wrote to the PHP error log.
     * The log is pointed at a file from inside the test, because PHPUnit
     * points it at a file of its own while a test runs.
     *
     * @return string[]
     */
    private function logged(callable $action): array
    {
        $log = (string) tempnam(sys_get_temp_dir(), 'env');
        $previous = ini_set('error_log', $log);
        try {
            $action();

            return array_values(array_filter(
                array_map('trim', (array) file($log)),
                'strlen'
            ));
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($log);
        }
    }
}
