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
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;

/**
 * Data update (on-premise) example. Looks up an IP address, reloads the
 * data file the engine was built from, and looks the address up again, as
 * an application does after it replaces the data file with a newer one.
 *
 * PHP has no data update service, so there is no automatic update, file
 * watcher or update on start-up. The application replaces the data file
 * and then calls refreshData. Under a process manager such as php-fpm, each
 * worker process has its own copy of the engine the extension built when
 * PHP started, so a reload in one worker does not reach the others. Restart
 * PHP after replacing the data file instead.
 */
class DataUpdateConsoleOnPremise
{
    // Where to ask for a copy of the Enterprise data file, tagged with this
    // example.
    private const CONTACT_US = 'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-classes-dataupdateconsoleonpremise.php&utm_term=enterprise-data-file';

    // The IP address looked up before and after the reload.
    private const IP_ADDRESS = '116.154.188.222';

    // Properties only the Enterprise data file has.
    private const ENTERPRISE_PROPERTIES = ['Latitude', 'Longitude'];

    /**
     * @param resource $output
     */
    public function run($output): void
    {
        // The engine is the one the extension built when PHP started, so
        // the pipeline takes no settings.
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder())->build();

        // The pipeline is closed in the finally block, which releases the
        // native data it holds.
        try {
            $engine = $pipeline->getElement('ip');
            $this->lookUp($pipeline, $engine, $output);

            // Reload the data file from where it is. An application calls
            // this once it has replaced that file with a newer one. Here the
            // file has not changed, so the results stay the same. Given a
            // path, refreshData loads the data file from there instead.
            fwrite($output, "Reloading the data file\n");
            $engine->refreshData();

            $this->lookUp($pipeline, $engine, $output);

            if (ExampleUtils::isFreeDataFile($engine)) {
                fwrite($output, 'Updating means replacing the data file with ' .
                    'a newer Enterprise data file, which also has the ' .
                    'location, range and connection properties. Contact us ' .
                    'for a copy at ' . self::CONTACT_US . "\n");
            }
        } finally {
            $pipeline->close();
        }
    }

    /**
     * Write which data file the engine uses and when it was published, then
     * look up the IP address.
     *
     * @param resource $output
     */
    private function lookUp(
        Pipeline $pipeline,
        IpIntelligenceOnPremise $engine,
        $output
    ): void {
        $published = $engine->engine->getPublishedTime();
        fwrite($output, sprintf(
            "The data file in use, '%s', was published on %04d-%02d-%02d.\n",
            $engine->engine->getDataFilePath(),
            $published->getYear(),
            $published->getMonth(),
            $published->getDay()
        ));

        // The FlowData is closed in the finally block, which releases the
        // results of the lookup straight away. Results that are held keep
        // the data they were read from in memory after a reload.
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', self::IP_ADDRESS);
            $ip = $flowData->process()->ip;

            fwrite($output, 'Results for ' . self::IP_ADDRESS . ":\n");
            // The free Lite data file has RegisteredName, and the free ASN
            // data file AsnName.
            ExampleUtils::outputProperty(
                $ip,
                ExampleUtils::hasProperty($engine, 'RegisteredName')
                    ? 'RegisteredName'
                    : 'AsnName',
                $output
            );
            foreach (self::ENTERPRISE_PROPERTIES as $name) {
                ExampleUtils::outputEnterpriseProperty($engine, $ip, $name, $output);
            }
        } finally {
            $flowData->close();
        }
    }
}
