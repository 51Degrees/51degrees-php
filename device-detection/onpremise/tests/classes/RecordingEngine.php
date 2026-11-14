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
 * Passes each call on to a native engine and records the calls into it, so
 * a test can see what crosses into native code. Its results record each
 * value read from them. It extends the SWIG class because the engine class
 * holds an \EngineHashSwig, and it overrides every method the engine class
 * calls in a request, because a method it does not override has no
 * native object to work on.
 */
class RecordingEngine extends \EngineHashSwig
{
    /**
     * Each value read from a result, in call order, as the accessor and
     * the key it was given.
     *
     * @var array<array{string, null|int|string}>
     */
    public array $reads = [];

    /**
     * How many times getMetaData was called.
     */
    public int $metaDataReads = 0;

    /**
     * How many times getRequiredProperties was called.
     */
    public int $requiredPropertiesReads = 0;

    private \EngineHashSwig $engine;

    public function __construct(\EngineHashSwig $engine)
    {
        $this->engine = $engine;
    }

    public function process($evidence): ?\ResultsHashSwig
    {
        $results = $this->engine->process($evidence);

        return $results === null ? null : new RecordingResults($results, $this);
    }

    public function getMetaData(): ?\MetaDataSwig
    {
        ++$this->metaDataReads;

        return $this->engine->getMetaData();
    }

    public function getRequiredProperties(): \VectorStringSwig
    {
        ++$this->requiredPropertiesReads;

        return $this->engine->getRequiredProperties();
    }
}
