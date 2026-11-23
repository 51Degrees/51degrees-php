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

namespace fiftyone\pipeline\ipintelligence\tests\classes;

/**
 * Passes each accessor call on to the native results and records the key it
 * was given. With a name set, every accessor is handed that name in place of
 * the key, so the same read goes by name.
 */
class RecordingResults
{
    /**
     * Each key an accessor was given, in call order.
     *
     * @var array<int|string>
     */
    public array $keys = [];

    /**
     * The name every accessor is handed in place of its key, or null to
     * pass the key on.
     */
    public ?string $name = null;

    private object $results;

    /**
     * @param object $results SWIG wrapped native results
     */
    public function __construct(object $results)
    {
        $this->results = $results;
    }

    /**
     * @param array<mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        $this->keys[] = $arguments[0];

        return $this->results->{$method}($this->name ?? $arguments[0]);
    }
}
