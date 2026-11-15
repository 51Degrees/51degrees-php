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

use fiftyone\pipeline\ipintelligence\IpIntelligenceCloud;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use PHPUnit\Framework\TestCase;

/**
 * The Data Keys of the IP Intelligence engines follow the general guidance
 * of access-to-results.md in the Pipeline specification. The keys of their
 * properties are the ones the data file or the cloud service gives.
 */
class KeyFormatTests extends TestCase
{
    /**
     * Letters, digits, full stops and hyphens only.
     */
    private const KEY_CHARACTERS = '/^[A-Za-z0-9.-]+$/';

    /**
     * The on-premise engine's key is read from the class, as building the
     * engine needs the extension and a data file.
     *
     * @return array<string>
     */
    private static function keys(): array
    {
        $onPremise = (new \ReflectionClass(IpIntelligenceOnPremise::class))
            ->getDefaultProperties();

        return [
            (new IpIntelligenceCloud())->dataKey,
            $onPremise['dataKey'],
        ];
    }

    public function testEveryKeyHasOnlyLettersDigitsFullStopsAndHyphens(): void
    {
        $this->assertSame([], array_values(array_filter(
            self::keys(),
            fn (string $key) => preg_match(self::KEY_CHARACTERS, $key) !== 1
        )));
    }
}
