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
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\engines\Engine;

/**
 * Engine that makes a call to the 51Degrees cloud service. Returns raw JSON
 * as a "json-response" property under the "cloud-response" dataKey.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/cloud-request-engine.md
 */
class CloudRequestEngine extends Engine
{
    // Joins license keys in one license key setting.
    private const LICENSE_KEY_SEPARATOR = '|';

    // Written in place of a value no message may hold.
    private const WITHHELD = '[withheld]';

    // Written in place of the License Key, or any part of it.
    private const HIDDEN = '(hidden)';

    // The fewest characters in a row from a withheld or hidden value that
    // are taken as part of it in a message.
    private const PART_LENGTH = 12;

    public string $dataKey = Constants::DATA_KEY;

    /**
     * @var array<string>
     */
    public array $deprecatedDataKeys = [Constants::DEPRECATED_DATA_KEY];

    /**
     * The metadata of the Element Data's one Property.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $properties = [
        Constants::JSON_RESPONSE_PROPERTY => [
            'name' => Constants::JSON_RESPONSE_PROPERTY,
            'type' => 'string',
            'description' => 'The raw JSON response body from the HTTP request.',
        ],
    ];

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

    // Set once a read by the Property's deprecated name has been logged.
    private bool $deprecatedPropertyReported = false;

    /**
     * @var array<string>
     */
    private array $evidenceKeys = [];
    private bool $evidenceKeysLoaded = false;
    // The failure of a fetch made for the filter, which processInternal
    // reports rather than fetching again.
    private ?\Throwable $evidenceKeysFailure = null;
    // Set once the accessible properties have been obtained.
    private bool $propertiesLoaded = false;
    // Set while the last request for the accessible properties failed.
    private bool $propertiesRequestFailed = false;
    private HttpClient $httpClient;
    private HumanConfidenceTransactions $humanConfidenceTransactions;
    /**
     * The values of the request being processed that no message may hold,
     * being every humanconfidence it carries, under any key, and the
     * transaction its call holds.
     *
     * @var array<string>
     */
    private array $secretsInFlight = [];

    /**
     * Warnings to log once the engine is added to a pipeline, which has the
     * logger.
     *
     * @var array<string>
     */
    private array $pendingWarnings = [];

    /**
     * Settings should contain a resourceKey, a licenseKey or both,
     * and optionally:
     * 1) requestedProperties, the fully qualified names of the properties
     *    to ask for, as an array or one comma separated string. Required
     *    with a licenseKey on its own. Beside a resourceKey the cloud
     *    service adds them to the properties the resource key carries.
     * 2) a cloudEndPoint, the address of the cloud service. When it is
     *    blank, the _51DEGREES_CLOUD_ENDPOINT environment variable is
     *    used, and when that is blank too, the default address. Blank
     *    means unset, empty or only whitespace.
     * 3) a cloudRequestOrigin to use when sending requests to cloud.
     * 4) discoveryResults, what getDiscoveryResults() gave after an earlier
     *    build with the same keys and cloudEndPoint, so that the engine
     *    asks the cloud service for nothing when it is built.
     *
     * Without discoveryResults the engine asks the cloud service for its
     * accessible properties and its accepted Evidence keys when it is
     * built. A 4xx answer, such as for a resource key the cloud service
     * does not accept, is thrown. Any other failure is logged as a warning
     * once the engine is in a pipeline, and the request is made again when
     * the engine is first used.
     *
     * The timeoutSeconds setting is the timeout of each request to the
     * cloud service, 2 seconds unless set. A given httpClient keeps its own
     * timeout unless the setting is given.
     *
     * The humanConfidenceTransactions setting keeps the transaction of each
     * page that asks for Human Confidence, and must be the one the pages
     * were made with. Unless set, it is one that keeps them in the default
     * folder.
     *
     * @param array{
     *     resourceKey?: string,
     *     licenseKey?: string,
     *     requestedProperties?: array<string>|string,
     *     timeoutSeconds?: float|int|string,
     *     cloudEndPoint?: string,
     *     cloudRequestOrigin?: string,
     *     discoveryResults?: array<string, mixed>,
     *     httpClient?: \fiftyone\pipeline\cloudrequestengine\HttpClient,
     *     humanConfidenceTransactions?: \fiftyone\pipeline\cloudrequestengine\HumanConfidenceTransactions
     * } $settings
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     *   for a 4xx answer to a discovery request
     * @throws \Exception for keys, requested properties or discovery
     *   results the engine cannot be built with
     */
    public function __construct(array $settings)
    {
        $this->resourceKey = self::keySetting($settings['resourceKey'] ?? null);
        $this->licenseKey = self::keySetting($settings['licenseKey'] ?? null);
        $this->requestedProperties = self::propertyNames($settings['requestedProperties'] ?? null);
        $this->refuseInvalidCredentials();

        $this->baseURL = self::cloudAddress($settings['cloudEndPoint'] ?? null);

        $this->httpClient = $settings['httpClient'] ?? new HttpClient();
        $timeoutSeconds = self::timeoutSetting($settings['timeoutSeconds'] ?? null);
        if ($timeoutSeconds !== null) {
            $this->httpClient->setTimeoutSeconds($timeoutSeconds);
        }
        $this->cloudRequestOrigin = $settings['cloudRequestOrigin'] ?? null;
        $this->humanConfidenceTransactions = $settings['humanConfidenceTransactions']
            ?? new HumanConfidenceTransactions();

        parent::__construct();

        if (isset($settings['discoveryResults'])) {
            $this->useDiscoveryResults($settings['discoveryResults']);
        } else {
            $this->discover();
        }
    }

    /**
     * Logs, as warnings through the logger of the first pipeline the
     * engine is added to, anything the engine had to warn about while it
     * was built. Each engine logs these once, so a site that
     * builds its pipeline in every request logs them in every request.
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        $warnings = $this->pendingWarnings;
        $this->pendingWarnings = [];
        foreach ($warnings as $warning) {
            $pipeline->log('warning', $warning);
        }
    }

    /**
     * The discovery results, being the accessible properties and the
     * accepted Evidence keys, which depend only on the keys and the cloud
     * endpoint. Pass them as the discoveryResults setting to build an
     * engine with the same keys and endpoint without asking the cloud
     * service. They hold no key, and can be stored as JSON. Null until
     * both have been obtained.
     *
     * @return null|array{credentials: string, evidenceKeys: array<string>, accessibleProperties: array<string, array<string, array<string, mixed>>>}
     */
    public function getDiscoveryResults(): ?array
    {
        if (!$this->propertiesLoaded || !$this->evidenceKeysLoaded) {
            return null;
        }

        return [
            'credentials' => $this->credentialsHash(),
            'evidenceKeys' => $this->evidenceKeys,
            'accessibleProperties' => $this->flowElementProperties,
        ];
    }

    /**
     * Whether the accessible properties have been obtained, so that
     * flowElementProperties lists every product the keys give.
     */
    public function propertiesLoaded(): bool
    {
        return $this->propertiesLoaded;
    }

    /**
     * Whether the accessible properties have not been obtained because the
     * last request for them failed.
     */
    public function propertiesRequestFailed(): bool
    {
        return !$this->propertiesLoaded && $this->propertiesRequestFailed;
    }

    /**
     * Instance of EvidenceKeyFilter of the evidence keys the cloud service
     * lists. The keys are asked for when the engine is built. Where that
     * failed, they are asked for again the first time they are needed,
     * which is when the first Evidence is set, so that the Evidence the
     * cloud service uses is kept. A failed request leaves the list empty,
     * and processInternal reports the failure.
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
     *
     * @throws \Exception when the request to the cloud service, or the
     *   request for the Evidence keys it accepts, fails, with the keys
     *   masked
     */
    public function processInternal(FlowData $flowData): void
    {
        if (!$this->propertiesLoaded) {
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
        $sealedResult = SealedHumanConfidence::in($flowData);
        // Every humanconfidence, under any key, is withheld from messages,
        // and set now, so the warning about conflicting Evidence withholds
        // it too.
        $humanConfidences = SealedHumanConfidence::valuesIn($flowData);
        $this->secretsInFlight = $humanConfidences;
        try {
            $fields = $this->getContent($flowData);
        } finally {
            $this->secretsInFlight = [];
        }
        $requested = $this->requestedProperties;
        $transaction = null;
        $sealedResultSent = false;
        if ($sealedResult !== null) {
            $sealedResultSent = $this->redeemFields($flowData, $fields, $requested, $transaction);
        } elseif (SealedHumanConfidence::elsewhereIn($flowData)) {
            self::leaveOutUnredeemable($fields);
        }
        if ($this->resourceKey !== '') {
            $fields[Constants::RESOURCE_PARAMETER] = $this->resourceKey;
        }
        if ($this->licenseKey !== '') {
            $fields[Constants::LICENSE_PARAMETER] = $this->licenseKey;
        }
        if (count($requested) > 0) {
            $fields[Constants::VALUES_PARAMETER] = implode(',', $requested);
        }

        $content = http_build_query($fields);

        $this->secretsInFlight = $transaction === null
            ? $humanConfidences
            : array_merge($humanConfidences, [$transaction]);
        try {
            $result = $this->request('POST', $url, $content);
        } finally {
            $this->secretsInFlight = [];
        }

        $data = new CloudRequestData($this, $result, $sealedResultSent);

        $flowData->setElementData($data);

        // The configured list alone, so a name added for a sealed result
        // is never reported.
        $this->reportPropertiesNotCovered($flowData, $result);
    }

    /**
     * A request that carries a sealed Human Confidence result is neither
     * served from nor stored in the cache, because a sealed result redeems
     * once. Nor is one that carries a humanconfidence under another key,
     * which is never sent, so that it's never kept in a cache key either.
     * Every other request is processed as before.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/caching.md#values-created-for-one-request
     *
     * @throws \Exception as processInternal does
     */
    public function process(FlowData $flowData): void
    {
        if (SealedHumanConfidence::in($flowData) !== null || SealedHumanConfidence::elsewhereIn($flowData)) {
            $this->processInternal($flowData);

            return;
        }

        parent::process($flowData);
    }

    /**
     * Makes the fields of a call that carries a sealed result into the
     * fields that redeem it, and says whether the call holds the sealed
     * result.
     *
     * No transaction from the request goes in the call, under either name,
     * and nor does the page id, which only the package uses. The sealed
     * result goes in only with a License Key, as the remote server redeems
     * nothing without one, and only with the transaction kept for the
     * request's page id, which goes in as transaction. Where no transaction
     * is kept for the page, as when the request has no page id, an unknown
     * one, or one whose transaction was taken or has expired, the sealed
     * result is left out and reads Medium. A call that holds the sealed
     * result names derived.HumanConfidence.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#redeeming-through-the-json-call
     *
     * @param array<string, int|string> $fields The call's fields so far
     * @param array<string> $requested The requested properties of this call
     * @param null|string $transaction Set to the transaction the call holds
     */
    private function redeemFields(
        FlowData $flowData,
        array &$fields,
        array &$requested,
        ?string &$transaction
    ): bool {
        $transaction = null;
        // The kept transaction is taken only where it is sent, so a request
        // without a License Key leaves it for the page.
        if ($this->licenseKey !== '') {
            $transaction = $this->takeTransaction(SealedHumanConfidence::pageIn($flowData));
        }
        foreach (array_keys($fields) as $name) {
            $name = (string) $name;
            if (
                SealedHumanConfidence::isTransactionField($name) ||
                strcasecmp($name, SealedHumanConfidence::PAGE_FIELD) === 0 ||
                ($transaction === null && strcasecmp($name, SealedHumanConfidence::FIELD) === 0)
            ) {
                unset($fields[$name]);
            }
        }
        if ($transaction === null) {
            return false;
        }

        $fields[SealedHumanConfidence::TRANSACTION_FIELD] = $transaction;
        foreach ($requested as $name) {
            if (strcasecmp($name, SealedHumanConfidence::REQUESTED_PROPERTY) === 0) {
                return true;
            }
        }
        $requested[] = SealedHumanConfidence::REQUESTED_PROPERTY;

        return true;
    }

    /**
     * The transaction kept for the page id, taken from the store so it
     * redeems once, or null where none is kept. The store's take() is
     * private, so a consumer has no accessor for a transaction, and the
     * engine calls it in the store's own scope.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#what-a-package-never-exposes
     */
    private function takeTransaction(?string $pageId): ?string
    {
        $take = \Closure::bind(
            static fn (HumanConfidenceTransactions $store, ?string $id): ?string => $store->take($id),
            null,
            HumanConfidenceTransactions::class
        );

        return $take($this->humanConfidenceTransactions, $pageId);
    }

    /**
     * Leaves humanconfidence, and any transaction or challenge, out of a
     * call whose request holds a humanconfidence under a key other than
     * query.humanconfidence. Only that key carries a sealed result, so the
     * value is never redeemed, and never with a transaction the request
     * chose.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#redeeming-through-the-json-call
     *
     * @param array<string, int|string> $fields The call's fields so far
     */
    private static function leaveOutUnredeemable(array &$fields): void
    {
        foreach (array_keys($fields) as $name) {
            $name = (string) $name;
            if (
                SealedHumanConfidence::isTransactionField($name) ||
                strcasecmp($name, SealedHumanConfidence::FIELD) === 0
            ) {
                unset($fields[$name]);
            }
        }
    }

    /**
     * A response whose JSON has a top-level member named fodid, in any case,
     * holds a 51Did created for the one request.
     */
    protected function holdsValueForOneRequest(ElementData $data): bool
    {
        $json = json_decode((string) CloudRequestData::jsonResponseOf($data), true);

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
     * Logs, as a warning through the logger of the first pipeline the
     * engine is added to, that its Element Data was read by the Property's
     * deprecated name. Each engine logs it once. CloudRequestData calls it.
     */
    public function reportDeprecatedProperty(): void
    {
        if ($this->deprecatedPropertyReported || count($this->pipelines) === 0) {
            return;
        }

        $this->deprecatedPropertyReported = true;
        $this->pipelines[0]->log('warning', sprintf(
            Constants::MESSAGE_PROPERTY_DEPRECATED,
            Constants::DEPRECATED_JSON_RESPONSE_PROPERTY,
            Constants::JSON_RESPONSE_PROPERTY
        ));
    }

    /**
     * Generate the Content to send in the POST request. One name under
     * several prefixes is sent once, with the value of the prefix that
     * evidence.md ranks highest, being query, header, cookie, server,
     * fiftyone and location, then any other prefix alphabetically. The
     * groups are added lowest precedence first, so a later value replaces
     * an earlier one, and a replacement by a key other than a query key
     * is raised as a warning.
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

        // Add the other evidence lowest precedence first.
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
            // the name, which is what the .NET engine does with it too. The
            // name keeps the letter case of the key, as .NET sends it.
            $suffix = (string) end($evidenceKeyParts);

            // Evidence never supplies the request's own fields, and a value
            // under the web request view's key is never one to send.
            if (
                strtolower((string) $evidenceKey) === Evidence::HTTP_REQUEST_KEY ||
                in_array(strtolower($suffix), Constants::RESERVED_EVIDENCE_SUFFIXES, true)
            ) {
                continue;
            }

            // Evidence keys ignore letter case, so a name already added in
            // other letters is the same name, and the later, higher
            // precedence value replaces it under its own spelling.
            $existing = null;
            foreach (array_keys($queryData) as $name) {
                if (strcasecmp((string) $name, $suffix) === 0) {
                    $existing = $name;

                    break;
                }
            }

            // Check and add the evidence to the query parameters.
            if ($existing === null) {
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
                            $conflicts[$key] = self::loggable((string) $key, $value);
                        }
                    }
                    $warningMessage = sprintf(
                        Constants::WARNING_MESSAGE,
                        $evidenceKey,
                        self::loggable((string) $evidenceKey, $evidenceValue)
                    );

                    foreach ($conflicts as $key => $value) {
                        if (!empty($conflictStr)) {
                            $conflictStr .= ', ';
                        }
                        $conflictStr .= sprintf('%s=>%s', $key, $value);
                    }
                    if (!empty($conflictStr)) {
                        // An Evidence value can hold the License Key.
                        trigger_error(
                            $this->maskLicenseKeys($warningMessage . $conflictStr),
                            E_USER_WARNING
                        );
                    }
                }

                // Replace the existing field, name and value, so the name
                // sent is the winning key's.
                unset($queryData[$existing]);
                $queryData[$suffix] = $evidenceValue;
            }
        }

        return $queryData;
    }

    /**
     * An Evidence value as a warning may show it. The value of a sealed
     * Human Confidence result, or of a transaction under either name, is
     * withheld, as no log entry may hold either.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#what-a-package-never-exposes
     *
     * @param int|string $value
     * @return int|string
     */
    private static function loggable(string $evidenceKey, $value)
    {
        $parts = explode(Constants::EVIDENCE_SEPERATOR, $evidenceKey, 2);
        $name = (string) end($parts);

        return strcasecmp($name, SealedHumanConfidence::FIELD) === 0 ||
            SealedHumanConfidence::isTransactionField($name)
            ? self::WITHHELD
            : $value;
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
            // Lowest precedence first, so the value added last wins.
            uksort($selectedEvidence, function ($a, $b): int {
                return self::compareOtherKeys((string) $b, (string) $a);
            });
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
     * @throws \Exception the error the request for the properties raised,
     *   with the keys masked
     */
    public function getEngineProperties(): array
    {
        try {
            $flowElementProperties = $this->requestEngineProperties();
        } catch (\Throwable $e) {
            $this->propertiesRequestFailed = true;

            throw $e;
        }

        $this->flowElementProperties = $flowElementProperties;
        $this->propertiesLoaded = true;
        $this->propertiesRequestFailed = false;

        return $flowElementProperties;
    }

    /**
     * Asks the cloud service for the accessible properties, keyed by Data
     * Key and then by lower case property name.
     *
     * @return array<string, array<string, array<string, bool|string>>>
     */
    private function requestEngineProperties(): array
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

        return $flowElementProperties;
    }

    /**
     * Asks the cloud service for the accessible properties and the
     * accepted Evidence keys. A 4xx answer is thrown, as the same keys can
     * never succeed. Any other failure, such as a request that could not be
     * made, a 5xx answer or an answer that could not be read, is logged as
     * a warning, and the request is made again when the engine is first
     * used.
     *
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     */
    private function discover(): void
    {
        try {
            $this->getEngineProperties();
        } catch (\Throwable $e) {
            $this->deferDiscovery('accessible properties', $e);
        }

        try {
            $this->loadEvidenceKeys();
        } catch (\Throwable $e) {
            $this->deferDiscovery('accepted Evidence keys', $e);
        }
    }

    /**
     * Throws a 4xx answer to a discovery request, and warns of any other
     * failure, leaving the request to be made on first use.
     *
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     */
    private function deferDiscovery(string $asked, \Throwable $failure): void
    {
        if (
            $failure instanceof CloudRequestException &&
            $failure->httpStatusCode >= 400 &&
            $failure->httpStatusCode < 500
        ) {
            throw $failure;
        }

        $this->warn(sprintf(
            Constants::MESSAGE_DISCOVERY_DEFERRED,
            $asked,
            $failure->getMessage()
        ));
    }

    /**
     * Uses the discovery results getDiscoveryResults() gave, refusing
     * anything else and results made for other keys or another endpoint.
     *
     * @param mixed $results
     * @throws \Exception
     */
    private function useDiscoveryResults($results): void
    {
        if (
            !is_array($results) ||
            !is_string($results['credentials'] ?? null) ||
            !is_array($results['evidenceKeys'] ?? null) ||
            !is_array($results['accessibleProperties'] ?? null)
        ) {
            throw new \Exception(Constants::MESSAGE_DISCOVERY_RESULTS_NOT_A_COPY);
        }
        foreach ($results['evidenceKeys'] as $key) {
            if (!is_string($key)) {
                throw new \Exception(Constants::MESSAGE_DISCOVERY_RESULTS_NOT_A_COPY);
            }
        }
        if (!hash_equals($this->credentialsHash(), $results['credentials'])) {
            throw new \Exception(Constants::MESSAGE_DISCOVERY_RESULTS_OTHER_CREDENTIALS);
        }

        $this->evidenceKeys = array_values($results['evidenceKeys']);
        $this->evidenceKeysLoaded = true;
        $this->flowElementProperties = $results['accessibleProperties'];
        $this->propertiesLoaded = true;
    }

    /**
     * Stands for the cloud endpoint and the keys in the discovery results,
     * so that results made for others are refused, without holding a key.
     */
    private function credentialsHash(): string
    {
        return hash(
            'sha256',
            $this->baseURL . "\n" . $this->resourceKey . "\n" . $this->licenseKey
        );
    }

    /**
     * Logs a warning through the logger of the first pipeline the engine
     * is in, or once it is added to one.
     */
    private function warn(string $message): void
    {
        if (count($this->pipelines) > 0) {
            $this->pipelines[0]->log('warning', $message);

            return;
        }

        $this->pendingWarnings[] = $message;
    }

    /**
     * Logs each entry of the warnings array in a response as a warning.
     *
     * @param array<string> $warnings
     */
    private function logCloudWarnings(array $warnings): void
    {
        foreach ($warnings as $warning) {
            $this->warn(sprintf(
                Constants::MESSAGE_CLOUD_WARNING,
                $this->maskLicenseKeys((string) $warning)
            ));
        }
    }

    /**
     * The entries of the warnings array in a successful response. Most
     * responses have none, so only one that names the array is decoded.
     *
     * @return array<string>
     */
    private static function warningsIn(string $body): array
    {
        if (stripos($body, '"warnings"') === false) {
            return [];
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['warnings']) || !is_array($json['warnings'])) {
            return [];
        }

        $warnings = [];
        foreach ($json['warnings'] as $warning) {
            $warnings[] = is_scalar($warning)
                ? (string) $warning
                : (string) json_encode($warning);
        }

        return $warnings;
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
     * Makes a request to the cloud service, logging each entry of the
     * warnings array in its response as a warning. The cloud service's
     * text can name the license key it was sent, so each of this engine's
     * license keys is masked in the warnings, and in the message and errors
     * of any error the request raises.
     *
     * @throws \Exception the error the request raised, with the keys masked
     */
    private function request(string $type, string $url, ?string $content = null): string
    {
        try {
            $body = $this->httpClient->makeCloudRequest(
                $type,
                $url,
                $content,
                $this->cloudRequestOrigin
            );
        } catch (CloudRequestException $e) {
            $this->logCloudWarnings($e->warnings);
            $mask = fn (string $text): string => $this->maskLicenseKeys($text);
            $message = $mask($e->getMessage());
            $errors = array_map($mask, $e->errors);
            $warnings = array_map($mask, $e->warnings);
            if (
                $message === $e->getMessage() &&
                $errors === $e->errors &&
                $warnings === $e->warnings
            ) {
                throw $e;
            }

            // No previous exception, as its message holds the key.
            throw new CloudRequestException(
                $message,
                $e->httpStatusCode,
                $e->responseHeaders,
                $errors,
                $warnings
            );
        } catch (\Exception $e) {
            $message = $this->maskLicenseKeys($e->getMessage());
            if ($message === $e->getMessage()) {
                throw $e;
            }

            // No previous exception, as its message holds the key.
            throw new \Exception($message, (int) $e->getCode());
        }

        $this->logCloudWarnings(self::warningsIn($body));

        return $body;
    }

    /**
     * The text with this engine's License Key hidden whole, and each part
     * of the key between vertical bars, so that text naming one of several
     * joined keys is hidden too. Every humanconfidence the request carries,
     * under any key, and the transaction of the call being made are
     * withheld.
     *
     * None of these is ever written in whole or in part, so each is
     * replaced outright rather than masked to its last characters, as it
     * is and as it would be sent in a form body or address, or written in
     * JSON. A key the HTTP client already masked to its last characters, as
     * it masks a license= value, is hidden the same way. The longest value
     * is replaced first, and nothing replaced is looked at again, so a
     * sealed result that is part of the key can't stop the key being
     * hidden. Then any run of PART_LENGTH or more characters taken from one
     * of them, such as a cut short copy in a cloud service message, is
     * replaced too.
     *
     * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/human-confidence.md#what-a-package-never-exposes
     */
    private function maskLicenseKeys(string $text): string
    {
        $hidden = [];
        foreach ($this->secretsInFlight as $secret) {
            foreach (self::writtenForms($secret) as $form) {
                $hidden[$form] = self::WITHHELD;
            }
        }
        if ($this->licenseKey !== '') {
            $keys = array_merge(
                [$this->licenseKey],
                explode(self::LICENSE_KEY_SEPARATOR, $this->licenseKey)
            );
            foreach ($keys as $key) {
                foreach (self::writtenForms($key) as $form) {
                    $hidden[$form] = self::HIDDEN;
                }
            }
        }
        if (count($hidden) === 0) {
            return $text;
        }
        // The masked form is mostly asterisks, so it's hidden only whole.
        $whole = $hidden;
        if ($this->licenseKey !== '') {
            $keys = array_merge(
                [$this->licenseKey],
                explode(self::LICENSE_KEY_SEPARATOR, $this->licenseKey)
            );
            foreach ($keys as $key) {
                if ($key !== '') {
                    $whole[LicenseKey::mask($key)] = self::HIDDEN;
                }
            }
        }

        // strtr tries the longest value first at each place in the text, and
        // never looks again at what it has written.
        return self::hideParts(strtr($text, $whole), $hidden);
    }

    /**
     * A value as it is, and as it is written in a form body or an address,
     * and inside a JSON string, so that a message that echoes it in any of
     * these forms is caught. Empty for an empty value.
     *
     * @return array<string>
     */
    private static function writtenForms(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $forms = [$value, rawurlencode($value), urlencode($value)];
        foreach ([0, JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE] as $flags) {
            $json = json_encode($value, $flags);
            if (is_string($json)) {
                $forms[] = substr($json, 1, -1);
            }
        }

        return array_values(array_unique($forms));
    }

    /**
     * The text with each run of characters that holds PART_LENGTH or more
     * characters in a row from one of the values replaced by that value's
     * token, so a part of a value is hidden as the whole is. A value
     * shorter than PART_LENGTH is hidden only whole.
     *
     * @param array<string, string> $tokens Each value and its token, the
     *   License Key's (hidden) taking precedence
     */
    private static function hideParts(string $text, array $tokens): string
    {
        $windows = [];
        foreach ([self::WITHHELD, self::HIDDEN] as $token) {
            foreach ($tokens as $value => $valueToken) {
                $value = (string) $value;
                if ($valueToken !== $token || strlen($value) < self::PART_LENGTH) {
                    continue;
                }
                for ($i = 0; $i + self::PART_LENGTH <= strlen($value); ++$i) {
                    $windows[substr($value, $i, self::PART_LENGTH)] = $token;
                }
            }
        }
        $length = strlen($text);
        if (count($windows) === 0 || $length < self::PART_LENGTH) {
            return $text;
        }

        // The token covering each character, where one does.
        $covered = [];
        for ($i = 0; $i + self::PART_LENGTH <= $length; ++$i) {
            $token = $windows[substr($text, $i, self::PART_LENGTH)] ?? null;
            if ($token === null) {
                continue;
            }
            for ($j = $i; $j < $i + self::PART_LENGTH; ++$j) {
                if (!isset($covered[$j]) || $token === self::HIDDEN) {
                    $covered[$j] = $token;
                }
            }
        }
        if (count($covered) === 0) {
            return $text;
        }

        $result = '';
        $last = null;
        for ($i = 0; $i < $length; ++$i) {
            $token = $covered[$i] ?? null;
            if ($token === null) {
                $result .= $text[$i];
            } elseif ($token !== $last) {
                $result .= $token;
            }
            $last = $token;
        }

        return $result;
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
     * The address of the cloud service, ending with a slash. The
     * cloudEndPoint setting comes first, then the
     * _51DEGREES_CLOUD_ENDPOINT environment variable, then the default.
     * A value that is absent, empty or only whitespace counts as blank and
     * passes to the next, and any other value is used as given.
     *
     * @param string|null $setting The cloudEndPoint setting
     */
    private static function cloudAddress(?string $setting): string
    {
        $address = $setting ?? '';
        if (trim($address) === '') {
            $address = (string) getenv(Constants::CLOUD_ENDPOINT_VARIABLE);
        }
        if (trim($address) === '') {
            $address = Constants::BASE_URL_DEFAULT;
        }

        return substr($address, -1) === '/' ? $address : $address . '/';
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
     * The timeoutSeconds setting in seconds, or null when it is absent or
     * empty.
     *
     * @param mixed $value
     * @throws \InvalidArgumentException when the value is not a number
     *   greater than 0
     */
    private static function timeoutSetting($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new \InvalidArgumentException(sprintf(
                Constants::MESSAGE_TIMEOUT_NOT_POSITIVE,
                var_export($value, true)
            ));
        }

        return (float) $value;
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

    /**
     * Compares two Evidence keys that are not query, header or cookie
     * Evidence by precedence. server, fiftyone and location come first, in
     * the order evidence.md lists them, then any other prefix
     * alphabetically, all ignoring letter case.
     *
     * @param string $a the first Evidence key
     * @param string $b the second Evidence key
     * @return int negative where $a has the higher precedence
     */
    private static function compareOtherKeys(string $a, string $b): int
    {
        $prefixA = strtolower(explode(Constants::EVIDENCE_SEPERATOR, $a, 2)[0]);
        $prefixB = strtolower(explode(Constants::EVIDENCE_SEPERATOR, $b, 2)[0]);
        $byRank = self::otherPrefixRank($prefixA) - self::otherPrefixRank($prefixB);
        if ($byRank !== 0) {
            return $byRank;
        }
        $byPrefix = strcmp($prefixA, $prefixB);
        if ($byPrefix !== 0) {
            return $byPrefix;
        }

        return strcasecmp($a, $b);
    }

    /**
     * The rank of a lower case prefix that is not query, header or cookie,
     * where a lower rank wins.
     *
     * @param string $prefix the prefix in lower case
     * @return int the rank
     */
    private static function otherPrefixRank(string $prefix): int
    {
        $rank = array_search($prefix, Constants::OTHER_PREFIX_PRECEDENCE, true);

        return $rank === false
            ? count(Constants::OTHER_PREFIX_PRECEDENCE)
            : (int) $rank;
    }
}
