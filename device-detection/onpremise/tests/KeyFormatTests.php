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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise;
use PHPUnit\Framework\TestCase;

/**
 * The Data Key of the on-premise Device Detection engine follows the
 * general guidance of access-to-results.md in the Pipeline specification.
 * The keys of its properties are the ones the data file gives.
 */
class KeyFormatTests extends TestCase
{
    /**
     * Letters, digits, full stops and hyphens only.
     */
    private const KEY_CHARACTERS = '/^[A-Za-z0-9.-]+$/';

    /**
     * The key is read from the class, as building the engine needs the
     * extension.
     *
     * @return array<string>
     */
    private static function keys(): array
    {
        $defaults = (new \ReflectionClass(DeviceDetectionOnPremise::class))
            ->getDefaultProperties();

        return [$defaults['dataKey']];
    }

    public function testEveryKeyHasOnlyLettersDigitsFullStopsAndHyphens(): void
    {
        $this->assertSame([], array_values(array_filter(
            self::keys(),
            fn (string $key) => preg_match(self::KEY_CHARACTERS, $key) !== 1
        )));
    }

    public function testEveryKeyIsLowerCase(): void
    {
        $this->assertSame([], array_values(array_filter(
            self::keys(),
            fn (string $key) => $key !== strtolower($key)
        )));
    }
}
