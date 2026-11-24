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

use fiftyone\pipeline\core\LicenseKey;

/**
 * Makes the HTTP requests the Cloud Request Engine sends to the cloud
 * service, each with a timeout. A test can give the engine a subclass that
 * answers without a network.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/cloud-request-engine.md#http-requests
 */
class HttpClient
{
    private float $timeoutSeconds = Constants::TIMEOUT_SECONDS_DEFAULT;

    /**
     * Sets the timeout of each request, in seconds. A request the cloud
     * service has not answered by then fails as one that could not be made.
     *
     * @throws \InvalidArgumentException when the timeout is not greater
     *   than 0, which curl would read as no timeout at all
     */
    public function setTimeoutSeconds(float $timeoutSeconds): void
    {
        if (!($timeoutSeconds > 0)) {
            throw new \InvalidArgumentException(sprintf(
                Constants::MESSAGE_TIMEOUT_NOT_POSITIVE,
                var_export($timeoutSeconds, true)
            ));
        }
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * The timeout of each request, in seconds.
     */
    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * Internal helper method to make a cloud request
     * uses CURL if available, falls back to file_get_contents.
     *
     * @param string $type Method use to send HTTP request
     * @param string $url
     * @param null|string $content Data to be sent in the post body
     * @param null|string $originHeader The value to use for the Origin header
     * @return string Associative array with data and error properties error contains any errors from the request, data contains the response
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     *   when the request cannot be made or the cloud service answers with
     *   an error
     */
    public function makeCloudRequest(string $type, string $url, ?string $content, ?string $originHeader): string
    {
        $headerText = '';
        if (!empty($originHeader)) {
            $headerText .= 'Origin: ' . $originHeader;
        }

        if (!function_exists('curl_version')) {
            $context = stream_context_create([
                'http' => [
                    'method' => $type,
                    'ignore_errors' => true,
                    'header' => $headerText,
                    'content' => $content,
                    'timeout' => $this->timeoutSeconds
                ]
            ]);

            // Before PHP 8.4, PHP gives the response headers only in
            // $http_response_header, and sets it only in a function that
            // has that variable. Being set first, it is not read as the
            // predefined variable PHP 8.5 deprecates.
            $http_response_header = [];
            $data = @file_get_contents($url, false, $context);

            if ($data === false) {
                // The request never reached the service, so there is no
                // status and no body to read. Without this, false went on
                // to validateResponse(), where the strict types this file
                // declares turned it into a type error naming that method,
                // and $http_response_header was never set either, so the
                // caller heard nothing about the connection.
                $error = error_get_last();

                throw new CloudRequestException(LicenseKey::maskQuery(sprintf(
                    Constants::MESSAGE_REQUEST_FAILED,
                    $url,
                    $error['message'] ?? 'the reason was not reported'
                )));
            }

            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?? [])
                : $http_response_header;
            $statusCode = $this->getHttpCode($responseHeaders);

            // Validate cloud response for errors.
            $this->validateResponse($data, $statusCode, $responseHeaders, $url);

            return $data;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        // The whole request, connecting included, stops at the timeout.
        // Without signals, a timeout below one second works too.
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int) ceil($this->timeoutSeconds * 1000));
        if (!empty($originHeader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                $headerText
            ]);
        }
        if (!empty($type) && strcasecmp($type, 'POST') === 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $data = curl_exec($ch);

        if ($data === false) {
            // curl could not make the request at all, for example where the
            // name does not resolve, the connection is refused, the
            // certificate cannot be checked or the request times out. There
            // is nothing to read, so the reason curl gave is reported.
            // Without this the false went on to substr() and the caller saw
            // a type error naming this file instead.
            $error = curl_error($ch);
            self::closeCurl($ch);

            throw new CloudRequestException(LicenseKey::maskQuery(sprintf(
                Constants::MESSAGE_REQUEST_FAILED,
                $url,
                $error === '' ? 'the reason was not reported' : $error
            )));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Get headers length
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // Get response header and body using header length
        $headerStr = substr($data, 0, $headerSize);
        $bodyStr = substr($data, $headerSize);
        $responseHeaders = explode("\r\n", $headerStr);

        // Validate cloud response body for errors.
        $this->validateResponse($bodyStr, $httpCode, $responseHeaders, $url);

        self::closeCurl($ch);

        return $bodyStr;
    }

    /**
     * Closes a curl handle before PHP 8.0, where it is a resource. From PHP
     * 8.0 it is an object, freed when the last reference to it goes, and
     * PHP 8.5 deprecates curl_close().
     *
     * @param mixed $ch
     */
    private static function closeCurl($ch): void
    {
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
    }

    /**
     * Parser function to get formatted headers.
     *
     * @param array<string> $headers
     * @return array<string, string>
     */
    public function parseHeaders(array $headers): array
    {
        $head = [];
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1])) {
                $head[trim($t[0])] = trim($t[1]);
            }
        }

        return $head;
    }

    /**
     * @param null|array<string> $http_response_header
     * @return int
     */
    private function getHttpCode(?array $http_response_header): int
    {
        if (is_array($http_response_header)) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) { // HTTP/1.0 <code> <text>
                return intval($parts[1]);
            } // Get code
        }

        return 0;
    }

    /**
     * @param array<string> $httpResponseHeaders
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     */
    private function validateResponse(string $cloudResponse, int $httpStatusCode, array $httpResponseHeaders, string $url): void
    {
        $message = null;
        $errors = [];
        $warnings = [];

        if ($cloudResponse) {
            $json = json_decode($cloudResponse, true);
            if (is_array($json)) {
                $errors = self::entries($json, 'errors');
                $warnings = self::entries($json, 'warnings');
            }

            if (count($errors) > 0) {
                $message = implode(',', $errors);
            } elseif ($httpStatusCode !== 200) {
                // If there were no errors returned but the response code was non
                // success then throw an exception.
                $message = sprintf(Constants::MESSAGE_ERROR_CODE_RETURNED, $url, $httpStatusCode, $cloudResponse);
            }
        } else {
            // If there were no errors but there was also no other data
            // in the response then add an explanation to the list of
            // messages.
            $message = sprintf(Constants::MESSAGE_NO_DATA_IN_RESPONSE, $url);
        }

        if (isset($message)) {
            // Get the response headers.
            $responseHeaders = $this->parseHeaders($httpResponseHeaders);
            $cloudError = LicenseKey::maskQuery(
                sprintf(Constants::EXCEPTION_CLOUD_ERROR, $message)
            );

            throw new CloudRequestException(
                $cloudError,
                $httpStatusCode,
                $responseHeaders,
                array_map([LicenseKey::class, 'maskQuery'], $errors),
                array_map([LicenseKey::class, 'maskQuery'], $warnings)
            );
        }
    }

    /**
     * The entries of an array in the response as text, or none where the
     * response has no such array.
     *
     * @param array<mixed> $json The decoded response
     * @return array<string>
     */
    private static function entries(array $json, string $name): array
    {
        if (!isset($json[$name]) || !is_array($json[$name])) {
            return [];
        }

        $entries = [];
        foreach ($json[$name] as $entry) {
            $entries[] = is_scalar($entry)
                ? (string) $entry
                : (string) json_encode($entry);
        }

        return $entries;
    }
}
