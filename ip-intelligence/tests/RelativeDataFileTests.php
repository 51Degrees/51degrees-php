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
use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * A relative path to the on-premise engine's data file is read against the
 * working directory of the process, as file-system-paths.md in the Pipeline
 * specification requires. The engine hands the path to the native code,
 * which opens it as given.
 */
class RelativeDataFileTests extends TestCase
{
    private static ?string $dataFile = null;

    private string $workingDirectory;

    public static function setUpBeforeClass(): void
    {
        self::$dataFile = self::locateDataFile();
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        if (self::$dataFile === null) {
            $this->markTestSkipped('No .ipi data file found (set _51DEGREES_IPI_PATH or place a file under assets or cxx/data/ip-intelligence).');
        }
        $this->workingDirectory = (string) getcwd();
    }

    protected function tearDown(): void
    {
        if (isset($this->workingDirectory)) {
            chdir($this->workingDirectory);
        }
    }

    /**
     * The data file, found where IpIntelligenceOnPremiseTests looks, as a
     * full path.
     */
    private static function locateDataFile(): ?string
    {
        $env = EnvironmentVariable::read(
            Constants::IPI_PATH_VARIABLE,
            Constants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
        );
        $candidates = array_merge(
            $env ? [$env] : [],
            glob(__DIR__ . '/../assets/*.ipi') ?: [],
            glob(__DIR__ . '/../../cxx/data/ip-intelligence/*.ipi') ?: []
        );
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path !== false && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * The address the engine gives back for Constants::IPV4_ADDRESS, with
     * an engine built from the data file path given.
     */
    private static function lookUp(string $dataFile): ?string
    {
        $pipeline = (new PipelineBuilder())
            ->add(new IpIntelligenceOnPremise(['dataFile' => $dataFile]))
            ->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
            $flowData->process();
            $ip = $flowData->get('ip')->get('ip');

            return $ip->hasValue ? $ip->value : null;
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    public function testARelativeDataFilePathIsReadFromTheWorkingDirectory(): void
    {
        $folder = dirname((string) self::$dataFile);
        $name = basename((string) self::$dataFile);
        $folderName = basename($folder);
        $forms = [
            [$folder, $name],
            [$folder, '.' . DIRECTORY_SEPARATOR . $name],
            [dirname($folder), $folderName . DIRECTORY_SEPARATOR . $name],
            [$folder, '..' . DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR . $name],
        ];

        foreach ($forms as [$directory, $path]) {
            chdir($directory);
            $this->assertSame(
                Constants::IPV4_ADDRESS,
                self::lookUp($path),
                $path
            );
        }
    }
}
