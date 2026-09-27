<?php

declare(strict_types=1);

namespace MiGears\Template;

/**
 * Minimalist PHP template engine.
 *
 * - Native PHP syntax, zero DSL to learn
 * - Optional ## ## syntax sugar (auto-compiled to PHP)
 * - Auto-escaping for safe output
 * - Layout inheritance (extends/start/section)
 * - View components
 * - Multiple template paths (theme support)
 *
 * Usage:
 *   $tpl = new Template(__DIR__ . '/views');
 *   echo $tpl->render('user/profile', ['name' => 'Alice']);
 *
 * In templates (.tpl.php files use ## ## syntax):
 *   ## $name ##                        escaped output
 *   ### $trustedHtml ###              raw output
 *   ## section('content') ##          section output
 *
 * In templates (.php files use native PHP, fully backward compatible):
 *   <?= $this->e($name) ?>            escaped output
 *   <?= $this->raw($trustedHtml) ?>   raw output
 *   <?php $this->extends('layout/main') ?>
 *   <?php $this->start('content') ?> ... <?php $this->end() ?>
 *   <?= $this->component('card', ['title' => 'Hi']) ?>
 */
class Template
{
    public const VERSION = '2.0.0';

    /** @var list<string> Template directories, searched in order */
    private array $paths;

    /** @var string|null Layout template set by extends() */
    private ?string $layout = null;

    /** @var array<string, string> Captured section contents */
    private array $sections = [];

    /**
     * Stack of the sections currently being captured: the name, and the level of
     * the output buffer start() opened. The level is what lets end() tell whether
     * the buffer it is about to clean is still its own.
     *
     * @var list<array{name: string, level: int}>
     */
    private array $sectionStack = [];

    /** @var TemplateCompiler|null Lazy-init compiler for ## ## syntax */
    private ?TemplateCompiler $compiler = null;

    /** @var string|null Cache directory for compiled templates */
    private ?string $cacheDir = null;

    /**
     * @param string $basePath Primary template directory
     * @param string|null $cacheDir Cache directory for compiled .tpl.php templates
     */
    public function __construct(string $basePath, ?string $cacheDir = null)
    {
        $this->paths = [rtrim($basePath, '/\\')];
        if ($cacheDir !== null) {
            $this->cacheDir = rtrim($cacheDir, '/\\');
        }
    }

    /**
     * Add an additional template directory (for theme overrides, etc.).
     * Directories are searched in the order they are added.
     */
    public function addPath(string $path): self
    {
        array_unshift($this->paths, rtrim($path, '/\\'));
        return $this;
    }

    /**
     * Check if a template file exists.
     */
    public function exists(string $template): bool
    {
        return $this->findTemplate($template) !== null;
    }

    /**
     * Render a template and return the output string.
     *
     * @param string $template Template path relative to any registered path, without .php
     * @param array<string, mixed> $data Variables to extract into the template
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->findTemplate($template);
        if ($file === null) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        // Reset per-render state
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];

        $content = $this->evaluate($file, $data);

        // If a layout was set, render the layout with captured sections
        /** @var string|null $layout Set by the template via extends(), which evaluate() includes at runtime. */
        $layout = $this->layout;
        if ($layout !== null) {
            $layoutFile = $this->findTemplate($layout);
            if ($layoutFile === null) {
                throw new \RuntimeException("Layout template not found: {$layout}");
            }
            // The layout uses section() to output sections
            $content = $this->evaluate($layoutFile, $data);
        }

        return $content;
    }

    /**
     * Escape a value for safe HTML output.
     * Scalars are escaped with htmlspecialchars.
     * Arrays/objects are JSON-encoded then escaped.
     */
    public function e(mixed $value, string $encoding = 'UTF-8'): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return htmlspecialchars(self::scalar($value), ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
        }
        return htmlspecialchars(
            self::json($value),
            ENT_QUOTES | ENT_SUBSTITUTE,
            $encoding
        );
    }

    /**
     * Output raw HTML without escaping.
     * Use only with trusted content.
     *
     * Accepts what e() accepts, so `### $expr ###` and `## $expr ##` differ only
     * in the escaping: an array reaching raw() used to be a TypeError from the
     * string parameter, which is the same value one construct over.
     */
    public function raw(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return self::scalar($value);
        }
        return self::json($value);
    }

    /**
     * The text of a scalar, with the floats JSON refuses named rather than cast.
     *
     * A non-finite float is a scalar, so it never reached json(): the cast warned
     * "unexpected NAN value was coerced to string" for NAN, and turned INF and
     * -INF into a quiet "INF" and "-INF". JSON has no representation for either,
     * and json() is where that is said out loud.
     */
    private static function scalar(mixed $value): string
    {
        if (is_float($value) && ! is_finite($value)) {
            self::json($value);
        }

        return (string) $value;
    }

    /**
     * The JSON text for a value the scalar path above cannot render on its own.
     *
     * JSON_INVALID_UTF8_SUBSTITUTE keeps one bad byte from emptying the whole
     * value, which is how json_encode() otherwise reports it — a false that then
     * reached htmlspecialchars() as a bool where a string was due. Some values no
     * flag can encode at all (NAN, INF, a recursion), and json_encode() answers
     * false for those too, so the failure is named here rather than passed on.
     */
    private static function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException(
                'cannot render a ' . get_debug_type($value) . ' as JSON: ' . json_last_error_msg()
            );
        }

        return $json;
    }

    /**
     * Set the layout template for the current render.
     * Call this at the top of a child template.
     */
    public function extends(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start capturing a named section.
     * Must be paired with end().
     */
    public function start(string $name): void
    {
        ob_start();
        $this->sectionStack[] = ['name' => $name, 'level' => ob_get_level()];
    }

    /**
     * End the current section and store its content.
     */
    public function end(): void
    {
        if ($this->sectionStack === []) {
            throw new \RuntimeException('end() called without a matching start()');
        }
        ['name' => $name, 'level' => $level] = array_pop($this->sectionStack);

        // The buffer start() opened may be gone: a template is free to call
        // ob_end_clean() itself. Cleaning whatever sits on top instead would take
        // the caller's own buffer — PHPUnit's, in a test — as this section's
        // content, so the level is checked before anything is read. Storing the
        // false that ob_get_clean() then answers made section() return a bool from
        // a method declared string, and the section was simply gone.
        if (ob_get_level() !== $level) {
            throw new \RuntimeException(
                "section \"{$name}\": the buffer start() opened is gone; something ended it before end()"
            );
        }

        // The level check just proved a buffer is there, so ob_get_clean() cannot
        // answer false here; the cast covers its declared string|false return.
        $this->sections[$name] = (string) ob_get_clean();
    }

    /**
     * Output the content of a named section.
     * Returns default value if section was not defined.
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Render a component template and return its output.
     *
     * A component is self-contained: it neither reads sections captured by an
     * outer render nor leaves its own layout/section state behind.
     *
     * @param string $name Component template path
     * @param array<string, mixed> $data Component data
     */
    public function component(string $name, array $data = []): string
    {
        $file = $this->findTemplate($name);
        if ($file === null) {
            throw new \RuntimeException("Component not found: {$name}");
        }

        $layout = $this->layout;
        $sections = $this->sections;
        $stack = $this->sectionStack;
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];

        try {
            return $this->evaluate($file, $data);
        } finally {
            $this->layout = $layout;
            $this->sections = $sections;
            $this->sectionStack = $stack;
        }
    }

    /**
     * Get the compiler instance (lazy-init).
     */
    public function getCompiler(): TemplateCompiler
    {
        return $this->compiler ??= new TemplateCompiler();
    }

    /**
     * Set a custom cache directory for compiled .tpl.php templates.
     */
    public function setCacheDir(string $dir): self
    {
        $this->cacheDir = rtrim($dir, '/\\');
        return $this;
    }

    /**
     * Evaluate a PHP template file with isolated scope.
     * .tpl.php files are compiled to PHP first; .php files run directly.
     *
     * @param array<string, mixed> $__data__ Variables extracted into the template scope
     */
    private function evaluate(string $__file__, array $__data__): string
    {
        // Compile .tpl.php files if needed
        if (str_ends_with($__file__, '.tpl.php')) {
            $__file__ = $this->getCompiledPath($__file__);
        }

        $level = ob_get_level();
        ob_start();

        try {
            extract($__data__, EXTR_SKIP);
            include $__file__;
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            // The buffers are gone by now, so only the stack is left: whatever it
            // still holds must not reach the next render.
            $this->sectionStack = [];
            throw $e;
        }

        // Close every buffer the template opened above the entry level: the
        // innermost one holds all of its output, and an unpaired start() must
        // not leave one behind for the next render.
        $content = ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return $content;
    }

    /**
     * Get the compiled cache path for a .tpl.php file.
     * Compiles if cache is stale or missing.
     */
    private function getCompiledPath(string $sourcePath): string
    {
        $cacheDir = $this->cacheDir ?? sys_get_temp_dir() . '/migears_template';
        return $this->getCompiler()->compileToCache($sourcePath, $cacheDir);
    }

    /**
     * Find a template file across all registered paths.
     * .tpl.php files take priority over .php files.
     * Returns the full path or null if not found.
     */
    private function findTemplate(string $template): ?string
    {
        // A name resolves inside the registered roots and nowhere else: ".." climbs
        // out of them, an empty or "." segment names the root itself, and a
        // backslash is a separator on Windows, where it would climb out too. A NUL
        // byte never reaches the filesystem call that would refuse it. Raw names
        // are not escaped here — the compiler refuses them with the page path in
        // its message — this is what a name arriving directly has to pass.
        if (str_contains($template, "\0")) {
            return null;
        }
        foreach (preg_split('#[/\\\\]#', $template) ?: [] as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        // Try .tpl.php first (new syntax), then .php (native PHP)
        foreach (['.tpl.php', '.php'] as $ext) {
            $file = $template . $ext;
            foreach ($this->paths as $path) {
                $fullPath = $path . '/' . $file;
                if (is_file($fullPath)) {
                    return $fullPath;
                }
            }
        }
        return null;
    }
}
