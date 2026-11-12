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

namespace fiftyone\pipeline\ipintelligence;

// Pull in the SWIG generated wrapper for the IP intelligence native engine.
// The wrapper is generated per PHP major version under on-premise/src/phpN.
$fiftyoneIpiSwigWrapper = __DIR__ . '/../on-premise/src/php' . explode('.', PHP_VERSION)[0] . '/FiftyOneDegreesIpIntelligenceEngine.php';
if (file_exists($fiftyoneIpiSwigWrapper)) {
    include_once $fiftyoneIpiSwigWrapper;
}

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\DataKeyedCache;
use fiftyone\pipeline\engines\Engine;

/**
 * The on-premise IP intelligence engine uses a local data file (.ipi) to
 * generate a set of properties about an IP address supplied as evidence.
 * It wraps the native IpIntelligenceEngineModule produced by SWIG from
 * 51degrees-cxx.
 *
 * Mirrors DeviceDetectionOnPremise in device-detection/onpremise and
 * IpIntelligenceOnPremise in 51degrees-python.
 */
class IpIntelligenceOnPremise extends Engine
{
    /**
     * Settings that only apply when this class builds its own engine.
     *
     * @var array<string>
     */
    private const ENGINE_SETTINGS = ['performanceProfile', 'restrictedProperties', 'concurrency'];

    public string $dataKey = Constants::ELEMENT_DATA_KEY;

    public $engine;

    /**
     * The synthetic properties added by this engine. The IP address supplied
     * as evidence is echoed back in the results (ip-intelligence-dotnet
     * parity).
     */
    private const METRIC_PROPERTIES = [
        'ip' => [
            'name' => 'Ip',
            'type' => 'IpAddress',
            'category' => 'Network',
            'description' => Constants::IP_DESCRIPTION,
            'component' => 'Network'
        ],
        'ipv6' => [
            'name' => 'IpV6',
            'type' => 'IpAddress',
            'category' => 'Network',
            'description' => Constants::IPV6_DESCRIPTION,
            'component' => 'Network'
        ]
    ];

    private $config;

    /**
     * @var array<string>
     */
    private array $evidenceKeys = [];

    /**
     * Each name the engine was built with, keyed by the lowercase name, as
     * the engine gives it.
     *
     * @var null|array<string, string>
     */
    private ?array $requiredNames = null;

    /**
     * The name, type and category of each property read so far, keyed by
     * the lowercase name.
     *
     * @var array<string, array<string, string>>
     */
    private array $readProperties = [];

    /**
     * Uses the engine the extension builds once, when PHP starts, from the
     * FiftyOneDegreesIpIntelligenceEngine.* php.ini settings. Set
     * FiftyOneDegreesIpIntelligenceEngine.data_file to the .ipi data file and
     * pass no settings. That engine always holds the whole data file in
     * memory, so a performance_profile ini setting other than
     * Constants::STARTUP_PERFORMANCE_PROFILE is rejected, as are
     * ENGINE_SETTINGS.
     *
     * @param array<string, mixed> $settings
     *   dataFile            - (optional) path to an .ipi data file to build
     *                         a new engine from, in place of the one built
     *                         when PHP starts. Each construction opens the
     *                         data file again and reads as much of it into
     *                         memory as the performance profile asks for,
     *                         the whole file with MaxPerformance. A web
     *                         server constructs this class in every request,
     *                         so the data file is loaded in every request.
     *   performanceProfile  - (optional, with dataFile) one of
     *                         Constants::PERFORMANCE_PROFILES, defaulting to
     *                         Constants::DEFAULT_PERFORMANCE_PROFILE.
     *   restrictedProperties- (optional, with dataFile) list of properties
     *                         to populate.
     *   concurrency         - (optional, with dataFile) expected concurrent
     *                         accesses.
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->pipelines = [];

        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            throw new \Exception(Messages::EXTENSION_NOT_LOADED);
        }

        // Reusing the global engine lets a long-running SAPI avoid re-opening
        // a multi-gigabyte data file on every request.
        $dataFile = $settings['dataFile'] ?? null;
        if ($dataFile === null && function_exists('ipi_engine_get') && \ipi_engine_get() !== null) {
            // The global engine is always built with MaxPerformance (see
            // on-premise/ipi-php.i), so another php.ini profile is a mistake
            // and is refused rather than ignored.
            $startupProfile = ini_get('FiftyOneDegreesIpIntelligenceEngine.performance_profile');
            if ($startupProfile !== false
                && $startupProfile !== ''
                && strcasecmp($startupProfile, Constants::STARTUP_PERFORMANCE_PROFILE) !== 0) {
                throw new \Exception(sprintf(Messages::PERFORMANCE_PROFILE_ERROR, $startupProfile));
            }

            // Only a setting with an actual value counts: callers commonly pass
            // options straight through as ['performanceProfile' => $profile ?? null],
            // where a null supplies nothing to apply.
            $supplied = array_keys(array_filter($settings, static fn ($value) => $value !== null));
            $rejected = array_values(array_intersect(self::ENGINE_SETTINGS, $supplied));
            if ($rejected !== []) {
                throw new \Exception(sprintf(Messages::GLOBAL_ENGINE_SETTINGS_REJECTED, implode(', ', $rejected)));
            }

            $this->engine = \ipi_engine_get();
            $this->initEngineMetaData();
            parent::__construct();

            return;
        }

        if (!$dataFile) {
            throw new \Exception(Messages::NO_STARTUP_ENGINE);
        }

        if (substr($dataFile, -strlen(Constants::DATA_FILE_EXTENSION)) !== Constants::DATA_FILE_EXTENSION) {
            throw new \Exception(sprintf(Messages::DATA_FILE_EXTENSION_ERROR, Constants::DATA_FILE_EXTENSION));
        }

        if (!file_exists($dataFile)) {
            throw new \Exception(sprintf(Messages::DATA_FILE_NOT_FOUND, $dataFile));
        }

        $performanceProfile = $settings['performanceProfile'] ?? Constants::DEFAULT_PERFORMANCE_PROFILE;
        if (!in_array($performanceProfile, Constants::PERFORMANCE_PROFILES, true)) {
            throw new \Exception(sprintf(
                Messages::INVALID_PERFORMANCE_PROFILE,
                $performanceProfile,
                implode(', ', Constants::PERFORMANCE_PROFILES)
            ));
        }

        $restrictedProperties = $settings['restrictedProperties'] ?? [];
        $concurrency = $settings['concurrency'] ?? null;

        // Build the required properties configuration.
        $propertiesVector = new \VectorStringSwig();
        foreach ($restrictedProperties as $property) {
            $propertiesVector->push($property);
        }
        if (!empty($restrictedProperties)) {
            $this->setRestrictedProperties($restrictedProperties);
        }
        $propertiesConfig = new \RequiredPropertiesConfigSwig($propertiesVector);

        // Assemble the engine configuration.
        $this->config = new \ConfigIpiSwig();
        $this->applyPerformanceProfile($this->config, $performanceProfile);
        // Do not prefix evidence keys with http_.
        $this->config->setUseUpperPrefixHeaders(false);
        if ($concurrency !== null) {
            $this->config->setConcurrency((int) $concurrency);
        }

        // Build the native engine.
        $this->engine = new \EngineIpiSwig($dataFile, $this->config, $propertiesConfig);

        $this->initEngineMetaData();

        parent::__construct();
    }

    /**
     * The accepted evidence keys (query.client-ip, server.client-ip,
     * query.client-ip-51d, ...). Note that this engine performs a single-IP
     * lookup: only the first parseable IPv4/IPv6 value from the evidence (in
     * the priority order used by getClientIps) actually drives the lookup.
     * This is not multi-evidence detection.
     */
    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter($this->evidenceKeys);
    }

    public function processInternal(FlowData $flowData): void
    {
        if ($this->engine === null) {
            throw new \Exception(Messages::ENGINE_CLOSED);
        }

        [$ipv4, $ipv6] = $this->getClientIps($flowData);

        // Look up the supplied client IP directly via the engine's string
        // overload. We deliberately do NOT build an EvidenceIpi map: the
        // native EvidenceBase derives from std::map but adds virtual methods,
        // which shifts the std::map sub-object off offset zero, and the SWIG
        // PHP binding does not adjust the pointer when dispatching the
        // inherited map setter - inserting into it corrupts memory. IP
        // intelligence is a single-IP lookup, so the process(ipAddress)
        // overload is both safe and the natural API here.
        $lookupIp = $ipv4 ?? $ipv6 ?? '';

        $result = $this->engine->process($lookupIp);

        $data = new SwigData($this, $result, $ipv4, $ipv6);

        $flowData->setElementData($data);
    }

    /**
     * Release the native engine this element built from its dataFile
     * setting. Its data is freed once no results read from it are still
     * held, so close every FlowData it processed as well. The shared global
     * engine belongs to the extension and stays loaded. Closing the Pipeline
     * closes the element.
     */
    public function close(): void
    {
        $this->engine = null;
    }

    /**
     * The on-premise engine manages its own internal cache and does not
     * support an external cache.
     *
     * @throws \Exception
     */
    public function setCache(?DataKeyedCache $cache): void
    {
        throw new \Exception(Messages::CACHE_ERROR);
    }

    /**
     * Ask the engine to start using the specified data file for lookups.
     *
     * @param null|string $fileName Path to a new data file, or null to
     *   reload the existing one from disk.
     */
    public function refreshData(?string $fileName = null): void
    {
        if ($fileName === null) {
            $this->engine->refreshData();
        } else {
            $this->engine->refreshData($fileName);
        }

        // The metadata follows the new data file.
        $this->initEngineMetaData();
    }

    /**
     * The product (data tier) of the loaded data file. Used by the missing
     * property service.
     */
    public function getProduct(): string
    {
        return $this->engine->getProduct();
    }

    /**
     * Builds the table of every available property when it is first
     * read.
     *
     * @return mixed
     * @throws \Exception
     */
    public function &__get(string $name)
    {
        if ($name === 'properties') {
            $this->properties = $this->buildProperties();

            return $this->properties;
        }

        trigger_error(
            sprintf('Undefined property: %s::$%s', static::class, $name),
            E_USER_WARNING
        );
        $none = null;

        return $none;
    }

    /**
     * The metadata a read needs of a property, holding at least its name
     * and type, or null when the engine has no available property of that
     * name. Until the table of every property is built, a property the
     * engine was built with is looked up on its own in native code, so a
     * request pays only for the properties it reads.
     *
     * @return null|array<string, mixed>
     * @throws \Exception
     */
    public function findProperty(string $name): ?array
    {
        $key = strtolower($name);
        if (isset($this->properties)) {
            return $this->properties[$key] ?? null;
        }
        if (isset(self::METRIC_PROPERTIES[$key])) {
            return self::METRIC_PROPERTIES[$key];
        }
        if (!isset($this->readProperties[$key])) {
            $property = $this->lookUpProperty($key);
            if ($property === null) {
                // Not a property the engine was built with, so the table
                // says whether the data set has it. Reading the table here
                // builds it through __get, where the isset above does not,
                // so an __isset on this class would make this line answer
                // null with nothing built.
                return $this->properties[$key] ?? null;
            }
            $this->readProperties[$key] = $property;
        }

        return $this->readProperties[$key];
    }

    private function applyPerformanceProfile($config, string $profile): void
    {
        switch ($profile) {
            case 'LowMemory':
                $config->setLowMemory();
                break;
            case 'MaxPerformance':
                $config->setMaxPerformance();
                break;
            case 'Balanced':
                $config->setBalanced();
                break;
            case 'BalancedTemp':
                $config->setBalancedTemp();
                break;
            case 'HighPerformance':
                $config->setHighPerformance();
                break;
        }
    }

    /**
     * The name, type and category of an available property the engine was
     * built with, from native code, or null for any other name.
     *
     * @return null|array<string, string>
     * @throws \Exception
     */
    private function lookUpProperty(string $key): ?array
    {
        $this->assertOpen();
        $name = $this->requiredNames()[$key] ?? null;
        if ($name === null) {
            return null;
        }
        $property = $this->engine->getMetaData()->getProperties()
            ->getByKey($name);
        if ($property === null || !$property->getAvailable()) {
            return null;
        }

        return [
            'name' => $property->getName(),
            'type' => $property->getType(),
            'category' => $property->getCategory()
        ];
    }

    /**
     * Each name the engine was built with, keyed by the lowercase name, as
     * the engine gives it, which for a property the data set has is the
     * data set's spelling.
     *
     * @return array<string, string>
     * @throws \Exception
     */
    private function requiredNames(): array
    {
        if ($this->requiredNames === null) {
            $this->assertOpen();
            $this->requiredNames = [];
            $required = $this->engine->getRequiredProperties();
            $count = $required->size();
            for ($i = 0; $i < $count; ++$i) {
                $name = $required->get($i);
                $this->requiredNames[strtolower($name)] ??= $name;
            }
        }

        return $this->requiredNames;
    }

    /**
     * Metadata needs the engine, so once it is closed only what was already
     * looked up can be read.
     *
     * @throws \Exception
     */
    private function assertOpen(): void
    {
        if ($this->engine === null) {
            throw new \Exception(Messages::ENGINE_CLOSED);
        }
    }

    private function initEngineMetaData(): void
    {
        // Build the evidence key list from the native engine.
        $evidences = $this->engine->getKeys();
        $evidenceKeysList = [];
        for ($i = 0; $i < $evidences->size(); ++$i) {
            $evidenceKeysList[] = strtolower($evidences->get($i));
        }
        $this->evidenceKeys = $evidenceKeysList;

        // PHP constructs this class on every request, so the table of every
        // property is built when it is first read (see __get), and a read
        // looks up only its own property (see findProperty). Each follows
        // the data file in use.
        unset($this->properties);
        $this->requiredNames = null;
        $this->readProperties = [];
    }

    /**
     * The table of every available property in the data set, and the echoed
     * addresses, keyed by the lowercase name.
     *
     * @return array<string, array<string, mixed>>
     * @throws \Exception
     */
    private function buildProperties(): array
    {
        $this->assertOpen();
        $propertiesInternal = $this->engine->getMetaData()->getProperties();
        $properties = [];
        for ($i = 0; $i < $propertiesInternal->getSize(); ++$i) {
            $property = $propertiesInternal->getByIndex($i);
            if ($property->getAvailable()) {
                $properties[strtolower($property->getName())] = [
                    'name' => $property->getName(),
                    'type' => $property->getType(),
                    'datafiles' => SwigHelpers::vectorToArray($property->getDataFilesWherePresent()),
                    'category' => $property->getCategory(),
                    'description' => $property->getDescription(),
                    'component' => $this->getComponentName($property)
                ];
            }
        }

        foreach (self::METRIC_PROPERTIES as $name => $property) {
            $properties[$name] = $property;
        }

        return $properties;
    }

    private function getComponentName($property): string
    {
        return $this->engine->getMetaData()->getComponentForProperty($property)->getName();
    }

    /**
     * Get the IPv4 and IPv6 addresses from the evidence, using the same
     * priority order as the other IP intelligence engines: query.client-ip
     * first, then server.client-ip, then any other parseable value in the
     * evidence accepted by the engine.
     *
     * @return array{0: null|string, 1: null|string}
     */
    private function getClientIps(FlowData $flowData): array
    {
        return self::clientIpsFromEvidence($flowData->evidence->getAll());
    }

    /**
     * Picks the first IPv4 and the first IPv6 address out of the evidence,
     * query.client-ip and server.client-ip first.
     *
     * An IPv4-mapped IPv6 address such as ::ffff:8.8.8.8 counts as the IPv4
     * address it carries, so it is reported as Ip and looked up as 8.8.8.8.
     * See the Address forms section of the IP Intelligence specification.
     *
     * @param array<string, mixed> $evidence
     * @return array{0: ?string, 1: ?string} [ipv4, ipv6]
     */
    private static function clientIpsFromEvidence(array $evidence): array
    {
        // Evidence keys ignore letter case and arrive in the letters they
        // were first set with, so every key is lowered before it is read.
        // A key given twice in other letters is then one key holding its
        // last value, as Evidence holds it.
        $lowered = [];
        foreach ($evidence as $key => $value) {
            $lowered[strtolower((string) $key)] = $value;
        }

        $ordered = [];
        foreach (['query.client-ip', 'server.client-ip'] as $key) {
            if (isset($lowered[$key])) {
                $ordered[] = $lowered[$key];
            }
        }
        foreach ($lowered as $value) {
            if (!in_array($value, $ordered, true)) {
                $ordered[] = $value;
            }
        }

        $ipv4 = null;
        $ipv6 = null;
        foreach ($ordered as $value) {
            $candidate = trim((string) $value);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ipv4 ??= $candidate;
            } elseif (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $mapped = self::ipv4MappedAddress($candidate);
                if ($mapped !== null) {
                    $ipv4 ??= $mapped;
                } else {
                    $ipv6 ??= $candidate;
                }
            }
        }

        return [$ipv4, $ipv6];
    }

    /**
     * The IPv4 address inside an IPv4-mapped IPv6 address, or null when the
     * address is not one. PHP has no call for this, so the 16 bytes are
     * checked by hand: ten zero bytes, two 0xff bytes, then the IPv4 address
     * (RFC 4291 section 2.5.5.2). The deprecated ::a.b.c.d form and the
     * 64:ff9b::/96 translation prefix are not mapped addresses and stay IPv6.
     */
    private static function ipv4MappedAddress(string $ipv6): ?string
    {
        $bytes = inet_pton($ipv6);
        if ($bytes === false || strlen($bytes) !== 16) {
            return null;
        }
        if (substr($bytes, 0, 12) !== "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            return null;
        }
        $ipv4 = inet_ntop(substr($bytes, 12));
        return $ipv4 === false ? null : $ipv4;
    }
}
