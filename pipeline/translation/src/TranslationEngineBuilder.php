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

namespace fiftyone\pipeline\translation;

/**
 * Builds a TranslationEngine in code. Each method returns the builder, so
 * the calls chain, and build() gives the engine.
 *
 * A source is a YAML file of "source: translation" lines for one language,
 * named [name].[locale].yml or [name].[locale].yaml, such as
 * countries.fr_FR.yml. The locale in the name is the language the file
 * holds, and all the sources for one language are used together.
 */
class TranslationEngineBuilder
{
    private const SOURCE_EXTENSIONS = ['yml', 'yaml'];

    private string $elementDataKey = TranslationEngine::DEFAULT_ELEMENT_DATA_KEY;

    private ?string $sourceElementDataKey = null;

    /**
     * @var array<string, string> source property => destination property
     */
    private array $translations = [];

    /**
     * @var array<string, array<string, string>> locale => source value =>
     *   translation
     */
    private array $localeMaps = [];

    private ?string $fixedLanguage = null;

    private string $missingTranslationBehavior = MissingTranslationBehavior::ORIGINAL;

    /**
     * Sets the Data Key of the element whose values are translated, such
     * as ip. Required.
     */
    public function setSourceElementDataKey(string $sourceElementDataKey): self
    {
        $this->sourceElementDataKey = $sourceElementDataKey;

        return $this;
    }

    /**
     * Sets the engine's own Data Key, which is translation unless set.
     * Translation Engines with the same key add their Properties to one
     * Element Data, so engines whose Properties are to be read apart each
     * need their own.
     */
    public function setElementDataKey(string $elementDataKey): self
    {
        $this->elementDataKey = $elementDataKey;

        return $this;
    }

    /**
     * Adds a source. Given a path alone, the file is read from disk, and a
     * path with a * or ? wildcard adds every file it matches. A relative
     * path is resolved against the working directory. Given contents too,
     * the first argument is the source's name and nothing is read.
     *
     * @throws \InvalidArgumentException when a source is not named
     *   [name].[locale].yml or .yaml, a file cannot be read, or a wildcard
     *   matches no file
     */
    public function addSource(string $path, ?string $contents = null): self
    {
        if ($contents !== null) {
            return $this->addContents($path, $contents);
        }

        if (strpbrk($path, '*?') === false) {
            return $this->addFile($path);
        }

        $files = array_filter(glob($path) ?: [], 'is_file');
        if (count($files) === 0) {
            throw new \InvalidArgumentException(sprintf(
                "No translation source matches '%s'. A relative path is " .
                "resolved against the working directory, which is '%s'.",
                $path,
                (string) getcwd()
            ));
        }
        foreach ($files as $file) {
            $this->addFile($file);
        }

        return $this;
    }

    /**
     * Translates the value of a property of the source element into a
     * property of this engine.
     *
     * @param string $source the source element's property, such as Country
     * @param string $destination this engine's property for the
     *   translation, such as CountryTranslated
     * @throws \InvalidArgumentException when the source property already
     *   has a destination
     */
    public function addTranslation(string $source, string $destination): self
    {
        if (isset($this->translations[$source])) {
            throw new \InvalidArgumentException(sprintf(
                "The source property '%s' is already translated into '%s', " .
                "and an engine keeps one destination for each source " .
                "property. Build a second engine to translate it into '%s'.",
                $source,
                $this->translations[$source],
                $destination
            ));
        }
        $this->translations[$source] = $destination;

        return $this;
    }

    /**
     * Sets the locale, such as en_GB, to always translate into. Unless it
     * is set, the locale comes from the Evidence of each request.
     */
    public function setFixedLanguage(string $language): self
    {
        $this->fixedLanguage = $language;

        return $this;
    }

    /**
     * Sets what a value with no translation gives, being
     * MissingTranslationBehavior::ORIGINAL (the default), EMPTY_STRING or
     * FLOW_ERROR, or the same names in any case.
     *
     * @throws \InvalidArgumentException when the behavior is not one of the
     *   three
     */
    public function setMissingTranslationBehavior(string $behavior): self
    {
        $this->missingTranslationBehavior = MissingTranslationBehavior::fromName($behavior);

        return $this;
    }

    /**
     * Builds the engine.
     *
     * @throws \InvalidArgumentException when the engine would translate
     *   nothing, because it has no source element, no translation or no
     *   source, or no source for the fixed language
     */
    public function build(): TranslationEngine
    {
        if ($this->sourceElementDataKey === null) {
            throw new \InvalidArgumentException(
                'The Translation Engine needs the Data Key of the element ' .
                'whose values it translates. Call setSourceElementDataKey, ' .
                'for example with ip.'
            );
        }
        if (count($this->translations) === 0) {
            throw new \InvalidArgumentException(
                'The Translation Engine has no property to translate. Call ' .
                'addTranslation with a property of the source element and ' .
                'the property to put its translation in.'
            );
        }
        if (count($this->localeMaps) === 0) {
            throw new \InvalidArgumentException(
                'The Translation Engine has no source, so it can translate ' .
                'nothing. Call addSource with a file such as ' .
                'countries.fr_FR.yml.'
            );
        }
        if (
            $this->fixedLanguage !== null &&
            !isset($this->localeMaps[$this->fixedLanguage])
        ) {
            throw new \InvalidArgumentException(sprintf(
                "The fixed language '%s' has no source, so the Translation " .
                'Engine would translate nothing. Add a source named ' .
                '[name].%s.yml, or give one of the languages the sources ' .
                'hold, being %s.',
                $this->fixedLanguage,
                $this->fixedLanguage,
                implode(', ', array_keys($this->localeMaps))
            ));
        }

        return new TranslationEngine(
            $this->elementDataKey,
            $this->sourceElementDataKey,
            $this->translations,
            $this->localeMaps,
            $this->fixedLanguage,
            $this->missingTranslationBehavior
        );
    }

    /**
     * Adds the file at a path as a source named by the file's name.
     *
     * @throws \InvalidArgumentException when the file cannot be read
     */
    private function addFile(string $path): self
    {
        $contents = is_file($path) && is_readable($path)
            ? file_get_contents($path)
            : false;
        if ($contents === false) {
            throw new \InvalidArgumentException(sprintf(
                "The translation source '%s' cannot be read. A relative " .
                "path is resolved against the working directory, which " .
                "is '%s'.",
                $path,
                (string) getcwd()
            ));
        }

        return $this->addContents(basename($path), $contents);
    }

    /**
     * Adds a source's lines to the translations of the locale its name
     * gives.
     *
     * @throws \InvalidArgumentException when the name gives no locale or is
     *   not a .yml or .yaml file
     */
    private function addContents(string $name, string $contents): self
    {
        $locale = Languages::localeFromFilename($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (
            $locale === null ||
            !in_array($extension, self::SOURCE_EXTENSIONS, true)
        ) {
            throw new \InvalidArgumentException(sprintf(
                "The translation source '%s' is not named " .
                '[name].[locale].yml or [name].[locale].yaml, such as ' .
                'countries.fr_FR.yml, so the language it holds is not known.',
                $name
            ));
        }

        // array_replace rather than array_merge, which would renumber a
        // source value that is a number.
        $this->localeMaps[$locale] = array_replace(
            $this->localeMaps[$locale] ?? [],
            YamlMap::parse($contents)
        );

        return $this;
    }
}
