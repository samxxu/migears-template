<?php

declare(strict_types=1);

namespace MiGears\Template;

/**
 * Compiles ## ## template syntax into pure PHP.
 *
 * Rules:
 *   ### $expr ###  →  <?= $this->raw($expr) ?>        Raw output (no escaping)
 *   ## $expr ##    →  <?= $this->e($expr) ?>          Escaped output
 *   ## section('name') ##  →  <?= $this->section('name') ?>  Section output
 *   \## … \##      →  literal ## (escaped hashes, left untouched by the rules above)
 *
 * Native PHP tags <?php ?> and <?= ?> are preserved as-is.
 * Control structures (if/foreach/for/while) should use native PHP syntax.
 */
class TemplateCompiler
{
    /**
     * Compile template source into PHP code.
     *
     * $template is the source's path when there is one; it is used only to name
     * the file in a compile error.
     */
    public function compile(string $source, ?string $template = null): string
    {
        // A backslash before a run of two or more hashes escapes it: \## is a literal
        // ##, \### a literal ###. Mask those runs first — the passes below are text-level
        // and would otherwise read them as output expressions.
        //
        // The mask is one NUL longer than the longest run of NULs already in the
        // source, so the source cannot contain the marker itself: a fixed
        // "\x00<index>\x00" collided with a template that carried that byte
        // sequence, and the restore below then rewrote whatever followed it as if
        // it were an escaped hash run.
        $marker = "\x00";
        while (str_contains($source, $marker)) {
            $marker .= "\x00";
        }

        $literals = [];
        $source = preg_replace_callback(
            '/\\\\(#{2,})/s',
            function (array $m) use (&$literals, $marker): string {
                $literals[] = $m[1];

                return $marker . (count($literals) - 1) . $marker;
            },
            $source
        );

        // Order matters: triple hashes first, then double
        // Otherwise ## would match inside ### ###

        // ### $expr ### — raw output (no HTML escaping)
        $source = preg_replace_callback(
            '/###\s*(.+?)\s*###/s',
            fn(array $m): string => "<?= \$this->raw({$m[1]}) ?>",
            $source
        );

        // ## $expr ## — escaped output
        $escaped = $source;
        $count = 0;
        $source = preg_replace_callback(
            '/##\s*(.+?)\s*##/s',
            /** @param array<int, array{0: string, 1: int}> $m */
            function (array $m) use ($template, $escaped): string {
                // The PREG_OFFSET_CAPTURE flag below makes each match [text, offset];
                // the offset is what turns into a line number for the error further down.
                $expr = $m[1][0];
                $offset = $m[1][1];
                // ## section('name') ## → section('name'). The name is author text,
                // so it is written out as a PHP literal the way the parser would
                // read it: splicing it between two quote characters let a name like
                // a') . system('id') . ('b close the string and run as code. The name
                // is one quoted literal on one line — a second argument, an empty
                // name or an embedded newline is not this sugar, and the branch below
                // refuses those instead of reading the punctuation off as the name.
                if (preg_match('/^section\(\s*\'([^\'\\\\\r\n]+)\'\s*\)$/i', $expr, $secMatch)) {
                    return '<?= $this->section(' . var_export($secMatch[1], true) . ') ?>';
                }
                if (preg_match('/^section\(\s*"([^"\\\\\r\n]+)"\s*\)$/i', $expr, $secMatch)) {
                    return '<?= $this->section(' . var_export($secMatch[1], true) . ') ?>';
                }
                // Any other bare section(...) is neither the sugar nor a call that can
                // resolve: section() is a method, so writing it out as a global
                // function reached render time as "Call to undefined function", with
                // nothing pointing at the template. Refuse it here instead, naming the
                // file and the line while the author can still see the mistake.
                if (preg_match('/^section\s*\(/i', $expr)) {
                    $line = substr_count($escaped, "\n", 0, $offset) + 1;
                    $where = $template !== null ? "in {$template} on line {$line}" : "on line {$line}";
                    throw new \RuntimeException(
                        "Unrecognised section() form {$where}: the ## ## sugar is section('name')"
                        . ' with a quoted literal; write $this->section(...) for anything else'
                    );
                }
                return "<?= \$this->e({$expr}) ?>";
            },
            $source,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        foreach ($literals as $index => $hashes) {
            $source = str_replace($marker . $index . $marker, $hashes, $source);
        }

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

        // "@" keeps the native "Failed to open stream" diagnostic out of the
        // caller's output: the exception below is the report, and printing both
        // says the same thing twice.
        $source = @file_get_contents($path);
        if ($source === false) {
            throw new \RuntimeException("Failed to read template: {$path}");
        }

        return $this->compile($source, $path);
    }

    /**
     * Check if a template needs compilation (source is newer than cache).
     */
    public function isStale(string $sourcePath, string $cachePath): bool
    {
        // An unreadable mtime means stale, not fresh. The source used to be read
        // with a bare filemtime(): a file deleted between the listing and the
        // compile warned, returned false, and false never beats the cache's
        // timestamp, so the run reported "already compiled" and exited 0. Stale
        // attempts the compile, which is where a missing file is named.
        $sourceTime = @filemtime($sourcePath);
        $cacheTime = @filemtime($cachePath);
        if ($sourceTime === false || $cacheTime === false) {
            return true;
        }

        return $sourceTime > $cacheTime;
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

        if (! is_dir($cacheDir) && ! @mkdir($cacheDir, 0755, true)) {
            throw new \RuntimeException("Cannot create cache directory: {$cacheDir}");
        }

        $written = @file_put_contents($cachePath, $compiled, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException("Failed to write cache file: {$cachePath}");
        }

        return $cachePath;
    }
}
