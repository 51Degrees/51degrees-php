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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

/**
 * Generic translation engine. Reads one or more properties from an upstream
 * element, translates each value (string, list of strings, or weighted list
 * of strings) into the language resolved from request evidence, and writes
 * the translated values under this engine's element data key.
 *
 * The locale is resolved from the evidence keys (in precedence order)
 * query.translation, query.accept-language, header.accept-language, unless a
 * fixed language is configured. See Languages::tryResolveLocale for the
 * resolution rules, including the English short-circuit.
 */
class TranslationEngine extends Engine
{
    public string $dataKey;

    // Evidence keys checked, in precedence order, to resolve the locale.
    public const EVIDENCE_KEYS = [
        'query.translation',
        'query.accept-language',
        'header.accept-language',
    ];

    /**
     * @var array<string, string> source property name => destination name
     */
    protected array $propertyMap;

    protected string $sourceElementDataKey;

    protected ?string $fixedLanguage;

    protected string $behavior;

    /**
     * @var array<string, Translator> locale => translator
     */
    protected array $translators = [];

    /**
     * @var array<string>
     */
    protected array $availableLocales = [];

    protected Translator $emptyTranslator;

    /**
     * The element data key of an engine built from settings, the key the
     * .NET Translation Engine uses.
     */
    public const DEFAULT_ELEMENT_DATA_KEY = 'translation';

    /**
     * Builds the engine from its arguments, or from the settings array that
     * PipelineBuilder::buildFromConfig passes every element it builds from a
     * configuration file.
     *
     * The settings use the specification's option names,
     * SourceElementDataKey (required), FixedLanguage and
     * MissingTranslationBehavior (Original, EmptyString or FlowError, in any
     * case), or the same names in camelCase, the style the settings of other
     * PHP elements use. This engine does not read sources and translations
     * from settings, so an engine built from settings has none and returns no
     * properties. A subclass that adds its own is the way to build a useful
     * one from a configuration file.
     *
     * @param string|array<string, mixed> $elementDataKey this engine's element
     *   data key, or the settings
     * @param null|string $sourceElementDataKey upstream element to read from
     * @param array<string, string> $propertyMap source => destination names
     * @param array<string, array<string, string>> $localeMaps locale => map
     * @param null|string $fixedLanguage a locale to always use, or null to
     *   resolve from evidence
     * @param string $missingTranslationBehavior what a value with no
     *   translation gives, one of the MissingTranslationBehavior values
     * @param null|string $behavior deprecated, use $missingTranslationBehavior.
     *   Used in its place when given.
     * @throws \InvalidArgumentException when no source element data key is
     *   given, or the settings name a missing translation behavior that is
     *   not one of the three
     */
    public function __construct(
        string|array $elementDataKey = [],
        ?string $sourceElementDataKey = null,
        array $propertyMap = [],
        array $localeMaps = [],
        ?string $fixedLanguage = null,
        string $missingTranslationBehavior = MissingTranslationBehavior::ORIGINAL,
        ?string $behavior = null
    ) {
        $behavior ??= $missingTranslationBehavior;
        if (is_array($elementDataKey)) {
            $settings = $elementDataKey;
            $elementDataKey = self::DEFAULT_ELEMENT_DATA_KEY;
            $sourceElementDataKey = self::stringSetting($settings, 'SourceElementDataKey');
            $fixedLanguage = self::stringSetting($settings, 'FixedLanguage');
            $named = self::stringSetting($settings, 'MissingTranslationBehavior');
            $behavior = $named === null
                ? MissingTranslationBehavior::ORIGINAL
                : MissingTranslationBehavior::fromName($named);
        }
        if ($sourceElementDataKey === null) {
            throw new \InvalidArgumentException(
                'The Translation Engine needs a source element data key, ' .
                'the SourceElementDataKey setting.'
            );
        }

        $this->pipelines = [];

        $this->dataKey = $elementDataKey;
        $this->sourceElementDataKey = $sourceElementDataKey;
        $this->propertyMap = $propertyMap;
        $this->fixedLanguage = $fixedLanguage;
        $this->behavior = $behavior;

        foreach ($localeMaps as $locale => $map) {
            $this->translators[$locale] = new Translator($map, $behavior);
            $this->availableLocales[] = $locale;
        }
        $this->emptyTranslator = new Translator([], $behavior);

        // Declare the destination properties so they can be read back.
        $properties = [];
        foreach ($propertyMap as $destination) {
            $properties[strtolower($destination)] = [
                'name' => $destination,
                'type' => 'Array',
            ];
        }
        $this->properties = $properties;

        parent::__construct();
    }

    /**
     * A text setting by the specification's name, or by the same name in
     * camelCase. Null when neither is given or the value is empty.
     *
     * @param array<string, mixed> $settings
     * @throws \InvalidArgumentException when the value is not text
     */
    private static function stringSetting(array $settings, string $name): ?string
    {
        $value = $settings[$name] ?? $settings[lcfirst($name)] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                "The Translation Engine's {$name} setting must be text."
            );
        }

        return $value;
    }

    public function getEvidenceKeyFilter(): BasicListEvidenceKeyFilter
    {
        return new BasicListEvidenceKeyFilter(self::EVIDENCE_KEYS);
    }

    public function processInternal(FlowData $flowData): void
    {
        $resolved = $this->resolveTranslator($flowData);
        $result = $this->buildTranslatedProperties($flowData, $resolved['translator']);

        $flowData->setElementData(new AspectDataDictionary($this, $result));
    }

    /**
     * Translate every mapped property and return the result keyed by
     * lower-cased destination property name. Subclasses can call this, add
     * further properties, and then set the element data themselves.
     *
     * @return array<string, AspectPropertyValue>
     */
    protected function buildTranslatedProperties(FlowData $flowData, Translator $translator): array
    {
        $source = $flowData->get($this->sourceElementDataKey);

        $result = [];
        foreach ($this->propertyMap as $sourceProperty => $destinationProperty) {
            $result[strtolower($destinationProperty)] =
                $this->translateProperty($source, $sourceProperty, $translator, $flowData);
        }

        return $result;
    }

    /**
     * Resolve the translator and locale to use for this request.
     *
     * @return array{translator: Translator, locale: null|string}
     */
    protected function resolveTranslator(FlowData $flowData): array
    {
        if ($this->fixedLanguage !== null) {
            $translator = $this->translators[$this->fixedLanguage] ?? $this->emptyTranslator;

            return ['translator' => $translator, 'locale' => $this->fixedLanguage];
        }

        $acceptLanguage = $this->getEvidenceValue($flowData);
        $matched = null;
        if (Languages::tryResolveLocale($acceptLanguage, $this->availableLocales, $matched) && $matched !== null) {
            return ['translator' => $this->translators[$matched], 'locale' => $matched];
        }

        // English short-circuit or no match: leave values untranslated.
        return ['translator' => $this->emptyTranslator, 'locale' => null];
    }

    /**
     * Read the first non-blank translation evidence value, in precedence
     * order. Each key is read through the evidence, which matches without
     * regard to letter case, because a key arrives in the letters it was
     * first set with.
     */
    protected function getEvidenceValue(FlowData $flowData): ?string
    {
        foreach (self::EVIDENCE_KEYS as $key) {
            $value = $flowData->evidence->get($key);
            if ($value !== null && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Translate a single source property into an AspectPropertyValue.
     *
     * @param mixed $source upstream element data
     */
    private function translateProperty($source, string $sourceProperty, Translator $translator, FlowData $flowData): AspectPropertyValue
    {
        try {
            $apv = $source->get(strtolower($sourceProperty));
        } catch (\Throwable $e) {
            return new AspectPropertyValue($e->getMessage());
        }

        if (!($apv instanceof AspectPropertyValue) || !$apv->hasValue) {
            $message = ($apv instanceof AspectPropertyValue && $apv->noValueMessage !== null)
                ? $apv->noValueMessage
                : sprintf("No value for source property '%s'.", $sourceProperty);

            return new AspectPropertyValue($message);
        }

        $errors = [];
        $translated = $translator->translate($apv->value, $errors);
        foreach ($errors as $error) {
            $flowData->setError($this->dataKey, $error);
        }

        if ($translated === null) {
            return new AspectPropertyValue('Translation produced no value.');
        }

        return new AspectPropertyValue(null, $translated);
    }
}
