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

use DateTimeImmutable;
use DateTimeZone;

/**
 * The typed answer to {@see DidClient::redeem()}, built from the body the
 * cloud's redeem endpoint sent with a 200 or a 503.
 *
 * The two time fields describe the redemption rather than the identifier.
 * {@see RedeemResult::$verifiedAt} is when the verify endpoint checked the
 * context and sealed the result, and
 * {@see RedeemResult::$secondsSinceVerified} is how long ago that was by the
 * service's clock, in whole seconds. Both are present on the redeemed and
 * expired outcomes and null otherwise.
 *
 * {@see RedeemResult::$status} says what happened to the sealed result,
 * and on {@see RedeemStatus::Success} the unsealed result is in
 * {@see RedeemResult::$context}, {@see RedeemResult::$signature} and
 * {@see RedeemResult::$factors}, with the Human Confidence Value the 51Did
 * carries in {@see RedeemResult::$humanConfidence}. The cloud still writes
 * every other status to `context`, so a caller that reads only
 * {@see RedeemResult::$context} reads it as before.
 */
final class RedeemResult
{
    /** The format the cloud writes `verifiedAt` in, ISO 8601 UTC to the second. */
    public const VERIFIED_AT_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * The creator context verdict. On any status other than success it
     * holds the same word as {@see RedeemResult::$status}, through the
     * obsolete cases {@see ContextOutcome::Expired},
     * {@see ContextOutcome::Replayed}, {@see ContextOutcome::Unreadable}
     * and {@see ContextOutcome::Unconfirmed}.
     */
    public readonly ContextOutcome $context;

    /**
     * The `context` string exactly as the cloud sent it, so a value this
     * build maps to {@see ContextOutcome::Unreadable} because it is not
     * known is still visible. Empty when the field was absent.
     */
    public readonly string $rawContext;

    /** The signature outcome, {@see SignatureOutcome::Unknown} when absent. */
    public readonly SignatureOutcome $signature;

    /**
     * Factor name to outcome, present only when the cloud sent `factors`,
     * which is the mismatch case. The keys are the names the cloud sent,
     * listed on {@see FactorOutcome}, and are kept exactly as sent.
     *
     * @var array<string, FactorOutcome>|null
     */
    public readonly ?array $factors;

    /** When the context was verified and sealed, UTC, or null. */
    public readonly ?DateTimeImmutable $verifiedAt;

    /** Whole seconds between sealing and redemption, or null. */
    public readonly ?int $secondsSinceVerified;

    /** The HTTP status, 200 or 503. */
    public readonly int $statusCode;

    /** The response body exactly as received. */
    public readonly string $raw;

    /**
     * What happened to the sealed result, from the `status` the cloud
     * sends with every redeem answer. A 503 is always
     * {@see RedeemStatus::Unconfirmed}, whatever the body says. Where the
     * service sent no `status`, it's read from `context`, as
     * {@see RedeemStatus::fromAnswer()} describes. A word this build does
     * not know reads as {@see RedeemStatus::Unreadable}.
     */
    public readonly RedeemStatus $status;

    /**
     * The Human Confidence Value the 51Did carries, sent on success to a
     * call that presented a License Key valid for Human Confidence. Null
     * otherwise, and then {@see RedeemResult::$humanConfidenceNullReason}
     * says why where the cloud sent a reason.
     */
    public readonly ?HumanConfidence $humanConfidence;

    /** Why the cloud sent no Value, or null where it sent none. */
    public readonly ?string $humanConfidenceNullReason;

    /**
     * The `humanConfidence` string exactly as the cloud sent it, so a word
     * this build reads as {@see HumanConfidence::Absent} because it is not
     * known is still visible. Null when the field was not sent.
     */
    public readonly ?string $rawHumanConfidence;

    /**
     * Where no status is given, it's read from the HTTP status and
     * `$rawContext`, as {@see RedeemStatus::fromAnswer()} describes.
     *
     * @param array<string, FactorOutcome>|null $factors
     */
    public function __construct(
        ContextOutcome $context,
        string $rawContext,
        SignatureOutcome $signature,
        ?array $factors,
        ?DateTimeImmutable $verifiedAt,
        ?int $secondsSinceVerified,
        int $statusCode,
        string $raw,
        ?RedeemStatus $status = null,
        ?HumanConfidence $humanConfidence = null,
        ?string $humanConfidenceNullReason = null,
        ?string $rawHumanConfidence = null
    ) {
        $this->context = $context;
        $this->rawContext = $rawContext;
        $this->signature = $signature;
        $this->factors = $factors;
        $this->verifiedAt = $verifiedAt;
        $this->secondsSinceVerified = $secondsSinceVerified;
        $this->statusCode = $statusCode;
        $this->raw = $raw;
        $this->status = $status
            ?? RedeemStatus::fromAnswer($statusCode, null, $rawContext);
        $this->humanConfidence = $humanConfidence;
        $this->humanConfidenceNullReason = $humanConfidenceNullReason;
        $this->rawHumanConfidence = $rawHumanConfidence;
    }

    /**
     * Builds the result from a redeem response. A body that is not a JSON
     * object, or one without a `context`, reads as
     * {@see ContextOutcome::Unreadable}, so nothing unexpected passes as a
     * verdict. A 503 reads as {@see RedeemStatus::Unconfirmed} and
     * {@see ContextOutcome::Unconfirmed}, whatever the body says.
     */
    public static function fromResponse(int $statusCode, string $body): self
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            $json = [];
        }
        $rawContext = isset($json['context']) && is_string($json['context'])
            ? $json['context']
            : '';
        $signature = isset($json['signature']) && is_string($json['signature'])
            ? $json['signature']
            : null;

        $factors = null;
        if (isset($json['factors']) && is_array($json['factors'])) {
            $factors = [];
            foreach ($json['factors'] as $name => $value) {
                $factors[(string) $name] = FactorOutcome::fromCloud(
                    is_string($value) ? $value : null
                );
            }
        }

        $verifiedAt = null;
        if (isset($json['verifiedAt']) && is_string($json['verifiedAt'])) {
            $parsed = DateTimeImmutable::createFromFormat(
                self::VERIFIED_AT_FORMAT,
                $json['verifiedAt'],
                new DateTimeZone('UTC')
            );
            $verifiedAt = $parsed === false ? null : $parsed;
        }

        $seconds = null;
        if (isset($json['secondsSinceVerified'])
            && is_numeric($json['secondsSinceVerified'])
        ) {
            $seconds = (int) $json['secondsSinceVerified'];
        }

        $status = isset($json['status']) && is_string($json['status'])
            ? $json['status']
            : null;
        [$humanConfidence, $nullReason, $rawHumanConfidence] =
            HumanConfidence::fromAnswer($json);

        return new self(
            $statusCode === 503
                ? ContextOutcome::Unconfirmed
                : ContextOutcome::fromCloud($rawContext),
            $rawContext,
            SignatureOutcome::fromCloud($signature),
            $factors,
            $verifiedAt,
            $seconds,
            $statusCode,
            $body,
            RedeemStatus::fromAnswer($statusCode, $status, $rawContext),
            $humanConfidence,
            $nullReason,
            $rawHumanConfidence
        );
    }

    /**
     * The result in the cloud's own shape (`signature`, `context`,
     * `factors` when present, `verifiedAt`, `secondsSinceVerified`), for a
     * server that relays a redemption to a page as JSON. Fields the cloud
     * did not send are left out, as the cloud leaves them out. `status`
     * and the Human Confidence fields are not included, so the array is
     * the same whichever service answered, and a Value read with the
     * licence key does not reach the page unless the server adds it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];
        if ($this->signature !== SignatureOutcome::Unknown) {
            $array['signature'] = $this->signature->value;
        }
        $array['context'] = $this->context->value;
        if ($this->factors !== null) {
            $factors = [];
            foreach ($this->factors as $name => $outcome) {
                $factors[$name] = $outcome->value;
            }
            $array['factors'] = $factors;
        }
        if ($this->verifiedAt !== null) {
            $array['verifiedAt'] = $this->verifiedAt->format(
                self::VERIFIED_AT_FORMAT
            );
        }
        if ($this->secondsSinceVerified !== null) {
            $array['secondsSinceVerified'] = $this->secondsSinceVerified;
        }
        return $array;
    }
}
