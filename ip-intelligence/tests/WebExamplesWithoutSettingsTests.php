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

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Renders the on-premise web examples for a request for the home page, as
 * they render once php.ini names the free Lite data file, with no other
 * settings. Each runs in its own PHP, because the extension builds its
 * engine when PHP starts. Each page must end with the Find out more
 * section, with every 51degrees.com link it carries tagged.
 */
class WebExamplesWithoutSettingsTests extends TestCase
{
    // Where the data repository keeps the free data files.
    private const DATA = __DIR__ . '/../../cxx/data/ip-intelligence';

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.'
            );
        }
    }

    public function testWeb(): void
    {
        $run = $this->runPage('onpremise/web/index.php');
        $this->assertStringContainsString(ExampleUtils::NEEDS_ENTERPRISE, $run->output);
    }

    /**
     * The page needs the Device Detection extension as well. Without it the
     * page says so, and still ends with Find out more.
     */
    public function testWebMixed(): void
    {
        $this->runPage('onpremise/web-mixed/index.php');
    }

    private function runPage(string $script): ExampleRun
    {
        $dataFile = realpath($this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            ['-d', ExampleUtils::DATA_FILE_SETTING . "={$dataFile}"],
            [
                ExampleConstants::IPI_PATH_VARIABLE,
                ExampleConstants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
            ],
            ExampleRun::WEB_REQUEST
        );
        $this->assertSame(0, $run->status, $run->printed());
        $run->assertPageEndsWithFindOutMore();

        return $run;
    }

    /**
     * The free Lite data file. CI fetches it into the package's assets
     * folder under its asset name, and a fresh clone downloads it into the
     * data repository.
     */
    private function liteDataFile(): string
    {
        $asset = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (is_file($asset)) {
            return $asset;
        }
        $path = self::DATA . '/' . ExampleConstants::LITE_DATA_FILE_NAME;
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile($path),
            'The free Lite data file could not be found or downloaded.'
        );

        return $path;
    }
}
