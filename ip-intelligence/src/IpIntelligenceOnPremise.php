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
 * Mirrors device-detection-php-onpremise's DeviceDetectionOnPremise and
 * ip-intelligence-python's IpIntelligenceOnPremise.
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

    private $config;

    /**
     * @var array<string>
     */
    private array $evidenceKeys = [];

    /**
     * @param array<string, mixed> $settings
     *   dataFile            - (required, unless the global engine is used)
     *                         path to the .ipi data file.
     *   performanceProfile  - (optional) one of Constants::PERFORMANCE_PROFILES,
     *                         defaulting to Constants::DEFAULT_PERFORMANCE_PROFILE.
     *   restrictedProperties- (optional) list of properties to populate.
     *   concurrency         - (optional) expected concurrent accesses.
     *
     * Omitting "dataFile" reuses the shared global engine when one exists.
     * That engine comes from the FiftyOneDegreesIpIntelligenceEngine.* ini
     * settings, so ENGINE_SETTINGS are rejected on that path.
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
            throw new \Exception('A "dataFile" setting is required for the on-premise IP intelligence engine, or set the FiftyOneDegreesIpIntelligenceEngine.data_file ini to build a shared global engine.');
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

    private function initEngineMetaData(): void
    {
        // Build the evidence key list from the native engine.
        $evidences = $this->engine->getKeys();
        $evidenceKeysList = [];
        for ($i = 0; $i < $evidences->size(); ++$i) {
            $evidenceKeysList[] = strtolower($evidences->get($i));
        }
        $this->evidenceKeys = $evidenceKeysList;

        // Build the properties metadata list.
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

        foreach ($this->getMetricProperties() as $name => $property) {
            $properties[$name] = $property;
        }

        $this->properties = $properties;
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
        $evidence = $flowData->evidence->getAll();

        $ordered = [];
        foreach (['query.client-ip', 'server.client-ip'] as $key) {
            if (isset($evidence[$key])) {
                $ordered[] = $evidence[$key];
            }
        }
        foreach ($evidence as $value) {
            if (!in_array($value, $ordered, true)) {
                $ordered[] = $value;
            }
        }

        $ipv4 = null;
        $ipv6 = null;
        foreach ($ordered as $value) {
            $candidate = trim((string) $value);
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && $ipv4 === null) {
                $ipv4 = $candidate;
            } elseif (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false && $ipv6 === null) {
                $ipv6 = $candidate;
            }
        }

        return [$ipv4, $ipv6];
    }

    /**
     * The synthetic properties added by this engine. The IP address supplied
     * as evidence is echoed back in the results (ip-intelligence-dotnet parity).
     *
     * @return array<string, array<string, string>>
     */
    private function getMetricProperties(): array
    {
        return [
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
    }
}
