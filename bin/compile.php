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

require __DIR__ . '/../vendor/autoload.php';

use MiGears\Template\TemplateCompiler;

$compiler = new TemplateCompiler();
$argv = $_SERVER['argv'];
$argc = count($argv);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php compile.php <file> [<output>]\n");
    fwrite(STDERR, "       php compile.php <dir> <cache-dir>\n");
    exit(1);
}

$source = $argv[1];

if (is_dir($source)) {
    // Batch mode: compile all .tpl.php files in directory
    if ($argc < 3) {
        fwrite(STDERR, "Batch mode requires output directory: php compile.php <dir> <cache-dir>\n");
        exit(1);
    }
    $outDir = $argv[2];
    if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
        fwrite(STDERR, "Cannot create output directory: {$outDir}\n");
        exit(1);
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.tpl.php')) {
            $cachePath = $compiler->compileToCache($file->getPathname(), $outDir);
            echo "Compiled: {$file->getPathname()} → {$cachePath}\n";
            $count++;
        }
    }

    echo "\nDone. {$count} file(s) compiled.\n";
    exit(0);
}

if (is_file($source)) {
    $compiled = $compiler->compileFile($source);

    if ($argc >= 3) {
        $out = $argv[2];
        $dir = dirname($out);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            fwrite(STDERR, "Cannot create directory: {$dir}\n");
            exit(1);
        }
        file_put_contents($out, $compiled);
        echo "Compiled to: {$out}\n";
    } else {
        // Print to stdout
        echo "--- Compiled PHP ---\n";
        echo $compiled;
        echo "\n--- End ---\n";
    }
    exit(0);
}

fwrite(STDERR, "File or directory not found: {$source}\n");
exit(1);
