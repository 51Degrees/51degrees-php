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

namespace fiftyone\pipeline\translation;

/**
 * Helpers for parsing an HTTP Accept-Language value and resolving it to one
 * of the locales for which a translation map is available.
 */
class Languages
{
    // A locale code as used by the YAML filenames, e.g. "fr_FR".
    public const LOCALE_REGEX = '/^[a-z]{2}_[A-Z]{2}$/';

    /**
     * Parse an Accept-Language style value into an ordered list of locale
     * candidates, highest quality first. Dashes are replaced with
     * underscores so "fr-FR" becomes "fr_FR".
     *
     * @return array<string>
     */
    public static function parseAcceptLanguage(?string $acceptLanguage): array
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return [];
        }

        $entries = [];
        foreach (explode(',', $acceptLanguage) as $index => $part) {
            $quality = 1.0;
            $value = $part;
            if (strpos($part, ';') !== false) {
                [$value, $params] = explode(';', $part, 2);
                if (preg_match('/q=([0-9.]+)/', $params, $m)) {
                    $quality = (float) $m[1];
                }
            }
            $value = str_replace('-', '_', trim($value));
            if ($value === '') {
                continue;
            }
            // Keep the original index so a stable sort preserves the header
            // order for equal quality values.
            $entries[] = ['value' => $value, 'quality' => $quality, 'index' => $index];
        }

        usort($entries, static function ($a, $b) {
            if ($a['quality'] === $b['quality']) {
                return $a['index'] <=> $b['index'];
            }

            return $b['quality'] <=> $a['quality'];
        });

        return array_map(static fn ($e) => $e['value'], $entries);
    }

    /**
     * Resolve an Accept-Language value to an available locale.
     *
     * Resolution rules, applied to each candidate in quality order:
     *  1. An exact case-insensitive match against an available locale wins.
     *  2. Otherwise, if the candidate's language is the base language (en),
     *     stop and return false - the source values are already English, so
     *     do not fall through to a lower-priority language.
     *  3. Otherwise, if the candidate is a two-letter language code, fall
     *     back to the first available locale that starts with it.
     *
     * @param array<string> $availableLocales
     * @param-out string|null $matchedLocale
     */
    public static function tryResolveLocale(
        ?string $acceptLanguage,
        array $availableLocales,
        ?string &$matchedLocale,
        string $baseLanguage = 'en'
    ): bool {
        $matchedLocale = null;

        foreach (self::parseAcceptLanguage($acceptLanguage) as $candidate) {
            foreach ($availableLocales as $locale) {
                if (strcasecmp($locale, $candidate) === 0) {
                    $matchedLocale = $locale;

                    return true;
                }
            }

            if ($baseLanguage !== '' &&
                stripos($candidate, $baseLanguage) === 0) {
                return false;
            }

            if (strlen($candidate) === 2) {
                foreach ($availableLocales as $locale) {
                    if (stripos($locale, $candidate) === 0) {
                        $matchedLocale = $locale;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract the locale from a translation filename. The locale is the
     * second-to-last dot separated segment and must match LOCALE_REGEX, so
     * "countries.fr_FR.yml" resolves to "fr_FR".
     */
    public static function localeFromFilename(string $name): ?string
    {
        $parts = explode('.', $name);
        if (count($parts) < 3) {
            return null;
        }
        $locale = $parts[count($parts) - 2];

        return preg_match(self::LOCALE_REGEX, $locale) === 1 ? $locale : null;
    }
}
