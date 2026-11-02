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
 * Stands in for a SWIG wrapped Value, which is what every ResultsIpi
 * accessor returns.
 */
class StubSwigValue
{
    private bool $hasValue;

    /**
     * @var mixed
     */
    private $value;

    private string $noValueMessage;

    /**
     * @param mixed $value
     */
    public function __construct(bool $hasValue, $value, string $noValueMessage = 'No value for this IP.')
    {
        $this->hasValue = $hasValue;
        $this->value = $value;
        $this->noValueMessage = $noValueMessage;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getNoValueMessage(): string
    {
        return $this->noValueMessage;
    }
}
