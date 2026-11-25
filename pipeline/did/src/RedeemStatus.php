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
 * What happened when a sealed result was redeemed, mapped from the
 * `status` string the cloud's redeem endpoint sends with every answer.
 *
 * The cloud also writes each word other than `success` to `context`, as it
 * did before `status` was added, so {@see RedeemResult::$context} still
 * reads those outcomes. Read this instead. For a service that does not
 * send `status`, {@see RedeemResult::$status} is read from `context`, and a
 * 503 is always {@see RedeemStatus::Unconfirmed}.
 */
enum RedeemStatus: string
{
    /**
     * The sealed result was redeemed, and the unsealed result is in
     * {@see RedeemResult::$context}, {@see RedeemResult::$signature} and
     * {@see RedeemResult::$factors}.
     */
    case Success = 'success';

    /** The sealed result is genuine but was redeemed too late. */
    case Expired = 'expired';

    /** The sealed result is genuine but was already redeemed. */
    case Replayed = 'replayed';

    /** First use could not be confirmed (503). The caller may retry. */
    case Unconfirmed = 'unconfirmed';

    /**
     * The sealed result could not be read for this 51Did, credential and
     * transaction.
     */
    case Unreadable = 'unreadable';

    /**
     * Maps the cloud's `status` string, answering null when the cloud sent
     * none and {@see RedeemStatus::Unreadable} for a word this build does
     * not know, so nothing unexpected passes as a success.
     */
    public static function fromCloud(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        return self::tryFrom($value) ?? self::Unreadable;
    }

    /**
     * The status of a redeem answer. A 503 is always
     * {@see RedeemStatus::Unconfirmed}, whatever the body says. Otherwise
     * it's read from `status` where the service sent one. A service that
     * sends no `status` writes the failure word to `context`, so the
     * status is then read from `context`. Any other `context` word this
     * build knows is a verdict, so the sealed result was redeemed. A word
     * it doesn't know, or no `context` at all, reads as
     * {@see RedeemStatus::Unreadable}.
     *
     * @internal Used by {@see RedeemResult}.
     */
    public static function fromAnswer(
        int $statusCode,
        ?string $status,
        string $context
    ): self {
        if ($statusCode === 503) {
            return self::Unconfirmed;
        }
        if ($status !== null) {
            return self::tryFrom($status) ?? self::Unreadable;
        }
        // The four failure words are both context words and status words.
        // "success" is a status word only, so as a context it's unknown.
        $verdict = ContextOutcome::tryFrom($context);
        if ($verdict === null) {
            return self::Unreadable;
        }
        return self::tryFrom($verdict->value) ?? self::Success;
    }
}
