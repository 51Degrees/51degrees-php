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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\tests\classes\MockIpEngine;
use fiftyone\pipeline\ipintelligence\translation\CountriesTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\CountryCodeTranslationEngine;
use PHPUnit\Framework\TestCase;

class IpIntelligenceTranslationTests extends TestCase
{
    // ---- helpers --------------------------------------------------------

    /**
     * Build a weighted list assigning descending weights by input order, so
     * the first code is the most probable. Mirrors the .NET Weightify helper.
     *
     * @param array<string> $codes
     * @return array<array<string, mixed>>
     */
    private static function weightify(array $codes): array
    {
        $n = count($codes);
        $out = [];
        foreach ($codes as $i => $code) {
            $out[] = ['value' => $code, 'rawweighting' => $n - $i];
        }

        return $out;
    }

    /**
     * Build a weighted list from explicit [code, weight] pairs.
     *
     * @param array<array{0: string, 1: int}> $pairs
     * @return array<array<string, mixed>>
     */
    private static function weightedExplicit(array $pairs): array
    {
        return array_map(static fn ($p) => ['value' => $p[0], 'rawweighting' => $p[1]], $pairs);
    }

    /**
     * @param array<array<string, mixed>> $geo
     * @param array<array<string, mixed>> $pop
     */
    private function buildPipeline(array $geo, array $pop)
    {
        return (new PipelineBuilder([]))
            ->add(new MockIpEngine($geo, $pop))
            ->add(new CountryCodeTranslationEngine())
            ->add(new CountriesTranslationEngine())
            ->build();
    }

    /**
     * @param array<array<string, mixed>> $geo
     * @param array<array<string, mixed>> $pop
     * @param array<string, string> $evidence
     */
    private function process(array $geo, array $pop, array $evidence = [])
    {
        $pipeline = $this->buildPipeline($geo, $pop);
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData;
    }

    /**
     * Extract the plain values from a weighted list AspectPropertyValue.
     *
     * @return array<string>
     */
    private function weightedValues($apv): array
    {
        return array_map(static fn ($i) => $i['value'], $apv->value);
    }

    // ---- tests (10a: ported from the .NET suite) -----------------------

    public function testCountryNamesFromCodes(): void
    {
        $weighted = self::weightify(['GB', 'FR']);
        $flowData = $this->process($weighted, $weighted);

        $names = $flowData->get('countrynames');
        $this->assertSame(
            ['United Kingdom', 'France'],
            $this->weightedValues($names->get('countrynamesgeographical'))
        );
        $this->assertSame(
            ['United Kingdom', 'France'],
            $this->weightedValues($names->get('countrynamespopulation'))
        );
    }

    public function testTranslatedCountryFrench(): void
    {
        $weighted = self::weightify(['GB', 'FR']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $translated = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['Royaume-Uni', 'France'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
    }

    public function testAllListsProducedCorrectlySorted(): void
    {
        // FR=30000, GB=35535. GB has the higher weight so leads the list.
        $weighted = self::weightedExplicit([['FR', 30000], ['GB', 35535]]);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $data = $flowData->get('countrynamestranslated');
        $names = $data->get('countrynamesgeographicalalltranslated')->value;
        $codes = $data->get('countrycodesgeographicalall')->value;

        $this->assertSame('GB', $codes[0]);
        $this->assertSame('Royaume-Uni', $names[0]);
        $this->assertSame('FR', $codes[1]);
        $this->assertSame('France', $names[1]);

        // Over 200 countries, GB/FR not repeated in the tail.
        $this->assertGreaterThan(200, count($codes));
        $this->assertSame(count($codes), count($names));
        $this->assertSame(1, count(array_keys($codes, 'GB', true)));
        $this->assertSame(1, count(array_keys($codes, 'FR', true)));
    }

    public function testEnglishLanguageNoTranslation(): void
    {
        $weighted = self::weightify(['GB', 'FR']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'en-US,en;q=0.9']);

        $translated = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['United Kingdom', 'France'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('', $translated->get('sortingcultureused')->value);
    }

    public function testPreferredLanguageMatchedBeforeLowerPriority(): void
    {
        $weighted = self::weightify(['DE']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'es,de-DE;q=0.8,fr;q=0.5']);

        // Spanish wins (es -> es_ES). Germany in Spanish is "Alemania".
        $translated = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['Alemania'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('es-ES', $translated->get('sortingcultureused')->value);
    }

    // ---- tests (10b: gap tests) ----------------------------------------

    public function testWeightsPreservedThroughTranslation(): void
    {
        $weighted = self::weightedExplicit([['GB', 35535], ['FR', 30000]]);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $items = $flowData->get('countrynamestranslated')
            ->get('countrynamesgeographicaltranslated')->value;

        // Names changed, weights unchanged.
        $this->assertSame('Royaume-Uni', $items[0]['value']);
        $this->assertSame(35535, $items[0]['rawweighting']);
        $this->assertSame('France', $items[1]['value']);
        $this->assertSame(30000, $items[1]['rawweighting']);
    }

    public function testEvidencePrecedenceQueryTranslationOverrides(): void
    {
        $weighted = self::weightify(['DE']);
        // query.translation should win over header.accept-language.
        $flowData = $this->process($weighted, $weighted, [
            'header.accept-language' => 'fr_FR',
            'query.translation' => 'de_DE',
        ]);

        $translated = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['Deutschland'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('de-DE', $translated->get('sortingcultureused')->value);
    }

    // ---- remaining 10a ports -------------------------------------------

    public function testAllListsProducedCorrectly(): void
    {
        $weighted = self::weightify(['GB', 'FR']); // GB highest
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $data = $flowData->get('countrynamestranslated');
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;

        $this->assertSame('GB', $codes[0]);
        $this->assertSame('Royaume-Uni', $names[0]);
        $this->assertSame('FR', $codes[1]);
        $this->assertSame(count($codes), count($names));
    }

    public function testAllListsWithoutLanguage(): void
    {
        $weighted = self::weightify(['GB', 'FR']);
        $flowData = $this->process($weighted, $weighted); // no evidence

        $data = $flowData->get('countrynamestranslated');
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;

        $this->assertSame('GB', $codes[0]);
        $this->assertSame('United Kingdom', $names[0]); // English, untranslated
        $this->assertSame('', $data->get('sortingcultureused')->value);
        $this->assertTrue($this->isSorted(array_slice($names, 2)));
    }

    public function testAllListsWithNoIpData(): void
    {
        $flowData = $this->process([], [], ['header.accept-language' => 'de_DE']);

        $data = $flowData->get('countrynamestranslated');
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;

        $this->assertGreaterThan(200, count($codes));
        $this->assertSame(count($codes), count($names));
        // Fully alphabetical when there are no weighted countries.
        $this->assertTrue($this->isSorted($names));
    }

    public function testPopulationAndGeographicalAreIndependent(): void
    {
        $geo = self::weightify(['DE']);
        $pop = self::weightify(['US', 'CN']);
        $flowData = $this->process($geo, $pop, ['header.accept-language' => 'de_DE']);

        $data = $flowData->get('countrynamestranslated');
        $geoCodes = $data->get('countrycodesgeographicalall')->value;
        $popCodes = $data->get('countrycodespopulationall')->value;

        $this->assertSame('DE', $geoCodes[0]);
        $this->assertSame('US', $popCodes[0]);
        $this->assertSame('CN', $popCodes[1]);
    }

    public function testGermanTranslation(): void
    {
        $weighted = self::weightify(['DE']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'de_DE']);

        $data = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['Deutschland'],
            $this->weightedValues($data->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('DE', $data->get('countrycodesgeographicalall')->value[0]);
        $this->assertSame('Deutschland', $data->get('countrynamesgeographicalalltranslated')->value[0]);
    }

    public function testAcceptLanguageWithDash(): void
    {
        $weighted = self::weightify(['GB', 'FR']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr-FR']);

        $translated = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['Royaume-Uni', 'France'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('fr-FR', $translated->get('sortingcultureused')->value);
    }

    public function testEnglishPreferredOverOtherLanguages(): void
    {
        $weighted = self::weightify(['DE']);
        $flowData = $this->process($weighted, $weighted, [
            'header.accept-language' => 'en-US,en;q=0.9,de-DE;q=0.5,fr;q=0.3',
        ]);

        $translated = $flowData->get('countrynamestranslated');
        // English wins despite German/French being available: no translation.
        $this->assertSame(
            ['Germany'],
            $this->weightedValues($translated->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame('', $translated->get('sortingcultureused')->value);
    }

    // ---- remaining 10b gap tests ---------------------------------------

    public function testEqualWeightTieBreak(): void
    {
        // Equal weights: order falls back to translated-name order.
        $weighted = self::weightedExplicit([['FR', 5000], ['DE', 5000]]);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $data = $flowData->get('countrynamestranslated');
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;

        // In French: Allemagne (DE) sorts before France (FR).
        $this->assertSame('DE', $codes[0]);
        $this->assertSame('Allemagne', $names[0]);
        $this->assertSame('FR', $codes[1]);
        $this->assertSame('France', $names[1]);
    }

    public function testUnknownLocaleFallsBackToEnglish(): void
    {
        $weighted = self::weightify(['GB']);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'zz-ZZ']);

        $data = $flowData->get('countrynamestranslated');
        $this->assertSame(
            ['United Kingdom'],
            $this->weightedValues($data->get('countrynamesgeographicaltranslated'))
        );
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;
        $this->assertGreaterThan(200, count($codes));
        $this->assertSame(count($codes), count($names));
        $this->assertSame('', $data->get('sortingcultureused')->value);
    }

    public function testMissingSingleNameStaysOriginal(): void
    {
        // Direct test of the base behaviour: a value absent from the map is
        // returned unchanged under the Original behaviour.
        $translator = new \fiftyone\pipeline\translation\Translator(
            ['France' => 'Frankreich'],
            \fiftyone\pipeline\translation\MissingTranslationBehavior::ORIGINAL
        );
        $errors = [];
        $this->assertSame('Frankreich', $translator->translate('France', $errors));
        $this->assertSame('Germany', $translator->translate('Germany', $errors));
        $this->assertCount(0, $errors);
    }

    public function testFullIndexAlignment(): void
    {
        $dir = \fiftyone\pipeline\ipintelligence\translation\TranslationPaths::resolveTranslationsDir();
        $codeToEnglish = \fiftyone\pipeline\translation\YamlMap::parseFile($dir . '/countrycodes.en_GB.yml');
        $englishToFrench = \fiftyone\pipeline\translation\YamlMap::parseFile($dir . '/OSM/countries.fr_FR.yml');

        $weighted = self::weightedExplicit([['FR', 30000], ['GB', 35535]]);
        $flowData = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);

        $data = $flowData->get('countrynamestranslated');
        $codes = $data->get('countrycodesgeographicalall')->value;
        $names = $data->get('countrynamesgeographicalalltranslated')->value;

        // For every index, the name is the French translation (or English
        // fallback) of the country whose code is at the same index.
        for ($i = 0; $i < count($codes); ++$i) {
            $english = $codeToEnglish[$codes[$i]] ?? null;
            $this->assertNotNull($english, "Unknown code at index {$i}: {$codes[$i]}");
            $expected = $englishToFrench[$english] ?? $english;
            $this->assertSame($expected, $names[$i], "Misalignment at index {$i}");
        }
    }

    public function testSortingCultureUsed(): void
    {
        $weighted = self::weightify(['FR']);
        $french = $this->process($weighted, $weighted, ['header.accept-language' => 'fr_FR']);
        $this->assertSame('fr-FR', $french->get('countrynamestranslated')->get('sortingcultureused')->value);

        $english = $this->process($weighted, $weighted, ['header.accept-language' => 'en-GB']);
        $this->assertSame('', $english->get('countrynamestranslated')->get('sortingcultureused')->value);
    }

    public function testPopulationTranslatedNamesDifferFromGeographical(): void
    {
        $geo = self::weightify(['FR']);
        $pop = self::weightify(['DE']);
        $flowData = $this->process($geo, $pop, ['header.accept-language' => 'de_DE']);

        $data = $flowData->get('countrynamestranslated');
        // In German: France -> Frankreich, Germany -> Deutschland.
        $this->assertSame(
            ['Frankreich'],
            $this->weightedValues($data->get('countrynamesgeographicaltranslated'))
        );
        $this->assertSame(
            ['Deutschland'],
            $this->weightedValues($data->get('countrynamespopulationtranslated'))
        );
        $this->assertSame('Deutschland', $data->get('countrynamespopulationalltranslated')->value[0]);
    }

    public function testConsoleExampleOrdersMostProbableFirst(): void
    {
        // FR=30000, GB=35535 -> GB is most probable and must print first.
        $weighted = self::weightedExplicit([['FR', 30000], ['GB', 35535]]);
        $pipeline = $this->buildPipeline($weighted, $weighted);

        $output = fopen('php://memory', 'rw+');
        (new \fiftyone\pipeline\ipintelligence\examples\classes\TranslationConsoleOnPremise())
            ->analyse($pipeline, ['query.client-ip' => '1.2.3.4', 'header.accept-language' => 'fr_FR'], $output);
        rewind($output);
        $text = (string) stream_get_contents($output);

        $this->assertStringContainsString('CountryCodesGeographicalAll: GB, FR,', $text);
    }

    /**
     * @param array<string> $names
     */
    private function isSorted(array $names): bool
    {
        $comparer = static function (string $a, string $b): int {
            $normalize = static function (string $s): string {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

                return strtolower($t === false ? $s : $t);
            };

            return strcmp($normalize($a), $normalize($b));
        };
        for ($i = 0; $i < count($names) - 1; ++$i) {
            if ($comparer($names[$i], $names[$i + 1]) > 0) {
                return false;
            }
        }

        return true;
    }
}
