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

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\tests\classes\OnPremiseTestCase;

/**
 * How the on-premise engine is created from the data file. It accepts the
 * data file's client IP keys, in their order, and it is created with only
 * the property names it is configured with.
 */
class EngineCreationTests extends OnPremiseTestCase
{
    private const CONFIGURED_NAMES = ['RegisteredName', 'RegisteredCountry'];

    public function testTheAcceptedEvidenceIsTheDataFilesKeysInTheirOrder(): void
    {
        $engine = self::buildEngine();
        try {
            $keys = $engine->engine->getKeys();
            $expected = [];
            for ($i = 0; $i < $keys->size(); ++$i) {
                $expected[] = strtolower($keys->get($i));
            }

            $this->assertNotSame([], $expected);
            $this->assertSame($expected, $engine->getEvidenceKeyFilter()->getList());
        } finally {
            $engine->close();
        }
    }

    public function testTheEngineIsCreatedWithOnlyTheNamesConfigured(): void
    {
        $engine = self::buildEngine([
            'restrictedProperties' => self::CONFIGURED_NAMES,
        ]);
        try {
            $this->assertSame(self::CONFIGURED_NAMES, self::requiredNames($engine));
        } finally {
            $engine->close();
        }
    }

    /**
     * The names the native engine was created with, in its order.
     *
     * @return array<string>
     */
    private static function requiredNames(IpIntelligenceOnPremise $engine): array
    {
        $required = $engine->engine->getRequiredProperties();
        $names = [];
        for ($i = 0; $i < $required->size(); ++$i) {
            $names[] = $required->get($i);
        }

        return $names;
    }
}
