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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\HumanConfidenceWebTestCase;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;

/**
 * The Human Confidence tests of what the staging cloud service serves and
 * the production one does not, being a passive level, HumanConfidenceSource
 * and the Human Confidence redeem. They call the service
 * CLOUD_STAGING_ENDPOINT names, with the resource key in
 * RESOURCE_KEY_BESPOKE_STAGING, and skip, naming the variable, when either
 * is not set. A test that needs a property the key does not include fails,
 * naming the property and the variable. Only CI sets the two, in
 * .github/workflows/cloud-human-confidence.yml, which runs the Staging
 * suite of phpunit.xml. The Integration suite leaves these tests out.
 */
class HumanConfidenceWebStagingTests extends HumanConfidenceWebTestCase
{
    // Where the cloud service says why HumanConfidenceSource has no value.
    private const SOURCE_NULL_REASON = 'humanconfidencesourcenullreason';

    // A value the cloud service did not seal, for the redeem.
    private const NOT_SEALED = 'not-a-sealed-value';

    protected static function resourceKeyVariable(): string
    {
        return Constants::STAGING_RESOURCE_ENV_VAR;
    }

    protected static function endpointVariable(): string
    {
        return Constants::STAGING_ENDPOINT_ENV_VAR;
    }

    // None, so the tests skip rather than call another cloud service.
    protected static function defaultEndpoint(): ?string
    {
        return null;
    }

    // None, so the example's Pipeline can't redeem, and reads Medium the
    // same way wherever the tests run.
    protected static function licenseKey(): ?string
    {
        return null;
    }

    /**
     * The cloud service's answer to a passive request that names both
     * properties carries the source Passive, because no browser tests have
     * run, and a plain level. This checks the cloud service, with no
     * example, so a failure here is the cloud service's or the key's.
     */
    public function testPassiveAnswerCarriesTheSourcePassive()
    {
        self::assertIncludesTheProperty(self::$properties);

        $derived = self::passiveAnswer(self::$key);

        $this->assertSame('Passive', $derived[self::SOURCE_PROPERTY] ?? null, (string) json_encode($derived));
        $this->assertContains($derived[self::PROPERTY] ?? null, self::LEVELS, (string) json_encode($derived));
    }

    /**
     * The first step through the example. The example asks the cloud
     * service with the headers of the request it was sent, and answers with
     * the source Passive, as no browser tests have run, and a plain level.
     * Its request names both properties, as Java's does, so it gets the
     * source whether or not the key's own list of properties holds it.
     */
    public function testExamplePassiveGivesTheSourcePassiveAndAPlainLevel()
    {
        self::assertIncludesTheProperty(self::$properties);

        $answer = self::get('/passive', Constants::UA_HEADER . Constants::CHROME_UA . "\r\n");

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $json = json_decode($answer['body'], true);
        $this->assertIsArray($json, self::printed($answer));
        $this->assertArrayNotHasKey('error', $json, self::printed($answer));
        $this->assertSame('Passive', $json['source'] ?? null, self::printed($answer));
        $this->assertContains($json['humanConfidence'] ?? null, self::LEVELS, self::printed($answer));
    }

    /**
     * The Basic product does not offer HumanConfidenceSource, so a Basic
     * key gets the source as null, with the reason it has no value. An
     * answer that leaves the source out says nothing about the product, so
     * it fails.
     */
    public function testBasicKeyGetsNoSource()
    {
        $key = ResourceKeys::find(Constants::BASIC_RESOURCE_ENV_VAR);
        if ($key === null) {
            $this->markTestSkipped(ResourceKeys::missingMessage(Constants::BASIC_RESOURCE_ENV_VAR));
        }

        $derived = self::passiveAnswer($key);

        $message = (string) json_encode($derived);
        $this->assertArrayHasKey(self::SOURCE_PROPERTY, $derived, $message);
        $this->assertNull($derived[self::SOURCE_PROPERTY], $message);
        $this->assertIsString($derived[self::SOURCE_NULL_REASON] ?? null, $message);
    }

    /**
     * The cloud service's redeem, asked with the resource key, answers a
     * value it did not seal with the status unreadable and the level
     * Medium, which it gives whenever the status is not success. It reads
     * the value as sealedResult and answers in status.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#the-redeem-endpoints
     */
    public function testRedeemOfAValueThatWasNotSealedIsUnreadable()
    {
        self::assertIncludesTheProperty(self::$properties);

        $answer = self::postToCloud('hc/redeem', [
            'resource' => self::$key,
            'sealedResult' => self::NOT_SEALED
        ]);

        $message = str_replace(
            self::$key,
            '<key>',
            "HTTP status {$answer['status']}.\nBody:\n{$answer['body']}"
        );
        $this->assertSame(200, $answer['status'], $message);
        $json = json_decode($answer['body'], true);
        $this->assertIsArray($json, $message);
        $this->assertSame('unreadable', $json['status'] ?? null, $message);
        $this->assertSame('Medium', $json['humanConfidence'] ?? null, $message);
    }

    /**
     * The third step through the example, with no license key and a value
     * the cloud service did not seal. The Pipeline can't redeem without a
     * License Key, so the example reads HumanConfidenceSource Active and
     * HumanConfidence Medium, with no error.
     */
    public function testExampleReadsMediumForASealedResultItCannotRedeem()
    {
        self::assertIncludesTheProperty(self::$properties);

        $answer = self::request('/humanconfidence', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['humanconfidence' => self::NOT_SEALED])
        ]);

        $this->assertNoFault($answer);
        $this->assertSame(200, $answer['status'], self::printed($answer));
        $json = json_decode($answer['body'], true);
        $this->assertIsArray($json, self::printed($answer));
        $this->assertArrayNotHasKey('error', $json, self::printed($answer));
        $this->assertSame('Active', $json['source'] ?? null, self::printed($answer));
        $this->assertSame('Medium', $json['humanConfidence'] ?? null, self::printed($answer));
    }
}
