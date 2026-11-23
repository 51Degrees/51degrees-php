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
 * Every error kept under one key of FlowData::$errors, in the order each was
 * added. The message holds each error's message on a line of its own, and
 * the previous exception is the first error.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/exception-handling.md
 */
class AggregateException extends \Exception
{
    /**
     * @var array<\Throwable>
     */
    public array $errors;

    /**
     * @param array<\Throwable> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);
        $messages = array_map(
            static fn (\Throwable $error): string => $error->getMessage(),
            $this->errors
        );

        parent::__construct(
            implode("\n", $messages),
            0,
            $this->errors[0] ?? null
        );
    }
}
