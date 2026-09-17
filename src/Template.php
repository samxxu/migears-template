<?php

declare(strict_types=1);

namespace MiGears\Template;

/**
 * Minimalist PHP template engine.
 *
 * - Native PHP syntax, zero DSL to learn
 * - Optional {{ }} syntax sugar (auto-compiled to PHP)
 * - Auto-escaping for safe output
 * - Layout inheritance (extends/start/section)
 * - View components
 * - Multiple template paths (theme support)
 *
 * Usage:
 *   $tpl = new Template(__DIR__ . '/views');
 *   echo $tpl->render('user/profile', ['name' => 'Alice']);
 *
 * In templates (.tpl.php files use {{ }} syntax):
 *   {{ $name }}                        escaped output
 *   {{{ $trustedHtml }}}              raw output
 *   {{ section('content') }}          section output
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

    /** @var bool Whether auto-escaping is enabled */
    private bool $autoEscape = true;

    /** @var string|null Layout template set by extends() */
    private ?string $layout = null;

    /** @var array<string, string> Captured section contents */
    private array $sections = [];

    /** @var list<string> Stack of section names currently being captured */
    private array $sectionStack = [];

    /** @var TemplateCompiler|null Lazy-init compiler for {{ }} syntax */
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
     * Enable or disable auto-escaping globally.
     * On by default. Turning off is not recommended.
     */
    public function setAutoEscape(bool $enabled): self
    {
        $this->autoEscape = $enabled;
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
        if ($this->layout !== null) {
            $layoutFile = $this->findTemplate($this->layout);
            if ($layoutFile === null) {
                throw new \RuntimeException("Layout template not found: {$this->layout}");
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
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, $encoding);
        }
        return htmlspecialchars(
            json_encode($value, JSON_UNESCAPED_UNICODE),
            ENT_QUOTES | ENT_SUBSTITUTE,
            $encoding
        );
    }

    /**
     * Output raw HTML without escaping.
     * Use only with trusted content.
     */
    public function raw(string $html): string
    {
        return $html;
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
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End the current section and store its content.
     */
    public function end(): void
    {
        if ($this->sectionStack === []) {
            throw new \RuntimeException('end() called without a matching start()');
        }
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
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
     * @param string $name Component template path
     * @param array<string, mixed> $data Component data
     */
    public function component(string $name, array $data = []): string
    {
        $file = $this->findTemplate($name);
        if ($file === null) {
            throw new \RuntimeException("Component not found: {$name}");
        }
        return $this->evaluate($file, $data);
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
            // Clear any dangling section buffers
            while ($this->sectionStack !== []) {
                array_pop($this->sectionStack);
                if (ob_get_level() > $level) {
                    ob_end_clean();
                }
            }
            throw $e;
        }

        return ob_get_clean();
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
