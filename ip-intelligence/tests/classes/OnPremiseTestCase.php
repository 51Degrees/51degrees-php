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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use PHPUnit\Framework\TestCase;

/**
 * A test of the on-premise engine with the native extension and a data
 * file, skipped when either is missing. The data file is the one the
 * _51DEGREES_IPI_PATH environment variable names, or the first .ipi file in
 * the package's assets folder or the data repository.
 */
abstract class OnPremiseTestCase extends TestCase
{
    protected static ?string $dataFile = null;

    public static function setUpBeforeClass(): void
    {
        self::$dataFile = self::locateDataFile();
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.'
            );
        }
        if (self::$dataFile === null) {
            $this->markTestSkipped(
                'No .ipi data file found (set _51DEGREES_IPI_PATH or place a ' .
                'file under assets or cxx/data/ip-intelligence).'
            );
        }
    }

    /**
     * An engine of its own built from the data file, which closes with the
     * test's Pipeline or by close().
     *
     * @param array<string, mixed> $settings added to the dataFile setting
     */
    protected static function buildEngine(array $settings = []): IpIntelligenceOnPremise
    {
        return new IpIntelligenceOnPremise(
            ['dataFile' => self::$dataFile] + $settings
        );
    }

    private static function locateDataFile(): ?string
    {
        $env = EnvironmentVariable::read(
            Constants::IPI_PATH_VARIABLE
        );
        if ($env && file_exists($env)) {
            return $env;
        }

        $candidates = array_merge(
            glob(__DIR__ . '/../../assets/*.ipi') ?: [],
            glob(__DIR__ . '/../../../cxx/data/ip-intelligence/*.ipi') ?: []
        );

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
