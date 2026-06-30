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
     * @param string $sourceElementDataKey upstream element to read from
     * @param array<string, string> $propertyMap source => destination names
     * @param array<string, array<string, string>> $localeMaps locale => map
     * @param null|string $fixedLanguage a locale to always use, or null to
     *   resolve from evidence
     */
    public function __construct(
        string $elementDataKey,
        string $sourceElementDataKey,
        array $propertyMap,
        array $localeMaps,
        ?string $fixedLanguage = null,
        string $behavior = MissingTranslationBehavior::ORIGINAL
    ) {
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
     * order.
     */
    protected function getEvidenceValue(FlowData $flowData): ?string
    {
        $evidence = $flowData->evidence->getAll();
        foreach (self::EVIDENCE_KEYS as $key) {
            if (isset($evidence[$key]) && trim((string) $evidence[$key]) !== '') {
                return (string) $evidence[$key];
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
            $flowData->setError($error->getMessage(), $this);
        }

        if ($translated === null) {
            return new AspectPropertyValue('Translation produced no value.');
        }

        return new AspectPropertyValue(null, $translated);
    }
}
