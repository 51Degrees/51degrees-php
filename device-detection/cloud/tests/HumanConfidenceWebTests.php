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

use fiftyone\pipeline\devicedetection\examples\cloud\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\Constants;
use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use fiftyone\pipeline\devicedetection\tests\classes\HumanConfidenceWebTestCase;
use fiftyone\pipeline\devicedetection\tests\classes\ResourceKeys;

/**
 * Serves the Human Confidence web example with PHP's built-in server and
 * asks it for what its page asks it for, with the resource key of the
 * tests, against the cloud service _51DEGREES_CLOUD_ENDPOINT names, or the
 * public one. The browser tests need a browser, so the steps that follow
 * them are checked only as far as a program can take them.
 *
 * Every test is skipped with no resource key, naming the variable. The
 * tests of the flow fail where the resource key does not include the
 * HumanConfidence property, as a test that cannot see a real level has
 * proved nothing. The page is then checked for the link to contact us.
 *
 * HumanConfidenceWebStagingTests checks what only the staging cloud service
 * serves, being a passive level, HumanConfidenceSource and the redeem,
 * including the example's passive step.
 */
class HumanConfidenceWebTests extends HumanConfidenceWebTestCase
{
    // The cloud service the example calls unless the environment names
    // another.
    private const DEFAULT_ENDPOINT = 'https://cloud.51degrees.com/api/v4/';

    // The style sheet vendored beside the page. The weekly example assets
    // update replaces it with the latest published release, so the test
    // compares the answer with the file rather than naming its rules.
    private const STYLE_SHEET = __DIR__ . '/../examples/cloud/static/examples-main.min.css';

    protected static function resourceKeyVariable(): string
    {
        return Constants::RESOURCE_ENV_VAR;
    }

    protected static function endpointVariable(): string
    {
        return ExampleUtils::CLOUD_ENDPOINT_VARIABLE;
    }

    protected static function defaultEndpoint(): ?string
    {
        return self::DEFAULT_ENDPOINT;
    }

    protected static function licenseKey(): ?string
    {
        return ResourceKeys::find(Constants::LICENSE_ENV_VAR);
    }

    /**
     * The page has a row for each of the three steps, the address of the
     * client script with the resource key, the cloud service and a
     * transaction, the page's page id, and the links to find out more, and is
     * never kept by a cache, as each load runs the browser tests.
     */
    public function testPageShowsTheThreeSteps()
    {
        self::assertIncludesTheProperty(self::$properties);

        $answer = self::get('/');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $this->assertSame('no-store', $answer['headers']['cache-control'] ?? null);
        foreach (['passive-source', 'passive-level', 'active-source', 'active-value',
            'redeem-detail', 'redeem-level', 'again'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $answer['body']);
        }
        $this->assertStringContainsString(
            'var SCRIPT = "' . self::$endpoint . rawurlencode(self::$key) . '.js?transaction=',
            $answer['body']
        );
        $this->assertMatchesRegularExpression('/var PAGE = "[^"]+";/', $answer['body']);
        $this->assertDoesNotMatchRegularExpression('/__[A-Z_]+__/', $answer['body']);
        $this->assertEndsWithFindOutMore($answer);
    }

    /**
     * With a resource key that does not include the property, the page
     * gives a tagged link to contact us in place of the three steps.
     */
    public function testPageGivesAContactLinkWithoutTheProperty()
    {
        $this->assertNotNull(self::$properties, self::notListedMessage());
        if (isset(self::$properties[self::PROPERTY])) {
            $this->markTestSkipped('The resource key includes the HumanConfidence property.');
        }

        $answer = self::get('/');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertNoFault($answer);
        $this->assertStringContainsString(
            'https://51degrees.com/contact-us?',
            html_entity_decode($answer['body'])
        );
        $this->assertStringNotContainsString('id="passive-level"', $answer['body']);
        $this->assertEndsWithFindOutMore($answer);
    }

    /**
     * The page links its style sheet, so the example serves the one
     * vendored beside the page, unchanged.
     */
    public function testStyleSheet()
    {
        $expected = (string) file_get_contents(self::STYLE_SHEET);
        $this->assertNotSame('', $expected, self::STYLE_SHEET . ' is missing or empty.');

        $answer = self::get('/examples-main.min.css');

        $this->assertSame(200, $answer['status'], self::printed($answer));
        $this->assertStringStartsWith('text/css', $answer['headers']['content-type'] ?? '');
        $this->assertSame($expected, $answer['body']);
    }

    /**
     * The example answers only the addresses its page asks for.
     */
    public function testAnotherAddressIsNotFound()
    {
        $answer = self::get('/not-part-of-the-example');

        $this->assertSame(404, $answer['status'], self::printed($answer));
    }

    /**
     * Checks that the page ends with the Find out more section and that
     * every 51degrees.com link in it is tagged, as every example's page is
     * checked.
     *
     * @param array{status: int, headers: array<string, string>, body: string} $answer
     */
    private function assertEndsWithFindOutMore(array $answer): void
    {
        $run = new ExampleRun();
        $run->output = $answer['body'];
        $run->httpStatus = $answer['status'];
        $run->assertPageEndsWithFindOutMore();
    }
}
