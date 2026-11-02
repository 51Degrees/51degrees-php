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

namespace fiftyone\pipeline\engines;

/**
 * A simple cache class which has a get and set method
 * Can be added to an engine to cache its results for specific evidence
 * This base class needs to be extended to form an actual cache.
 */
class DataKeyedCache
{
    /**
     * Get an item from the cache.
     * This method needs to be implemented in a subclass to work.
     *
     * @param string|array<string, mixed> $cacheKey
     * @return mixed
     */
    public function get($cacheKey) {}

    /**
     * Add a value to the cache
     * This method needs to be implemented in a subclass to work.
     *
     * @param string|array<string, mixed> $cacheKey
     * @param mixed $value
     */
    public function set($cacheKey, $value): void {}
}
