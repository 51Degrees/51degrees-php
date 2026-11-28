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

namespace fiftyone\pipeline\devicedetection\examples\cloud\classes;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\Constants;
use fiftyone\pipeline\cloudrequestengine\DerivedCloudEngine;
use fiftyone\pipeline\cloudrequestengine\HumanConfidenceTransactions;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;

/**
 * Human Confidence demo server. See ../humanConfidenceWeb.php for the
 * flow.
 */
class HumanConfidenceWeb
{
    // The environment variable the License Key is read from.
    public const LICENSE_KEY_VARIABLE = '_51DEGREES_LICENSE_KEY';

    // The limit on each request to the cloud unless the timeoutSeconds
    // setting gives one. A short limit, so a slow cloud can't hang the demo.
    public const TIMEOUT_SECONDS_DEFAULT = 10.0;

    // What the page is told where the Pipeline can't be built.
    public const MESSAGE_NOT_BUILT =
        "The Pipeline couldn't be built. The server log has the details.";

    // What the page is told where a Property can't be read.
    public const MESSAGE_NOT_READ =
        "The Property couldn't be read. The server log has the details.";

    // The two Properties the example reads, as the derived Element Data
    // names them. Both are named in the request, so a Resource Key whose
    // own list of properties holds HumanConfidence but not
    // HumanConfidenceSource still gets the source.
    private const PROPERTIES = [
        'source' => 'humanconfidencesource',
        'humanConfidence' => 'humanconfidence'
    ];
    private const REQUESTED_PROPERTIES = 'derived.HumanConfidence,derived.HumanConfidenceSource';

    /** @var string */
    private $resourceKey = '';

    /** @var string */
    private $licenseKey = '';

    /** @var string The cloud API base, ending in a slash */
    private $api = Constants::BASE_URL_DEFAULT;

    /** @var float */
    private $timeoutSeconds = self::TIMEOUT_SECONDS_DEFAULT;

    /**
     * The settings the environment variables give.
     *
     * @return array<string, null|string>
     */
    public static function getSettingsFromEnv()
    {
        return [
            'resourceKey' => ExampleUtils::getResourceKeyFromEnv(),
            'licenseKey' => EnvironmentVariable::read(self::LICENSE_KEY_VARIABLE),
            'cloudEndPoint' => ExampleUtils::getCloudEndpoint()
        ];
    }

    /**
     * Answers the request PHP is serving.
     *
     * @param array<string, mixed> $settings resourceKey, the Resource Key,
     * required. licenseKey, a License Key of the same account, which the
     * Pipeline needs to redeem. cloudEndPoint, the API base including the
     * /api/v4/ segment. timeoutSeconds, the limit on each request to the
     * cloud.
     * @param callable $output Writes the answer
     */
    public function run($settings, callable $output)
    {
        $this->resourceKey = (string) ($settings['resourceKey'] ?? '');
        $this->licenseKey = (string) ($settings['licenseKey'] ?? '');
        $endpoint = trim((string) ($settings['cloudEndPoint'] ?? ''));
        if ($endpoint !== '') {
            $this->api = $endpoint;
        }
        $this->api = rtrim($this->api, '/') . '/';
        $timeoutSeconds = (float) ($settings['timeoutSeconds'] ?? 0);
        if ($timeoutSeconds > 0) {
            $this->timeoutSeconds = $timeoutSeconds;
        }

        // The built-in PHP server routes every request to one script, so
        // the example dispatches on the request path itself.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($path === '/') {
            $this->page($output);

            return;
        }

        if ($path === '/examples-main.min.css') {
            header('Content-Type: text/css');
            $output((string) file_get_contents(__DIR__ . '/../static/examples-main.min.css'));

            return;
        }

        // The page asks for the two Properties twice: first with nothing
        // from the browser tests, and then with the sealed result they
        // gave. The example reads them the same way both times.
        if ($path === '/passive' || $path === '/humanconfidence') {
            $this->json(200, $this->read(), $output);

            return;
        }

        http_response_code(404);
    }

    /**
     * The page, or a link to contact us where the Resource Key doesn't
     * include the property.
     */
    private function page(callable $output)
    {
        // The page must never come from a cache, as each load runs the
        // tests and has a transaction of its own.
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');

        if ($this->includesProperty() === false) {
            $contact = htmlspecialchars(ExampleUtils::getTaggedLink(
                'contact-us',
                __FILE__,
                'human-confidence-not-included'
            ));
            $output(
                "<head>\n    <title>Human Confidence demo</title>\n" .
                "    <link rel=\"stylesheet\" href=\"examples-main.min.css\">\n</head>\n" .
                "<div class=\"c-eg-page\">\n" .
                "    <h2 class=\"c-eg-page__title\">Human Confidence demo</h2>\n" .
                "    <p class=\"c-eg-page__lead\"><a href=\"{$contact}\">Contact us</a></p>\n" .
                ExampleUtils::getFindOutMoreHtml(__FILE__) .
                "</div>\n"
            );

            return;
        }

        if ($this->licenseKey === '') {
            error_log(
                'No ' . self::LICENSE_KEY_VARIABLE . ' set. The page still shows Passive and ' .
                "Active, but the Pipeline can't redeem the sealed result without " .
                'one, so HumanConfidence read on the server will be Medium.'
            );
        }

        // Each page gets a page id and a transaction of its own from the
        // package. Its script carries the transaction to the cloud, and the
        // page sends the page id back with the sealed result, so two pages
        // open at once each redeem their own.
        $page = (new HumanConfidenceTransactions())->newPage();
        $json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

        // Read per request, so an edit to the page shows on the next load.
        $output(strtr((string) file_get_contents(__DIR__ . '/../static/humanConfidence.html'), [
            '__SCRIPT__' => (string) json_encode($page->scriptUrl($this->api, $this->resourceKey), $json),
            '__PAGE__' => (string) json_encode($page->pageId(), $json),
            '__FIND_OUT_MORE__' => ExampleUtils::getFindOutMoreHtml(__FILE__)
        ]));
    }

    /**
     * Whether the Resource Key includes the property, from the list of
     * properties the Cloud Request Engine got for it. Null where the
     * engine didn't get the list, and the page then shows what the cloud
     * answers.
     *
     * @return null|bool
     */
    private function includesProperty()
    {
        try {
            $engine = $this->engine();
        } catch (\Exception $e) {
            return null;
        }
        if (!$engine->propertiesLoaded()) {
            return null;
        }

        return isset($engine->flowElementProperties['derived'][self::PROPERTIES['humanConfidence']]);
    }

    /**
     * Processes this request with the Pipeline and reads HumanConfidence
     * and HumanConfidenceSource. Where the request carries a sealed result
     * from the browser tests, the Pipeline redeems it. The example never
     * does.
     *
     * @return array<string, null|string>
     */
    private function read()
    {
        try {
            $pipeline = $this->pipeline();
        } catch (\Exception $e) {
            // The Cloud Request Engine refused the Resource Key or the
            // License Key. The page gets a fixed message, as nothing a page
            // receives may hold the License Key, and the details are logged
            // with the key hidden.
            error_log(
                'The Pipeline could not be built: ' .
                $this->withoutLicenseKey($e->getMessage())
            );

            return ['error' => self::MESSAGE_NOT_BUILT];
        }
        // A Flow Data is for this one request. PHP runs each request on one
        // thread, so nothing else uses it at the same time. A Flow Data
        // must not be used by two threads at once, so never keep one for
        // another request or hand it to another thread.
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->setFromWebRequest();
        $flowData->process();

        $answer = [];
        foreach (self::PROPERTIES as $name => $property) {
            [$answer[$name], $answer[$name . 'Reason']] = $this->property($flowData, $property);
        }

        return $answer;
    }

    /**
     * The text with the License Key replaced, for a log entry.
     *
     * @param string $text
     * @return string
     */
    private function withoutLicenseKey($text)
    {
        return $this->licenseKey === ''
            ? $text
            : str_replace($this->licenseKey, '(hidden)', $text);
    }

    /**
     * A Property's value, or null and the message that says why it has
     * none.
     *
     * @param string $property
     * @return array{0: null|string, 1: null|string}
     */
    private function property(FlowData $flowData, $property)
    {
        try {
            $value = $flowData->derived->{$property};
        } catch (\Exception $e) {
            // The page gets a fixed message rather than the exception's.
            error_log("{$property} could not be read: " . $this->withoutLicenseKey($e->getMessage()));

            return [null, self::MESSAGE_NOT_READ];
        }
        if (!$value->hasValue) {
            return [null, $value->noValueMessage];
        }

        return [is_string($value->value) ? $value->value : null, null];
    }

    /**
     * A Pipeline of the Cloud Request Engine and the engine that gives the
     * derived Element Data. PHP keeps nothing from one request to the next,
     * so it is built for each.
     *
     * @return Pipeline
     */
    private function pipeline()
    {
        // A failed request to the cloud is kept in the FlowData rather than
        // thrown, so the reads below say what happened.
        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => true
        ]))
            ->add($this->engine())
            ->add(new DerivedCloudEngine())
            ->build();
    }

    /**
     * @return CloudRequestEngine
     */
    private function engine()
    {
        $settings = [
            'resourceKey' => $this->resourceKey,
            'requestedProperties' => self::REQUESTED_PROPERTIES,
            'cloudEndPoint' => $this->api,
            'timeoutSeconds' => $this->timeoutSeconds
        ];
        if ($this->licenseKey !== '') {
            $settings['licenseKey'] = $this->licenseKey;
        }

        return new CloudRequestEngine($settings);
    }

    /**
     * Answers the page with a status and a JSON body.
     *
     * @param int $status
     * @param array<string, null|string> $body
     */
    private function json($status, $body, callable $output)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $output((string) json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
