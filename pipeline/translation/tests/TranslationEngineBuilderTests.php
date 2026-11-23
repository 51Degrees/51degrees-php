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

namespace fiftyone\pipeline\translation\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngine;
use fiftyone\pipeline\translation\TranslationEngineBuilder;
use fiftyone\pipeline\translation\tests\classes\SourceElement;
use PHPUnit\Framework\TestCase;

/**
 * An upstream element giving a list of countries under the data key ip.
 */
class CountryListElement extends FlowElement
{
    public string $dataKey = 'ip';

    public array $properties = [
        'countries' => ['name' => 'Countries', 'type' => 'Array'],
    ];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'countries' => new AspectPropertyValue(null, ['England', 'Scotland']),
        ]));
    }
}

/**
 * Building the Translation Engine in code with TranslationEngineBuilder, as
 * the Translation Engine specification's Components, Sources and
 * Configuration sections describe.
 */
class TranslationEngineBuilderTests extends TestCase
{
    // A folder of source files, made for each test and removed after it.
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR .
            'translation-builder-' . bin2hex(random_bytes(6));
        mkdir($this->folder);
        file_put_contents(
            $this->folder . '/countries.fr_FR.yml',
            "England: Angleterre\nScotland: Ecosse\n"
        );
        file_put_contents(
            $this->folder . '/countries.es_ES.yaml',
            "England: Inglaterra\n"
        );
        file_put_contents($this->folder . '/notes.txt', "Not a source.\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->folder);
    }

    public function testBuildGivesATranslationEngine(): void
    {
        $engine = (new TranslationEngineBuilder())
            ->setSourceElementDataKey('ip')
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->addTranslation('Country', 'CountryTranslated')
            ->setFixedLanguage('fr_FR')
            ->build();

        $this->assertInstanceOf(TranslationEngine::class, $engine);
        $this->assertSame(
            TranslationEngine::DEFAULT_ELEMENT_DATA_KEY,
            $engine->dataKey
        );
        $this->assertSame('Angleterre', self::translated($engine));
    }

    public function testASourceGivenByPath(): void
    {
        $engine = self::builder()
            ->addSource($this->folder . '/countries.fr_FR.yml')
            ->setFixedLanguage('fr_FR')
            ->build();

        $this->assertSame('Angleterre', self::translated($engine));
    }

    /**
     * A wildcard adds every file it matches, one per language, and the
     * language comes from the Evidence when none is fixed. A .yaml file is
     * read as well as a .yml one.
     */
    public function testAWildcardAddsEachLanguage(): void
    {
        $engine = self::builder()
            ->addSource($this->folder . '/countries.*')
            ->build();

        $this->assertSame(
            'Angleterre',
            self::translated($engine, ['query.translation' => 'fr_FR'])
        );
        $this->assertSame(
            'Inglaterra',
            self::translated($engine, ['header.accept-language' => 'es-ES'])
        );
    }

    /**
     * A relative path is resolved against the working directory, as the
     * file system paths feature asks.
     */
    public function testARelativePathIsResolvedAgainstTheWorkingDirectory(): void
    {
        $workingDirectory = getcwd();
        chdir($this->folder);
        try {
            $engine = self::builder()
                ->addSource('countries.fr_FR.yml')
                ->addSource('*.yaml')
                ->build();
        } finally {
            chdir($workingDirectory);
        }

        $this->assertSame(
            'Angleterre',
            self::translated($engine, ['query.translation' => 'fr_FR'])
        );
        $this->assertSame(
            'Inglaterra',
            self::translated($engine, ['query.translation' => 'es_ES'])
        );
    }

    /**
     * Two sources for one language are both used.
     */
    public function testSourcesForOneLanguageAreCombined(): void
    {
        $engine = (new TranslationEngineBuilder())
            ->setSourceElementDataKey('ip')
            ->addSource('england.fr_FR.yml', 'England: Angleterre')
            ->addSource('scotland.fr_FR.yml', 'Scotland: Ecosse')
            ->addTranslation('Countries', 'CountriesTranslated')
            ->setFixedLanguage('fr_FR')
            ->build();

        $pipeline = (new PipelineBuilder())
            ->add(new CountryListElement())
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(
            ['Angleterre', 'Ecosse'],
            $flowData->get('translation')->get('countriestranslated')->value
        );
    }

    public function testTheMissingTranslationBehaviorIsOriginalByDefault(): void
    {
        $engine = self::scotlandOnly()->build();

        $this->assertSame('England', self::translated($engine));
    }

    public function testTheMissingTranslationBehaviorCanBeSet(): void
    {
        $engine = self::scotlandOnly()
            ->setMissingTranslationBehavior(MissingTranslationBehavior::EMPTY_STRING)
            ->build();

        $this->assertSame('', self::translated($engine));
    }

    /**
     * The behavior is matched without regard to case, as it is in a
     * configuration file.
     */
    public function testTheMissingTranslationBehaviorIgnoresCase(): void
    {
        $engine = self::scotlandOnly()
            ->setMissingTranslationBehavior('flowerror')
            ->build();
        $pipeline = (new PipelineBuilder(['suppressProcessExceptions' => true]))
            ->add(new SourceElement())
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(['translation'], array_keys($flowData->errors));
    }

    public function testAnUnknownMissingTranslationBehaviorIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TranslationEngineBuilder())->setMissingTranslationBehavior('Guess');
    }

    public function testTheElementDataKeyCanBeSet(): void
    {
        $engine = self::builder()
            ->setElementDataKey('countries')
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->setFixedLanguage('fr_FR')
            ->build();

        $this->assertSame('countries', $engine->dataKey);
        $this->assertSame('Angleterre', self::translated($engine));
    }

    public function testBuildNeedsASourceElementDataKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('setSourceElementDataKey');
        (new TranslationEngineBuilder())
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->addTranslation('Country', 'CountryTranslated')
            ->build();
    }

    public function testBuildNeedsATranslation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('addTranslation');
        (new TranslationEngineBuilder())
            ->setSourceElementDataKey('ip')
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->build();
    }

    public function testBuildNeedsASource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('addSource');
        self::builder()->build();
    }

    /**
     * A fixed language with no source would leave every value
     * untranslated, so it is refused, naming the languages there are.
     */
    public function testAFixedLanguageWithNoSourceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fr_FR');
        self::builder()
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->setFixedLanguage('fr-FR')
            ->build();
    }

    /**
     * A source whose name gives no language would never be used, so it is
     * refused.
     */
    public function testASourceNameWithNoLanguageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('countries.yml');
        (new TranslationEngineBuilder())->addSource('countries.yml', 'England: Angleterre');
    }

    public function testASourceThatIsNotYamlIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('countries.fr_FR.txt');
        (new TranslationEngineBuilder())->addSource('countries.fr_FR.txt', 'England: Angleterre');
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('countries.de_DE.yml');
        (new TranslationEngineBuilder())->addSource($this->folder . '/countries.de_DE.yml');
    }

    public function testAWildcardMatchingNothingIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('regions.*.yml');
        (new TranslationEngineBuilder())->addSource($this->folder . '/regions.*.yml');
    }

    /**
     * The engine gives one destination for each source property, so a
     * second destination for the same source property is refused rather
     * than replacing the first.
     */
    public function testASecondTranslationOfOneSourcePropertyIsRefused(): void
    {
        $builder = (new TranslationEngineBuilder())
            ->addTranslation('Country', 'CountryTranslated');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CountryTranslated');
        $builder->addTranslation('Country', 'CountryInFrench');
    }

    /**
     * A builder with the source element and the translation every test
     * uses.
     */
    private static function builder(): TranslationEngineBuilder
    {
        return (new TranslationEngineBuilder())
            ->setSourceElementDataKey('ip')
            ->addTranslation('Country', 'CountryTranslated');
    }

    /**
     * A builder whose one source has no translation for England, the value
     * the source element gives.
     */
    private static function scotlandOnly(): TranslationEngineBuilder
    {
        return self::builder()
            ->addSource('countries.fr_FR.yml', 'Scotland: Ecosse')
            ->setFixedLanguage('fr_FR');
    }

    /**
     * The value the engine gives for the source element's country, England.
     *
     * @param array<string, string> $evidence
     * @return mixed
     */
    private static function translated(TranslationEngine $engine, array $evidence = [])
    {
        $pipeline = (new PipelineBuilder())
            ->add(new SourceElement())
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->get($engine->dataKey)->get('countrytranslated')->value;
    }
}
