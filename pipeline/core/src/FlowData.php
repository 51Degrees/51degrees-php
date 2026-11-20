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
 * FlowData is created by a specific Pipeline
 * It collects evidence set by the user
 * It passes evidence to FlowElements in the Pipeline
 * These elements can return ElementData or populate an errors object.
 *
 * A FlowData is not safe to use from several threads at once. It holds
 * the evidence and results of one request and takes no lock. The caller is
 * responsible for any locking, so a caller that shares a FlowData between
 * threads coordinates that itself.
 *
 * @property \fiftyone\pipeline\core\JsonBundlerElement $jsonbundler
 * @property array<string, \fiftyone\pipeline\core\ElementData> $data
 * Deprecated. Use tryGet() or listElementData() instead. The ElementData by
 * data key, made from the list each time it is read.
 * @property array<string, \Throwable> $errors The errors of processing by
 * key, a copy each time it is read.
 */
class FlowData
{
    public Pipeline $pipeline;
    public bool $stopped = false;
    public Evidence $evidence;

    public bool $processed = false;

    /**
     * The ElementData, as a list whose head is the newest node. Each node
     * holds the data key, the ElementData and the next node, and never
     * changes once added. Each request runs on one thread, so a plain write
     * adds a node.
     *
     * @var null|array{key: string, data: mixed, next: null|array}
     */
    private ?array $elementDataHead = null;

    /**
     * The errors of processing by key, each the one error under its key or
     * an AggregateException holding every error under it. It is read as
     * the public errors property, which gives a copy, so changing one entry
     * of that copy changes nothing and PHP raises a notice. Setting the
     * whole property throws once processing has finished.
     *
     * @var array<string, \Throwable>
     */
    private array $errors = [];

    /**
     * Constructor for FlowData.
     *
     * @param \fiftyone\pipeline\core\Pipeline $pipeline Parent Pipeline
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
        $this->evidence = new Evidence($this);
    }

    /**
     * Magic getter to allow $FlowData->FlowElementKey getting.
     */
    public function __get(string $flowElementKey)
    {
        if ($flowElementKey === 'data') {
            return $this->dataByKey();
        }
        if ($flowElementKey === 'errors') {
            return $this->errors;
        }

        return $this->get($flowElementKey);
    }

    /**
     * Sets the deprecated data array, which replaces every ElementData with
     * the array's, in its order, or the errors. Either adds to the FlowData,
     * so it throws once processing has finished.
     *
     * @param string $name the property name
     * @param mixed $value the value
     * @throws \Exception once processing has finished
     */
    public function __set(string $name, $value): void
    {
        if ($name === 'errors') {
            $this->checkNotFinished('errors');
            $this->errors = $value;

            return;
        }

        if ($name === 'data' && is_array($value)) {
            $this->checkNotFinished('ElementData');
            $this->elementDataHead = null;
            foreach ($value as $key => $data) {
                $this->addNode((string) $key, $data);
            }

            return;
        }

        $this->{$name} = $value;
    }

    /**
     * Whether the deprecated data array, the errors or an ElementData is
     * there.
     *
     * @param string $name the property name
     */
    public function __isset(string $name): bool
    {
        return $name === 'data' || $name === 'errors' || $this->tryGet($name) !== null;
    }

    /**
     * process function runs the process function on every attached FlowElement
     * allowing data to be changed based on evidence
     * This can only be run once per FlowData instance.
     *
     * @return static
     */
    public function process(): FlowData
    {
        // A finished FlowData takes no more writes, so it is processed once.
        if ($this->processed) {
            throw new \Exception(Messages::FLOW_DATA_PROCESSED);
        }

        foreach ($this->pipeline->flowElements as $flowElement) {
            if (!$this->stopped) {
                // ElementData another FlowElement added under this key is
                // open for writing again while this one processes.
                self::setFinished($this->findByKey($flowElement->dataKey), false);

                // All errors are caught and stored in an errors array keyed by the
                // FlowElement that set the error
                try {
                    $flowElement->process($this);
                } catch (\Throwable $e) {
                    $this->setError($flowElement->dataKey, $e);
                } finally {
                    self::setFinished($this->findByKey($flowElement->dataKey), true);
                }
            }
        }

        // The FlowData, and every ElementData in it, including any the
        // caller added, is read only from now on.
        $this->processed = true;
        foreach ($this->listElementData() as $data) {
            self::setFinished($data, true);
        }

        if (count($this->errors) != 0 && $this->pipeline->suppressProcessExceptions === false) {
            $exception = reset($this->errors);

            throw $exception;
        }

        return $this;
    }

    /**
     * Release the native resources held by the element data in this
     * FlowData, such as the results of an on-premise engine, which keep the
     * engine's data in memory until they are released. Call it in a finally
     * block once the FlowData is finished with, so that this happens
     * straight away rather than when the garbage collector next runs.
     *
     * Only element data with a close method of its own is closed, so element
     * data that needs no cleanup, such as an entry in a results cache, is
     * left alone. Values already read are unaffected.
     */
    public function close(): void
    {
        foreach ($this->listElementData() as $elementData) {
            if (method_exists($elementData, 'close')) {
                $elementData->close();
            }
        }
    }

    /**
     * Retrieve data by FlowElement object.
     */
    public function getFromElement(FlowElement $flowElement): ElementData
    {
        return $this->get($flowElement->dataKey);
    }

    /**
     * Retrieve data by FlowElement key, compared ignoring case as every
     * string key is, or by a deprecated Data Key the element had before,
     * which is logged once.
     *
     * @throws \Exception
     */
    public function get(string $flowElementKey): ElementData
    {
        $data = $this->tryGet($flowElementKey);
        if ($data !== null) {
            return $data;
        }

        throw new \Exception(sprintf(
            Messages::NO_ELEMENT_DATA,
            $flowElementKey,
            join(',', array_keys($this->dataByKey()))
        ));
    }

    /**
     * The ElementData under a FlowElement's data key, or null if there is
     * none. Data added later under the same key replaces the earlier. A key
     * that finds nothing as given is looked for again ignoring its letter
     * case, finding the first added, then as a deprecated data key.
     *
     * @param string $flowElementKey name of FlowElement
     * @return null|\fiftyone\pipeline\core\ElementData
     */
    public function tryGet(string $flowElementKey)
    {
        $found = $this->findByKey($flowElementKey);
        if ($found !== null) {
            return $found;
        }

        // A deprecated Data Key gives the data under the key that replaced
        // it.
        $currentKey = $this->pipeline->currentDataKey($flowElementKey);
        if ($currentKey !== $flowElementKey) {
            for ($node = $this->elementDataHead; $node !== null; $node = $node['next']) {
                if ($node['key'] === $currentKey) {
                    return $node['data'];
                }
            }
        }

        return null;
    }

    /**
     * The ElementData under a key as given, or else ignoring its letter
     * case, finding the first added, or null.
     *
     * @return mixed
     */
    private function findByKey(string $flowElementKey)
    {
        for ($node = $this->elementDataHead; $node !== null; $node = $node['next']) {
            if ($node['key'] === $flowElementKey) {
                return $node['data'];
            }
        }

        // Of keys that differ from it only in case, the first added is
        // found, which is the last in the list.
        $found = null;
        for ($node = $this->elementDataHead; $node !== null; $node = $node['next']) {
            if (strcasecmp($node['key'], $flowElementKey) === 0) {
                $found = $node['data'];
            }
        }
        return $found;
    }

    /**
     * Every ElementData in Pipeline order, being the order the FlowElements
     * were added to the Pipeline, the newest for each data key. Data under a
     * key no FlowElement of the Pipeline has comes last, in the order it was
     * added.
     *
     * @return array<int, \fiftyone\pipeline\core\ElementData>
     */
    public function listElementData(): array
    {
        return array_values($this->dataByKey());
    }

    /**
     * The newest ElementData for each data key, by key, in Pipeline order.
     *
     * @return array<string, \fiftyone\pipeline\core\ElementData>
     */
    private function dataByKey(): array
    {
        $nodes = [];
        for ($node = $this->elementDataHead; $node !== null; $node = $node['next']) {
            if (!array_key_exists($node['key'], $nodes)) {
                $nodes[$node['key']] = $node['data'];
            }
        }

        // The list holds the newest first, so it is reversed into the order
        // the data was added before it is sorted.
        $added = array_reverse(array_keys($nodes));
        $position = [];
        foreach ($added as $index => $key) {
            $position[$key] = [
                $this->pipeline->dataKeyPosition($key) ?? PHP_INT_MAX,
                $index
            ];
        }
        usort($added, function ($a, $b) use ($position) {
            return $position[$a] <=> $position[$b];
        });

        $byKey = [];
        foreach ($added as $key) {
            $byKey[$key] = $nodes[$key];
        }

        return $byKey;
    }

    /**
     * Adds a node to the head of the list.
     *
     * @param string $key the data key
     * @param mixed $data the ElementData
     */
    private function addNode(string $key, $data): void
    {
        $this->elementDataHead = [
            'key' => $key,
            'data' => $data,
            'next' => $this->elementDataHead
        ];
    }

    /**
     * Set data (used by FlowElement).
     */
    public function setElementData(ElementData $data): void
    {
        $this->checkNotFinished('ElementData');
        $this->addNode($data->flowElement->dataKey, $data);
    }

    /**
     * Throws once processing has finished, because a finished FlowData is
     * read only. Every method that writes to the FlowData calls this first.
     *
     * @param string $what what is being added, for the message
     * @throws \Exception once processing has finished
     */
    public function checkNotFinished(string $what): void
    {
        if ($this->processed) {
            throw new \Exception(sprintf(Messages::FLOW_DATA_FINISHED, $what));
        }
    }

    /**
     * Records whether the FlowElements that write an ElementData have
     * finished. Anything that is not ElementData is left alone.
     *
     * @param mixed $data the ElementData
     */
    private static function setFinished($data, bool $finished): void
    {
        if ($data instanceof ElementData) {
            $data->setWritesFinished($finished);
        }
    }

    /**
     * Set error (should be keyed by FlowElement dataKey). Every error is
     * kept. A second error under a key makes the entry an
     * AggregateException that holds each error under the key in order.
     */
    public function setError(string $key, \Throwable $error): void
    {
        $this->checkNotFinished('an error');

        $existing = $this->errors[$key] ?? null;
        if ($existing === null) {
            $this->errors[$key] = $error;
        } else {
            $this->errors[$key] = new AggregateException(array_merge(
                $existing instanceof AggregateException
                    ? $existing->errors
                    : [$existing],
                [$error]
            ));
        }

        $logMessage = 'Error occurred during processing';

        if (!empty($key)) {
            $logMessage = $logMessage . ' of ' . $key . ". \n" . $error->getMessage();
        }

        $this->pipeline->log('error', $logMessage);
    }

    /**
     * Get an array evidence stored in the FlowData, filtered by
     * its FlowElements' EvidenceKeyFilters.
     *
     * @return array<string, int|string>
     */
    public function getEvidenceDataKey(): array
    {
        $requestedEvidence = [];

        foreach ($this->pipeline->flowElements as $flowElement) {
            $requestedEvidence = array_merge($requestedEvidence, $flowElement->filterEvidence($this));
        }

        return $requestedEvidence;
    }

    /**
     * Stop processing any subsequent FlowElements.
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * Get data from FlowElement based on property metadata.
     *
     * @param mixed $metaValue
     * @return array<string, mixed>
     */
    public function getWhere(string $metaKey, $metaValue): array
    {
        $metaKey = strtolower($metaKey);
        $metaValue = strtolower($metaValue);

        $keys = [];

        if (isset($this->pipeline->propertyDatabase[$metaKey][$metaValue])) {
            foreach ($this->pipeline->propertyDatabase[$metaKey][$metaValue] as $key => $value) {
                $keys[$key] = $value['flowElement'];
            }
        }

        $output = [];

        foreach ($keys as $key => $flowElement) {
            try {
                $output[$key] = $this->get($flowElement)->get($key);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $output;
    }
}
