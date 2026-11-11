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
 * Storage of evidence on a FlowData object.
 */
class Evidence
{
    /**
     * PHP's own default for max_input_vars, used where the setting cannot
     * be read. See maxInputVars().
     */
    private const DEFAULT_MAX_INPUT_VARS = 1000;

    /**
     * The two request headers PHP puts in $_SERVER without the HTTP_ prefix,
     * by their $_SERVER name, with the header name each is given in the web
     * request.
     */
    private const UNPREFIXED_HEADERS = [
        'CONTENT_TYPE' => 'content-type',
        'CONTENT_LENGTH' => 'content-length',
    ];

    protected FlowData $flowData;

    /**
     * @var array<string, int|string>
     */
    protected array $evidence = [];

    /**
     * Values no element in the Pipeline accepts, kept apart from the values
     * above so that only a reader asking for them sees them. A Cloud
     * Request Engine sends them, because what the cloud service uses
     * changes over time. Every other reader, being an element's filtered
     * view, the JavaScript served to the browser and the client side cache
     * key, reads the accepted values alone.
     *
     * @var array<string, int|string>
     */
    protected array $unaccepted = [];

    /**
     * The key the web request is held under.
     */
    public const HTTP_REQUEST_KEY = 'server.http-request';

    /**
     * The web request, kept apart from the values above so that it never
     * reaches an element's filter, a cache key or a request to the cloud.
     */
    protected ?HttpRequest $httpRequest = null;

    /**
     * Evidence container constructor.
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData Parent FlowData
     */
    public function __construct(FlowData $flowData)
    {
        $this->flowData = $flowData;
    }

    /**
     * Add the key value pair to the evidence collection where a flow
     * element can use the key, and to the unaccepted collection where none
     * can, so that a Cloud Request Engine can still send it. A key is held
     * in one collection or the other and never in both.
     *
     * @param int|string $value
     */
    public function set(string $key, $value): void
    {
        $keep = false;

        foreach ($this->flowData->pipeline->flowElements as $flowElement) {
            if ($flowElement->filterEvidenceKey($key)) {
                $keep = true;
                break;
            }
        }

        if ($keep) {
            // A key accepted now may have been unaccepted when it was first
            // set, which happens where an element resolves its filter after
            // the Evidence was set, so the earlier copy goes.
            unset($this->unaccepted[$key]);
            $this->evidence[$key] = $value;

            return;
        }

        unset($this->evidence[$key]);
        $this->unaccepted[$key] = $value;
    }

    /**
     * Helper function to set multiple pieces of evidence from an array.
     *
     * @param array<string, int|string> $array
     */
    public function setArray($array): void
    {
        if (!is_array($array)) {
            $this->flowData->setError('core', new \Exception(Messages::PASS_KEY_VALUE));

            return;
        }

        foreach ($array as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Extract evidence from a web request
     * No argument version automatically reads from current request using the
     * $_SERVER, $_COOKIE, $_GET and $_POST globals.
     *
     * @param null|array<string, string> $server Key-value pairs for the HTTP headers
     * @param null|array<string, string> $cookies Key-value pairs for the cookies
     * @param null|array<string, string> $query Key-value pairs for the form parameters
     */
    public function setFromWebRequest(?array $server = null, ?array $cookies = null, ?array $query = null): void
    {
        if ($server === null) {
            $server = $_SERVER;
        }

        if ($cookies === null) {
            $cookies = $_COOKIE;
        }

        if ($query === null) {
            $query = static::queryFromRequest($server);
        }

        $evidence = [];
        $headers = [];

        foreach ($server as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));

                $key = strtolower($key);

                $evidence['header.' . $key] = $value;
                $headers[$key] = (string) $value;
            }
        }

        foreach (self::UNPREFIXED_HEADERS as $name => $key) {
            if (isset($server[$name]) && !isset($headers[$key])) {
                $headers[$key] = (string) $server[$name];
            }
        }

        foreach ($cookies as $key => $value) {
            $evidence['cookie.' . $key] = $value;
        }

        foreach ($query as $key => $value) {
            $evidence['query.' . $key] = $value;
        }

        if (isset($server['SERVER_ADDR'])) {
            $evidence['server.host-ip'] = $server['SERVER_ADDR'];
        }

        if (isset($server['REMOTE_ADDR'])) {
            $evidence['server.client-ip'] = $server['REMOTE_ADDR'];
        }

        // Protocol

        if (isset($server['HTTPS']) && ($server['HTTPS'] == 'on' || $server['HTTPS'] == 1) || isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }

        // The web request's view keeps the request's own protocol, so the
        // Referer below, which is the scheme of the page that made the
        // request, changes only the header.protocol Evidence.
        $requestProtocol = $protocol;

        // Override protocol with referer header if set

        if (isset($server['HTTP_REFERER']) && $server['HTTP_REFERER']) {
            $protocol = parse_url($server['HTTP_REFERER'], PHP_URL_SCHEME);
        }

        $evidence['header.protocol'] = $protocol;

        $this->setArray($evidence);

        $this->httpRequest = new HttpRequest(
            $headers,
            $cookies,
            $query,
            isset($_SESSION) && is_array($_SESSION) ? $_SESSION : [],
            (string) ($server['REMOTE_ADDR'] ?? ''),
            $requestProtocol,
            (string) ($server['REQUEST_METHOD'] ?? ''),
            self::pathOf((string) ($server['REQUEST_URI'] ?? ''))
        );
    }

    /**
     * The path of a request target, being everything before the first ? or #,
     * left as sent.
     */
    private static function pathOf(string $target): string
    {
        $end = strcspn($target, '?#');

        return substr($target, 0, $end);
    }

    /**
     * The form parameters of the current request, with the names as the
     * browser sent them.
     *
     * PHP replaces a dot or a space in a parameter name with an underscore
     * when it fills $_GET and $_POST, so a request carrying 'id.usage'
     * arrives as 'id_usage' and the cloud service, which reads 'id.usage',
     * never sees it. The raw query string and, for a form encoded body, the
     * raw body are read again here so those names survive. A name PHP left
     * alone is taken from $_GET and $_POST as before, and where a name was
     * changed the changed one is dropped in favour of the name that was
     * sent.
     *
     * Only a name holding a dot is put back, see isDottedName(). A name
     * written in the array form, such as 'a[b]', is left to PHP, which
     * builds an array from it. A multipart body cannot be read a second
     * time, so a dotted name sent in one keeps the underscore PHP gave it.
     *
     * @param array<string, string> $server Key-value pairs for the HTTP headers
     * @return array<string, int|string>
     */
    protected static function queryFromRequest(array $server): array
    {
        // Merge the GET and POST parameters favoring the GET keys if there
        // are keys that conflict.
        $query = array_merge($_POST, $_GET);

        $sent = [];
        if (static::isFormEncodedPost($server)) {
            $sent = static::parseFormEncoded(static::rawBody());
        }

        // The query string is applied last for the same reason the GET
        // parameters win above.
        foreach (static::parseFormEncoded((string) ($server['QUERY_STRING'] ?? '')) as $name => $value) {
            $sent[$name] = $value;
        }

        foreach ($sent as $name => $value) {
            if (static::isDottedName($name) === false) {
                continue;
            }

            unset($query[static::asPhpGivesIt($name)]);
            $query[$name] = $value;
        }

        return $query;
    }

    /**
     * Whether the request carries a form encoded body, which is the only
     * body that can be read again as name and value pairs.
     *
     * @param array<string, string> $server Key-value pairs for the HTTP headers
     */
    protected static function isFormEncodedPost(array $server): bool
    {
        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return false;
        }

        // Some SAPI and proxy configurations pass the content type on only
        // as HTTP_CONTENT_TYPE. Reading CONTENT_TYPE alone left the body
        // unread on those, so a dotted name posted in a form body kept the
        // underscore with nothing to say the request had not been re-read.
        $contentType = $server['CONTENT_TYPE']
            ?? $server['HTTP_CONTENT_TYPE']
            ?? '';

        return strpos(
            strtolower((string) $contentType),
            'application/x-www-form-urlencoded'
        ) === 0;
    }

    /**
     * The body of the request exactly as it arrived. Overridden in tests,
     * because php://input cannot be written to.
     */
    protected static function rawBody(): string
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }

    /**
     * Reads form encoded text into name and value pairs, decoding both and
     * keeping every name exactly as it was sent. A repeated name takes the
     * last value, as PHP does. A name in the array form is skipped, because
     * PHP builds an array for it and this is not trying to replace that.
     *
     * Reading stops after max_input_vars names, which is where PHP itself
     * stops filling $_GET and $_POST. Without the limit a request could
     * carry any number of names past the point PHP would have given up, and
     * every one of them would be offered to every flow element in the
     * pipeline.
     *
     * @return array<string, string>
     */
    protected static function parseFormEncoded(string $raw): array
    {
        $values = [];
        $remaining = static::maxInputVars();

        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }

            if ($remaining <= 0) {
                break;
            }

            --$remaining;

            $split = strpos($pair, '=');
            $name = urldecode($split === false ? $pair : substr($pair, 0, $split));

            if ($name === '' || strpos($name, '[') !== false) {
                continue;
            }

            $values[$name] = $split === false
                ? ''
                : urldecode(substr($pair, $split + 1));
        }

        return $values;
    }

    /**
     * Whether a name sent by the browser is one whose dots are put back.
     *
     * A dot is put back because it separates the parts of the names the
     * cloud service reads, such as 'id.usage'. A space is not: no name the
     * service reads holds one, PHP strips a leading space rather than
     * replacing it so the name it gave could not be found to drop, and
     * putting the space back produced an evidence key with a space in it,
     * such as 'query.user agent' for a request sending 'user+agent', which
     * nothing downstream matches. A name holding both is left to PHP whole,
     * because putting back only the dot would leave PHP's name in place
     * beside it and the evidence would carry the parameter twice.
     */
    protected static function isDottedName(string $name): bool
    {
        return strpos($name, '.') !== false && strpos($name, ' ') === false;
    }

    /**
     * The name PHP gives a parameter in $_GET and $_POST. Only a name
     * holding a dot and no space reaches here, see isDottedName(), so
     * replacing the dot is the whole of it.
     */
    protected static function asPhpGivesIt(string $name): string
    {
        return str_replace('.', '_', $name);
    }

    /**
     * How many parameters PHP itself would keep from one input source,
     * which is how many are read from the request here.
     *
     * ini_get returns false where the setting cannot be read, and a value
     * of zero or less would drop every parameter, so PHP's documented
     * default stands in for both.
     */
    protected static function maxInputVars(): int
    {
        $configured = (int) ini_get('max_input_vars');

        return $configured > 0 ? $configured : self::DEFAULT_MAX_INPUT_VARS;
    }

    /**
     * Get a piece of evidence by key.
     *
     * @return null|int|string|HttpRequest
     */
    public function get(string $key)
    {
        if ($key === self::HTTP_REQUEST_KEY) {
            return $this->httpRequest;
        }

        return $this->evidence[$key] ?? null;
    }

    /**
     * Get all evidence, apart from the web request that get() returns under
     * server.http-request.
     *
     * @return array<string, int|string>
     */
    public function getAll(): array
    {
        return $this->evidence;
    }

    /**
     * Every value set on this Flow Data, both the values an element accepts
     * and the values none does, apart from the web request that get()
     * returns under server.http-request.
     *
     * A Cloud Request Engine sends this, as the Processing section of the
     * cloud request engine specification requires. No other reader wants
     * it, because an unaccepted value is one no element in the Pipeline
     * asked for.
     *
     * @return array<string, int|string>
     */
    public function getAllIncludingUnaccepted(): array
    {
        return array_merge($this->unaccepted, $this->evidence);
    }
}
