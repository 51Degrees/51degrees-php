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

/**
 * A script that has been read and validated, and that can be run for one
 * request once its source properties have been read.
 *
 * A condition is an array with a `kind` of `compare`, `check`,
 * `aggregate`, `all`, `any` or `not`. ScriptValidator builds them, and
 * nothing else should.
 */
final class DerivedScript
{
    /**
     * The sentence that ends every no value message, naming what usually
     * causes a source property to be missing.
     */
    public const USUAL_CAUSES =
        'Usual causes are the element that supplies the property not being in ' .
        'the pipeline, or being added after this element rather than before it, ' .
        'the property being excluded in the engine configuration, the property ' .
        'not being included in the resource key, or JavaScript that populates ' .
        'the property not having run yet.';

    public int $format = 1;

    public string $name;

    public string $version;

    public bool $deprecated = false;

    public ?string $deprecationNote = null;

    /**
     * Where the script came from, being `built in <Name>`, a file path or
     * `code`.
     */
    public string $source;

    /**
     * The Output block with PascalCase keys, including the computed
     * Dependencies, and Values as a list of Name and Description pairs.
     *
     * @var array<string, mixed>
     */
    public array $output;

    /**
     * Every source property the script names, in the order the script
     * first names them, checks before rules. Each has `key` (the name in
     * lower case), `name` (as first written), `type`, `elementKey` and
     * `propertyName`.
     *
     * @var array<int, array<string, string>>
     */
    public array $properties = [];

    /**
     * @var array<int, array{name: string, condition: array<string, mixed>}>
     */
    public array $checks = [];

    /**
     * Each rule has `when`, a condition or null for the Else, and `value`.
     *
     * @var array<int, array{when: ?array<string, mixed>, value: mixed}>
     */
    public array $rules = [];

    public function outputName(): string
    {
        return $this->output['Name'];
    }

    public function valueType(): string
    {
        return $this->output['ValueType'];
    }

    /**
     * Runs the script for one request.
     *
     * @param array<int, array{available: bool, value: mixed, reason: ?string}> $slots
     *     the source properties read for this request, in the order of
     *     `properties`
     * @param null|int $matched set to the index of the rule that matched,
     *     or null where the script has no value
     */
    public function evaluate(array $slots, ?int &$matched = null): AspectPropertyValue
    {
        $matched = null;

        // Every source property is read before anything is evaluated, and
        // where any one of them is missing the script says so rather than
        // answering on part of the evidence.
        $missing = [];
        foreach ($this->properties as $i => $property) {
            if (!$slots[$i]['available']) {
                $missing[] = sprintf(
                    "'%s' (%s).",
                    $property['name'],
                    $slots[$i]['reason']
                );
            }
        }
        if ($missing) {
            return new AspectPropertyValue($this->missingMessage($missing));
        }

        $checks = [];
        foreach ($this->checks as $i => $check) {
            $checks[$i] = $this->test($check['condition'], $slots, $checks);
        }

        foreach ($this->rules as $i => $rule) {
            // An Else has no condition and always matches.
            if ($rule['when'] === null ||
                $this->test($rule['when'], $slots, $checks)) {
                $matched = $i;
                $value = new AspectPropertyValue();
                $value->value = $rule['value'];

                return $value;
            }
        }

        // The validator makes every script end in an Else, so the loop
        // always returns.
        throw new \LogicException(sprintf(
            "the rules of '%s' do not end in an Else, which format 1 does " .
            'not allow',
            $this->outputName()
        ));
    }

    /**
     * @param array<int, string> $details
     */
    private function missingMessage(array $details): string
    {
        $count = count($details);

        return sprintf(
            "Derived property '%s' has no value because %s. %s %s",
            $this->outputName(),
            $count === 1
                ? '1 source property was not available'
                : $count . ' source properties were not available',
            implode(' ', $details),
            self::USUAL_CAUSES
        );
    }

    /**
     * Every condition is true or false, because conditions only run once
     * every source property has been read.
     *
     * @param array<string, mixed> $condition
     * @param array<int, array{available: bool, value: mixed, reason: ?string}> $slots
     * @param array<int, bool> $checks the checks already evaluated
     */
    private function test(array $condition, array $slots, array $checks): bool
    {
        switch ($condition['kind']) {
            case 'compare':
                return self::compare(
                    $slots[$condition['slot']]['value'],
                    $condition['op'],
                    $condition['value']
                );
            case 'check':
                // A check that names a check defined after it reads a check
                // not yet evaluated, which is false.
                return $checks[$condition['index']] ?? false;
            case 'aggregate':
                return self::compare(
                    $this->count($condition['agg'], $condition['group'], $checks),
                    $condition['op'],
                    $condition['value']
                );
            case 'all':
                foreach ($condition['items'] as $item) {
                    if (!$this->test($item, $slots, $checks)) {
                        return false;
                    }
                }

                return true;
            case 'any':
                foreach ($condition['items'] as $item) {
                    if ($this->test($item, $slots, $checks)) {
                        return true;
                    }
                }

                return false;
            case 'not':
                return !$this->test($condition['item'], $slots, $checks);
            default:
                throw new \LogicException(
                    "unknown condition kind '" . $condition['kind'] . "'"
                );
        }
    }

    /**
     * Counts the checks in a group that passed or failed. A null group is
     * every check. A check not yet evaluated, which only a check counting
     * other checks can meet, is counted as neither.
     *
     * @param null|array<int, int> $group
     * @param array<int, bool> $checks
     */
    private function count(string $agg, ?array $group, array $checks): int
    {
        $indexes = $group ?? array_keys($checks);
        $count = 0;
        foreach ($indexes as $index) {
            if (($checks[$index] ?? null) === ($agg === 'Passed')) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Both sides are already of the inferred type, so a strict comparison
     * is the comparison the format defines. Strings compare ordinally and
     * with regard to letter case.
     *
     * @param mixed $left
     * @param mixed $right
     */
    private static function compare($left, string $op, $right): bool
    {
        switch ($op) {
            case 'Eq':
                return $left === $right;
            case 'Ne':
                return $left !== $right;
            case 'Gt':
                return $left > $right;
            case 'Ge':
                return $left >= $right;
            case 'Lt':
                return $left < $right;
            case 'Le':
                return $left <= $right;
            case 'In':
                return in_array($left, $right, true);
            case 'NotIn':
                return !in_array($left, $right, true);
            case 'StartsWith':
                return str_starts_with($left, $right);
            case 'EndsWith':
                return str_ends_with($left, $right);
            case 'Contains':
                return str_contains($left, $right);
            default:
                throw new \LogicException("unknown operator '$op'");
        }
    }
}
