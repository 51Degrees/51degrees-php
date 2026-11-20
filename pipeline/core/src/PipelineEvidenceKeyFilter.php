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
 * The Evidence a Pipeline accepts, being every key that at least one of its
 * FlowElements accepts. Each element is asked when a key is checked, so the
 * filter follows an element whose accepted Evidence changes.
 *
 * The Pipeline builds one when it is built. It is a class rather than a
 * closure because an element's data refers to its element, the element to
 * its Pipelines, and a session cache serializes that data, which a closure
 * would stop.
 *
 * @internal Built only by Pipeline
 */
final class PipelineEvidenceKeyFilter extends EvidenceKeyFilter
{
    /**
     * @var array<\fiftyone\pipeline\core\FlowElement>
     */
    private array $flowElements;

    /**
     * @param array<\fiftyone\pipeline\core\FlowElement> $flowElements The
     *   Pipeline's FlowElements
     */
    public function __construct(array $flowElements)
    {
        $this->flowElements = $flowElements;
    }

    /**
     * Refuses a filter function. Every caller shares this filter, so a
     * function set by one would change it for all of them, and a closure
     * would stop the Pipeline being serialized.
     *
     * @param mixed $value
     * @throws \Exception
     */
    public function __set(string $name, $value): void
    {
        throw new \Exception(sprintf(Messages::PIPELINE_IMMUTABLE, $name));
    }

    /**
     * Whether any of the Pipeline's FlowElements accepts the key, in any
     * letter case.
     */
    public function filterEvidenceKey(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->flowElements as $flowElement) {
            if ($flowElement->filterEvidenceKey($key)) {
                return true;
            }
        }

        return false;
    }
}
