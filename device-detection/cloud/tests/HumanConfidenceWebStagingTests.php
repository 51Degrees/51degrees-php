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
 * the production one does not, being HumanConfidenceSource and the redeem
 * of a sealed value. They call the service CLOUD_STAGING_ENDPOINT names,
 * with the resource key in RESOURCE_KEY_BESPOKE_STAGING, and skip, naming
 * the variable, when either is not set. Only CI sets the two, in
 * .github/workflows/cloud-human-confidence.yml, which runs the Staging
 * suite of phpunit.xml. The Integration suite leaves these tests out.
 */
class HumanConfidenceWebStagingTests extends HumanConfidenceWebTestCase
{
    // Where the cloud service says why HumanConfidenceSource has no value.
    private const SOURCE_NULL_REASON = 'humanconfidencesourcenullreason';

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

    // None, so the example redeems with no key and the cloud service
    // answers it the same wherever the tests run.
    protected static function licenseKey(): ?string
    {
        return null;
    }

    /**
     * The answer the example reads in the first step carries the source
     * Passive, because no browser tests have run, and a plain level.
     */
    public function testPassiveAnswerCarriesTheSourcePassive()
    {
        $this->skipWithoutTheProperty();

        $derived = self::passiveAnswer(self::$key);

        $this->assertSame('Passive', $derived[self::SOURCE_PROPERTY] ?? null, (string) json_encode($derived));
        $this->assertContains($derived[self::PROPERTY] ?? null, self::LEVELS, (string) json_encode($derived));
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
     * The third step, with a value the cloud service did not seal. The
     * cloud service has a redeem, so the example passes on what the cloud
     * service said rather than an error of its own, and never a level.
     */
    public function testRedeemOfAValueThatWasNotSealedGivesNoLevel()
    {
        $this->skipWithoutTheProperty();

        $answer = self::request('/redeem', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['value' => 'not-a-sealed-value'])
        ]);

        $this->assertNotSame(500, $answer['status'], self::printed($answer));
        $this->assertNotSame('', trim($answer['body']), self::printed($answer));
        $this->assertNoFault($answer);
        $json = json_decode($answer['body'], true);
        $this->assertIsArray($json, self::printed($answer));
        $this->assertArrayNotHasKey('error', $json, self::printed($answer));
        $this->assertArrayNotHasKey('humanConfidence', $json, self::printed($answer));
    }
}
