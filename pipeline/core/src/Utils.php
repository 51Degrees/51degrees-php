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

class Utils
{
    /**
     * How long a browser may keep a client-side response, unless the caller
     * gives another time.
     */
    public const CLIENT_SIDE_MAX_AGE_SECONDS = 1800;

    /**
     * The Cache-Control of a client-side response to a request whose
     * processing recorded errors. It goes with no ETag, so a browser that
     * kept the response has nothing to revalidate with and asks again in
     * full once processing works.
     */
    private const CACHE_CONTROL_AFTER_ERRORS = 'private, no-cache';

    /**
     * The headers a browser adds when it revalidates or reloads. They differ
     * between a request and its revalidation and change no result, so they
     * are left out of the ETag and the Vary header even when an element
     * accepts them.
     */
    private const REVALIDATION_HEADERS = [
        'cache-control',
        'if-match',
        'if-modified-since',
        'if-none-match',
        'if-range',
        'if-unmodified-since',
        'pragma',
    ];

    /**
     * An entity tag of RFC 9110 section 8.8.3, being an optional W/ and an
     * opaque tag in double quotes of visible characters other than the
     * double quote. The second group is the opaque tag without its quotes.
     */
    private const ENTITY_TAG = '/\G(W\/)?"([\x21\x23-\x7e\x80-\xff]*)"/';

    /**
     * The optional whitespace around the commas of a list, RFC 9110 section
     * 5.6.3.
     */
    private const OWS = " \t";

    /**
     * Answer a request for the client-side JavaScript with the JavaScript
     * the Pipeline's JavaScript Builder Element writes, and the cache
     * headers of the web integration specification.
     *
     * The FlowData holds the request's Evidence and has not been processed,
     * because the ETag is a hash of the Evidence the request supplied, and
     * processing adds Evidence, such as the session id the Sequence Element
     * gives a request without one. The ETag is weak, W/"...", because the
     * response can change while the Evidence does not, for example when the
     * data file is updated.
     *
     * A request whose If-None-Match header matches the ETag, by the weak
     * comparison of RFC 9110 section 13.1.2, is not processed. It gets a 304
     * when it is a GET or HEAD and a 412 otherwise. An If-None-Match that is
     * neither "*" nor a list of entity tags matches nothing. Any other
     * request is processed and gets a 200 with Cache-Control
     * 'max-age=1800, private', or the max age given, and the ETag, or
     * 'private, no-cache' and no ETag when processing recorded errors.
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData The request's
     * FlowData, with its Evidence set and not yet processed
     * @param null|array<string, mixed> $server The request's server values,
     * $_SERVER when not given
     * @param int $maxAgeSeconds How long a browser may keep the response
     * @throws \LogicException When the FlowData has been processed
     */
    public static function javascriptResponse(
        FlowData $flowData,
        ?array $server = null,
        int $maxAgeSeconds = self::CLIENT_SIDE_MAX_AGE_SECONDS
    ): ClientSideResponse {
        return self::clientSideResponse(
            $flowData,
            $server ?? $_SERVER,
            $maxAgeSeconds,
            'application/x-javascript',
            static function (FlowData $flowData): string {
                return (string) $flowData->javascriptbuilder->javascript;
            }
        );
    }

    /**
     * Answer a request from the client-side JavaScript with the JSON the
     * Pipeline's JSON Builder Element writes, in the same way as
     * javascriptResponse().
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData The request's
     * FlowData, with its Evidence set and not yet processed
     * @param null|array<string, mixed> $server The request's server values,
     * $_SERVER when not given
     * @param int $maxAgeSeconds How long a browser may keep the response
     * @throws \LogicException When the FlowData has been processed
     */
    public static function jsonResponse(
        FlowData $flowData,
        ?array $server = null,
        int $maxAgeSeconds = self::CLIENT_SIDE_MAX_AGE_SECONDS
    ): ClientSideResponse {
        return self::clientSideResponse(
            $flowData,
            $server ?? $_SERVER,
            $maxAgeSeconds,
            'application/json',
            static function (FlowData $flowData): string {
                return (string) json_encode($flowData->jsonbundler->json);
            }
        );
    }

    /**
     * Set response headers in the response object (e.g. Accept-CH).
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData A processed FlowData instance to get the response header values from
     */
    public static function setResponseHeader(FlowData $flowData): void
    {
        $setHeaderElementKey = Constants::SETHEADER_ELEMENT_KEY;
        $setHeaderDataKey = Constants::SETHEADER_DATA_KEY;

        // Get response headers dictionary containing key values to be set in response
        $responseHeaderDict = $flowData->{$setHeaderElementKey}->{$setHeaderDataKey};

        foreach ($responseHeaderDict as $responseKey => $responseValue) {
            $responseValue = str_replace(',', ', ', $responseValue);

            if (strlen($responseValue) > 0) {
                header("{$responseKey}: {$responseValue}");
            }
        }
    }

    /**
     * Whether an If-None-Match value matches an ETag, by RFC 9110 section
     * 13.1.2. "*" matches, as there is always a response. A list of entity
     * tags matches when one of them has the same opaque tag, whether or not
     * either is weak. Anything else matches nothing.
     *
     * @param null|string $ifNoneMatch The If-None-Match value, or null
     * @param string $opaqueTag The ETag's opaque tag, without W/ or quotes
     */
    private static function ifNoneMatchMatches(
        ?string $ifNoneMatch,
        string $opaqueTag
    ): bool {
        if ($ifNoneMatch === null) {
            return false;
        }

        $value = trim($ifNoneMatch, self::OWS);
        if ($value === '*') {
            return true;
        }

        $tags = self::entityTags($value);

        return $tags !== null && in_array($opaqueTag, $tags, true);
    }

    /**
     * @param array<string, mixed> $server
     * @param callable(FlowData): string $content
     */
    private static function clientSideResponse(
        FlowData $flowData,
        array $server,
        int $maxAgeSeconds,
        string $contentType,
        callable $content
    ): ClientSideResponse {
        if ($flowData->processed) {
            throw new \LogicException(Messages::CLIENT_SIDE_PROCESSED);
        }

        $opaqueTag = self::opaqueTag($flowData->evidence->getAll());
        $etag = 'W/"' . $opaqueTag . '"';
        $cacheControl = 'max-age=' . $maxAgeSeconds . ', private';
        $vary = self::vary($flowData, $server);
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $ifNoneMatch = $server['HTTP_IF_NONE_MATCH'] ?? null;

        $headers = [];
        if (self::ifNoneMatchMatches(
            is_string($ifNoneMatch) ? $ifNoneMatch : null,
            $opaqueTag
        )) {
            if ($method !== 'GET' && $method !== 'HEAD') {
                return new ClientSideResponse(412, [], '');
            }

            // The headers of a good response, which RFC 9110 section 15.4.5
            // asks a 304 to repeat.
            $headers['Cache-Control'] = $cacheControl;
            if ($vary !== '') {
                $headers['Vary'] = $vary;
            }
            $headers['ETag'] = $etag;

            return new ClientSideResponse(304, $headers, '');
        }

        $flowData->process();
        $body = $content($flowData);
        $failed = count($flowData->errors) > 0;

        $headers['Cache-Control'] = $failed
            ? self::CACHE_CONTROL_AFTER_ERRORS
            : $cacheControl;
        if ($vary !== '') {
            $headers['Vary'] = $vary;
        }
        if (!$failed) {
            $headers['ETag'] = $etag;
        }
        $headers['Content-Type'] = $contentType;
        $headers['Content-Length'] = (string) strlen($body);

        return new ClientSideResponse(
            200,
            $headers,
            $method === 'HEAD' ? '' : $body
        );
    }

    /**
     * A SHA-256 hash of the Evidence, which is the same in every process,
     * so a browser revalidating against another process, after a restart
     * or behind a load balancer, still matches.
     *
     * @param array<string, mixed> $evidence
     */
    private static function opaqueTag(array $evidence): string
    {
        ksort($evidence, SORT_STRING);
        $hash = hash_init('sha256');
        foreach ($evidence as $key => $value) {
            $key = (string) $key;
            if (self::isRevalidationHeader($key)) {
                continue;
            }
            // A repeated query parameter gives an array.
            $text = is_scalar($value)
                ? (string) $value
                : (string) json_encode($value);
            hash_update($hash, $key . "\0" . $text . "\0");
        }

        return substr(hash_final($hash), 0, 32);
    }

    /**
     * The request headers the Pipeline's elements accept, for the Vary
     * header.
     *
     * @param array<string, mixed> $server
     */
    private static function vary(FlowData $flowData, array $server): string
    {
        $names = [];
        foreach (array_keys($server) as $name) {
            if (strpos((string) $name, 'HTTP_') !== 0) {
                continue;
            }
            $name = strtolower(str_replace('_', '-', substr((string) $name, 5)));
            $key = 'header.' . $name;
            if (self::isRevalidationHeader($key)) {
                continue;
            }
            foreach ($flowData->pipeline->flowElements as $flowElement) {
                if ($flowElement->filterEvidenceKey($key)) {
                    $names[$name] = ucwords($name, '-');
                    break;
                }
            }
        }
        ksort($names, SORT_STRING);

        return implode(', ', $names);
    }

    private static function isRevalidationHeader(string $key): bool
    {
        return strpos($key, 'header.') === 0
            && in_array(
                substr($key, strlen('header.')),
                self::REVALIDATION_HEADERS,
                true
            );
    }

    /**
     * The opaque tags of a list of entity tags, which may have whitespace
     * around its commas and empty elements (RFC 9110 section 5.6.1.2), or
     * null when the value is not such a list.
     *
     * @return null|array<int, string>
     */
    private static function entityTags(string $value): ?array
    {
        $tags = [];
        $position = 0;
        $length = strlen($value);
        while (true) {
            $position += strspn($value, self::OWS, $position);
            if ($position === $length) {
                return $tags;
            }
            if ($value[$position] !== ',') {
                $found = preg_match(
                    self::ENTITY_TAG,
                    $value,
                    $tag,
                    0,
                    $position
                );
                if ($found !== 1) {
                    return null;
                }
                $tags[] = $tag[2];
                $position += strlen($tag[0]);
                $position += strspn($value, self::OWS, $position);
                if ($position === $length) {
                    return $tags;
                }
                if ($value[$position] !== ',') {
                    return null;
                }
            }
            ++$position;
        }
    }
}
