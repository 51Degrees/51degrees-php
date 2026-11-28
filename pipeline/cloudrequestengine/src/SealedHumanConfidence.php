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

namespace fiftyone\pipeline\cloudrequestengine;

use fiftyone\pipeline\core\FlowData;

/**
 * Finds the sealed Human Confidence result a request carries, and holds the
 * names and values the package uses for one.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#when-humanconfidence-arrives-sealed
 *
 * @internal Used by the Cloud Request Engine and the derived cloud engine.
 */
final class SealedHumanConfidence
{
    /** The Evidence that carries the sealed result. */
    public const EVIDENCE = 'query.humanconfidence';

    /**
     * The Evidence that holds the page id the package issued to the page,
     * under which it keeps the transaction it issued for that page.
     */
    public const PAGE_EVIDENCE = 'query.' . HumanConfidencePage::PARAMETER;

    /** The name the call sends the sealed result under. */
    public const FIELD = 'humanconfidence';

    /**
     * The name of the page id once its prefix is removed. It is the
     * package's own, so it is left out of the call.
     */
    public const PAGE_FIELD = HumanConfidencePage::PARAMETER;

    /** The name the call sends the kept transaction under. */
    public const TRANSACTION_FIELD = 'transaction';

    /**
     * The names under which a call can carry a transaction, being its name
     * and the older name for the same value. Only the one the package kept
     * goes in a call that carries a sealed result.
     */
    public const TRANSACTION_FIELDS = ['transaction', 'challenge'];

    /** The Property the call names so the response gives the Value. */
    public const REQUESTED_PROPERTY = 'derived.HumanConfidence';

    /** The Data Key of the Element Data that holds the two Properties. */
    public const DATA_KEY = 'derived';

    /** The Property that holds the Value. */
    public const VALUE_PROPERTY = 'humanconfidence';

    /** The Property that says how the Value was worked out. */
    public const SOURCE_PROPERTY = 'humanconfidencesource';

    /** The source of every request that carries a sealed result. */
    public const SOURCE = 'Active';

    /** The Value wherever the sealed result was not redeemed. */
    public const NOT_REDEEMED = 'Medium';

    /** The only Values a redeemed sealed result gives. */
    public const LEVELS = ['High', 'Medium', 'Low'];

    /**
     * The sealed result the request carries, or null where its Evidence
     * holds no `query.humanconfidence` or holds an empty one.
     */
    public static function in(FlowData $flowData): ?string
    {
        $value = self::evidence($flowData, self::EVIDENCE);

        return $value === '' ? null : $value;
    }

    /**
     * Whether the request's Evidence holds a humanconfidence that isn't
     * empty under a key other than query.humanconfidence, such as a header,
     * a cookie, a server value, or a key with no prefix. Only
     * query.humanconfidence carries a sealed result, so such a value is
     * never redeemed.
     */
    public static function elsewhereIn(FlowData $flowData): bool
    {
        if (!(new \ReflectionProperty(FlowData::class, 'evidence'))->isInitialized($flowData)) {
            return false;
        }
        foreach ($flowData->evidence->getAllIncludingUnaccepted() as $key => $value) {
            $parts = explode('.', (string) $key, 2);
            if (
                strcasecmp((string) end($parts), self::FIELD) === 0 &&
                strcasecmp((string) $key, self::EVIDENCE) !== 0 &&
                is_scalar($value) &&
                (string) $value !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every humanconfidence the request's Evidence holds that isn't empty,
     * under query.humanconfidence or any other key, in any letter case.
     * None may be written to a message.
     *
     * @return array<string>
     */
    public static function valuesIn(FlowData $flowData): array
    {
        if (!(new \ReflectionProperty(FlowData::class, 'evidence'))->isInitialized($flowData)) {
            return [];
        }
        $values = [];
        foreach ($flowData->evidence->getAllIncludingUnaccepted() as $key => $value) {
            $parts = explode('.', (string) $key, 2);
            if (
                strcasecmp((string) end($parts), self::FIELD) === 0 &&
                is_scalar($value) &&
                (string) $value !== ''
            ) {
                $values[] = (string) $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * The page id the request carries, or null where it carries none.
     */
    public static function pageIn(FlowData $flowData): ?string
    {
        $value = self::evidence($flowData, self::PAGE_EVIDENCE);

        return $value === '' ? null : $value;
    }

    /**
     * The Value to give for a response to a call that carried the sealed
     * result: the response's `derived.humanconfidence` where it is High,
     * Medium, or Low and the response's errors do not name HumanConfidence,
     * and Medium otherwise.
     */
    public static function valueFrom(?string $response): string
    {
        $json = json_decode((string) $response, true);
        if (!is_array($json)) {
            return self::NOT_REDEEMED;
        }
        $json = array_change_key_case($json);
        foreach ((array) ($json['errors'] ?? []) as $error) {
            if (is_string($error) && stripos($error, 'humanconfidence') !== false) {
                return self::NOT_REDEEMED;
            }
        }
        $derived = $json[self::DATA_KEY] ?? null;
        if (!is_array($derived)) {
            return self::NOT_REDEEMED;
        }
        $value = array_change_key_case($derived)[self::VALUE_PROPERTY] ?? null;

        return is_string($value) && in_array($value, self::LEVELS, true)
            ? $value
            : self::NOT_REDEEMED;
    }

    /**
     * Whether a field of the call, named without its prefix, is a
     * transaction under either name, in any letter case.
     */
    public static function isTransactionField(string $name): bool
    {
        return in_array(strtolower($name), self::TRANSACTION_FIELDS, true);
    }

    /**
     * An Evidence value as text, matching the key in any letter case and
     * whether or not an element accepts it, or an empty string.
     */
    private static function evidence(FlowData $flowData, string $key): string
    {
        // A FlowData made without its constructor, as a test double is,
        // holds no Evidence.
        if (!(new \ReflectionProperty(FlowData::class, 'evidence'))->isInitialized($flowData)) {
            return '';
        }
        foreach ($flowData->evidence->getAllIncludingUnaccepted() as $name => $value) {
            if (strcasecmp((string) $name, $key) === 0 && is_string($value)) {
                return $value;
            }
        }

        return '';
    }
}
