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
 * The typed answer to {@see DidClient::verifyDetailed()}, built from the
 * body the cloud's verify endpoint sent with a 200 or a 400.
 */
final class VerifyResponse
{
    /**
     * True when the 51Did has the correct shape and a valid signature,
     * exactly as {@see DidClient::verify()} answers.
     */
    public readonly bool $valid;

    /**
     * The Human Confidence Value the 51Did carries, sent only for a 51Did
     * whose signature verifies and only to a call that presented a License
     * Key valid for Human Confidence. Null otherwise, and then
     * {@see VerifyResponse::$humanConfidenceNullReason} says why where the
     * cloud sent a reason.
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

    /** The HTTP status, 200 or 400. */
    public readonly int $statusCode;

    /** The response body exactly as received. */
    public readonly string $raw;

    public function __construct(
        bool $valid,
        ?HumanConfidence $humanConfidence,
        ?string $humanConfidenceNullReason,
        int $statusCode,
        string $raw,
        ?string $rawHumanConfidence = null
    ) {
        $this->valid = $valid;
        $this->humanConfidence = $humanConfidence;
        $this->humanConfidenceNullReason = $humanConfidenceNullReason;
        $this->statusCode = $statusCode;
        $this->raw = $raw;
        $this->rawHumanConfidence = $rawHumanConfidence;
    }

    /**
     * Builds the answer from a verify response. `valid` is true only for
     * the JSON value true, so anything else reads as not valid.
     */
    public static function fromResponse(int $statusCode, string $body): self
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            $json = [];
        }
        [$value, $reason, $rawValue] = HumanConfidence::fromAnswer($json);
        return new self(
            ($json['valid'] ?? null) === true,
            $value,
            $reason,
            $statusCode,
            $body,
            $rawValue
        );
    }
}
