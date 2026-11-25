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

namespace fiftyone\pipeline\did;

/**
 * The Human Confidence Value a 51Did carries, mapped from the
 * `humanConfidence` string the cloud's 51Did endpoints send.
 *
 * The cloud sends the Value only to a call that presents a License Key
 * valid for Human Confidence. Any other call gets
 * `humanConfidenceNullReason` in its place, which the client keeps as
 * text, and the Value is then null.
 */
enum HumanConfidence: string
{
    /**
     * High confidence that the 51Did was made for a device being used by a
     * human who was looking at the page.
     */
    case High = 'High';

    /** Medium confidence. */
    case Medium = 'Medium';

    /** Low confidence. */
    case Low = 'Low';

    /**
     * The 51Did carries no Value, because no Human Confidence Value was
     * available when it was made. Every 51Did made before the cloud
     * carried the Value reads as Absent. So does a word this build doesn't
     * know, so an unknown word never passes as High, Medium or Low.
     */
    case Absent = 'Absent';

    /**
     * Maps the cloud's `humanConfidence` string without regard to case,
     * answering null when the cloud sent none. A word this build does not
     * know reads as {@see HumanConfidence::Absent}, so an unknown word
     * never passes as a Value and is never mistaken for a field that
     * wasn't sent.
     */
    public static function fromCloud(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        // ASCII only, so the answer does not depend on the locale.
        $word = ucfirst(strtr(
            $value,
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz'
        ));
        return self::tryFrom($word) ?? self::Absent;
    }

    /**
     * Reads the Value, its null reason and the raw word from a decoded
     * 51Did endpoint answer. A `humanConfidence` that is present but is
     * not a word this build knows, or is not a string at all, reads as
     * {@see HumanConfidence::Absent}. One that is missing or null reads as
     * null, and then the null reason says why where the cloud sent one.
     *
     * @internal Used by the package's own response classes.
     *
     * @param array<mixed> $json
     * @return array{0: ?HumanConfidence, 1: ?string, 2: ?string} The
     *     Value, the `humanConfidenceNullReason` text, and the
     *     `humanConfidence` string as sent, each null when not sent.
     */
    public static function fromAnswer(array $json): array
    {
        $sent = $json['humanConfidence'] ?? null;
        $raw = is_string($sent) ? $sent : null;
        $value = $sent === null
            ? null
            : ($raw === null ? self::Absent : self::fromCloud($raw));
        $reason = isset($json['humanConfidenceNullReason'])
            && is_string($json['humanConfidenceNullReason'])
            ? $json['humanConfidenceNullReason']
            : null;
        return [$value, $reason, $raw];
    }
}
