# migears/template

![Version](https://img.shields.io/badge/version-2.0.0-blue)

Minimalist PHP template engine — native PHP with optional `## ##` syntax sugar.

PHP itself is already a template language. miGears Template adds just a few things on top: **auto-escaping**, **layout inheritance**, **view components**, and optional **`## ##` syntax sugar** that compiles to pure PHP.

## Features

- **Native PHP syntax** — zero DSL, zero learning curve
- **Optional `## ##` syntax** — auto-compiled to PHP, for cleaner output syntax
- **Auto-escaping** — `$this->e()` / `## $var ##` for safe HTML output, XSS protection by default
- **Layout inheritance** — `extends()` / `start()` / `section()`, like Blade but simpler
- **View components** — reusable UI fragments with isolated scope
- **Multiple template paths** — theme support, override by adding paths
- **Zero dependencies** — just PHP 8.1+

## Installation

```bash
composer require migears/template
```

Requires: PHP 8.1+.

## Quick Start

```php
use MiGears\Template\Template;

$tpl = new Template(__DIR__ . '/views');

echo $tpl->render('user/profile', [
    'name' => 'Alice',
    'age' => 25,
]);
```

### Basic Template (Native PHP — `.php` files)

Use `.php` extension for native PHP templates:

```php
<!-- views/hello.php -->
<h1>Hello, <?= $this->e($name) ?>!</h1>
<ul>
<?php foreach ($items as $item): ?>
    <li><?= $this->e($item) ?></li>
<?php endforeach ?>
</ul>
```

### Syntax Sugar (`.tpl.php` files)

Use `.tpl.php` extension for the `## ##` syntax — auto-compiled to pure PHP at runtime:

```php
<!-- views/hello.tpl.php -->
<h1>Hello, ## $name ##!</h1>
<ul>
<?php foreach ($items as $item): ?>
    <li>## $item ##</li>
<?php endforeach ?>
</ul>
```

**Syntax rules:**

| Syntax | Compiles to | Description |
|--------|-------------|-------------|
| `## $expr ##` | `<?= $this->e($expr) ?>` | Escaped output (auto HTML-escaping) |
| `### $expr ###` | `<?= $this->raw($expr) ?>` | Raw output (no escaping, for trusted HTML) |
| `## section('name') ##` | `<?= $this->section('name') ?>` | Output a section |
| `\## … \##` | literal `##` | Escaped hashes — a backslash before a run of two or more hashes keeps it literal |

Control structures (`if`, `foreach`, `for`, `while`) use **native PHP tags** — this preserves IDE syntax highlighting, auto-completion, and error checking.

**How it works:**
1. `.tpl.php` files are compiled to pure PHP cache files on first render
2. Cache is regenerated only when the source file changes (mtime check)
3. `.php` files run directly — zero compilation overhead
4. Both can coexist in the same project

**Manual compilation (for debugging):**

```bash
# Compile a single file and print the generated PHP to stdout
php vendor/bin/compile.php views/hello.tpl.php

# Compile to a specific file
php vendor/bin/compile.php views/hello.tpl.php cache/hello.php

# Compile all .tpl.php files in a directory
php vendor/bin/compile.php views/ cache/
```

### Auto-Escaping

```php
// Escaped (safe for user input) — always use this by default
<?= $this->e($userInput) ?>

// Raw HTML — only use with trusted content
<?= $this->raw($trustedHtml) ?>
```

`$this->e()` handles strings, numbers, null (returns empty string), and arrays/objects (JSON-encoded then escaped).

Escaping is a property of the syntax you write, not a runtime switch: `## $expr ##` compiles to `$this->e($expr)`, `### $expr ###` to `$this->raw($expr)`, and a native `<?= $expr ?>` outputs exactly what you hand it. A global toggle is not offered — it would have to change what `## ##` means per render, which the compiled cache (keyed by source path alone) cannot express.

Literal hashes are written with a backslash: `\##` compiles to a literal `##`, `\###` to `###`. Without it, a `##` in the source is read as an output expression — the pass is text-level and does not distinguish markup from PHP code.

One trap follows: `## $this->raw($expr) ##` is **escaped anyway**, because the sugar wraps whatever sits inside in `$this->e()`, so the call is a silent no-op. For raw output write `### $expr ###` or native `<?= $this->raw($expr) ?>`.

### Layout Inheritance

```php
<!-- views/layout/main.php — the layout -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->section('title', 'Default Title') ?></title>
</head>
<body>
    <header>My App</header>
    <main>
        <?= $this->section('content') ?>
    </main>
    <footer>© 2026</footer>
</body>
</html>
```

```php
<!-- views/user/profile.php — child template -->
<?php $this->extends('layout/main') ?>

<?php $this->start('title') ?>
    Profile of <?= $this->e($name) ?>
<?php $this->end() ?>

<?php $this->start('content') ?>
    <h2><?= $this->e($name) ?></h2>
    <p>Age: <?= $age ?></p>
<?php $this->end() ?>
```

### View Components

```php
<!-- views/components/card.php -->
<div class="card">
    <h3><?= $this->e($title) ?></h3>
    <p><?= $this->e($body) ?></p>
</div>
```

```php
<!-- In any template -->
<?= $this->component('components/card', [
    'title' => 'Welcome',
    'body' => 'Hello world',
]) ?>
```

### Multiple Paths (Theme Support)

```php
$tpl = new Template(__DIR__ . '/views');
$tpl->addPath(__DIR__ . '/themes/dark');  // checked first
```

Templates are searched in reverse order of `addPath()` calls. First match wins.

## API Reference

| Method | Description |
|--------|-------------|
| `render(string $template, array $data = []): string` | Render a template (.php or .tpl.php) |
| `exists(string $template): bool` | Check if template exists |
| `e(mixed $value): string` | Escape for HTML output |
| `raw(string $html): string` | Output raw HTML (trust required) |
| `extends(string $layout): void` | Set layout template |
| `start(string $name): void` | Start capturing a section |
| `end(): void` | End current section |
| `section(string $name, string $default = ''): string` | Output section content |
| `component(string $name, array $data = []): string` | Render a component |
| `addPath(string $path): self` | Add template directory |
| `setCacheDir(string $dir): self` | Set cache directory for compiled .tpl.php |
| `getCompiler(): TemplateCompiler` | Get the compiler instance |

## Design Philosophy

PHP is already a template engine. miGears Template provides just the essential tools that every template engine needs:

1. **Safety** — auto-escaping prevents XSS
2. **Reuse** — layout inheritance and components reduce duplication
3. **Simplicity** — you already know the syntax

**`## ##` syntax sugar is optional** — it compiles to pure PHP, so you can always see what's happening. Use `.tpl.php` for cleaner output syntax, or `.php` for native PHP. Both work seamlessly together.

**What we don't do**:
- No template-level DSL (no `{% if %}`, `{% foreach %}` — use native PHP tags)
- No sandbox (templates are PHP code)
- No built-in i18n (use with `migears/i18n`)

## Integration with miGears Web

```php
$rest = new MiRest(__DIR__ . '/resources', 'App\\Resources');
$rest->set('template', fn() => new Template(__DIR__ . '/views'));

// In a resource:
class Profile extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $tpl = $this->service('template');
        $html = $tpl->render('user/profile', ['name' => 'Alice']);
        return Response::html($html);
    }
}
```

## License

MIT

---

# migears/template

![Version](https://img.shields.io/badge/version-2.0.0-blue)

极简 PHP 模板引擎 — 原生 PHP + 可选 `## ##` 语法糖。

PHP 本身就是模板语言。miGears Template 只在之上加了几件事：**自动转义**、**布局继承**、**视图组件**，以及可选的 **`## ##` 语法糖**（自动编译为纯 PHP）。

## 特性

- **原生 PHP 语法** — 零 DSL、零学习成本
- **可选 `## ##` 语法** — 自动编译为 PHP，输出语法更简洁
- **自动转义** — `$this->e()` / `## $var ##` 安全输出 HTML，默认防 XSS
- **布局继承** — `extends()` / `start()` / `section()`，类似 Blade 但更简单
- **视图组件** — 可复用 UI 片段，作用域隔离
- **多模板目录** — 支持主题，通过添加路径覆盖模板
- **零依赖** — 只需要 PHP 8.1+

## 安装

```bash
composer require migears/template
```

要求：PHP 8.1+。

## 快速开始

```php
use MiGears\Template\Template;

$tpl = new Template(__DIR__ . '/views');

echo $tpl->render('user/profile', [
    'name' => 'Alice',
    'age' => 25,
]);
```

### 基础模板（原生 PHP — `.php` 文件）

使用 `.php` 扩展名编写原生 PHP 模板：

```php
<!-- views/hello.php -->
<h1>你好，<?= $this->e($name) ?>！</h1>
<ul>
<?php foreach ($items as $item): ?>
    <li><?= $this->e($item) ?></li>
<?php endforeach ?>
</ul>
```

### 语法糖（`.tpl.php` 文件）

使用 `.tpl.php` 扩展名编写 `## ##` 语法 — 运行时自动编译为纯 PHP：

```php
<!-- views/hello.tpl.php -->
<h1>你好，## $name ##！</h1>
<ul>
<?php foreach ($items as $item): ?>
    <li>## $item ##</li>
<?php endforeach ?>
</ul>
```

**语法规则：**

| 语法 | 编译结果 | 说明 |
|------|---------|------|
| `## $expr ##` | `<?= $this->e($expr) ?>` | 转义输出（自动 HTML 转义） |
| `### $expr ###` | `<?= $this->raw($expr) ?>` | 原始输出（不转义，用于信任的 HTML） |
| `## section('name') ##` | `<?= $this->section('name') ?>` | 输出区块 |
| `\## … \##` | 字面 `##` | 转义井号——反斜杠加两个及以上井号，保持字面 |

控制结构（`if`、`foreach`、`for`、`while`）使用**原生 PHP 标签** — 保留 IDE 语法高亮、自动补全和错误检查。

**工作原理：**
1. `.tpl.php` 文件首次渲染时编译为纯 PHP 缓存文件
2. 源文件修改后才重新编译（mtime 检查）
3. `.php` 文件直接运行 — 零编译开销
4. 两种文件可以在同一项目中共存

**手动编译（用于调试）：**

```bash
# 编译单个文件并输出生成的 PHP 代码到终端
php vendor/bin/compile.php views/hello.tpl.php

# 编译到指定文件
php vendor/bin/compile.php views/hello.tpl.php cache/hello.php

# 编译目录下所有 .tpl.php 文件
php vendor/bin/compile.php views/ cache/
```

### 自动转义

```php
// 转义输出（用户输入安全）— 默认用这个
<?= $this->e($userInput) ?>

// 原始 HTML — 只用于信任内容
<?= $this->raw($trustedHtml) ?>
```

`$this->e()` 支持字符串、数字、null（返回空字符串）、数组/对象（JSON 编码后转义）。

转义取决于你写下的语法，而不是运行期开关：`## $expr ##` 编译为 `$this->e($expr)`，`### $expr ###` 编译为 `$this->raw($expr)`，而原生 `<?= $expr ?>` 原样输出你给的东西。本引擎不提供全局开关——那会要求 `## ##` 的含义随渲染实例变化，而编译缓存只以源文件路径为键，表达不了这种差异。

由此有一个坑：`## $this->raw($expr) ##` **仍会被转义**，因为糖会把里面的表达式整体包进 `$this->e()`，那次调用等于静默失效。要原样输出请写 `### $expr ###` 或原生 `<?= $this->raw($expr) ?>`。

需要字面井号时在前面加反斜杠：`\##` 编译为字面 `##`，`\###` 为 `###`。不加反斜杠的 `##` 一律被当作输出表达式——这一层是按文本扫描的，不区分标记还是 PHP 代码。

### 布局继承

```php
<!-- views/layout/main.php — 布局 -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->section('title', '默认标题') ?></title>
</head>
<body>
    <header>我的应用</header>
    <main>
        <?= $this->section('content') ?>
    </main>
    <footer>© 2026</footer>
</body>
</html>
```

```php
<!-- views/user/profile.php — 子模板 -->
<?php $this->extends('layout/main') ?>

<?php $this->start('title') ?>
    <?= $this->e($name) ?> 的个人资料
<?php $this->end() ?>

<?php $this->start('content') ?>
    <h2><?= $this->e($name) ?></h2>
    <p>年龄：<?= $age ?></p>
<?php $this->end() ?>
```

### 视图组件

```php
<!-- views/components/card.php -->
<div class="card">
    <h3><?= $this->e($title) ?></h3>
    <p><?= $this->e($body) ?></p>
</div>
```

```php
<!-- 在任意模板中 -->
<?= $this->component('components/card', [
    'title' => '欢迎',
    'body' => '你好，世界',
]) ?>
```

### 多目录（主题支持）

```php
$tpl = new Template(__DIR__ . '/views');
$tpl->addPath(__DIR__ . '/themes/dark');  // 优先查找
```

模板按 `addPath()` 调用的逆序查找，先找到的优先使用。

## API 参考

| 方法 | 说明 |
|------|------|
| `render(string $template, array $data = []): string` | 渲染模板（.php 或 .tpl.php） |
| `exists(string $template): bool` | 检查模板是否存在 |
| `e(mixed $value): string` | HTML 转义输出 |
| `raw(string $html): string` | 原始 HTML 输出（需信任内容） |
| `extends(string $layout): void` | 设置布局模板 |
| `start(string $name): void` | 开始捕获区块 |
| `end(): void` | 结束当前区块 |
| `section(string $name, string $default = ''): string` | 输出区块内容 |
| `component(string $name, array $data = []): string` | 渲染组件 |
| `addPath(string $path): self` | 添加模板目录 |
| `setCacheDir(string $dir): self` | 设置 .tpl.php 的编译缓存目录 |
| `getCompiler(): TemplateCompiler` | 获取编译器实例 |

## 设计哲学

PHP 本身就是模板引擎。miGears Template 只提供每个模板引擎都需要的核心工具：

1. **安全** — 自动转义防止 XSS
2. **复用** — 布局继承和组件减少重复
3. **简洁** — 你已经懂语法了

**`## ##` 语法糖是可选的** — 它编译为纯 PHP，你随时可以看到实际运行的代码。用 `.tpl.php` 获得更简洁的输出语法，或用 `.php` 写原生 PHP。两种方式无缝共存。

**我们不做的事**：
- 没有模板级 DSL（没有 `{% if %}`、`{% foreach %}` — 用原生 PHP 标签）
- 没有沙箱（模板就是 PHP 代码）
- 没有内置国际化（配合 `migears/i18n` 使用）

## 与 miGears Web 集成

```php
$rest = new MiRest(__DIR__ . '/resources', 'App\\Resources');
$rest->set('template', fn() => new Template(__DIR__ . '/views'));

// 在资源类中：
class Profile extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $tpl = $this->service('template');
        $html = $tpl->render('user/profile', ['name' => 'Alice']);
        return Response::html($html);
    }
}
```

## 许可证

MIT
