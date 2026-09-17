<?php

declare(strict_types=1);

namespace MiGears\Template;

/**
 * Compiles {{ }} template syntax into pure PHP.
 *
 * Rules:
 *   {{{ $expr }}}  →  <?= $this->raw($expr) ?>        Raw output (no escaping)
 *   {{ $expr }}    →  <?= $this->e($expr) ?>          Escaped output
 *   {{ section('name') }}  →  <?= $this->section('name') ?>  Section output
 *
 * Native PHP tags <?php ?> and <?= ?> are preserved as-is.
 * Control structures (if/foreach/for/while) should use native PHP syntax.
 */
class TemplateCompiler
{
    /**
     * Compile template source into PHP code.
     */
    public function compile(string $source): string
    {
        // Order matters: triple braces first, then double
        // Otherwise {{ would match inside {{{ }}}

        // {{{ $expr }}} — raw output (no HTML escaping)
        $source = preg_replace_callback(
            '/\{\{\{\s*(.+?)\s*\}\}\}/s',
            fn(array $m): string => "<?= \$this->raw({$m[1]}) ?>",
            $source
        );

        // {{ $expr }} — escaped output
        $source = preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function (array $m): string {
                $expr = $m[1];
                // {{ section('name') }} → section('name')
                if (preg_match('/^section\(\s*["\'](.+?)["\']\s*\)$/i', $expr, $secMatch)) {
                    return "<?= \$this->section('{$secMatch[1]}') ?>";
                }
                return "<?= \$this->e({$expr}) ?>";
            },
            $source
        );

        return $source;
    }

    /**
     * Compile a template file and return the compiled PHP code.
     */
    public function compileFile(string $path): string
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Template file not found: {$path}");
        }

        $source = file_get_contents($path);
        if ($source === false) {
            throw new \RuntimeException("Failed to read template: {$path}");
        }

        return $this->compile($source);
    }

    /**
     * Check if a template needs compilation (source is newer than cache).
     */
    public function isStale(string $sourcePath, string $cachePath): bool
    {
        if (! is_file($cachePath)) {
            return true;
        }

        return filemtime($sourcePath) > filemtime($cachePath);
    }

    /**
     * Get the cache path for a given source file.
     */
    public function getCachePath(string $sourcePath, string $cacheDir): string
    {
        $hash = md5($sourcePath);
        $name = basename($sourcePath, '.tpl.php');
        return rtrim($cacheDir, '/\\') . '/' . $name . '_' . $hash . '.php';
    }

    /**
     * Compile a file and write the result to cache.
     * Returns the cache file path.
     */
    public function compileToCache(string $sourcePath, string $cacheDir): string
    {
        $cachePath = $this->getCachePath($sourcePath, $cacheDir);

        if (! $this->isStale($sourcePath, $cachePath)) {
            return $cachePath;
        }

        $compiled = $this->compileFile($sourcePath);

        if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0755, true)) {
            throw new \RuntimeException("Cannot create cache directory: {$cacheDir}");
        }

        $written = file_put_contents($cachePath, $compiled, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException("Failed to write cache file: {$cachePath}");
        }

        return $cachePath;
    }
}
