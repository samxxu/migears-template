# migears/template

![Version](https://img.shields.io/badge/version-2.0.0-blue)

Minimalist PHP template engine — native PHP with optional `## ##` syntax sugar.

PHP itself is already a template language. miGears Template adds just a few things on top: **auto-escaping**, **layout inheritance**, **view components**, and optional **`## ##` syntax sugar** that compiles to pure PHP.

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **Native PHP syntax** — zero DSL, zero learning curve
- **Optional `## ##` syntax** — auto-compiled to PHP, for cleaner output syntax
- **Auto-escaping** — `$this->e()` / `## $var ##` for safe HTML output, XSS protection by default
- **Layout inheritance** — `extends()` / `start()` / `section()`, like Blade but simpler
- **View components** — reusable UI fragments with isolated scope
- **Multiple template paths** — theme support, override by adding paths
- **Zero dependencies** — just PHP 8.1+

## Boundaries

**In scope**

- Rendering a template to a string: `render()` / `exists()`, for both native `.php` templates and `## ##` `.tpl.php` templates, with `e()` for escaped output and `raw()` for raw output.
- Layout inheritance (`extends()` / `start()` / `end()` / `section()`, single-level) and self-contained view components (`component()`).
- The `## ##` syntax sugar and its compiler (`TemplateCompiler`): `## $expr ##` → escaped, `### $expr ###` → raw, `## section('name') ##` → section, compiled to a pure-PHP cache file; plus the `bin/compile.php` CLI for manual or debug compilation.
- Multiple template directories with theme override (`addPath()`, searched in reverse order), with template-name resolution confined to those registered roots.

**Not in scope (by design)**

- Page structure and routing — this engine renders a template you name; the page vocabulary and turning a page declaration into `.tpl.php` belong to `migears/pages` (which in turn leaves routing to the front-end framework you pair it with).
- No template-level DSL — control structures (`if` / `foreach` / `for` / `while`) use native PHP tags, not `{% %}`-style syntax.
- No sandbox — a template is PHP code and runs with the full privileges of the process.
- No built-in i18n — translation belongs to `migears/i18n`.

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
| `## section('name') ##` | `<?= $this->section('name') ?>` | Output a section — **not** escaped: a section holds markup a template already wrote, so escaping it here would escape it twice |
| `\## … \##` | literal `##` | Escaped hashes — a backslash before a run of two or more hashes keeps it literal |

Control structures (`if`, `foreach`, `for`, `while`) use **native PHP tags** — this preserves IDE syntax highlighting, auto-completion, and error checking.

A `## section(...) ##` block is recognised only as `section('name')` with a quoted literal name. Any other shape — a second argument, an empty or multi-line name, or a compound expression like `section('x') . 'y'` — is a **compile error naming the template and the line**, instead of reaching render time as a call to a global `section()`; write `$this->section(...)` inside `## ##` for any other form.

**How it works:**
1. `.tpl.php` files are compiled to pure PHP cache files on first render
2. Cache is regenerated only when the source file changes (mtime check, at second granularity — a source edited within the same second as the cache file is not counted as newer)
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

- Exit code: `0` on success, `1` on any failure; `--help` prints this usage
- An unrecognised `-`/`--option` is refused before anything is read, so a mistyped option can no longer land in the `<cache-dir>` position and write the compiled files into a directory named after it
- A missing composer autoloader and an unwritable target are named rather than ending in an uncaught fatal, or in a "Compiled to:" line for a file that was never written
- A template that cannot be compiled is reported as `<file>: <message>`, the batch goes on with the remaining files, and the run exits `1`; the report is sorted by path

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

Layouts are **single-level**: one render applies exactly one layout, the one the page itself asks for. A layout must not call `extends()` — if it does, that call is silently ignored (no warning) and the page is not wrapped a second time, so what renders is that layout's own output. The page front ends built on this engine (`migears-pages` and its XML/YAML siblings) follow the same one-level rule: each page emits a single `extends()`.

A render is **isolated from the renders around it**. A template may call `$this->render('partial')` to nest one render inside another, or inside a section it is currently capturing: the nested call neither reads the outer render's sections and layout nor leaves its own behind, and the outer state is put back even when the nested template throws. That is what keeps a failure deep inside a partial from taking the page's own sections down with it.

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

A component is **self-contained**: it is a fragment, not a page. It neither reads the sections captured around it nor applies a layout — an `extends()` call inside a component is silently ignored (no warning), and the component's own sections are isolated from its caller's. To render a full page use `render('...')`, not `component('...')`.

### Multiple Paths (Theme Support)

```php
$tpl = new Template(__DIR__ . '/views');
$tpl->addPath(__DIR__ . '/themes/dark');  // checked first
```

Templates are searched in reverse order of `addPath()` calls. First match wins. A name resolves inside those roots and nowhere else: `..`, `.` and empty path segments (and a NUL byte) are refused rather than looked up, so a name never reaches a file beside them.

## API Reference

| Method | Description |
|--------|-------------|
| `render(string $template, array $data = []): string` | Render a template (.php or .tpl.php) |
| `exists(string $template): bool` | Check if template exists |
| `e(mixed $value, string $encoding = 'UTF-8'): string` | Escape for HTML output. Arrays and objects are JSON-encoded first; `$encoding` is passed to `htmlspecialchars()` |
| `raw(mixed $value): string` | Output without escaping (trust required). Takes what `e()` takes, so `### $expr ###` and `## $expr ##` differ only in the escaping |
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
        $tpl = $this->resolve('template');
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

## 边界

**范围内**

- 把模板渲染成字符串：`render()` / `exists()`，原生 `.php` 模板与 `## ##` 的 `.tpl.php` 模板都支持，用 `e()` 转义输出、`raw()` 原样输出。
- 布局继承（`extends()` / `start()` / `end()` / `section()`，只有一层）与自包含的视图组件（`component()`）。
- `## ##` 语法糖及其编译器（`TemplateCompiler`）：`## $expr ##` → 转义、`### $expr ###` → 原样、`## section('name') ##` → 区块，编译为纯 PHP 缓存文件；以及用于手动/调试编译的 `bin/compile.php` CLI。
- 多模板目录与主题覆盖（`addPath()`，按逆序查找），模板名只在已注册的根内解析。

**范围外（刻意不做）**

- 页面结构与路由 —— 本引擎只渲染你指定名字的模板；页面词汇表、把页面声明编译成 `.tpl.php` 属于 `migears/pages`（它又把路由留给与之搭配的前端框架）。
- 没有模板级 DSL —— 控制结构（`if` / `foreach` / `for` / `while`）用原生 PHP 标签，而非 `{% %}` 式语法。
- 没有沙箱 —— 模板就是 PHP 代码，以进程的完整权限运行。
- 没有内置国际化 —— 翻译属于 `migears/i18n`。

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
| `## section('name') ##` | `<?= $this->section('name') ?>` | 输出区块——**不转义**：区块装的是模板已经写出的标记，在这里转义等于转义两次 |
| `\## … \##` | 字面 `##` | 转义井号——反斜杠加两个及以上井号，保持字面 |

控制结构（`if`、`foreach`、`for`、`while`）使用**原生 PHP 标签** — 保留 IDE 语法高亮、自动补全和错误检查。

`## section(...) ##` 块只认带引号字面量名字的 `section('name')`。其余任何形态——多一个参数、空名字或多行名字、或 `section('x') . 'y'` 这样的复合表达式——都是**编译期错误并指名模板与行号**，不会拖到渲染期变成一次对全局 `section()` 的调用；其它写法请在 `## ##` 里写 `$this->section(...)`。

**工作原理：**
1. `.tpl.php` 文件首次渲染时编译为纯 PHP 缓存文件
2. 源文件修改后才重新编译（mtime 检查，粒度为秒——与缓存文件同一秒内改动的源文件不会被判定为更新）
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

- 退出码：成功 `0`，任一失败 `1`；`--help` 打印用法
- 未识别的 `-`/`--option` 在读取任何东西之前即被拒绝，拼错的选项不会再落到 `<cache-dir>` 位上、把编译产物写进以它命名的目录
- 缺 composer autoloader、目标不可写都会被点名，而不是以未捕获致命错误收场，或对根本没写成的文件打印 "Compiled to:"
- 无法编译的模板按 `<file>: <消息>` 报告，批量继续处理其余文件，整轮退出 `1`；报告按路径排序

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

布局只有**一层**：一次渲染只应用一个布局，即页面自身要求的那个。布局不得调用 `extends()`——若调用了，该调用会被静默忽略（无提示），页面不会被再包一层，最终渲染的就是该布局自身的输出。构建在本引擎之上的页面前端（`migears-pages` 及其 XML/YAML 同族）遵循同样的一层规则：每个页面只发出一次 `extends()`。

一次渲染与它周围的渲染**相互隔离**。模板可以调用 `$this->render('partial')`，把一次渲染嵌进另一次渲染，或嵌进它此刻正在捕获的区块里：被嵌套的那次既不读取外层渲染的区块与布局，也不会把自己的留在外面；即使被嵌套的模板抛异常，外层状态也会归位。正是这一点，让片段深处的失败不会顺手带走页面自己的区块。

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

组件是**自包含**的：它是片段，不是页面。它既不读取外层捕获的区块，也不应用布局——组件内部的 `extends()` 调用会被静默忽略（无提示），组件自身的区块与调用方相互隔离。要渲染完整页面请用 `render('...')`，而不是 `component('...')`。

### 多目录（主题支持）

```php
$tpl = new Template(__DIR__ . '/views');
$tpl->addPath(__DIR__ . '/themes/dark');  // 优先查找
```

模板按 `addPath()` 调用的逆序查找，先找到的优先使用。名字只在这些根内解析，不会到根外去找：`..`、`.`、空路径段（以及 NUL 字节）一律拒绝，名字因此触达不到根旁的文件。

## API 参考

| 方法 | 说明 |
|------|------|
| `render(string $template, array $data = []): string` | 渲染模板（.php 或 .tpl.php） |
| `exists(string $template): bool` | 检查模板是否存在 |
| `e(mixed $value, string $encoding = 'UTF-8'): string` | HTML 转义输出。数组与对象先 JSON 编码；`$encoding` 传给 `htmlspecialchars()` |
| `raw(mixed $value): string` | 不转义输出（需信任内容）。参数与 `e()` 一致，因此 `### $expr ###` 与 `## $expr ##` 只差转义 |
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
        $tpl = $this->resolve('template');
        $html = $tpl->render('user/profile', ['name' => 'Alice']);
        return Response::html($html);
    }
}
```

## 许可证

MIT
