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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;

/**
 * Computes one property per script from properties other elements in the
 * pipeline have already produced, and writes them under the element data
 * key `derived`. The element holds no data file, sends no request, keeps
 * nothing between requests and reads no evidence.
 *
 * A script runs where every source property it names can be read, and
 * otherwise has no value, with a message naming every property that could
 * not be read and why.
 *
 * Built from a configuration file, the settings are `Scripts`, the names
 * of scripts this package carries, and `ScriptFiles`, paths to script
 * files where a file name may hold a wildcard. Each is a list, or one
 * string with commas between the entries, where an entry holding a comma
 * is wrapped in double quotes. DerivedPropertyElementBuilder adds scripts
 * in code.
 */
class DerivedPropertyElement extends FlowElement
{
    public const DATA_KEY = 'derived';

    private const NOT_PRESENT = 'property not present on this request';

    public string $dataKey = self::DATA_KEY;

    /**
     * @var array<DerivedScript>
     */
    private array $scripts;

    /**
     * The scripts whose missing element data has been logged, so it is
     * logged once rather than on every request.
     *
     * @var array<int, bool>
     */
    private array $reported = [];

    /**
     * @param array<string, mixed> $settings `Scripts` and `ScriptFiles`, as
     *     a pipeline configuration file gives them
     * @param array<DerivedScript> $scripts scripts already read, which come
     *     before those the settings name
     * @throws ScriptValidationException a script is not valid, or two
     *     scripts write the same property
     * @throws \InvalidArgumentException no script is configured, or a
     *     setting is not one the element has
     */
    public function __construct(array $settings = [], array $scripts = [])
    {
        parent::__construct();

        // A setting written wrongly would otherwise leave its scripts out
        // without a word.
        foreach (array_keys($settings) as $key) {
            if (strcasecmp((string) $key, 'Scripts') !== 0 &&
                strcasecmp((string) $key, 'ScriptFiles') !== 0) {
                throw new \InvalidArgumentException(sprintf(
                    "The derived property element has no setting '%s'. Its " .
                        'settings are Scripts and ScriptFiles.',
                    $key
                ));
            }
        }

        $set = new ScriptSet();
        foreach ($scripts as $script) {
            $set->add($script);
        }
        foreach (self::listSetting($settings, 'Scripts') as $name) {
            $set->addBuiltIn($name);
        }
        foreach (self::listSetting($settings, 'ScriptFiles') as $path) {
            $set->addFile($path);
        }
        if ($set->faults()) {
            throw new ScriptValidationException($set->faults());
        }
        if (!$set->scripts()) {
            throw new \InvalidArgumentException(
                'At least one script must be configured. Add one with ' .
                'addBuiltInScript, addScript or addScriptFile of ' .
                'DerivedPropertyElementBuilder, or with the Scripts and ' .
                'ScriptFiles settings.'
            );
        }
        $this->scripts = $set->scripts();
        self::checkOutputNamesAreUnique($this->scripts);

        foreach ($this->scripts as $script) {
            $this->properties[strtolower($script->outputName())] =
                self::metadata($script);
        }
    }

    /**
     * The scripts the element runs, in the order it runs them.
     *
     * @return array<DerivedScript>
     */
    public function getScripts(): array
    {
        return $this->scripts;
    }

    /**
     * Logs what each script produces, and refuses a pipeline that cannot
     * supply a property a script reads, or in which another element writes
     * a property a script writes.
     *
     * @throws PipelineConfigurationException
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        $this->logScripts($pipeline);
        $this->checkPipeline($pipeline);
    }

    public function processInternal(FlowData $flowData): void
    {
        // Another derived property element earlier in the pipeline shares
        // the element data, as the properties of both are under `derived`.
        $data = $flowData->data[self::DATA_KEY] ?? null;
        if (!($data instanceof DerivedPropertyData)) {
            $data = new DerivedPropertyData($this);
            $flowData->setElementData($data);
        }
        // Each value is written as soon as it is produced, so a later
        // script can read what an earlier one wrote.
        foreach ($this->scripts as $index => $script) {
            $slots = [];
            foreach ($script->properties as $property) {
                $slots[] = $this->readSlot($flowData, $property, $index);
            }
            $data->setValue($script->outputName(), $script->evaluate($slots));
        }
    }

    // -----------------------------------------------------------------
    // Settings and metadata.
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $settings
     * @return array<string>
     */
    private static function listSetting(array $settings, string $name): array
    {
        $value = null;
        foreach ($settings as $key => $setting) {
            if (strcasecmp((string) $key, $name) === 0) {
                $value = $setting;
                break;
            }
        }
        if ($value === null) {
            return [];
        }
        if (is_string($value)) {
            return trim($value) === '' ? [] : self::split($value);
        }
        if (is_array($value)) {
            $list = [];
            foreach ($value as $entry) {
                if (!is_string($entry)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The %s setting lists something that is not a string. ' .
                        'Give a list of strings, or one string with commas ' .
                        'between the entries.',
                        $name
                    ));
                }
                $list[] = $entry;
            }

            return $list;
        }

        throw new \InvalidArgumentException(sprintf(
            'The %s setting must be a list of strings, or one string with ' .
            'commas between the entries.',
            $name
        ));
    }

    /**
     * Splits a setting on its commas, where an entry wrapped in double
     * quotes may hold a comma, and two double quotes inside the quotes are
     * one.
     *
     * @return array<string>
     */
    private static function split(string $value): array
    {
        $entries = [];
        $current = '';
        $quoted = false;
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $character = $value[$i];
            if ($character === '"') {
                if ($quoted && $i + 1 < $length && $value[$i + 1] === '"') {
                    $current .= '"';
                    ++$i;
                } else {
                    $quoted = !$quoted;
                }
            } elseif ($character === ',' && !$quoted) {
                $entries[] = trim($current);
                $current = '';
            } else {
                $current .= $character;
            }
        }
        $entries[] = trim($current);

        return $entries;
    }

    /**
     * @param array<DerivedScript> $scripts
     */
    private static function checkOutputNamesAreUnique(array $scripts): void
    {
        $seen = [];
        $faults = [];
        foreach ($scripts as $script) {
            $key = strtolower($script->outputName());
            if (isset($seen[$key])) {
                $faults[] = new ScriptFault(
                    $script->name,
                    $script->source,
                    'Output.Name',
                    null,
                    sprintf(
                        "two scripts in the same element write the property " .
                            "'%s', being '%s' and '%s'. One element writes " .
                            'each property once',
                        $script->outputName(),
                        $seen[$key],
                        $script->name
                    )
                );
                continue;
            }
            $seen[$key] = $script->name;
        }
        if ($faults) {
            throw new ScriptValidationException($faults);
        }
    }

    /**
     * The property metadata of a script, in the shape the other elements
     * give, with the rest of the Output block carried through.
     *
     * @return array<string, mixed>
     */
    private static function metadata(DerivedScript $script): array
    {
        $output = $script->output;
        $metadata = [
            'name' => $output['Name'],
            'type' => self::typeName($output['ValueType']),
            'category' => $output['Category'] ?? '',
            'description' => $output['Description'],
            'available' => true,
            'isList' => false,
        ];
        foreach ($output as $key => $value) {
            if (in_array($key, ['Name', 'ValueType', 'Category', 'Description',
                'IsList', 'Values'], true) || $value === null) {
                continue;
            }
            $metadata[lcfirst($key)] = $value;
        }
        if ($output['Values'] !== null) {
            $metadata['values'] = array_map(
                fn (array $value) => [
                    'name' => $value['Name'],
                    'description' => $value['Description'],
                ],
                $output['Values']
            );
        }

        return $metadata;
    }

    /**
     * The type names on-premise Device Detection gives its properties.
     */
    private static function typeName(string $valueType): string
    {
        switch ($valueType) {
            case ValueConverter::BOOL:
                return 'Boolean';
            case ValueConverter::INT:
                return 'Integer';
            case ValueConverter::DOUBLE:
                return 'Double';
            default:
                return 'String';
        }
    }

    // -----------------------------------------------------------------
    // Building the pipeline.
    // -----------------------------------------------------------------

    private function logScripts(Pipeline $pipeline): void
    {
        foreach ($this->scripts as $script) {
            $pipeline->log('info', sprintf(
                "Derived property script '%s' version %s, format %d, from %s, " .
                    "produces '%s.%s' as %s.",
                $script->name,
                $script->version,
                $script->format,
                $script->source,
                self::DATA_KEY,
                $script->outputName(),
                $script->valueType()
            ));
            if ($script->deprecated) {
                $pipeline->log('warning', sprintf(
                    "Derived property script '%s' is deprecated. %s",
                    $script->name,
                    $script->deprecationNote
                ));
            }
            $pipeline->log('debug', sprintf(
                "Derived property script '%s' compiled to %s",
                $script->name,
                CanonicalJson::write($script)
            ));
        }
    }

    /**
     * Where an element sits is not judged, because the element may read a
     * property from an element added after it on purpose. Only the
     * elements whose data key a script names are asked about their
     * properties, so that building a pipeline, which PHP does on every
     * request, does not make an engine build a table of every property.
     */
    private function checkPipeline(Pipeline $pipeline): void
    {
        $others = array_values(array_filter(
            $pipeline->flowElements,
            fn (FlowElement $element) => $element !== $this
        ));
        $faults = [];

        foreach ($this->scripts as $script) {
            foreach ($others as $element) {
                if (strcasecmp($element->dataKey, self::DATA_KEY) === 0 &&
                    self::supplies($element, $script->outputName()) === true) {
                    $faults[] = sprintf(
                        "The script '%s' writes the derived property '%s', and " .
                            'another element in the pipeline already writes a ' .
                            'property of that name. One pipeline writes each ' .
                            'derived property once.',
                        $script->name,
                        $script->outputName()
                    );
                    break;
                }
            }
        }

        foreach ($this->scripts as $index => $script) {
            foreach ($script->properties as $property) {
                if ($this->isSupplied($others, $property, $index)) {
                    continue;
                }
                // Every property a script names is needed, so a pipeline
                // that cannot supply one would give no value on every
                // request. Failing the build says so where the mistake was
                // made.
                $faults[] = sprintf(
                    "The script '%s' needs the property '%s', and no element " .
                        "in the pipeline supplies it. Either add the element " .
                        "that supplies '%s', or change the script so that it " .
                        "does not name '%s'.",
                    $script->name,
                    $property['name'],
                    $property['name'],
                    $property['name']
                );
            }
        }

        if ($faults) {
            throw new PipelineConfigurationException(
                'The derived property element cannot be used in this pipeline.' .
                "\n" . implode("\n", $faults)
            );
        }
    }

    /**
     * @param array<FlowElement> $others
     * @param array<string, string> $property
     */
    private function isSupplied(array $others, array $property, int $index): bool
    {
        // A script can read what an earlier script of this element writes,
        // and nothing that a later one writes, as the scripts run in order.
        if (strcasecmp($property['elementKey'], self::DATA_KEY) === 0) {
            for ($i = 0; $i < $index; ++$i) {
                if (strcasecmp(
                    $this->scripts[$i]->outputName(),
                    $property['propertyName']
                ) === 0) {
                    return true;
                }
            }
        }
        foreach ($others as $element) {
            if (strcasecmp($element->dataKey, $property['elementKey']) === 0 &&
                self::supplies($element, $property['propertyName']) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an element gives a property, or null where it does not know
     * yet. A cloud engine learns its properties from the first response,
     * so an element listing no properties may give any of them.
     */
    private static function supplies(FlowElement $element, string $name): ?bool
    {
        try {
            // The on-premise engines find one property without building
            // the table of every property.
            if (method_exists($element, 'findProperty')) {
                return $element->findProperty($name) !== null;
            }
            $properties = $element->getProperties();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$properties) {
            return null;
        }
        foreach ($properties as $key => $metadata) {
            if (strcasecmp((string) $key, $name) === 0 ||
                (is_array($metadata) && isset($metadata['name']) &&
                    is_string($metadata['name']) &&
                    strcasecmp($metadata['name'], $name) === 0)) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------
    // Reading source properties.
    // -----------------------------------------------------------------

    /**
     * Reads one source property for one request as the type the script
     * inferred for it.
     *
     * @param array<string, string> $property
     * @return array{available: bool, value: mixed, reason: ?string}
     */
    private function readSlot(FlowData $flowData, array $property, int $index): array
    {
        $key = $property['elementKey'];
        $name = $property['propertyName'];

        $elementData = self::elementData($flowData, $key);
        if ($elementData === null) {
            $this->reportMissingElementData($flowData, $index, $property);

            return self::notAvailable($key, $name, self::NOT_PRESENT);
        }

        // PHP elements keep their properties under lower case names, so
        // that is tried first, and then the name as the script writes it.
        $raw = null;
        $error = null;
        foreach (array_unique([strtolower($name), $name]) as $candidate) {
            try {
                $raw = $elementData->get($candidate);
            } catch (\Throwable $e) {
                $error = $error ?? $e->getMessage();
                $raw = null;
            }
            if ($raw !== null) {
                break;
            }
        }
        if ($raw === null) {
            return self::notAvailable(
                $key,
                $name,
                $error === null || $error === '' ? self::NOT_PRESENT : $error
            );
        }

        // A source value with its own no value state hands its message on,
        // so the reason reaches back to the element that knows it.
        if ($raw instanceof AspectPropertyValue) {
            if (!$raw->hasValue) {
                return self::notAvailable(
                    $key,
                    $name,
                    $raw->noValueMessage === null || $raw->noValueMessage === ''
                        ? self::NOT_PRESENT
                        : $raw->noValueMessage
                );
            }
            $raw = $raw->value;
            if ($raw === null) {
                return self::notAvailable($key, $name, self::NOT_PRESENT);
            }
        }

        // A list of weighted values gives the value with the highest
        // weight. Any other list, where one value is needed, cannot be read.
        if (Missing::isList($raw)) {
            $heaviest = null;
            if (!self::heaviest($raw, $heaviest)) {
                return self::absent('held a list where a single value is needed');
            }
            $raw = $heaviest;
            if ($raw === null) {
                return self::notAvailable($key, $name, self::NOT_PRESENT);
            }
        }

        $value = null;
        if (!ValueConverter::convert($raw, $property['type'], $value)) {
            return self::absent(sprintf(
                "held '%s' which cannot be read as %s",
                ValueConverter::display($raw),
                $property['type']
            ));
        }

        return ['available' => true, 'value' => $value, 'reason' => null];
    }

    /**
     * The element data under a key, matched without regard to case.
     */
    private static function elementData(FlowData $flowData, string $key): ?ElementData
    {
        if (isset($flowData->data[$key])) {
            return $flowData->data[$key];
        }
        foreach ($flowData->data as $dataKey => $data) {
            if (strcasecmp((string) $dataKey, $key) === 0) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Takes the value of the member with the highest weight, the first
     * winning where two are equal. A member is a mapping with a value, and
     * the weight is `rawweighting`, as IP Intelligence gives it, or
     * `weighting` or `weight`.
     *
     * @param array<mixed> $list
     * @param mixed $value set to the heaviest member's value
     * @return bool false where the list is not a list of weighted values
     */
    private static function heaviest(array $list, &$value): bool
    {
        if (!$list) {
            return false;
        }
        $best = null;
        $bestWeight = null;
        foreach ($list as $member) {
            if ($member instanceof \stdClass) {
                $member = get_object_vars($member);
            }
            if (!is_array($member)) {
                return false;
            }
            $found = false;
            $memberValue = null;
            $weight = 0;
            foreach ($member as $field => $fieldValue) {
                $field = strtolower((string) $field);
                if ($field === 'value') {
                    $found = true;
                    $memberValue = $fieldValue;
                } elseif (in_array($field, ['rawweighting', 'weighting', 'weight'], true) &&
                    (is_int($fieldValue) || is_float($fieldValue))) {
                    $weight = $fieldValue;
                }
            }
            if (!$found) {
                return false;
            }
            if ($bestWeight === null || $weight > $bestWeight) {
                $best = $memberValue;
                $bestWeight = $weight;
            }
        }
        $value = $best;

        return true;
    }

    /**
     * @return array{available: bool, value: mixed, reason: ?string}
     */
    private static function notAvailable(string $key, string $name, string $detail): array
    {
        return self::absent(sprintf(
            "element '%s' has no value for '%s': %s",
            $key,
            $name,
            $detail
        ));
    }

    /**
     * @return array{available: bool, value: mixed, reason: ?string}
     */
    private static function absent(string $reason): array
    {
        return ['available' => false, 'value' => null, 'reason' => $reason];
    }

    /**
     * @param array<string, string> $property
     */
    private function reportMissingElementData(
        FlowData $flowData,
        int $index,
        array $property
    ): void {
        if (isset($this->reported[$index])) {
            return;
        }
        $this->reported[$index] = true;
        $flowData->pipeline->log('error', sprintf(
            "The script '%s' reads '%s', and the request carried no element " .
                "data '%s' at all, so the script has no value. The usual cause " .
                'is the element that supplies it running after this element ' .
                'rather than before it. This is reported once.',
            $this->scripts[$index]->name,
            $property['name'],
            $property['elementKey']
        ));
    }
}
