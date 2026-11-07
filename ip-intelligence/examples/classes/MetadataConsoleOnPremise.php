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

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;

/**
 * Metadata (on-premise) example. Lists the properties present in a local
 * .ipi data file along with their type, category and component.
 */
class MetadataConsoleOnPremise
{
    // Where to ask for a copy of the Enterprise data file, tagged with this
    // example.
    private const CONTACT_US = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-classes-metadataconsoleonpremise.php&utm_term=enterprise-data-file';

    /**
     * @param resource $output
     */
    public function run(string $dataFile, $output): void
    {
        $engine = new IpIntelligenceOnPremise([
            'dataFile' => $dataFile
        ]);

        // The engine is closed in the finally block, which releases the
        // native data it loaded from the data file.
        try {
            $properties = $engine->getProperties();

            fwrite($output, sprintf("IP intelligence properties (%d):\n", count($properties)));
            foreach ($properties as $property) {
                fwrite($output, sprintf(
                    "\t%s [%s] (%s / %s)\n",
                    $property['name'],
                    $property['type'] ?? 'Unknown',
                    $property['component'] ?? '',
                    $property['category'] ?? ''
                ));
            }

            fwrite($output, "\nAccepted evidence keys:\n");
            $filter = $engine->getEvidenceKeyFilter();
            foreach (['query.client-ip', 'server.client-ip', 'query.client-ip-51d', 'server.client-ip-51d'] as $key) {
                if ($filter->filterEvidenceKey($key)) {
                    fwrite($output, sprintf("\t%s\n", $key));
                }
            }
            if (ExampleUtils::isFreeDataFile($engine)) {
                fwrite($output, "\n" . ExampleUtils::getEnterpriseOffer(self::CONTACT_US) . "\n");
            }
        } finally {
            $engine->close();
        }
    }
}
