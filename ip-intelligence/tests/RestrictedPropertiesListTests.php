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
 * The restrictedProperties setting of an IP Intelligence on-premise engine
 * built from a data file takes a list of names as an array, or as one
 * comma-separated string, which is how pipeline-configuration.md in the
 * Pipeline specification gives a list in a configuration file.
 */
class RestrictedPropertiesListTests extends TestCase
{
    private const NAMES = ['RegisteredName', 'RegisteredCountry'];

    private static ?string $dataFile = null;

    public static function setUpBeforeClass(): void
    {
        $path = EnvironmentVariable::read(
            Constants::IPI_PATH_VARIABLE,
            Constants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR
        );
        $candidates = array_merge(
            $path ? [$path] : [],
            glob(__DIR__ . '/../assets/*.ipi') ?: [],
            glob(__DIR__ . '/../../cxx/data/ip-intelligence/*.ipi') ?: []
        );
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                self::$dataFile = $candidate;

                return;
            }
        }
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        if (self::$dataFile === null) {
            $this->markTestSkipped('No .ipi data file found (set _51DEGREES_IPI_PATH or place a file under assets or cxx/data/ip-intelligence).');
        }
    }

    /**
     * The restricted names and the Properties of an engine built with the
     * list given.
     *
     * @param array<string>|string $list
     * @return array{0: array<string>, 1: array<string>}
     */
    private static function restricted($list): array
    {
        $engine = new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'restrictedProperties' => $list,
        ]);
        try {
            return [
                $engine->restrictedProperties,
                array_keys($engine->getProperties())
            ];
        } finally {
            $engine->close();
        }
    }

    public function testACommaSeparatedStringIsTheSameAsAnArray()
    {
        $fromArray = self::restricted(self::NAMES);

        $this->assertSame(self::NAMES, $fromArray[0]);
        $this->assertSame($fromArray, self::restricted('RegisteredName,RegisteredCountry'));
        $this->assertSame($fromArray, self::restricted(' RegisteredName , RegisteredCountry '));
    }

    public function testAConfigurationFileCanGiveTheListAsAString()
    {
        $pipeline = (new PipelineBuilder())->buildFromConfig([
            'PipelineOptions' => [
                'Elements' => [[
                    'BuilderName' => IpIntelligenceOnPremise::class,
                    'BuildParameters' => [
                        'dataFile' => self::$dataFile,
                        'restrictedProperties' => 'RegisteredName,RegisteredCountry',
                    ],
                ]],
            ],
        ]);
        try {
            $this->assertSame(
                self::NAMES,
                $pipeline->getElement('ip')->restrictedProperties
            );
        } finally {
            $pipeline->close();
        }
    }
}
