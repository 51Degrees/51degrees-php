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

namespace fiftyone\pipeline\ipintelligence\examples\classes;

use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\translation\CountriesTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\CountryCodeTranslationEngine;

/**
 * Country translation console example (on-premise). Mirrors
 * ip-intelligence-dotnet-examples OnPremise/GettingStarted-Console: for each
 * IP it prints the ordered "All" code lists (most probable country first,
 * then the rest alphabetically) for both the geographical and population
 * dimensions.
 */
class TranslationConsoleOnPremise
{
    /**
     * Build the on-premise pipeline (IP intelligence + the two translation
     * engines) and analyse the example IP addresses.
     *
     * @param resource $output
     */
    public function run(string $dataFile, $output): void
    {
        $pipeline = (new PipelineBuilder([]))
            ->add(new IpIntelligenceOnPremise(['dataFile' => $dataFile, 'performanceProfile' => 'MaxPerformance']))
            ->add(new CountryCodeTranslationEngine())
            ->add(new CountriesTranslationEngine())
            ->build();

        foreach (ExampleConstants::EVIDENCE_VALUES as $evidence) {
            $this->analyse($pipeline, $evidence, $output);
        }
    }

    /**
     * Process a single piece of evidence and print the ordered code lists.
     * Separated out so it can be exercised with an injected pipeline (for
     * example a mocked IP intelligence engine) in tests.
     *
     * @param array<string, string> $evidence
     * @param resource $output
     */
    public function analyse(Pipeline $pipeline, array $evidence, $output): void
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setArray($evidence);
        $result = $flowData->process();

        $translated = $result->countrynamestranslated;
        $geo = $translated->get('countrycodesgeographicalall');
        $pop = $translated->get('countrycodespopulationall');

        fwrite($output, 'Input: ' . json_encode($evidence) . "\n");
        fwrite($output, "\tCountryCodesGeographicalAll: " . $this->codesLine($geo) . "\n");
        fwrite($output, "\tCountryCodesPopulationAll: " . $this->codesLine($pop) . "\n\n");
    }

    private function codesLine($apv): string
    {
        if (!$apv->hasValue || !is_array($apv->value)) {
            return '(none)';
        }

        return implode(', ', $apv->value);
    }
}
