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
 * The response to a request for the client-side JavaScript or JSON, as
 * Utils::javascriptResponse() and Utils::jsonResponse() build it. Building
 * it sends nothing, so send() writes it once it is complete.
 */
class ClientSideResponse
{
    /**
     * The HTTP status code, being 200, 304 or 412.
     */
    public int $status;

    /**
     * The response headers, by name.
     *
     * @var array<string, string>
     */
    public array $headers;

    /**
     * The content, empty for a 304, a 412 and an answer to HEAD.
     */
    public string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * Send the status, the headers and the content.
     *
     * @param null|callable $output Writes the content, which is echoed
     * when none is given
     */
    public function send(?callable $output = null): void
    {
        http_response_code($this->status);

        if (!isset($this->headers['Content-Type'])) {
            // PHP adds its default Content-Type to a response that sets none,
            // and a 304 or a 412 has no content to describe. Setting any
            // Content-Type stops PHP adding the default, and removing it
            // then leaves none, with the default_mimetype setting unchanged.
            header('Content-Type:');
            header_remove('Content-Type');
        }

        foreach ($this->headers as $name => $value) {
            // Vary is a list, so one set before is kept beside this one.
            header($name . ': ' . $value, $name !== 'Vary');
        }

        if ($this->body !== '') {
            if ($output === null) {
                echo $this->body;
            } else {
                $output($this->body);
            }
        }
    }
}
