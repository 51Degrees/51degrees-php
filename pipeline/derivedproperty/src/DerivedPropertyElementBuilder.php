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
 * Builds a DerivedPropertyElement from scripts this package carries, script
 * files and script text held by code. The element runs the scripts in the
 * order they were added. Every fault in every script is reported together
 * when build is called.
 */
class DerivedPropertyElementBuilder
{
    private ScriptSet $scripts;

    public function __construct()
    {
        $this->scripts = new ScriptSet();
    }

    /**
     * Adds a script this package carries, by its name, such as
     * `HumanConfidence`. BuiltInScripts::names() lists them.
     */
    public function addBuiltInScript(string $name): self
    {
        $this->scripts->addBuiltIn($name);

        return $this;
    }

    /**
     * Adds a script file, or every file a wildcard in the file name matches,
     * such as `derived/*.yaml`. A script's Name must equal its file name
     * without the extension.
     */
    public function addScriptFile(string $path): self
    {
        $this->scripts->addFile($path);

        return $this;
    }

    /**
     * Adds the text of a script held by code, as YAML or as JSON. The
     * script's Name must equal the name given.
     */
    public function addScript(string $name, string $text): self
    {
        $this->scripts->addText($name, $text);

        return $this;
    }

    /**
     * @throws ScriptValidationException a script is not valid
     * @throws \InvalidArgumentException no script was added
     */
    public function build(): DerivedPropertyElement
    {
        if ($this->scripts->faults()) {
            throw new ScriptValidationException($this->scripts->faults());
        }

        return new DerivedPropertyElement([], $this->scripts->scripts());
    }
}
