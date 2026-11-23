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

namespace fiftyone\pipeline\derivedproperty;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns the text of a derived property script into a document, where a
 * mapping is a \stdClass and a list is a PHP list. YAML and JSON are both
 * accepted and give the same document.
 *
 * Mappings are objects rather than arrays so that an empty mapping and an
 * empty list stay different, and so that a mapping with numeric keys is
 * never read as a list.
 */
final class ScriptParser
{
    /**
     * Reads script text.
     *
     * @throws ScriptParseException the text is not YAML or JSON, is not a
     *     mapping, or has two keys in one mapping that differ only in case
     */
    public static function parse(string $text): \stdClass
    {
        // A byte order mark is not part of the script.
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }
        $document = preg_match('/^\s*\{/', $text) === 1
            ? self::parseJson($text)
            : self::parseYaml($text);
        if ($document === null) {
            throw new ScriptParseException('the script is empty');
        }
        if (!($document instanceof \stdClass)) {
            throw new ScriptParseException(
                'the script must be a mapping of keys to values at the top level'
            );
        }
        self::requireDistinctKeys($document);

        return $document;
    }

    /**
     * @return mixed
     */
    private static function parseJson(string $text)
    {
        try {
            return json_decode($text, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ScriptParseException(
                'the text is not valid JSON: ' . $e->getMessage()
            );
        }
    }

    /**
     * Reads YAML with the YAML 1.2 meaning of plain words, so `yes` and
     * `no` stay strings, which is what the reference tools read.
     *
     * @return mixed
     */
    private static function parseYaml(string $text)
    {
        try {
            return Yaml::parse($text, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (ParseException $e) {
            $line = $e->getParsedLine();
            throw new ScriptParseException(
                'the text is not valid YAML: ' . $e->getMessage(),
                $line > 0 ? $line : null
            );
        }
    }

    /**
     * Every key in the format is matched without regard to case, so two
     * keys in one mapping that differ only in case are one key written
     * twice, and a reader matching without regard to case would quietly
     * drop one of them. So both are refused.
     *
     * @param mixed $node
     */
    private static function requireDistinctKeys($node): void
    {
        if (is_array($node)) {
            foreach ($node as $item) {
                self::requireDistinctKeys($item);
            }

            return;
        }
        if (!($node instanceof \stdClass)) {
            return;
        }
        $seen = [];
        foreach (get_object_vars($node) as $key => $value) {
            $key = (string) $key;
            $lower = strtolower($key);
            if (isset($seen[$lower])) {
                throw new ScriptParseException(sprintf(
                    "the keys '%s' and '%s' differ only in case, and keys " .
                    'are matched without regard to case, so one of the two ' .
                    'would be dropped',
                    $seen[$lower],
                    $key
                ));
            }
            $seen[$lower] = $key;
            self::requireDistinctKeys($value);
        }
    }
}
