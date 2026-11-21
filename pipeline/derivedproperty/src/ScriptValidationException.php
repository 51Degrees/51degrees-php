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

namespace fiftyone\pipeline\derivedproperty;

/**
 * Raised when the element is built with scripts that are not valid. Every
 * fault in every script is carried, so one build shows everything that is
 * wrong.
 */
class ScriptValidationException extends \InvalidArgumentException
{
    /**
     * @var array<ScriptFault>
     */
    private array $faults;

    /**
     * @param array<ScriptFault> $faults
     */
    public function __construct(array $faults)
    {
        $this->faults = array_values($faults);
        parent::__construct(
            'The derived property scripts are not valid.' . PHP_EOL .
            implode(PHP_EOL, array_map('strval', $this->faults))
        );
    }

    /**
     * @return array<ScriptFault>
     */
    public function getFaults(): array
    {
        return $this->faults;
    }
}
