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

namespace fiftyone\pipeline\core\tests\classes;

/**
 * An element that accepts the Evidence keys it is given and counts the
 * times it is asked about each key, so a test can tell how often the
 * Pipeline asks it.
 */
class KeyCountingElement extends ListedEvidenceElement
{
    /**
     * The times the element was asked about each key, by the key in lower
     * case.
     *
     * @var array<string, int>
     */
    public array $checks = [];

    /**
     * @param array<string> $keys The Evidence keys the element accepts
     * @param string $dataKey The element's Data Key, so that a Pipeline can
     *   hold more than one
     */
    public function __construct(array $keys, string $dataKey = 'keycounting')
    {
        parent::__construct($keys);
        $this->dataKey = $dataKey;
    }

    public function filterEvidenceKey(string $key): bool
    {
        $lower = strtolower($key);
        $this->checks[$lower] = ($this->checks[$lower] ?? 0) + 1;

        return parent::filterEvidenceKey($key);
    }

    /**
     * The times the element was asked about any of the given keys.
     *
     * @param array<string> $keys
     */
    public function checksOf(array $keys): int
    {
        $total = 0;
        foreach ($keys as $key) {
            $total += $this->checks[strtolower($key)] ?? 0;
        }

        return $total;
    }
}
