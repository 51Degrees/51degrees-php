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

namespace fiftyone\pipeline\derivedproperty\tests\classes;

use fiftyone\pipeline\core\Logger;

/**
 * Keeps every line logged at or above its level.
 */
class RecordingLogger extends Logger
{
    /**
     * @var array<int, array<string, string>>
     */
    public array $lines = [];

    public function __construct(string $level = 'trace')
    {
        parent::__construct($level);
    }

    /**
     * @param array<string, string> $log
     */
    public function logInternal(array $log): void
    {
        $this->lines[] = $log;
    }

    /**
     * The messages logged at one level.
     *
     * @return array<string>
     */
    public function messages(string $level): array
    {
        return array_values(array_map(
            fn (array $line) => $line['message'],
            array_filter($this->lines, fn (array $line) => $line['level'] === $level)
        ));
    }
}
