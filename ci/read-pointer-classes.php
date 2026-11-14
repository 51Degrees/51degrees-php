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

// Run by the Native extensions under Valgrind workflow with both on-premise
// extensions loaded. getComponentId returns a SWIG\_p_byte, one of the
// pointer classes both extensions register under the same name, so the
// second to load replaces the first's. Each engine's ids are read and
// dropped, and each engine processes a request, so Valgrind sees any use of
// a class entry that was freed.

declare(strict_types=1);

function readComponentIds(string $label, $engine): void
{
    $components = $engine->getMetaData()->getComponents();
    for ($i = 0; $i < $components->getSize(); $i++) {
        $component = $components->getByIndex($i);
        $id = $component->getComponentId();
        printf(
            "%s %s %s %d\n",
            $label,
            $component->getName(),
            get_class($id),
            $component->getComponentIdAsInt()
        );
        unset($id, $component);
    }
}

$deviceDetection = FiftyOneDegreesHashEngine::engine_get();
$ipIntelligence = FiftyOneDegreesIpi::ipi_engine_get();

for ($round = 0; $round < 3; $round++) {
    readComponentIds('dd', $deviceDetection);
    readComponentIds('ipi', $ipIntelligence);

    $evidence = new EvidenceDeviceDetectionSwig();
    $evidence->set(
        'header.user-agent',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
    );
    $results = $deviceDetection->process($evidence);
    printf("dd IsMobile %s\n", $results->getValueAsString('IsMobile')->getValue());
    unset($results, $evidence);

    $results = $ipIntelligence->process('116.154.188.222');
    $country = $results->getValuesAsWeightedStringList('RegisteredCountry')->getValue();
    printf("ipi RegisteredCountry %s\n", $country->get(0)->getValue());
    unset($country, $results);
}

// Throwaway: the chained read of 51degrees-php#92, which reads freed memory
// with the wrapper rc has.
echo $ipIntelligence->process('116.154.188.222')
    ->getValuesAsWeightedStringList('RegisteredCountry')
    ->getValue()->get(0)->getValue(), "
";

gc_collect_cycles();
echo "done\n";
