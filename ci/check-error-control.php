<?php
// Fails when a PHP file of this repository uses the @ error control
// operator, which hides every message the expression raises. A failure a
// call is expected to have is handled where it happens instead. Only the
// tokens PHP itself reads count, so an @ in a comment, a string, an
// annotation or an email address does not.
//
//   php ci/check-error-control.php [file ...]
//
// With no file, every PHP file git tracks is checked, except those of
// vendor folders and the cxx submodule.

// Places where the operator stays, as "path:line". None at present.
const ALLOWED = [];

$files = array_slice($argv, 1);
if ($files === []) {
    $root = dirname(__DIR__);
    exec('git -C ' . escapeshellarg($root) . ' ls-files -- "*.php"', $listed, $status);
    if ($status !== 0) {
        fwrite(STDERR, "git could not list the files.\n");
        exit(2);
    }
    foreach ($listed as $path) {
        if (preg_match('~(^|/)vendor/|^cxx/~', $path) !== 1) {
            $files[] = $root . '/' . $path;
        }
    }
    $prefix = $root . '/';
} else {
    $prefix = '';
}

$found = [];
foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "{$file} could not be read.\n");
        exit(2);
    }
    $line = 1;
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            $line = $token[2] + substr_count($token[1], "\n");
            continue;
        }
        if ($token === '@') {
            $place = substr($file, strlen($prefix)) . ':' . $line;
            if (!in_array($place, ALLOWED, true)) {
                $found[] = $place;
            }
        }
    }
}

if ($found !== []) {
    fwrite(STDERR, "The @ operator hides every message of the expression. " .
        "Handle the expected failure instead, at:\n  " .
        implode("\n  ", $found) . "\n");
    exit(1);
}
echo 'No PHP file uses the @ operator, ' . count($files) . " files checked.\n";
