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

/**
 * A read-only view of a web request, held in Evidence under
 * server.http-request. It has no setters, and each getter returns a copy of
 * its array.
 */
final class HttpRequest
{
    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @var array<string, mixed>
     */
    private array $cookies;

    /**
     * @var array<string, mixed>
     */
    private array $query;

    /**
     * @var array<string, mixed>
     */
    private array $session;

    private string $clientIp;
    private string $protocol;
    private string $method;
    private string $path;

    /**
     * @param array<string, string> $headers Header values by lower case name
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $query
     * @param array<string, mixed> $session
     */
    public function __construct(
        array $headers,
        array $cookies,
        array $query,
        array $session,
        string $clientIp,
        string $protocol,
        string $method,
        string $path
    ) {
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->query = $query;
        $this->session = $session;
        $this->clientIp = $clientIp;
        $this->protocol = $protocol;
        $this->method = $method;
        $this->path = $path;
    }

    /**
     * Every header, including Cookie, by lower case name.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return $this->session;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    /**
     * The request's own protocol, http or https, from the HTTPS server
     * value or the X-Forwarded-Proto header. The Referer does not change it.
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * The path of the request line as sent, with no decoding.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
