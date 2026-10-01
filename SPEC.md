# migears/template Module Specification

Version: 2.0.0
Date: 2026-09-29

## 1. Positioning

miGears Template is a minimal PHP template engine that renders a named template to a string: native `.php` templates run directly, `.tpl.php` templates carry an optional `## ##` syntax sugar compiled to pure PHP. It sits in the presentation layer of the miGears stack and depends on nothing else — it turns one template name plus data into HTML. It is not a page engine, not a router, not a sandbox and not an i18n layer.

## 2. Boundaries

### 2.1 In scope
- Rendering a template to a string: `render()` / `exists()`, for both native `.php` templates and `## ##` `.tpl.php` templates, with `e()` for escaped output and `raw()` for raw output.
- Layout inheritance (`extends()` / `start()` / `end()` / `section()`, single-level) and self-contained view components (`component()`).
- The `## ##` syntax sugar and its compiler (`TemplateCompiler`): `## $expr ##` → escaped, `### $expr ###` → raw, `## section('name') ##` → section, compiled to a pure-PHP cache file; plus the `bin/compile.php` CLI for manual or debug compilation.
- Multiple template directories with theme override (`addPath()`, searched in reverse order), with template-name resolution confined to those registered roots.

### 2.2 Out of scope (explicitly not done)
- Page structure and routing — this engine renders a template you name; the page vocabulary and turning a page declaration into `.tpl.php` belong to `migears/pages` (which in turn leaves routing to the front-end framework you pair it with).
- No template-level DSL — control structures (`if` / `foreach` / `for` / `while`) use native PHP tags, not `{% %}`-style syntax.
- No sandbox — a template is PHP code and runs with the full privileges of the process.
- No built-in i18n — translation belongs to `migears/i18n`.

## 3. Public contract

| API | Signature | Meaning |
| --- | --- | --- |
| `render` | `render(string $template, array $data = []): string` | Render `.tpl.php` or `.php`; resets per-render state and applies the layout the template asked for. |
| `exists` | `exists(string $template): bool` | Whether the template resolves in a registered root. |
| `e` | `e(mixed $value, string $encoding = 'UTF-8'): string` | HTML-escape; arrays/objects are JSON-encoded first. |
| `raw` | `raw(mixed $value): string` | Same inputs as `e()`, output without escaping. |
| `extends` | `extends(string $layout): void` | Set the layout template for the current render. |
| `start` | `start(string $name): void` | Start capturing a named section (opens a buffer). |
| `end` | `end(): void` | End the current section and store its content. |
| `section` | `section(string $name, string $default = ''): string` | Output a captured section, or `$default`. |
| `component` | `component(string $name, array $data = []): string` | Render a self-contained fragment. |
| `addPath` | `addPath(string $path): self` | Register a directory, searched before the ones added earlier. |

Sugar forms (text-level, compiled by `TemplateCompiler`):

| Sugar | Compiles to | Meaning |
| --- | --- | --- |
| `## $expr ##` | `<?= $this->e($expr) ?>` | Escaped output |
| `### $expr ###` | `<?= $this->raw($expr) ?>` | Raw output |
| `## section('name') ##` | `<?= $this->section('name') ?>` | Section output, **not** escaped |
| `\## … \##` | literal `##` | A backslash before a run of two or more hashes keeps it literal |

## 4. Invariants and error behaviour

- Escaping is a property of the syntax, not a runtime switch: `## $expr ##` compiles to `e()`, `### $expr ###` to `raw()`, and a native `<?= $expr ?>` outputs exactly what you hand it. No global toggle is offered — it would have to change what `## ##` means per render, which the compiled cache (keyed by source path alone) cannot express.
- The sugar wraps whatever sits between the hashes in `e()`, so `## $this->raw($expr) ##` is escaped anyway (a silent no-op); raw output needs `### $expr ###` or a native `<?= $this->raw(...) ?>`. The pass is text-level, so an unescaped `##` in the source is read as an output expression even inside PHP code.
- `e()` and `raw()` accept the same values: `null` → `''`, scalars stringified, arrays/objects JSON-encoded with `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE` (a bad byte is replaced, not dropped). A value no flag can encode (NAN, INF, -INF, a recursion) throws a `RuntimeException` (`cannot render a <type> as JSON: …`).
- A template name resolves only inside the registered roots: a NUL byte, or any empty / `.` / `..` path segment (split on `/` and `\`), is refused; `.tpl.php` is tried before `.php`; roots are searched in reverse `addPath()` order.
- Layouts are single-level (a layout's own `extends()` is silently ignored). A component is self-contained: its `extends()` is silently ignored and its layout/sections/stack are saved and restored around the call, so it neither reads the caller's sections nor disturbs them.
- `render()` resets layout, sections and the section stack per call; a failing template unwinds every buffer it opened and clears the section stack, so a later render inherits neither buffers nor sections.
- `## section('name') ##` recognises one quoted literal as the name. Any other `section(...)` shape inside `## ##` — a second argument, an empty or embedded-newline name, or a compound expression — is a compile-time `RuntimeException` naming the template and the line, not a call to a global `section()` that fails at render time; `$this->section(...)` written inside `## ##` is ordinary escaped output.
- Cache: a `.tpl.php` is compiled to a cache file on first render and recompiled only when the source mtime is strictly greater than the cache's (second granularity, so a same-second edit is not seen as newer). The default cache dir is `sys_get_temp_dir() . '/migears_template'` (overridable via `setCacheDir()`), and the file name is `basename_md5(sourcePath).php`.
- Thrown `RuntimeException`s: `Template not found: …`, `Layout template not found: …`, `Component not found: …`, `end() called without a matching start()`, `section "…": the buffer start() opened is gone; …`; the compiler adds `Template file not found: …`, `Failed to read template: …`, `Cannot create cache directory: …`, `Failed to write cache file: …`, `Unrecognised section() form …`.

## 5. Dependencies

### 5.1 Required
- Runtime: PHP `^8.1` only — `composer.json` `require` names nothing else (zero dependencies). `bin/compile.php` additionally needs a composer autoloader and reports it when it cannot find one.
- Development: `phpunit/phpunit ^10` and `phpstan/phpstan ^2.2` (PHPStan level 6 over `src`).

### 5.2 Forbidden by design
- No dependency on a page/router layer (`migears/pages`, its XML/YAML siblings, or the paired front-end router): the engine only renders a template it is given a name for.
- No dependency on `migears/i18n`: translation is a separate concern.
- No template-level DSL parser and no sandbox runtime: control flow stays native PHP and templates run with the full privileges of the process.

## 6. Test plan

Three files under `tests/`:

- `TemplateTest.php` — basic render and string return; `e()`/`raw()` for escaping, `null`, ints, arrays-as-JSON, broken UTF-8 and NAN/INF refusal; layout extends + sections + section default; single-level layouts; `setCacheDir()` placing the compiled file; components, component input escaping, a component ignoring its own layout, and component state isolation; `addPath()` override and fallback; `Template not found` / `Component not found` / `Layout template not found`; no leaked buffers or sections after a failing template; an unpaired `start()` leaving no buffer; names that cannot climb out of the roots; the `## ##` sugar (escaped, raw, the `## $this->raw() ##` trap, custom cache dir, `.tpl.php` priority over `.php`); and `end()` without `start()` or after a closed buffer.
- `TemplateCompilerTest.php` — `\##` / `\###` literal hashes (inside PHP source too, and beside real interpolation), the NUL mask not colliding with source bytes, `## ##` / `### ###` over variables, properties, array access, method and chained calls, string literals and ternaries, several per line, `## section('name') ##` in both quote styles and a name that must not close its own string literal (the artefact is `php -l`-checked), native PHP preserved, mixed syntax, whitespace/multiline, `compileFile`, `isStale` (no cache / cache newer / source newer / source gone), `getCachePath`, and plain HTML / empty / CSS / JS / single-`#` left untouched.
- `CliTest.php` — `--help` with and without an autoloader, compile to stdout / to a file / a directory batch, batch needing an output dir, a missing input, an unknown option rejected before the input is read, an unwritable target not reported as success, a missing autoloader, an unexpected error mapped to exit 1, and a batch continuing past an unreadable template.

Cases that must stay covered: escaping by default, path confinement to the registered roots, the one-level layout rule, component isolation, cache-staleness semantics, no buffer/section leak across renders, and the CLI's `0` / `1` exit codes.

---

# migears/template 模块规格说明

版本：2.0.0
日期：2026-09-29

## 1. 定位

miGears Template 是一个极简 PHP 模板引擎，把一个具名模板渲染成字符串：原生 `.php` 模板直接运行，`.tpl.php` 模板带有可选的 `## ##` 语法糖，编译为纯 PHP。它位于 miGears 技术栈的展现层，除此之外不依赖任何东西——只把一个模板名加数据变成 HTML。它不是页面引擎、不是路由、不是沙箱，也不是国际化层。

## 2. 边界

### 2.1 范围内
- 把模板渲染成字符串：`render()` / `exists()`，原生 `.php` 模板与 `## ##` 的 `.tpl.php` 模板都支持，用 `e()` 转义输出、`raw()` 原样输出。
- 布局继承（`extends()` / `start()` / `end()` / `section()`，只有一层）与自包含的视图组件（`component()`）。
- `## ##` 语法糖及其编译器（`TemplateCompiler`）：`## $expr ##` → 转义、`### $expr ###` → 原样、`## section('name') ##` → 区块，编译为纯 PHP 缓存文件；以及用于手动/调试编译的 `bin/compile.php` CLI。
- 多模板目录与主题覆盖（`addPath()`，按逆序查找），模板名只在已注册的根内解析。

### 2.2 范围外（刻意不做）
- 页面结构与路由 —— 本引擎只渲染你指定名字的模板；页面词汇表、把页面声明编译成 `.tpl.php` 属于 `migears/pages`（它又把路由留给与之搭配的前端框架）。
- 没有模板级 DSL —— 控制结构（`if` / `foreach` / `for` / `while`）用原生 PHP 标签，而非 `{% %}` 式语法。
- 没有沙箱 —— 模板就是 PHP 代码，以进程的完整权限运行。
- 没有内置国际化 —— 翻译属于 `migears/i18n`。

## 3. 公共契约

| API | 签名 | 说明 |
| --- | --- | --- |
| `render` | `render(string $template, array $data = []): string` | 渲染 `.tpl.php` 或 `.php`；重置每次渲染的状态，并应用模板要求的布局。 |
| `exists` | `exists(string $template): bool` | 模板能否在已注册的根内解析到。 |
| `e` | `e(mixed $value, string $encoding = 'UTF-8'): string` | HTML 转义；数组/对象先 JSON 编码。 |
| `raw` | `raw(mixed $value): string` | 接受与 `e()` 相同的值，输出不转义。 |
| `extends` | `extends(string $layout): void` | 为本次渲染设置布局模板。 |
| `start` | `start(string $name): void` | 开始捕获具名区块（打开一个缓冲区）。 |
| `end` | `end(): void` | 结束当前区块并保存其内容。 |
| `section` | `section(string $name, string $default = ''): string` | 输出已捕获的区块，否则输出 `$default`。 |
| `component` | `component(string $name, array $data = []): string` | 渲染一个自包含片段。 |
| `addPath` | `addPath(string $path): self` | 注册一个目录，将排在更早添加的目录之前被查找。 |

语法糖形式（按文本扫描，由 `TemplateCompiler` 编译）：

| 语法糖 | 编译结果 | 说明 |
| --- | --- | --- |
| `## $expr ##` | `<?= $this->e($expr) ?>` | 转义输出 |
| `### $expr ###` | `<?= $this->raw($expr) ?>` | 原样输出 |
| `## section('name') ##` | `<?= $this->section('name') ?>` | 区块输出，**不**转义 |
| `\## … \##` | 字面 `##` | 井号前有反斜杠且井号连续两个及以上，保持字面 |

## 4. 不变式与错误行为

- 转义取决于你写下的语法，而不是运行期开关：`## $expr ##` 编译为 `e()`，`### $expr ###` 编译为 `raw()`，原生 `<?= $expr ?>` 原样输出你给的东西。不提供全局开关——那会让 `## ##` 的含义随渲染实例变化，而编译缓存只以源文件路径为键，表达不了这种差异。
- 糖会把两个井号之间的内容整体包进 `e()`，因此 `## $this->raw($expr) ##` 仍会被转义（静默失效）；要原样输出需写 `### $expr ###` 或原生 `<?= $this->raw(...) ?>`。这一层按文本扫描，不加反斜杠的 `##` 即使在 PHP 代码里也会被当作输出表达式。
- `e()` 与 `raw()` 接受相同的值：`null` → `''`，标量转字符串，数组/对象用 `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE` 做 JSON 编码（坏字节被替换，而不是丢弃）。任何标志都编码不了的值（NAN、INF、-INF、递归）抛出 `RuntimeException`（`cannot render a <type> as JSON: …`）。
- 模板名只在已注册的根内解析：NUL 字节，或任何空 / `.` / `..` 路径段（按 `/` 与 `\` 切分）都会被拒绝；`.tpl.php` 先于 `.php` 尝试；根按 `addPath()` 的逆序查找。
- 布局只有一层（布局自身的 `extends()` 被静默忽略）。组件是自包含的：其 `extends()` 被静默忽略，其布局/区块/栈在调用前后保存并恢复，因此既不读取调用方的区块，也不打扰它们。
- `render()` 每次调用都重置布局、区块与区块栈；失败的模板会收拢它打开的每个缓冲区并清空区块栈，因此之后的渲染不会继承缓冲区或区块。
- `## section('name') ##` 只认一个带引号的字面量名字。`## ##` 内其余任何 `section(...)` 形态——多一个参数、空名字或内嵌换行的名字、复合表达式——都是编译期 `RuntimeException`，并指名模板与行号，而不是拖到渲染期去调用并不存在的全局 `section()`；写在 `## ##` 里的 `$this->section(...)` 则按普通转义输出处理。
- 缓存：`.tpl.php` 首次渲染时编译为缓存文件，仅当源文件 mtime 严格大于缓存 mtime（粒度为秒，故同一秒内的改动不被视为更新）时才重新编译。默认缓存目录为 `sys_get_temp_dir() . '/migears_template'`（可用 `setCacheDir()` 覆盖），文件名为 `basename_md5(sourcePath).php`。
- 抛出的 `RuntimeException`：`Template not found: …`、`Layout template not found: …`、`Component not found: …`、`end() called without a matching start()`、`section "…": the buffer start() opened is gone; …`；编译器另抛 `Template file not found: …`、`Failed to read template: …`、`Cannot create cache directory: …`、`Failed to write cache file: …`、`Unrecognised section() form …`。

## 5. 依赖

### 5.1 必需
- 运行期：仅 PHP `^8.1` —— `composer.json` 的 `require` 没有点名其它任何东西（零依赖）。`bin/compile.php` 另外需要 composer autoloader，找不到时会报告。
- 开发期：`phpunit/phpunit ^10` 与 `phpstan/phpstan ^2.2`（PHPStan level 6，覆盖 `src`）。

### 5.2 设计上禁止
- 不依赖页面/路由层（`migears/pages`、其 XML/YAML 同族，或搭配的前端路由）：本引擎只渲染你给了名字的模板。
- 不依赖 `migears/i18n`：翻译是另一件事。
- 没有模板级 DSL 解析器，没有沙箱运行期：控制流保持原生 PHP，模板以进程的完整权限运行。

## 6. 测试计划

`tests/` 下三个文件：

- `TemplateTest.php` —— 基础渲染与字符串返回；`e()`/`raw()` 的转义、`null`、整数、数组转 JSON、坏 UTF-8 与 NAN/INF 拒绝；布局 extends + 区块 + 区块默认值；单层布局；`setCacheDir()` 把编译产物放进该目录；组件、组件输入转义、组件忽略自身布局、组件状态隔离；`addPath()` 覆盖与回退；`Template not found` / `Component not found` / `Layout template not found`；失败模板之后不残留缓冲区或区块；未配对的 `start()` 不留下缓冲区；无法爬出根的名字；`## ##` 语法糖（转义、原样、`## $this->raw() ##` 陷阱、自定义缓存目录、`.tpl.php` 优先于 `.php`）；以及没有 `start()` 或在缓冲区已关闭后调用 `end()`。
- `TemplateCompilerTest.php` —— `\##` / `\###` 字面井号（在 PHP 代码里、以及紧邻真实插值时同样成立）、NUL 掩码不与源字节冲突、`## ##` / `### ###` 作用于变量、属性、数组访问、方法调用与链式调用、字符串字面量与三元、单行多个、`## section('name') ##` 的两种引号风格以及一个不得闭合自身字符串字面量的名字（产物会过 `php -l`）、原生 PHP 保留、混合语法、空白/跨行、`compileFile`、`isStale`（无缓存 / 缓存更新 / 源更新 / 源已消失）、`getCachePath`，以及纯 HTML / 空串 / CSS / JS / 单个 `#` 保持原样。
- `CliTest.php` —— `--help`（有、无 autoloader 各一次）、编译到 stdout / 到文件 / 目录批量、批量缺少输出目录、输入缺失、未知选项在读取输入前即被拒绝、不可写目标不报告成功、缺少 autoloader、意外错误映射为退出码 1、批量在遇到不可读模板后继续。

必须保持覆盖的用例：默认转义、模板名限定在已注册根内、单层布局规则、组件隔离、缓存过期语义、渲染之间不泄漏缓冲区/区块，以及 CLI 的 `0` / `1` 退出码。
