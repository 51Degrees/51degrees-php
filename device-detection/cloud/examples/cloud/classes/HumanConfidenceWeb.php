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

use fiftyone\pipeline\cloudrequestengine\Constants;

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

    // The property the Resource Key must include.
    private const PROPERTY = 'HumanConfidence';

    // The properties the passive call reads, named in its request. Both
    // are named, so a Resource Key whose own list of properties holds
    // HumanConfidence but not HumanConfidenceSource still gets the source.
    private const PASSIVE_VALUES = 'derived.humanconfidence,derived.humanconfidencesource';

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
     * required. licenseKey, a License Key of the same account, required to
     * redeem. cloudEndPoint, the API base including the /api/v4/ segment.
     * timeoutSeconds, the limit on each request to the cloud.
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

        if ($path === '/passive') {
            $this->json(200, $this->passive(), $output);

            return;
        }

        if ($path === '/redeem') {
            $this->redeem($output);

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
        // The page must never come from a cache, as each load runs the tests.
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
                "Active, but an account that holds License Keys can't redeem " .
                'without one, so the redeemed level will be unreadable.'
            );
        }

        // Read per request, so an edit to the page shows on the next load.
        $output(strtr((string) file_get_contents(__DIR__ . '/../static/humanConfidence.html'), [
            '__RESOURCE__' => $this->resourceKey,
            '__API__' => $this->api,
            '__FIND_OUT_MORE__' => ExampleUtils::getFindOutMoreHtml(__FILE__)
        ]));
    }

    /**
     * Whether the Resource Key includes the property, from the cloud's own
     * list of the properties the key gives. Null where the cloud didn't
     * give the list, and the page then shows what the cloud answers.
     *
     * @return null|bool
     */
    private function includesProperty()
    {
        try {
            $answer = $this->request(
                'GET',
                $this->api . 'accessibleproperties?resource=' . rawurlencode($this->resourceKey)
            );
        } catch (\RuntimeException $e) {
            return null;
        }
        $json = $answer['status'] === 200 ? json_decode($answer['body'], true) : null;
        if (!is_array($json)) {
            return null;
        }
        $json = array_change_key_case($json);
        if (!is_array($json['products'] ?? null)) {
            return null;
        }

        foreach ($json['products'] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $product = array_change_key_case($product);
            if (!is_array($product['properties'] ?? null)) {
                continue;
            }
            foreach ($product['properties'] as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $property = array_change_key_case($property);
                if (
                    is_string($property['name'] ?? null) &&
                    strcasecmp($property['name'], self::PROPERTY) === 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Passive: this server asks the cloud for the visitor, with the
     * visitor's headers and IP address, naming the two properties it reads,
     * and reads the source and the plain level from the answer.
     *
     * @return array<string, null|string>
     */
    private function passive()
    {
        try {
            $names = $this->cloudHeaders();
            $evidence = [];
            foreach ($_SERVER as $name => $value) {
                if (strpos((string) $name, 'HTTP_') !== 0 || !is_string($value)) {
                    continue;
                }
                $header = strtolower(str_replace('_', '-', substr((string) $name, 5)));
                if (isset($names[$header])) {
                    $evidence[$header] = $value;
                }
            }
            // Behind a load balancer or CDN, use the visitor's address it
            // passes on. Set last, so no header can replace it.
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (
                stripos($ip, '::ffff:') === 0 &&
                filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ) {
                $ip = substr($ip, 7);
            }
            if ($ip !== '') {
                $evidence['client-ip'] = $ip;
            }
            // Named after the headers, so no header can replace them.
            $evidence['values'] = self::PASSIVE_VALUES;

            $answer = $this->request(
                'POST',
                $this->api . rawurlencode($this->resourceKey) . '.json',
                $evidence
            );
            if ($answer['status'] < 200 || $answer['status'] > 299) {
                return ['error' => "HTTP {$answer['status']}: " . self::trim($answer['body'])];
            }
            $json = json_decode($answer['body'], true);
            if (!is_array($json)) {
                return ['error' => json_last_error_msg()];
            }
            $derived = $json['derived'] ?? null;

            return [
                'source' => self::read($derived, 'humanconfidencesource'),
                'humanConfidence' => self::read($derived, 'humanconfidence')
            ];
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * The request headers the cloud reads, from its own list. Only these
     * are passed on, so headers such as Authorization stay here. PHP keeps
     * nothing from one request to the next, so the list is fetched for
     * each.
     *
     * @return array<string, bool> Keyed by the header's name in lower case
     */
    private function cloudHeaders()
    {
        $answer = $this->request('GET', $this->api . 'evidencekeys');
        if ($answer['status'] < 200 || $answer['status'] > 299) {
            throw new \RuntimeException("HTTP {$answer['status']}: " . self::trim($answer['body']));
        }
        $keys = json_decode($answer['body'], true);
        if (!is_array($keys)) {
            throw new \RuntimeException(json_last_error_msg());
        }

        $names = [];
        foreach ($keys as $key) {
            if (is_string($key) && stripos($key, 'header.') === 0) {
                $names[strtolower(substr($key, 7))] = true;
            }
        }

        return $names;
    }

    /**
     * Redeem: the page sends the sealed value, and this server redeems it
     * with the License Key. The answer goes back to the page as the cloud
     * gave it.
     */
    private function redeem(callable $output)
    {
        $value = $_POST['value'] ?? '';
        $fields = ['value' => is_string($value) ? $value : ''];
        if ($this->licenseKey !== '') {
            $fields['license'] = $this->licenseKey;
        }

        try {
            $answer = $this->request('POST', $this->api . 'hc/redeem', $fields);
        } catch (\RuntimeException $e) {
            $this->json(502, ['error' => $e->getMessage()], $output);

            return;
        }

        if ($answer['status'] === 404) {
            $this->json(
                200,
                ['error' => "The service at {$this->api} doesn't redeem Human Confidence."],
                $output
            );

            return;
        }

        http_response_code($answer['status'] > 0 ? $answer['status'] : 502);
        header('Content-Type: application/json');
        $output($answer['body']);
    }

    /**
     * Makes a request to the cloud and gives its status and body, whatever
     * the status.
     *
     * @param string $method
     * @param string $url
     * @param null|array<string, string> $fields Form fields for the body
     * @return array{status: int, body: string}
     * @throws \RuntimeException where the cloud gave no answer
     */
    private function request($method, $url, $fields = null)
    {
        $http = [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => $this->timeoutSeconds
        ];
        if ($fields !== null) {
            $http['header'] = 'Content-Type: application/x-www-form-urlencoded';
            $http['content'] = http_build_query($fields);
        }

        // A request that cannot be made gives a warning and false. The
        // warning is the message of the exception below.
        $reason = null;
        set_error_handler(
            static function (int $level, string $message) use (&$reason): bool {
                $reason = $message;

                return true;
            },
            E_WARNING
        );

        try {
            $stream = fopen($url, 'r', false, stream_context_create(['http' => $http]));
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            throw new \RuntimeException($reason ?? $url);
        }
        if ($reason !== null) {
            // A warning from a request that was made is not expected, so it
            // is raised again.
            trigger_error($reason, E_USER_WARNING);
        }
        $lines = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = (string) stream_get_contents($stream);
        fclose($stream);

        // The last status line is the answer's own, after any redirect.
        $status = 0;
        foreach ((array) $lines as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $line, $code) === 1) {
                $status = (int) $code[1];
            }
        }

        return ['status' => $status, 'body' => $body];
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

    /**
     * @param mixed $parent
     * @param string $name
     * @return null|string
     */
    private static function read($parent, $name)
    {
        return is_array($parent) && is_string($parent[$name] ?? null)
            ? $parent[$name]
            : null;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function trim($text)
    {
        return strlen($text) > 200 ? substr($text, 0, 200) : $text;
    }
}
