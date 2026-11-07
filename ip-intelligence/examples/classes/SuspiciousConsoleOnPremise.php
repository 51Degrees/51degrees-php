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

/**
 * Suspicious IP analysis (on-premise). Mirrors
 * ip-intelligence-dotnet-examples OnPremise/Suspicious-Console: it combines the
 * IP intelligence diversity properties with network/location values to make a
 * simple assessment of whether an IP address is likely to be the source of
 * suspicious requests (VPN / proxy / hosting).
 *
 * This is a basic heuristic for illustration and should be tuned and tested
 * before any production use. An enterprise data file is needed for the
 * diversity properties to be populated.
 */
class SuspiciousConsoleOnPremise
{
    // Where to ask for a copy of the Enterprise data file, tagged with this
    // example.
    private const CONTACT_US = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-classes-suspiciousconsoleonpremise.php&utm_term=enterprise-data-file';

    // The properties the example decides with, which only the Enterprise
    // data file has.
    private const SUSPICIOUS_PROPERTIES = [
        'IsCellular',
        'HardwareDiversity',
        'BrowserDiversity',
        'LocationConfidence',
        'IsHosted',
        'HumanProbability'
    ];

    /**
     * @param resource $output
     */
    public function run(string $dataFile, $output): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            fwrite($output, "The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.\n");
            return;
        }

        $config = new \ConfigIpiSwig();
        // PHP runs the engine with the whole data file in memory, as
        // on-premise/ipi-php.i explains.
        $config->setMaxPerformance();
        $config->setUseUpperPrefixHeaders(false);
        $engine = new \EngineIpiSwig($dataFile, $config, new \RequiredPropertiesConfigSwig(new \VectorStringSwig()));
        if (ExampleUtils::lacksEnterpriseProperties(
            $engine,
            'Finding suspicious IP addresses',
            self::SUSPICIOUS_PROPERTIES,
            $output,
            self::CONTACT_US
        )) {
            return;
        }

        foreach (ExampleConstants::EVIDENCE_VALUES as $evidence) {
            $this->analyseEvidence($evidence, $engine, $output);
        }
    }

    /**
     * @param array<string, string> $evidence
     * @param resource $output
     */
    private function analyseEvidence(array $evidence, $engine, $output): void
    {
        $ip = $evidence['query.client-ip'];
        $results = $engine->process($ip);

        $isCellular = $this->boolVal($results, 'IsCellular');
        $hardwareDiversity = $this->intVal($results, 'HardwareDiversity');
        $browserDiversity = $this->intVal($results, 'BrowserDiversity');
        $locationConfidence = $this->strVal($results, 'LocationConfidence');
        $hosted = $this->boolVal($results, 'IsHosted');
        $country = $this->strVal($results, 'CountryCode');
        $registeredCountry = $this->strVal($results, 'RegisteredCountry');
        $human = $this->intVal($results, 'HumanProbability');

        // A simple "suspicious" score combining the properties above. This
        // mirrors the .NET example exactly. It is a basic illustration only.
        $isSuspicious =
            // High hardware-profile diversity, not cellular, low location
            // confidence -> likely VPN / proxy.
            ($hardwareDiversity >= 7 && $isCellular === false && $locationConfidence === 'Low')
            // Hosted, and the observed country differs from the registered
            // country -> indication of VPN / proxy use.
            || ($hosted === true && $country !== null && $country !== 'Unknown' && $country !== $registeredCountry)
            // Browser versions significantly more diverse than hardware.
            || (($browserDiversity - $hardwareDiversity) > 2);

        fwrite($output, "Input values:\n");
        foreach ($evidence as $key => $value) {
            fwrite($output, sprintf("\t%s: %s\n", $key, $value));
        }
        fwrite($output, "Results:\n");
        fwrite($output, sprintf("\tIsCellular: %s\n", $isCellular ? 'true' : 'false'));
        fwrite($output, sprintf("\tHardwareDiversity: %d\n", $hardwareDiversity));
        fwrite($output, sprintf("\tBrowserDiversity: %d\n", $browserDiversity));
        fwrite($output, sprintf("\tLocationConfidence: %s\n", $locationConfidence ?? 'Unknown'));
        fwrite($output, sprintf("\tIsHosted: %s\n", $hosted ? 'true' : 'false'));
        fwrite($output, sprintf("\tCountry: %s\n", $country ?? 'Unknown'));
        fwrite($output, sprintf("\tRegisteredCountry: %s\n", $registeredCountry ?? 'Unknown'));
        fwrite($output, sprintf("\tHumanProbability: %d\n", $human));
        fwrite($output, sprintf("\tIsSuspicious: %s\n", $isSuspicious ? 'true' : 'false'));
        fwrite($output, "\n");
    }

    private function intVal($results, string $name): int
    {
        $v = $results->getValueAsInteger($name);
        return $v->hasValue() ? (int) $v->getValue() : 0;
    }

    private function boolVal($results, string $name): bool
    {
        $v = $results->getValueAsBool($name);
        return $v->hasValue() ? (bool) $v->getValue() : false;
    }

    private function strVal($results, string $name): ?string
    {
        $v = $results->getValueAsString($name);
        return $v->hasValue() ? (string) $v->getValue() : null;
    }
}
