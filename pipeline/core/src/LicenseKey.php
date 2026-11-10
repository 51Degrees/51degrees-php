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

namespace fiftyone\pipeline\core;

/**
 * Masks license keys for log entries and error messages. A license key is
 * shown as its last 4 characters, and a key of 4 characters or fewer is
 * replaced entirely.
 */
class LicenseKey
{
    private const VISIBLE_CHARACTERS = 4;

    private const MASK_CHARACTER = '*';

    /**
     * The key with every character before the last 4 replaced.
     */
    public static function mask(string $key): string
    {
        $length = strlen($key);

        if ($length <= self::VISIBLE_CHARACTERS) {
            return str_repeat(self::MASK_CHARACTER, $length);
        }

        return str_repeat(self::MASK_CHARACTER, $length - self::VISIBLE_CHARACTERS)
            . substr($key, -self::VISIBLE_CHARACTERS);
    }

    /**
     * The text with the value of each license, licenseKey and licenseKeys
     * query parameter masked, in either spelling, as the cloud service also
     * reads licence. A value may hold several keys separated by a vertical
     * bar or a comma. The text can be a URL or a message that names one.
     */
    public static function maskQuery(string $text): string
    {
        return (string) preg_replace_callback(
            '/([?&]licen[cs]e(?:keys?)?=)([^&#\s\'"]*)/i',
            function (array $match): string {
                $keys = preg_split('/([|,])/', $match[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($keys as $index => $key) {
                    if ($key !== '|' && $key !== ',') {
                        $keys[$index] = self::mask($key);
                    }
                }

                return $match[1] . implode('', $keys);
            },
            $text
        );
    }
}
