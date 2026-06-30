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
 * Translates values using a single locale's translation map. Handles a plain
 * string, a list of strings, and a weighted list of strings (an array of
 * ['value' => string, 'weighting' => number] entries). For a weighted list
 * each entry's weighting is preserved unchanged and only its value is
 * translated.
 */
class Translator
{
    /**
     * @var array<string, string> translation map keyed by lower-cased source
     */
    private array $translations;

    private string $behavior;

    /**
     * @param array<string, string> $translations source value to translation
     */
    public function __construct(array $translations = [], string $behavior = MissingTranslationBehavior::ORIGINAL)
    {
        // Store with lower-cased keys for case-insensitive lookup.
        $this->translations = [];
        foreach ($translations as $key => $value) {
            $this->translations[strtolower((string) $key)] = $value;
        }
        $this->behavior = $behavior;
    }

    /**
     * Translate a value. The value may be a string, a list of strings, or a
     * weighted list of ['value' => ..., 'weighting' => ...] entries.
     *
     * @param mixed $value
     * @param array<\Exception> $errors collects errors when behavior is FlowError
     * @return mixed translated value of the same shape, or null on FlowError
     */
    public function translate($value, array &$errors)
    {
        if (is_string($value)) {
            return $this->translateString($value, $errors);
        }

        if (is_array($value)) {
            if (count($value) === 0) {
                return [];
            }

            $first = reset($value);
            if (is_array($first) && array_key_exists('value', $first)) {
                // Weighted list: translate the value, keep the weighting.
                $out = [];
                foreach ($value as $item) {
                    $translated = $item;
                    $translated['value'] = $this->translateString((string) $item['value'], $errors);
                    $out[] = $translated;
                }

                return $out;
            }

            // Plain list of strings.
            return array_map(fn ($v) => $this->translateString((string) $v, $errors), $value);
        }

        // Null or any other type is returned unchanged.
        return $value;
    }

    /**
     * @param array<\Exception> $errors
     * @return string|null
     */
    private function translateString(string $value, array &$errors)
    {
        $key = strtolower($value);
        if (isset($this->translations[$key]) && trim((string) $this->translations[$key]) !== '') {
            return $this->translations[$key];
        }

        switch ($this->behavior) {
            case MissingTranslationBehavior::EMPTY_STRING:
                return '';
            case MissingTranslationBehavior::FLOW_ERROR:
                $errors[] = new \Exception(sprintf("There was no translation found for the value '%s'.", $value));

                return null;
            case MissingTranslationBehavior::ORIGINAL:
            default:
                return $value;
        }
    }
}
