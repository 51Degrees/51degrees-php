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

namespace fiftyone\pipeline\devicedetection\tests\classes;

/**
 * Passes each read on to the native results and records it in the engine
 * that gave them. It extends the SWIG class because the engine's process
 * returns one, and it overrides every accessor SwigData calls, because an
 * accessor it does not override has no native object to work on.
 */
class RecordingResults extends \ResultsHashSwig
{
    private \ResultsHashSwig $results;

    private RecordingEngine $engine;

    public function __construct(\ResultsHashSwig $results, RecordingEngine $engine)
    {
        $this->results = $results;
        $this->engine = $engine;
    }

    public function getValueAsBool($key): \BoolValueSwig
    {
        $this->engine->reads[] = ['getValueAsBool', $key];

        return $this->results->getValueAsBool($key);
    }

    public function getValueAsString($key): \StringValueSwig
    {
        $this->engine->reads[] = ['getValueAsString', $key];

        return $this->results->getValueAsString($key);
    }

    public function getValueAsInteger($key): \IntegerValueSwig
    {
        $this->engine->reads[] = ['getValueAsInteger', $key];

        return $this->results->getValueAsInteger($key);
    }

    public function getValueAsDouble($key): \DoubleValueSwig
    {
        $this->engine->reads[] = ['getValueAsDouble', $key];

        return $this->results->getValueAsDouble($key);
    }

    public function getValues($key): \VectorStringValuesSwig
    {
        $this->engine->reads[] = ['getValues', $key];

        return $this->results->getValues($key);
    }

    public function getDeviceId($resultIndex = null): string
    {
        $this->engine->reads[] = ['getDeviceId', $resultIndex];

        return $resultIndex === null
            ? $this->results->getDeviceId()
            : $this->results->getDeviceId($resultIndex);
    }

    public function getDifference($resultIndex = null): int
    {
        $this->engine->reads[] = ['getDifference', $resultIndex];

        return $resultIndex === null
            ? $this->results->getDifference()
            : $this->results->getDifference($resultIndex);
    }

    public function getDrift($resultIndex = null): int
    {
        $this->engine->reads[] = ['getDrift', $resultIndex];

        return $resultIndex === null
            ? $this->results->getDrift()
            : $this->results->getDrift($resultIndex);
    }

    public function getMethod($resultIndex = null): int
    {
        $this->engine->reads[] = ['getMethod', $resultIndex];

        return $resultIndex === null
            ? $this->results->getMethod()
            : $this->results->getMethod($resultIndex);
    }

    public function getMatchedNodes(): int
    {
        $this->engine->reads[] = ['getMatchedNodes', null];

        return $this->results->getMatchedNodes();
    }

    public function getIterations(): int
    {
        $this->engine->reads[] = ['getIterations', null];

        return $this->results->getIterations();
    }

    public function getUserAgents(): int
    {
        $this->engine->reads[] = ['getUserAgents', null];

        return $this->results->getUserAgents();
    }

    public function getUserAgent($resultIndex): string
    {
        $this->engine->reads[] = ['getUserAgent', $resultIndex];

        return $this->results->getUserAgent($resultIndex);
    }
}
