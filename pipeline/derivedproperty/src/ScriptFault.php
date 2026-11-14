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
 * One thing wrong with a script, naming the script, where it came from, the
 * place in the document such as `Rules[3].When.All[1]`, and the line where
 * the parser knows it.
 */
final class ScriptFault
{
    /**
     * The file name the script was expected to have, or null where none was
     * given.
     */
    public ?string $script;

    /**
     * Where the script came from, being a built in name, a file path or
     * `code`.
     */
    public string $source;

    /**
     * The place in the document, or an empty string for the document as a
     * whole.
     */
    public string $path;

    public ?int $line;

    public string $message;

    public function __construct(
        ?string $script,
        string $source,
        string $path,
        ?int $line,
        string $message
    ) {
        $this->script = $script;
        $this->source = $source;
        $this->path = $path;
        $this->line = $line;
        $this->message = $message;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (%s)%s at %s: %s',
            $this->script ?? 'script',
            $this->source,
            $this->line === null ? '' : ' line ' . $this->line,
            $this->path === '' ? '(document)' : $this->path,
            $this->message
        );
    }
}
