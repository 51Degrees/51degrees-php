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

namespace fiftyone\pipeline\devicedetection;

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\DataKeyedCache;
use fiftyone\pipeline\engines\Engine;

class DeviceDetectionOnPremise extends Engine
{
    public string $dataKey = 'device';
    public \EngineHashSwig $engine;

    /**
     * The index of each property the engine was built with, keyed by the
     * lowercase name. The index is the place of the name in the build-time
     * list, so it names the same property after any reload, and every read
     * of a value goes by it rather than by name. It is built when it is
     * first read, and is empty for an engine built with no names.
     *
     * @var array<string, int>
     */
    public array $propertyIndexes;

    /**
     * The match metrics, which the results give rather than the data set.
     */
    private const METRIC_PROPERTIES = [
        'matchednodes' => [
            'name' => 'MatchedNodes',
            'type' => 'Integer',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::MATCHED_NODES_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ],
        'difference' => [
            'name' => 'Difference',
            'type' => 'Integer',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::DIFFERENCE_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ],
        'drift' => [
            'name' => 'Drift',
            'type' => 'Integer',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::DRIFT_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ],
        'deviceid' => [
            'name' => 'DeviceId',
            'type' => 'String',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::DEVICE_ID_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ],
        'useragents' => [
            'name' => 'UserAgents',
            'type' => 'Array',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::USER_AGENTS_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => true,
            'available' => true
        ],
        'iterations' => [
            'name' => 'Iterations',
            'type' => 'Integer',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::ITERATIONS_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ],
        'method' => [
            'name' => 'Method',
            'type' => 'String',
            'dataFiles' => ['Lite', 'Premium', 'Enterprise'],
            'description' => Constants::METHOD_DESCRIPTION,
            'category' => 'DeviceMetrics',
            'component' => 'MatchMetrics',
            'isList' => false,
            'available' => true
        ]
    ];

    /**
     * @var array<string>
     */
    private array $evidenceKeys;

    private bool $builtWithNames;

    /**
     * Each name the engine was built with, keyed by the lowercase name, with
     * its index in that list and the name as the engine gives it.
     *
     * @var null|array<string, array{0: int, 1: string}>
     */
    private ?array $requiredNames = null;

    /**
     * The name, type and category of each property read so far, keyed by
     * the lowercase name.
     *
     * @var array<string, array<string, string>>
     */
    private array $readProperties = [];

    public function __construct()
    {
        // List of pipelines the flowElement has been added to
        $this->pipelines = [];

        // The on-premise Hash engine for PHP can only run with the in-memory
        // MaxPerformance profile. PHP is normally run under a process manager
        // (Apache MPM prefork, php-fpm) that forks the worker process. Any other
        // profile keeps the data file open and shares those file handles across
        // the fork, which corrupts detection (see issue #38). The engine is
        // always built in memory, so any other configured profile is a mistake
        // that is rejected here with a clear exception rather than being
        // silently ignored.
        $performanceProfile = ini_get('FiftyOneDegreesHashEngine.performance_profile');
        if ($performanceProfile !== false
            && $performanceProfile !== ''
            && strcasecmp($performanceProfile, 'MaxPerformance') !== 0) {
            throw new \Exception(sprintf(Messages::PERFORMANCE_PROFILE_ERROR, $performanceProfile));
        }

        $this->engine = \FiftyOneDegreesHashEngine::engine_get();

        $requiredProperties = self::startupSetting(
            'FiftyOneDegreesHashEngine.required_properties'
        );

        // An engine built with no names keeps reading by name, because
        // native code then gives each property its own place in the data
        // set as its index, which a reload can move.
        $this->builtWithNames = (bool) $requiredProperties;
        if ($this->builtWithNames) {
            $this->setRestrictedProperties(explode(',', $requiredProperties));
        }

        // PHP constructs this class on every request, while the native
        // engine is built once, when PHP starts. So the table of every
        // property and the index table are built when they are first read
        // (see __get), and a read looks up only its own property (see
        // findProperty).
        unset($this->properties, $this->propertyIndexes);

        // Make evidence list
        $evidences = $this->engine->getKeys();
        $evidenceKeysList = [];
        for ($i = 0; $i < $evidences->size(); ++$i) {
            $evidence = strtolower($evidences->get($i));
            $evidence = str_replace('http_', '', $evidence);
            $evidence = str_replace('http_x-', '', $evidence);
            $evidenceKeysList[] = $evidence;
        }
        $this->evidenceKeys = $evidenceKeysList;

        parent::__construct(...func_get_args());
    }

    /**
     * Instance of EvidenceKeyFilter based on the evidence keys fetched
     * from the cloud service by the private getEvidenceKeys() method.
     */
    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter($this->evidenceKeys);
    }

    public function processInternal(FlowData $flowData): void
    {
        // Make evidence collection
        $evidence = $flowData->evidence->getAll();

        $evidenceInternal = new \EvidenceDeviceDetectionSwig();

        foreach ($evidence as $key => $value) {
            if ($this->filterEvidenceKey($key) && is_string($value)) {
                $evidenceInternal->set($key, $value);
            }
        }

        $result = $this->engine->process($evidenceInternal);

        $data = new SwigData($this, $result);

        $flowData->setElementData($data);
    }

    /**
     * Add a cache to an engine.
     *
     * @param null|\fiftyone\pipeline\engines\DataKeyedCache $cache Cache with get and set methods
     * @throws \Exception
     */
    public function setCache(?DataKeyedCache $cache): void
    {
        throw new \Exception(Messages::CACHE_ERROR);
    }

    /**
     * Ask the engine to start using the specified data file
     * for detections.
     * This can be used in 3 different scenarios:
     * 1. The data file that was originally used to create the engine has
     * been updated on disk. In this case, no parameters are needed.
     * 2. A new data file is available, but it is in a different location
     * to the original. In this case, the parameter should be the new data
     * file location.
     * 3. A new data file is available in memory. The first parameter will
     * be the variable holding the in-memory data file. The second will
     * be the size of the data file in bytes.
     *
     * @param null|string $fileName_or_data Data file path or the variable holding the in-memory data file
     * @param null|int $length Length of the in-memory data file in bytes
     */
    public function refreshData(?string $fileName_or_data = null, ?int $length = null): void
    {
        switch (func_num_args()) {
            case 0: $this->engine->refreshData();
                break;
            case 1: $this->engine->refreshData($fileName_or_data);
                break;
            default: 
                $this->engine->refreshData($fileName_or_data, $length);
        }

        // The metadata follows the new data set. The index table does not,
        // because an index is the place of a name in the list the engine
        // was built with, which a reload never changes.
        unset($this->properties);
        $this->requiredNames = null;
        $this->readProperties = [];
    }

    /**
     * Builds the table of every property, or the index table, when it is
     * first read.
     *
     * @return mixed
     */
    public function &__get(string $name)
    {
        switch ($name) {
            case 'properties':
                $this->properties = $this->buildProperties();

                return $this->properties;
            case 'propertyIndexes':
                $this->propertyIndexes = [];
                if ($this->builtWithNames) {
                    foreach ($this->requiredNames() as $key => $named) {
                        $this->propertyIndexes[$key] = $named[0];
                    }
                }

                return $this->propertyIndexes;
        }

        trigger_error(
            sprintf('Undefined property: %s::$%s', static::class, $name),
            E_USER_WARNING
        );
        $none = null;

        return $none;
    }

    /**
     * The metadata a read needs of a property, holding at least its name,
     * type and category, or null when the engine has no property of that
     * name. Until the table of every property is built, a property the
     * engine was built with is looked up on its own in native code, so a
     * request pays only for the properties it reads.
     *
     * @return null|array<string, mixed>
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
                // says whether the data set has it.
                return $this->properties[$key] ?? null;
            }
            $this->readProperties[$key] = $property;
        }

        return $this->readProperties[$key];
    }

    /**
     * The index of a property the engine was built with, or null when the
     * engine was not built with the name. A caller with the index reads a
     * value without the native code looking the name up.
     */
    public function getPropertyIndex(string $name): ?int
    {
        return $this->propertyIndexes[strtolower($name)] ?? null;
    }

    /**
     * The name, type and category of a property the engine was built with,
     * from native code, or null for any other name.
     *
     * @return null|array<string, string>
     */
    private function lookUpProperty(string $key): ?array
    {
        $named = $this->requiredNames()[$key] ?? null;
        if ($named === null) {
            return null;
        }
        $property = $this->engine->getMetaData()->getProperties()
            ->getByKey($named[1]);
        if ($property === null) {
            return null;
        }

        return [
            'name' => $property->getName(),
            'type' => $this->getPropertyType($property),
            'category' => $property->getCategory()
        ];
    }

    /**
     * Each name the engine was built with, keyed by the lowercase name, with
     * its index and the name as the engine gives it.
     *
     * The order is the engine's required property order, which native code
     * fixes at build and works out again for every data set it loads, so an
     * index names the same property however the data set orders its own
     * properties.
     *
     * @return array<string, array{0: int, 1: string}>
     */
    private function requiredNames(): array
    {
        if ($this->requiredNames === null) {
            $this->requiredNames = [];
            $required = $this->engine->getRequiredProperties();
            $count = $required->size();
            for ($i = 0; $i < $count; ++$i) {
                // A name given twice keeps its first place, as native code
                // does.
                $name = $required->get($i);
                $key = strtolower($name);
                if (!isset($this->requiredNames[$key])) {
                    $this->requiredNames[$key] = [$i, $name];
                }
            }
        }

        return $this->requiredNames;
    }

    /**
     * The table of every property in the data set, and the match metrics,
     * keyed by the lowercase name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildProperties(): array
    {
        $propertiesInternal = $this->engine->getMetaData()->getProperties();

        $properties = [];
        for ($i = 0; $i < $propertiesInternal->getSize(); ++$i) {
            $property = $propertiesInternal->getByIndex($i);
            $properties[strtolower($property->getName())] = [
                'name' => $property->getName(),
                'type' => $this->getPropertyType($property),
                'dataFiles' => SwigHelpers::vectorToArray($property->getDataFilesWherePresent()),
                'category' => $property->getCategory(),
                'description' => $property->getDescription(),
                'available' => $property->getAvailable(),
                'isList' => $property->getIsList(),
                'component' => $this->getComponentName($property)
            ];
        }

        foreach (self::METRIC_PROPERTIES as $name => $property) {
            $properties[$name] = $property;
        }

        return $properties;
    }

    /**
     * The value a setting had when PHP started, which is the value the
     * start-up engine was built with. ini_get gives the value for this
     * request, which a per-directory setting or ini_set can change after the
     * engine was built.
     */
    private static function startupSetting(string $name): string
    {
        $value = get_cfg_var($name);

        return is_string($value) ? $value : '';
    }

    private function getComponentName($property)
    {
        $component = $this->engine->getMetaData()->getComponentForProperty($property);

        return $component->getName();
    }

    private function getPropertyType($property): string
    {
        switch ($property->getType()) {
            case 'string': 
                return 'String';
            case 'int': 
                return 'Integer';
            case 'bool': 
                return 'Boolean';
            case 'double': 
                return 'Double';
            case 'javascript': 
                return 'JavaScript';
            case 'string[]': 
                return 'Array';
            default: 
                return 'String';
        }
    }
}
