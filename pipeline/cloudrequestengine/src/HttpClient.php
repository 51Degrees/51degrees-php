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

class HttpClient
{
    /**
     * Internal helper method to make a cloud request
     * uses CURL if available, falls back to file_get_contents.
     *
     * @param string $type Method use to send HTTP request
     * @param string $url
     * @param null|string $content Data to be sent in the post body
     * @param null|string $originHeader The value to use for the Origin header
     * @return string Associative array with data and error properties error contains any errors from the request, data contains the response
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
                    'content' => $content
                ]
            ]);

            $data = @file_get_contents($url, false, $context);

            if ($data === false) {
                // The request never reached the service, so there is no
                // status and no body to read. Without this, false went on
                // to validateResponse(), where the strict types this file
                // declares turned it into a type error naming that method,
                // and $http_response_header was never set either, so the
                // caller heard nothing about the connection.
                $error = error_get_last();

                throw new CloudRequestException(sprintf(
                    Constants::MESSAGE_REQUEST_FAILED,
                    $url,
                    $error['message'] ?? 'the reason was not reported'
                ));
            }

            $statusCode = $this->getHttpCode($http_response_header);

            // Validate cloud response for errors.
            $this->validateResponse($data, $statusCode, $http_response_header, $url);

            return $data;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
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
            curl_close($ch);

            throw new CloudRequestException(sprintf(
                Constants::MESSAGE_REQUEST_FAILED,
                $url,
                $error === '' ? 'the reason was not reported' : $error
            ));
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

        curl_close($ch);

        return $bodyStr;
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

        if ($cloudResponse) {
            $json = json_decode($cloudResponse, true);

            if (isset($json['errors']) && count($json['errors'])) {
                $message = implode(',', $json['errors']);
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
            $cloudError = sprintf(Constants::EXCEPTION_CLOUD_ERROR, $message);

            throw new CloudRequestException($cloudError, $httpStatusCode, $responseHeaders);
        }
    }
}
