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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * A request that never reaches the cloud service is reported as a request
 * that could not be made.
 *
 * curl_exec() answers false for every such failure, among them a name that
 * does not resolve, a connection that is refused, a certificate that cannot
 * be checked and a request that times out. That false went on to substr(),
 * so the caller saw 'substr(): Argument #1 ($string) must be of type
 * string, false given' naming HttpClient, which says nothing about the
 * connection.
 */
class HttpClientFailureTests extends TestCase
{
    /**
     * Port 1 is reserved and nothing listens on it, so the connection is
     * refused at once and the test needs no network of its own.
     */
    private const UNREACHABLE = 'http://127.0.0.1:1/unreachable.json';

    public function testARequestThatCannotBeMadeSaysSo()
    {
        $client = new HttpClient();

        try {
            $client->makeCloudRequest('GET', self::UNREACHABLE, null, null);
            $this->fail('The request answered rather than failing.');
        } catch (CloudRequestException $exception) {
            $this->assertStringContainsString(
                self::UNREACHABLE,
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                'could not be made',
                $exception->getMessage()
            );
        }
    }

    public function testTheFailureIsNotReportedAsAnEmptyResponse()
    {
        $client = new HttpClient();

        try {
            $client->makeCloudRequest('POST', self::UNREACHABLE, 'a=1', null);
            $this->fail('The request answered rather than failing.');
        } catch (CloudRequestException $exception) {
            $this->assertStringNotContainsString(
                'No data in response',
                $exception->getMessage()
            );
        }
    }
}
