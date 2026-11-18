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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\translation\TranslationEngine;
use fiftyone\pipeline\translation\TranslationEngineBuilder;
use fiftyone\pipeline\translation\tests\classes\SourceElement;
use PHPUnit\Framework\TestCase;

/**
 * Several Translation Engines in one Pipeline, which the Translation Engine
 * specification's Key Considerations and Behavior of Multiple Engines say
 * write to one Element Data.
 */
class MultipleEnginesTests extends TestCase
{
    /**
     * Two engines with the same Data Key, one built with the builder and one
     * with the constructor, each add their Property to one Element Data.
     */
    public function testEnginesWithTheSameKeyShareOneElementData(): void
    {
        $flowData = self::process(self::french(), self::spanish('translation'));
        $data = $flowData->get('translation');

        $this->assertSame('Angleterre', $data->get('countryfrench')->value);
        $this->assertSame('Inglaterra', $data->get('countryspanish')->value);
    }

    /**
     * The JSON has one entry for the shared Element Data, holding the
     * Properties of both engines.
     */
    public function testTheJsonHoldsThePropertiesOfEveryEngine(): void
    {
        $json = self::process(self::french(), self::spanish('translation'))
            ->jsonbundler->json;

        $this->assertSame(
            ['countryfrench' => 'Angleterre', 'countryspanish' => 'Inglaterra'],
            $json['translation']
        );
    }

    /**
     * Engines with different Data Keys each keep their own Element Data.
     */
    public function testEnginesWithDifferentKeysKeepTheirOwnData(): void
    {
        $flowData = self::process(self::french(), self::spanish('spanish'));

        $this->assertSame(
            ['countryfrench'],
            array_keys($flowData->get('translation')->asDictionary())
        );
        $this->assertSame(
            ['countryspanish'],
            array_keys($flowData->get('spanish')->asDictionary())
        );
    }

    private static function french(): TranslationEngine
    {
        return (new TranslationEngineBuilder())
            ->setSourceElementDataKey('ip')
            ->addSource('countries.fr_FR.yml', 'England: Angleterre')
            ->addTranslation('Country', 'CountryFrench')
            ->setFixedLanguage('fr_FR')
            ->build();
    }

    private static function spanish(string $elementDataKey): TranslationEngine
    {
        return new TranslationEngine(
            $elementDataKey,
            'ip',
            ['Country' => 'CountrySpanish'],
            ['es_ES' => ['England' => 'Inglaterra']],
            'es_ES'
        );
    }

    /**
     * Processes a Pipeline of the source element and the engines, in that
     * order.
     */
    private static function process(TranslationEngine ...$engines): FlowData
    {
        $builder = (new PipelineBuilder())->add(new SourceElement());
        foreach ($engines as $engine) {
            $builder->add($engine);
        }

        return $builder->build()->createFlowData()->process();
    }
}
