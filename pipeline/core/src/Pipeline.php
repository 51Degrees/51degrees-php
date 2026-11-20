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
 * Pipeline holding a list of FlowElements for processing,
 * can create FlowData that will be passed through these,
 * collecting ElementData
 * Should be constructed through the PipelineBuilder class. A Pipeline
 * cannot be changed once it is built, so its elements and settings can be
 * read but not changed.
 *
 * @property-read array<\fiftyone\pipeline\core\FlowElement> $flowElements
 *   The FlowElements, in the order they run
 * @property-read array<string, \fiftyone\pipeline\core\FlowElement> $flowElementsList
 *   The FlowElements by Data Key
 * @property-read Logger $logger The logger setting
 * @property-read mixed $metaDataStore The metaDataStore setting
 * @property-read bool $suppressProcessExceptions The
 *   suppressProcessExceptions setting
 * @property-read bool $autoCloseElements The autoCloseElements setting
 * @property-read array<string, array<string, array<string, array<string, string>>>> $propertyDatabase
 *   The metadata of every element's properties
 */
class Pipeline
{
    /**
     * The properties that __get gives as copies, so that the Pipeline
     * cannot be changed once it is built. __set refuses every property the
     * Pipeline declares.
     */
    private const READABLE = [
        'flowElements',
        'flowElementsList',
        'propertyDatabase',
        'logger',
        'metaDataStore',
        'suppressProcessExceptions',
        'autoCloseElements',
    ];

    /**
     * The properties given in the settings when the Pipeline is built.
     */
    private const SETTINGS = [
        'logger',
        'metaDataStore',
        'suppressProcessExceptions',
        'autoCloseElements',
    ];

    /**
     * @var array<\fiftyone\pipeline\core\FlowElement>
     */
    private array $flowElements;

    /**
     * Each FlowElement by its Data Key, and also by each deprecated Data
     * Key it has that no other FlowElement has as its own.
     *
     * @var array<string, \fiftyone\pipeline\core\FlowElement>
     */
    private array $flowElementsList = [];

    /**
     * The Data Key of the FlowElement each deprecated Data Key gives.
     *
     * @var array<string, string>
     */
    private array $deprecatedDataKeys = [];

    /**
     * The deprecated Data Keys already logged, so each is logged once.
     *
     * @var array<string, bool>
     */
    private array $deprecatedDataKeysLogged = [];

    /**
     * The position of each data key in Pipeline order, by which a FlowData
     * lists its ElementData.
     *
     * @var array<string, int>
     */
    private array $dataKeyPositions = [];

    private Logger $logger;

    /**
     * Whatever the metaDataStore setting gives, which the Pipeline keeps
     * for the caller.
     *
     * @var mixed
     */
    private $metaDataStore;

    /**
     * If true, an error from a FlowElement is kept in the FlowData's errors,
     * otherwise processing throws the first one.
     */
    private bool $suppressProcessExceptions;

    /**
     * If true, the default, closing the Pipeline closes its FlowElements.
     * Set the autoCloseElements setting to false when the elements are
     * shared with another Pipeline, and close each element once every
     * Pipeline using it is finished with.
     */
    private bool $autoCloseElements;

    /**
     * The metadata of every element's properties, keyed by metadata name and
     * then by value, which FlowData::getWhere searches. PHP builds the
     * pipeline on every request and most requests never search, so it is
     * left uninitialized until __get builds it when it is first read.
     *
     * @var array<string, array<string, array<string, array<string, string>>>>
     */
    private array $propertyDatabase;

    /**
     * How many times an element of the Pipeline has updated its property
     * list. An element that keeps what it worked out from the properties of
     * the Pipeline's elements works it out again when this has changed.
     */
    private int $propertyListUpdates = 0;

    /**
     * Whether an element accepts a request line key, which
     * acceptsRequestLine() decides the first time it is asked and keeps,
     * so a request reads one boolean rather than asking every element.
     * Null until then.
     */
    private ?bool $acceptsRequestLine = null;

    /**
     * The Evidence the Pipeline accepts, which getEvidenceKeyFilter gives.
     * It is always needed and the elements it asks do not change once the
     * Pipeline is built, so the constructor builds it once. It holds no
     * closure, so the Pipeline can still be serialized, as a session cache
     * does through an element's data.
     */
    private EvidenceKeyFilter $evidenceKeyFilter;

    /**
     * Pipeline constructor.
     *
     * @param array<\fiftyone\pipeline\core\FlowElement> $flowElements List of FlowElements
     * @param array<string, mixed> $settings The logger, metaDataStore,
     *   suppressProcessExceptions and autoCloseElements settings
     */
    public function __construct(array $flowElements, array $settings)
    {
        $this->logger = $settings['logger'] ?? new Logger(null);
        $this->metaDataStore = $settings['metaDataStore'] ?? null;
        $this->suppressProcessExceptions = (bool) ($settings['suppressProcessExceptions'] ?? false);
        $this->autoCloseElements = (bool) ($settings['autoCloseElements'] ?? true);

        $this->flowElements = $flowElements;

        foreach (array_values($flowElements) as $index => $flowElement) {
            if (!isset($this->dataKeyPositions[$flowElement->dataKey])) {
                $this->dataKeyPositions[$flowElement->dataKey] = $index;
            }
        }

        foreach ($flowElements as $flowElement) {
            // An element's own Data Key takes the place of another
            // element's deprecated one.
            $this->flowElementsList[$flowElement->dataKey] = $flowElement;
            unset($this->deprecatedDataKeys[$flowElement->dataKey]);
            foreach ($flowElement->deprecatedDataKeys as $deprecatedKey) {
                if (!isset($this->flowElementsList[$deprecatedKey])) {
                    $this->flowElementsList[$deprecatedKey] = $flowElement;
                    $this->deprecatedDataKeys[$deprecatedKey] = $flowElement->dataKey;
                }
            }

            $flowElement->pipelines[] = $this;

            $flowElement->onRegistration($this);
        }

        $this->evidenceKeyFilter = new PipelineEvidenceKeyFilter($this->flowElements);
    }

    /**
     * Gives a copy of an element list, a setting or propertyDatabase, which
     * it builds from every element when it is first read.
     *
     * @return mixed
     */
    public function &__get(string $name)
    {
        if (!in_array($name, self::READABLE, true)) {
            trigger_error(
                sprintf('Undefined property: %s::$%s', static::class, $name),
                E_USER_WARNING
            );
            $none = null;

            return $none;
        }

        // A write to the uninitialized property goes straight to it, not
        // through __set, which refuses it.
        if ($name === 'propertyDatabase' && !$this->hasPropertyDatabase()) {
            $this->propertyDatabase = [];
            foreach ($this->flowElements as $flowElement) {
                $this->addToPropertyDatabase($flowElement);
            }
        }

        $copy = $this->{$name};

        return $copy;
    }

    /**
     * Whether a property read through __get is there, so that isset, ?? and
     * empty read it. The element lists always are, and so is
     * propertyDatabase, which __get builds when it is first read. A setting
     * is there unless it is null.
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, self::SETTINGS, true)) {
            return isset($this->{$name});
        }

        return in_array($name, self::READABLE, true);
    }

    /**
     * Refuses to set any property the Pipeline declares, because a Pipeline
     * cannot be changed once it is built. A setting is given when the
     * Pipeline is built instead. Any other property is set.
     *
     * @param mixed $value
     * @throws \Exception
     */
    public function __set(string $name, $value): void
    {
        if (in_array($name, self::SETTINGS, true)) {
            throw new \Exception(
                sprintf(Messages::PIPELINE_SETTING_IMMUTABLE, $name)
            );
        }

        if (property_exists(self::class, $name)) {
            throw new \Exception(sprintf(Messages::PIPELINE_IMMUTABLE, $name));
        }

        $this->{$name} = $value;
    }

    /**
     * Create a FlowData based on what's in the Pipeline.
     */
    public function createFlowData(): FlowData
    {
        return new FlowData($this);
    }

    /**
     * Close the FlowElements that have a close method, which releases the
     * native resources they hold, unless the autoCloseElements setting is
     * false. Call it in a finally block once the Pipeline is finished with.
     *
     * FlowData created by the Pipeline are not closed by it. Close each one
     * as well, because the results it holds keep the engine's data in memory
     * until they are released.
     */
    public function close(): void
    {
        if (!$this->autoCloseElements) {
            return;
        }

        // Every element is closed even if one fails, and the first failure
        // is thrown once they all have been.
        $failure = null;
        foreach ($this->flowElements as $flowElement) {
            if (!method_exists($flowElement, 'close')) {
                continue;
            }
            try {
                $flowElement->close();
            } catch (\Throwable $e) {
                $failure = $failure ?? $e;
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    public function log(string $level, string $message): void
    {
        $this->logger->log($level, $message);
    }

    /**
     * The Evidence the Pipeline accepts, being every key that at least one
     * of its FlowElements accepts, in any letter case. Each element is asked
     * when a key is checked, so the filter follows an element whose accepted
     * Evidence changes, as it does when an engine's data is refreshed.
     *
     * The filter is built once, when the Pipeline is built, and every call
     * gives that same filter.
     */
    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        return $this->evidenceKeyFilter;
    }

    /**
     * Whether an element in the Pipeline accepts any of the request line
     * keys, being Evidence::REQUEST_METHOD_KEY, REQUEST_PATH_KEY and
     * REQUEST_QUERY_KEY. Evidence::setFromWebRequest adds the three only
     * when it does.
     *
     * The answer depends only on the Pipeline, so the elements are asked
     * once, the first time a request needs the answer, and every later
     * request reads the answer kept here. Asking every element about the
     * three keys on every request made each detection slower. An element
     * that starts to accept a request line key after the first request,
     * as a Cloud Request Engine could if the cloud service listed one and
     * the list could not be fetched until then, is seen by a Pipeline
     * built after that.
     */
    public function acceptsRequestLine(): bool
    {
        if ($this->acceptsRequestLine === null) {
            $this->acceptsRequestLine = $this->anyElementAccepts([
                Evidence::REQUEST_METHOD_KEY,
                Evidence::REQUEST_PATH_KEY,
                Evidence::REQUEST_QUERY_KEY,
            ]);
        }

        return $this->acceptsRequestLine;
    }

    /**
     * Whether the Pipeline's filter accepts any of the keys.
     *
     * @param array<string> $keys
     */
    private function anyElementAccepts(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->evidenceKeyFilter->filterEvidenceKey($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a FlowElement by its Data Key, compared ignoring case as every
     * string key is, after the exact key. As in FlowData::get, an element's
     * own Data Key comes before a deprecated Data Key, which is logged once.
     *
     * @throws \Exception if no FlowElement has the key
     */
    public function getElement(string $key): FlowElement
    {
        if (isset($this->flowElementsList[$key]) &&
            !isset($this->deprecatedDataKeys[$key])) {
            return $this->flowElementsList[$key];
        }

        foreach ($this->flowElementsList as $dataKey => $flowElement) {
            if (!isset($this->deprecatedDataKeys[$dataKey]) &&
                strcasecmp((string) $dataKey, $key) === 0) {
                return $flowElement;
            }
        }

        $currentKey = $this->currentDataKey($key);
        if (isset($this->flowElementsList[$currentKey])) {
            return $this->flowElementsList[$currentKey];
        }

        throw new \Exception(sprintf(
            Messages::NO_ELEMENT,
            $key,
            implode(',', array_keys(array_diff_key(
                $this->flowElementsList,
                $this->deprecatedDataKeys
            )))
        ));
    }

    /**
     * The position of a data key in Pipeline order, being the order the
     * FlowElements were added, or null for a key no FlowElement has.
     */
    public function dataKeyPosition(string $key): ?int
    {
        return $this->dataKeyPositions[$key] ?? null;
    }

    /**
     * The Data Key of the FlowElement a deprecated Data Key gives, in any
     * letter case, logging once for each deprecated key that it was used,
     * or the key unchanged when it is not deprecated. getElement and
     * FlowData::get call it after their own keys, so an old key finds the
     * element and its data.
     */
    public function currentDataKey(string $key): string
    {
        $deprecatedKey = $this->deprecatedKeyNamed($key);
        if ($deprecatedKey === null) {
            return $key;
        }

        $currentKey = $this->deprecatedDataKeys[$deprecatedKey];
        if (!isset($this->deprecatedDataKeysLogged[$deprecatedKey])) {
            $this->deprecatedDataKeysLogged[$deprecatedKey] = true;
            $this->log('warning', sprintf(
                Messages::DATA_KEY_DEPRECATED,
                $deprecatedKey,
                $currentKey
            ));
        }

        return $currentKey;
    }

    /**
     * The deprecated Data Key the key names, found by the exact key and then
     * in any letter case, or null when it names none.
     */
    private function deprecatedKeyNamed(string $key): ?string
    {
        if (isset($this->deprecatedDataKeys[$key])) {
            return $key;
        }

        foreach (array_keys($this->deprecatedDataKeys) as $deprecatedKey) {
            if (strcasecmp((string) $deprecatedKey, $key) === 0) {
                return (string) $deprecatedKey;
            }
        }

        return null;
    }

    /**
     * Update metadata store for a FlowElement based on its list of properties.
     */
    public function updatePropertyDatabaseForFlowElement(FlowElement $flowElement): void
    {
        ++$this->propertyListUpdates;

        // Before its first read there is no database, and the read builds
        // it from the properties the elements have then.
        if (!$this->hasPropertyDatabase()) {
            return;
        }

        $this->addToPropertyDatabase($flowElement);
    }

    /**
     * How many times an element of the Pipeline has updated its property
     * list, so that an element keeping what it worked out from those
     * properties knows when to work it out again.
     */
    public function getPropertyListUpdates(): int
    {
        return $this->propertyListUpdates;
    }

    /**
     * Whether propertyDatabase has been built. get_object_vars leaves out
     * an uninitialized property and does not call __isset.
     */
    private function hasPropertyDatabase(): bool
    {
        return array_key_exists('propertyDatabase', get_object_vars($this));
    }

    private function addToPropertyDatabase(FlowElement $flowElement): void
    {
        $dataKey = $flowElement->dataKey;

        // First unset any properties stored by the FlowElement
        foreach ($this->propertyDatabase as $propertyValues) {
            foreach ($propertyValues as $propertyList) {
                foreach ($propertyList as $key => $info) {
                    if ($info['flowElement'] === $dataKey) {
                        unset($propertyList[$key]);
                    }
                }
            }
        }

        $properties = $flowElement->getProperties();

        foreach ($properties as $key => $property) {
            foreach ($property as $metaKey => $metaValue) {
                $metaKey = strtolower($metaKey);

                if (!isset($this->propertyDatabase[$metaKey])) {
                    $this->propertyDatabase[$metaKey] = [];
                }

                if (!is_string($metaValue)) {
                    continue;
                }

                $metaValue = strtolower($metaValue);

                if (!isset($this->propertyDatabase[$metaKey][$metaValue])) {
                    $this->propertyDatabase[$metaKey][$metaValue] = [];
                }

                $property['flowElement'] = $dataKey;

                $this->propertyDatabase[$metaKey][$metaValue][$key] = $property;
            }
        }
    }
}
