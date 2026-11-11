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

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\Evidence;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\LicenseKey;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

// Engine that makes a call to the 51Degrees cloud service
// Returns raw JSON as a "cloud" property under "cloud" dataKey
class CloudRequestEngine extends Engine
{
    // Joins license keys in one license key setting.
    private const LICENSE_KEY_SEPARATOR = '|';

    public string $dataKey = 'cloud';

    // Default base url
    public string $baseURL;
    public ?string $cloudRequestOrigin;

    /**
     * @var array<string, array<string, array<string, bool|string>>>
     */
    public array $flowElementProperties = [];
    public string $resourceKey;
    public string $licenseKey;

    /**
     * The fully qualified names of the properties each request asks for.
     *
     * @var array<string>
     */
    public array $requestedProperties;

    // Set once the requested properties have been compared with an answer.
    private bool $notCoveredReported = false;

    /**
     * @var array<string>
     */
    private array $evidenceKeys = [];
    private bool $evidenceKeysLoaded = false;
    // The failure of a fetch made for the filter, which processInternal
    // reports rather than fetching again.
    private ?\Throwable $evidenceKeysFailure = null;
    private HttpClient $httpClient;

    /**
     * Settings should contain a resourceKey, a licenseKey or both,
     * and optionally:
     * 1) requestedProperties, the fully qualified names of the properties
     *    to ask for, as an array or one comma separated string. Required
     *    with a licenseKey on its own. Beside a resourceKey the cloud
     *    service adds them to the properties the resource key carries.
     * 2) a cloudEndPoint to overwrite the default baseurl
     * 3) a cloudRequestOrigin to use when sending requests to cloud.
     *
     * @param array{
     *     resourceKey?: string,
     *     licenseKey?: string,
     *     requestedProperties?: array<string>|string,
     *     cloudEndPoint?: string,
     *     cloudRequestOrigin?: string,
     *     httpClient?: \fiftyone\pipeline\cloudrequestengine\HttpClient
     * } $settings
     */
    public function __construct(array $settings)
    {
        $this->resourceKey = self::keySetting($settings['resourceKey'] ?? null);
        $this->licenseKey = self::keySetting($settings['licenseKey'] ?? null);
        $this->requestedProperties = self::propertyNames($settings['requestedProperties'] ?? null);
        $this->refuseInvalidCredentials();

        if (isset($settings['cloudEndPoint'])) {
            $this->baseURL = $settings['cloudEndPoint'];
        } else {
            // Check if base URL is set via environment variable
            $envVarUrl = getenv(Constants::CLOUD_ENDPOINT_VARIABLE);
            if (empty($envVarUrl)) {
                // Use default if nothing else is set
                $this->baseURL = Constants::BASE_URL_DEFAULT;
            } else {
                $this->baseURL = $envVarUrl;
            }
        }

        // Make sure the base URL end with '/'
        $length = strlen($this->baseURL);
        if ($length > 0 && substr($this->baseURL, $length - 1) != '/') {
            $this->baseURL = $this->baseURL . '/';
        }

        $this->httpClient = $settings['httpClient'] ?? new HttpClient();
        $this->cloudRequestOrigin = $settings['cloudRequestOrigin'] ?? null;

        parent::__construct();
    }

    /**
     * Instance of EvidenceKeyFilter of the evidence keys the cloud service
     * lists. The keys are fetched the first time they are needed, which is
     * when the first Evidence is set, so that the Evidence the cloud service
     * uses is kept. A failed fetch leaves the list empty, and processInternal
     * reports the failure.
     */
    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        if (!$this->evidenceKeysLoaded && $this->evidenceKeysFailure === null) {
            try {
                $this->loadEvidenceKeys();
            } catch (\Throwable $e) {
                $this->evidenceKeysFailure = $e;
            }
        }

        return new BasicListEvidenceKeyFilter($this->evidenceKeys);
    }

    /**
     * Processing function for the CloudRequestEngine
     * Makes a request to the cloud service with the supplied resource key
     * and evidence and returns a JSON object that is then parsed by cloud engines
     * placed later in the pipeline.
     */
    public function processInternal(FlowData $flowData): void
    {
        if (count($this->flowElementProperties) === 0) {
            $this->flowElementProperties = $this->getEngineProperties();
        }

        if (!$this->evidenceKeysLoaded) {
            $failure = $this->evidenceKeysFailure;
            $this->evidenceKeysFailure = null;
            if ($failure !== null) {
                throw $failure;
            }
            $this->loadEvidenceKeys();
        }

        // A resource key names the route. A license key on its own has no
        // resource key to put there, so it uses the route that takes none.
        $url = $this->resourceKey !== ''
            ? $this->baseURL . $this->resourceKey . '.json?'
            : $this->baseURL . Constants::LICENSE_DATA_PATH;

        // The keys go in the body, the license key never in the URL. They
        // and the list are set after the evidence, so evidence cannot
        // replace them.
        $fields = $this->getContent($flowData);
        if ($this->resourceKey !== '') {
            $fields[Constants::RESOURCE_PARAMETER] = $this->resourceKey;
        }
        if ($this->licenseKey !== '') {
            $fields[Constants::LICENSE_PARAMETER] = $this->licenseKey;
        }
        if (count($this->requestedProperties) > 0) {
            $fields[Constants::VALUES_PARAMETER] = implode(',', $this->requestedProperties);
        }

        $content = http_build_query($fields);

        $result = $this->request('POST', $url, $content);

        $data = new AspectDataDictionary($this, ['cloud' => $result]);

        $flowData->setElementData($data);

        $this->reportPropertiesNotCovered($flowData, $result);
    }

    /**
     * A response whose JSON has a top-level member named fodid, in any case,
     * holds a 51Did created for the one request.
     */
    protected function holdsValueForOneRequest(ElementData $data): bool
    {
        $json = json_decode((string) $data->get('cloud'), true);

        if (!is_array($json)) {
            return false;
        }

        foreach (array_keys($json) as $member) {
            if (strcasecmp((string) $member, 'fodid') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate the Content to send in the POST request. The evidence keys
     * e.g. 'query.' and 'header.' have an order of precedence. These are
     * added to the evidence in reverse order, if there is conflict then
     * the queryData value is overwritten.
     *
     * 'query.' evidence should take precedence over all other evidence.
     * If there are evidence keys other than 'query.' that conflict then
     * this is unexpected so a warning will be logged.
     *
     * Every Evidence value goes in, including a value no element in the
     * Pipeline accepts, because what the cloud service uses changes over
     * time. The reserved names, and any value under the web request view's
     * key, are left out by addQueryData. The view itself is never in the
     * Evidence values.
     *
     * @return array<string, int|string>
     */
    public function getContent(FlowData $flowData): array
    {
        $queryData = [];

        $evidence = $flowData->evidence->getAllIncludingUnaccepted();

        // Add evidence in reverse alphabetical order, excluding special keys.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_OTHER));
        // Add cookie evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_COOKIE_PREFIX));
        // Add header evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_HTTPHEADER_PREFIX));

        // Add query evidence.
        return $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_QUERY_PREFIX));
    }

    /**
     * Add query data to the evidence.
     *
     * @param array<string, string> $queryData The destination Array to add query data to
     * @param array<string, int|string> $allEvidence All evidence in the flow data. This is used to report which evidence keys are conflicting
     * @param array<string, int|string> $evidence Evidence to add to the query Data
     * @return array<string, int|string>
     */
    public function addQueryData(array $queryData, array $allEvidence, array $evidence): array
    {
        foreach ($evidence as $evidenceKey => $evidenceValue) {
            // Split at the first dot only, as the .NET engine does, because
            // some evidence names have dots of their own, for example
            // 'query.id.usage', which is sent as 'id.usage'.
            $evidenceKeyParts = explode(Constants::EVIDENCE_SEPERATOR, $evidenceKey, 2);
            $prefix = strtolower($evidenceKeyParts[0]);
            // end() rather than [1], because a key with no dot at all
            // splits into one part and that part is both the prefix and
            // the name, which is what the .NET engine does with it too.
            $suffix = strtolower(end($evidenceKeyParts));

            // Evidence never supplies the request's own fields, and a value
            // under the web request view's key is never one to send.
            if (
                strtolower((string) $evidenceKey) === Evidence::HTTP_REQUEST_KEY ||
                in_array($suffix, Constants::RESERVED_EVIDENCE_SUFFIXES, true)
            ) {
                continue;
            }

            // Check and add the evidence to the query parameters.
            if (!array_key_exists($suffix, $queryData)) {
                $queryData[$suffix] = $evidenceValue;
            } else {
                // The queryParameter exists already.

                // Get the conflicting pieces of evidence and then log a
                // warning, if the evidence prefix is not query. Otherwise, a
                // warning is not needed as query evidence is expected
                // to overwrite any existing evidence with the same suffix.
                if (strcmp($prefix, Constants::EVIDENCE_QUERY_PREFIX) !== 0) {
                    $conflicts = [];
                    $conflictStr = '';

                    foreach ($allEvidence as $key => $value) {
                        if (strcasecmp($key, $evidenceKey) !== 0 && stripos($key, $suffix) !== false) {
                            $conflicts[$key] = $value;
                        }
                    }
                    $warningMessage = sprintf(Constants::WARNING_MESSAGE, $evidenceKey, $evidenceValue);

                    foreach ($conflicts as $key => $value) {
                        if (!empty($conflictStr)) {
                            $conflictStr .= ', ';
                        }
                        $conflictStr .= sprintf('%s=>%s', $key, $value);
                    }
                    if (!empty($conflictStr)) {
                        trigger_error($warningMessage . $conflictStr, E_USER_WARNING);
                    }
                }

                // Overwrite the existing queryParameter value.
                $queryData[$suffix] = $evidenceValue;
            }
        }

        return $queryData;
    }

    /**
     * Get evidence with specified prefix.
     *
     * @param array<string, int|string> $evidence All evidence in the flow data
     * @param string $type Required evidence key prefix
     * @return array<string, int|string>
     */
    public function getSelectedEvidence(array $evidence, string $type): array
    {
        $selectedEvidence = [];
        if (strcmp($type, Constants::EVIDENCE_OTHER) == 0) {
            foreach ($evidence as $key => $value) {
                if (
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_QUERY_PREFIX) &&
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_HTTPHEADER_PREFIX) &&
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_COOKIE_PREFIX)
                ) {
                    $selectedEvidence[$key] = $value;
                }
            }
            krsort($selectedEvidence);
        } else {
            foreach ($evidence as $key => $value) {
                if ($this->keyHasPrefix($key, $type)) {
                    $selectedEvidence[$key] = $value;
                }
            }
        }

        return $selectedEvidence;
    }

    /**
     * Check that the key of a KeyValuePair has the given prefix.
     *
     * @param string $itemKey Key to check
     * @param string $prefix The prefix to check for
     * @return bool true if the key has the prefix
     */
    public function keyHasPrefix(string $itemKey, string $prefix): bool
    {
        $key = explode(Constants::EVIDENCE_SEPERATOR, $itemKey);

        return strcasecmp($key[0], $prefix) === 0;
    }

    /**
     * Get properties for cloud engines from the cloud service.
     *
     * @return array<string, string|array<string, array<string, bool|string>>>
     */
    public function getEngineProperties(): array
    {
        // Get properties for all engines. A license key goes in the body of
        // a POST rather than in the URL, so it stays out of anything that
        // names the URL. A resource key on its own goes in the query string.

        $propertiesURL = $this->baseURL . 'accessibleProperties';

        if ($this->licenseKey === '') {
            $properties = $this->request(
                'GET',
                $propertiesURL . '?resource=' . $this->resourceKey
            );
        } else {
            $credentials = [];
            if ($this->resourceKey !== '') {
                $credentials[Constants::RESOURCE_PARAMETER] = $this->resourceKey;
            }
            $credentials[Constants::LICENSE_PARAMETER] = $this->licenseKey;
            $properties = $this->request(
                'POST',
                $propertiesURL,
                http_build_query($credentials)
            );
        }

        $properties = json_decode($properties, true);

        $properties = $this->lowerCaseArrayKeys($properties);

        $flowElementProperties = [];

        // Change indexes to be by name
        foreach ($properties['products'] as $dataKey => $elementProperties) {
            foreach ($elementProperties['properties'] as $index => $meta) {
                $flowElementProperties[$dataKey][strtolower($meta['name'])] = $meta;
            }
        }

        $this->flowElementProperties = $flowElementProperties;

        return $flowElementProperties;
    }

    /**
     * Fetch the evidence keys the cloud service lists.
     */
    private function loadEvidenceKeys(): void
    {
        $this->evidenceKeys = $this->getEvidenceKeys();
        $this->evidenceKeysLoaded = true;
    }

    /**
     * Internal function for getting evidence keys used by cloud engines.
     *
     * @return array<string> List of keys
     */
    private function getEvidenceKeys(): array
    {
        $evidenceKeyRequest = $this->request('GET', $this->baseURL . 'evidencekeys');

        return json_decode($evidenceKeyRequest, true);
    }

    /**
     * Makes a request to the cloud service. The cloud service's error text
     * can name the license key it was sent, so each of this engine's
     * license keys is masked in the message of any error the request
     * raises.
     *
     * @throws \Exception the error the request raised, with the keys masked
     */
    private function request(string $type, string $url, ?string $content = null): string
    {
        try {
            return $this->httpClient->makeCloudRequest(
                $type,
                $url,
                $content,
                $this->cloudRequestOrigin
            );
        } catch (\Exception $e) {
            $message = $this->maskLicenseKeys($e->getMessage());
            if ($message === $e->getMessage()) {
                throw $e;
            }
            if ($e instanceof CloudRequestException) {
                throw new CloudRequestException(
                    $message,
                    $e->httpStatusCode,
                    $e->responseHeaders
                );
            }

            // No previous exception, as its message holds the key.
            throw new \Exception($message, (int) $e->getCode());
        }
    }

    /**
     * The text with this engine's license key masked, and then each part
     * of the key between vertical bars, so that text naming one of several
     * joined keys is masked too.
     */
    private function maskLicenseKeys(string $text): string
    {
        if ($this->licenseKey === '') {
            return $text;
        }

        $keys = array_merge(
            [$this->licenseKey],
            explode(self::LICENSE_KEY_SEPARATOR, $this->licenseKey)
        );
        foreach ($keys as $key) {
            if ($key !== '') {
                $text = str_replace($key, LicenseKey::mask($key), $text);
            }
        }

        return $text;
    }

    /**
     * Internal helper method to lowercase keys returned from the
     * cloud service.
     *
     * @param array<int|string, array<string, mixed>|string> $arr
     * @return array<int|string, array<string, mixed>|string>
     */
    private function lowerCaseArrayKeys(array $arr): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                $item = $this->lowerCaseArrayKeys($item);
            }

            return $item;
        }, array_change_key_case($arr));
    }

    /**
     * Compares the requested properties with an answer and logs, once, the
     * names the answer left out. The cloud service drops a property the keys
     * do not cover without naming it, so only the engine can notice. A
     * property present but null counts as answered, because its nullreason
     * says why it has no value. The answer cannot change while the keys and
     * the list stay the same, so one comparison is enough.
     */
    private function reportPropertiesNotCovered(FlowData $flowData, string $result): void
    {
        if ($this->notCoveredReported || count($this->requestedProperties) === 0) {
            return;
        }

        $answer = json_decode($result, true);
        if (!is_array($answer)) {
            return;
        }

        $this->notCoveredReported = true;

        // The cloud service lower-cases the names in its answer.
        $answer = array_change_key_case($answer);
        $notCovered = [];
        foreach ($this->requestedProperties as $name) {
            $parts = explode(Constants::EVIDENCE_SEPERATOR, strtolower($name), 2);
            $section = $answer[$parts[0]] ?? null;
            if (
                count($parts) < 2 ||
                !is_array($section) ||
                !array_key_exists($parts[1], array_change_key_case($section))
            ) {
                $notCovered[] = $name;
            }
        }

        // Without a license key, a property no licence grants is answered
        // as null with its reason, so a name left out is not a property.
        if (count($notCovered) > 0) {
            $flowData->pipeline->log('warning', sprintf(
                $this->licenseKey === ''
                    ? Constants::MESSAGE_NOT_PROPERTIES
                    : Constants::MESSAGE_PROPERTIES_NOT_COVERED,
                implode(', ', $notCovered)
            ));
        }
    }

    /**
     * A key setting as given, or empty when it is absent, empty or only
     * whitespace, which all count as absent.
     *
     * @param mixed $value
     */
    private static function keySetting($value): string
    {
        $key = (string) ($value ?? '');

        return trim($key) === '' ? '' : $key;
    }

    /**
     * The requested properties setting as trimmed names, leaving out empty
     * ones.
     *
     * @param null|array<string>|string $value an array of names or one comma separated string
     * @return array<string>
     */
    private static function propertyNames($value): array
    {
        if ($value === null) {
            return [];
        }
        $names = [];
        foreach (is_string($value) ? explode(',', $value) : $value as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Refuses the two combinations the cloud service refuses on every
     * request, so a deployment sees one error when the engine is built
     * rather than a failure on each request.
     */
    private function refuseInvalidCredentials(): void
    {
        if ($this->resourceKey === '' && $this->licenseKey === '') {
            throw new \Exception(Constants::MESSAGE_NO_CREDENTIAL);
        }
        if ($this->resourceKey === '' && count($this->requestedProperties) === 0) {
            throw new \Exception(Constants::MESSAGE_LICENSE_KEY_NEEDS_PROPERTIES);
        }
    }
}
