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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\Constants;
use PHPUnit\Framework\TestCase;

/**
 * The string keys the Cloud Request Engine gives, being its Data Key and
 * the key of its property, each with the older key it still answers to,
 * and the Data Key a cloud engine is given when it sets none of its own,
 * follow the general guidance of access-to-results.md in the Pipeline
 * specification.
 */
class KeyFormatTests extends TestCase
{
    /**
     * Letters, digits, full stops and hyphens only.
     */
    private const KEY_CHARACTERS = '/^[A-Za-z0-9.-]+$/';

    /**
     * The Data Key CloudEngine gives a cloud engine that does not set its
     * own. Every engine in these packages sets one, and changing this
     * default would rename a public value in a patch release, so it is
     * held to the character rule and left out of the lower case rule only.
     */
    private const BASE_CLASS_DEFAULTS = ['CloudEngineBase'];

    /**
     * The keys an engine starts with, read from the class, as building the
     * engine asks the cloud service for discovery.
     *
     * @return array<string>
     */
    private static function keys(): array
    {
        $defaults = (new \ReflectionClass(CloudRequestEngine::class))
            ->getDefaultProperties();
        $keys = [$defaults['dataKey']];
        foreach (array_keys($defaults['properties']) as $key) {
            $keys[] = (string) $key;
        }
        $keys[] = Constants::DEPRECATED_DATA_KEY;
        $keys[] = Constants::DEPRECATED_JSON_RESPONSE_PROPERTY;
        $keys[] = (new \ReflectionClass(CloudEngine::class))
            ->getDefaultProperties()['dataKey'];

        return $keys;
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
            array_diff(self::keys(), self::BASE_CLASS_DEFAULTS),
            fn (string $key) => $key !== strtolower($key)
        )));
    }
}
