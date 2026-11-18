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

namespace fiftyone\pipeline\devicedetection\tests;

use PHPUnit\Framework\TestCase;

/**
 * Fails when a test or an example creates a FlowData or a pipeline without
 * closing it in a finally block. The results of an on-premise engine hold
 * the engine's data in memory until they are released, and PHP frees a
 * FlowData that is not closed only when its cycle collector runs, because a
 * FlowData refers to itself through its evidence.
 *
 * The same check is in each package with an on-premise engine. It reads the
 * code rather than running it, because PHP cannot list the objects that are
 * still alive.
 */
class ResourceCleanupTests extends TestCase
{
    /**
     * The folders of this package whose code must close what it creates.
     */
    private const FOLDERS = ['examples', 'tests', 'performance-tests', 'configuration-tests'];

    /**
     * Engine classes that load their own data, and so must be closed when
     * they are built outside a pipeline.
     */
    private const ENGINES = [];

    /**
     * When set, only files containing this text are read, so that the code
     * of other engines in the same package is left out.
     */
    private const ONLY_FILES_WITH = '';

    public function testEveryFlowDataAndPipelineIsClosed()
    {
        $problems = [];
        $root = dirname(__DIR__);
        foreach (self::FOLDERS as $folder) {
            $path = $root . '/' . $folder;
            if (!is_dir($path)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' &&
                    strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
                    $code = file_get_contents($file->getPathname());
                    if (self::ONLY_FILES_WITH !== '' && strpos($code, self::ONLY_FILES_WITH) === false) {
                        continue;
                    }
                    $name = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                    foreach (self::check($code) as $problem) {
                        $problems[] = $name . ':' . $problem;
                    }
                }
            }
        }

        $this->assertSame([], $problems, 'Close each of these in a finally block.');
    }

    /**
     * The check finds what is not closed, so that it can be relied on.
     */
    public function testCheckFindsWhatIsNotClosed()
    {
        $leaks = <<<'PHP'
        <?php
        function leak($builder)
        {
            $pipeline = $builder->build();
            $flowData = $pipeline->createFlowData();
            $flowData->process();
            $pipeline->createFlowData()->process();
        }
        PHP;
        $this->assertCount(3, self::check($leaks));

        $closed = <<<'PHP'
        <?php
        function closed($builder)
        {
            $pipeline = $builder->build();
            try {
                $flowData = $pipeline->createFlowData();
                try {
                    $flowData->process();
                } finally {
                    $flowData->close();
                }
            } finally {
                $pipeline->close();
            }
        }
        PHP;
        $this->assertSame([], self::check($closed));
    }

    /**
     * The problems in one file of PHP code, each as the line number and
     * what is not closed.
     *
     * @return array<string>
     */
    private static function check(string $code): array
    {
        $tokens = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $tokens[] = [$token[0], $token[1], $token[2]];
            } else {
                $tokens[] = [null, $token, 0];
            }
        }

        $problems = [];
        $kept = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            if ($tokens[$i][1] !== '=' || $i === 0) {
                continue;
            }

            // The variable or property assigned, and the statement assigned.
            [$target, $line] = self::target($tokens, $i);
            if ($target === null) {
                continue;
            }
            // The value ends at the end of the statement, or where a
            // default value in a parameter list or an array ends.
            $end = $i + 1;
            $depth = 0;
            while ($end < $count) {
                $text = $tokens[$end][1];
                if ($depth === 0 && in_array($text, [';', ',', ')', ']'], true)) {
                    break;
                }
                $depth += in_array($text, ['(', '[', '{'], true) ? 1 : 0;
                $depth -= in_array($text, [')', ']', '}'], true) ? 1 : 0;
                ++$end;
            }
            $value = array_slice($tokens, $i + 1, $end - $i - 1);

            $what = null;
            if (self::calls($value, 'createFlowData')) {
                $what = 'FlowData';
                $kept[] = self::position($value, 'createFlowData');
            } elseif (stripos($target, 'pipeline') !== false &&
                (self::calls($value, 'build') || self::calls($value, 'buildFromConfig'))) {
                $what = 'pipeline';
            } elseif (self::creates($value)) {
                $what = 'engine';
            }
            if ($what === null) {
                continue;
            }

            $scope = self::scope($tokens, $i);
            // An engine added to a pipeline is closed with the pipeline, and
            // what a function returns is for its caller to close.
            if (($what === 'engine' && self::found($tokens, $scope, '->add(' . $target)) ||
                self::found($tokens, $scope, 'return' . $target . ';')) {
                continue;
            }
            if (!self::closedIn($tokens, $scope, $target)) {
                $problems[] = "{$line}: the {$what} {$target} is not closed";
            }
        }

        // A FlowData that is not kept in a variable cannot be closed.
        for ($i = 1; $i < $count; ++$i) {
            if ($tokens[$i][1] === 'createFlowData' && $tokens[$i - 1][1] === '->' &&
                !in_array($tokens[$i][2], $kept, true)) {
                $problems[] = "{$tokens[$i][2]}: the FlowData is not kept, so it is not closed";
            }
        }

        return $problems;
    }

    /**
     * The variable or property assigned by the = at $i, as text, and its
     * line, or null for anything else.
     */
    private static function target(array $tokens, int $i): array
    {
        $before = $tokens[$i - 1];
        if ($before[0] === T_VARIABLE) {
            if ($i >= 3 && in_array($tokens[$i - 2][1], ['::'], true)) {
                return [$tokens[$i - 3][1] . '::' . $before[1], $before[2]];
            }

            return [$before[1], $before[2]];
        }
        if ($before[0] === T_STRING && $i >= 3 && $tokens[$i - 2][1] === '->' &&
            $tokens[$i - 3][0] === T_VARIABLE) {
            return [$tokens[$i - 3][1] . '->' . $before[1], $tokens[$i - 3][2]];
        }

        return [null, 0];
    }

    /**
     * Whether the tokens call the method of the given name.
     */
    private static function calls(array $tokens, string $method): bool
    {
        return self::position($tokens, $method) !== null;
    }

    /**
     * The line of the first call of the method of the given name.
     */
    private static function position(array $tokens, string $method): ?int
    {
        for ($j = 1; $j + 1 < count($tokens); ++$j) {
            if ($tokens[$j][1] === $method && $tokens[$j - 1][1] === '->' && $tokens[$j + 1][1] === '(') {
                return $tokens[$j][2];
            }
        }

        return null;
    }

    /**
     * Whether the tokens build one of the engines that must be closed.
     */
    private static function creates(array $tokens): bool
    {
        for ($j = 0; $j + 1 < count($tokens); ++$j) {
            if ($tokens[$j][0] === T_NEW) {
                $class = $tokens[$j + 1][1];
                foreach (self::ENGINES as $engine) {
                    if (substr($class, -strlen($engine)) === $engine) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The first and last token of the function body holding the token at
     * $i, or of the whole file when it is in none.
     */
    private static function scope(array $tokens, int $i): array
    {
        $scope = [0, count($tokens) - 1];
        for ($j = 0; $j < $i; ++$j) {
            if ($tokens[$j][0] !== T_FUNCTION) {
                continue;
            }
            $open = $j;
            while ($open < count($tokens) && $tokens[$open][1] !== '{' && $tokens[$open][1] !== ';') {
                ++$open;
            }
            if ($open >= count($tokens) || $tokens[$open][1] !== '{') {
                continue;
            }
            $close = self::match($tokens, $open);
            if ($open < $i && $i < $close) {
                $scope = [$open, $close];
            }
        }

        return $scope;
    }

    /**
     * The token closing the brace that opens at $open.
     */
    private static function match(array $tokens, int $open): int
    {
        $depth = 0;
        for ($j = $open; $j < count($tokens); ++$j) {
            $text = $tokens[$j][1];
            if ($text === '{' || $tokens[$j][0] === T_CURLY_OPEN || $tokens[$j][0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                ++$depth;
            } elseif ($text === '}') {
                --$depth;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return count($tokens) - 1;
    }

    /**
     * Whether the target is closed in a finally block in the scope. A
     * property may be closed anywhere in the file, such as in tearDown.
     */
    private static function closedIn(array $tokens, array $scope, string $target): bool
    {
        $isProperty = strpos($target, '->') !== false || strpos($target, '::') !== false;
        [$first, $last] = $isProperty ? [0, count($tokens) - 1] : $scope;
        for ($j = $first; $j <= $last; ++$j) {
            if (!$isProperty && $tokens[$j][0] !== T_FINALLY) {
                continue;
            }
            $from = $j;
            $to = $last;
            if (!$isProperty) {
                $from = $j + 1;
                $to = self::match($tokens, $from);
            }
            for ($k = $from; $k <= $to; ++$k) {
                if (self::text($tokens, $k, 4) === $target . '->close(' ||
                    self::text($tokens, $k, 6) === $target . '->close(') {
                    return true;
                }
            }
            if ($isProperty) {
                break;
            }
        }

        return false;
    }

    /**
     * Whether the scope holds the given text, made of up to four tokens
     * with no spaces, such as '->add($engine'.
     */
    private static function found(array $tokens, array $scope, string $text): bool
    {
        for ($k = $scope[0]; $k <= $scope[1]; ++$k) {
            for ($length = 1; $length <= 4; ++$length) {
                if (self::text($tokens, $k, $length) === $text) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The text of $length tokens from $k.
     */
    private static function text(array $tokens, int $k, int $length): string
    {
        $text = '';
        for ($j = $k; $j < $k + $length && $j < count($tokens); ++$j) {
            $text .= $tokens[$j][1];
        }

        return $text;
    }
}
