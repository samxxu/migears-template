#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * miGears Template Compiler — CLI
 *
 * Usage:
 *   php bin/compile.php <file>              Compile single file, print to stdout
 *   php bin/compile.php <file> <out>        Compile single file to <out>
 *   php bin/compile.php <dir> <cache-dir>   Compile all .tpl.php in <dir> to <cache-dir>
 *
 * Examples:
 *   php bin/compile.php views/home.tpl.php
 *   php bin/compile.php views/home.tpl.php cache/home.php
 *   php bin/compile.php views/ cache/
 */

use MiGears\Template\TemplateCompiler;

foreach ([
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
] as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

const USAGE = <<<TXT
Usage: php compile.php <file> [<output>]
       php compile.php <dir> <cache-dir>

  <file>       a .tpl.php file; the compiled PHP goes to stdout unless <output>
               is given
  <output>     file to write the compiled PHP to
  <dir>        directory; every .tpl.php inside it is compiled to <cache-dir>
  <cache-dir>  output directory for the compiled cache files

Options:
  -h, --help   Show this help

TXT;

function usage(int $code): never
{
    fwrite($code === 0 ? STDOUT : STDERR, USAGE);
    exit($code);
}

$rest = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        usage(0);
    }
    if (str_starts_with($arg, '-')) {
        // An unrecognised option used to fall through to the positional list,
        // where `compile.php views/ --cache` became the *cache directory*: the
        // run succeeded and wrote the compiled files into a directory named
        // "--cache" instead of failing.
        fwrite(STDERR, "unknown option: {$arg}\n");
        usage(1);
    }
    $rest[] = $arg;
}

$source = $rest[0] ?? null;
$target = $rest[1] ?? null;
if ($source === null || count($rest) > 2) {
    usage(1);
}

// Without the autoloader the require in a bare script either dies outright or
// leaves "new TemplateCompiler" to fail with an uncaught Error and exit code
// 255. Say what is missing instead, and keep --help working without it.
if (! class_exists(TemplateCompiler::class)) {
    fwrite(STDERR, "cannot find the composer autoloader; run \"composer install\" in the package, or call the installed vendor/bin/compile.php instead\n");
    exit(1);
}

$run = static function () use ($source, $target): int {
    $compiler = new TemplateCompiler();

    if (is_dir($source)) {
        if ($target === null) {
            fwrite(STDERR, "batch mode needs an output directory: php compile.php <dir> <cache-dir>\n");

            return 1;
        }
        // is_dir() again after mkdir(): two runs racing on the same directory
        // both see the missing directory, and only one of them creates it.
        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            fwrite(STDERR, "cannot create output directory: {$target}\n");

            return 1;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.tpl.php')) {
                $cachePath = $compiler->compileToCache($file->getPathname(), $target);
                echo "Compiled: {$file->getPathname()} → {$cachePath}\n";
                $count++;
            }
        }

        echo "\nDone. {$count} file(s) compiled.\n";

        return 0;
    }

    if (! is_file($source)) {
        fwrite(STDERR, "file or directory not found: {$source}\n");

        return 1;
    }

    $compiled = $compiler->compileFile($source);

    if ($target === null) {
        echo "--- Compiled PHP ---\n";
        echo $compiled;
        echo "\n--- End ---\n";

        return 0;
    }

    $dir = dirname($target);
    if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
        fwrite(STDERR, "cannot create directory: {$dir}\n");

        return 1;
    }

    // The return value used to be dropped: an unwritable target leaked a PHP
    // warning, printed "Compiled to: <target>" and exited 0.
    if (@file_put_contents($target, $compiled, LOCK_EX) === false) {
        fwrite(STDERR, "cannot write compiled file: {$target}\n");

        return 1;
    }

    echo "Compiled to: {$target}\n";

    return 0;
};

try {
    exit($run());
} catch (Throwable $e) {
    // Last resort: the compiler reports an unusable template or cache directory
    // by throwing, and a caller reading only the exit code still has to be able
    // to tell a failure (1) from a crash of its own.
    fwrite(STDERR, 'fatal: ' . $e->getMessage() . "\n");
    exit(1);
}
