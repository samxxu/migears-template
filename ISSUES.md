# migears-template — Known Issues / 已知问题

> Generated from the miGears Full-Module Code Review Report (3rd round, 2026-09-26).
> Status refreshed in place on 2026-09-27, once every item below had been closed; the
> report tool stays the source of truth, so regenerate before trusting this file.
>
> 摘自 miGears 全模块代码评审报告（第三轮，2026-09-26）。
> 状态于 2026-09-27 在原文就地刷新（此时下列问题均已关闭）；报告工具仍是唯一权威来源，采信本文件前请重新生成。

| | |
|---|---|
| Status / 状态 | **All findings closed / 全部问题已关闭** |
| Findings / 问题 | P0 0 · P1 0 · P2 0 · P3 0 — closed 6 · open 0 |
| Size / 体量 | src 549 lines · 98 tests |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs. 
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档。

## Verdict / 结论

All six items are closed. The four P2 items are fixed in code; the section item is closed as documentation rather than behaviour — a section holds markup the template already wrote, so escaping it at the layout would escape it twice and break the layout, which the README now says out loud; and the mtime item keeps `>` with its second granularity documented, because `>=` would recompile on every render whenever a source and its cache share a timestamp.

Closing them turned up one more, fixed in the same pass: a bare non-finite float is a scalar, so it never reached the JSON branch — the cast warned for NAN and quietly answered "INF" for the others (P2-5).

第三轮六条问题全部关闭。四条 P2 已在代码中修复；section 一条以文档方式关闭而非改行为——区块装的是模板自己写出的标记，在布局处转义等于转义两次并破坏布局，README 现已写明；mtime 一条保留 `>` 并把秒级粒度写进文档，因为改成 `>=` 会在源文件与缓存时间戳相同时每次渲染都重新编译。

关闭过程中又发现一条，已同批修好：裸的非有限浮点是标量，因此从未进入 JSON 分支——转换时 NAN 报警告，其余静默给出「INF」（P2-5）。

## P1

### P1-1

- **Status / 状态**: Fixed, as documentation / 已修（文档方式） — `de20827`
- **Where / 位置**: `README.md:78,314` vs `:17`
- **Verification / 验证**: reproduced, then documented / 已复现，后补文档
- **EN**: The syntax table said only "Output a section", which reads as escaped output next to the feature table's "default XSS protection". The behaviour is kept: a section holds markup the template already wrote, so escaping it at the layout would escape it twice and break every layout. The row now states that it is not escaped, and why, in both languages
- **中文**: 语法表只写「输出区块」，紧邻特性表的「默认 XSS 防护」，容易被读成已转义。行为保持不变：区块装的是模板已经写出的标记，在布局处转义等于转义两次并破坏布局。中英文两处表格现已写明「不转义」及其原因

## P2

### P2-1

- **Status / 状态**: Fixed / 已修 — `22f7890`
- **Where / 位置**: `src/Template.php:154`
- **Verification / 验证**: reproduced / 已复现
- **EN**: `raw()` took only `string` while `e()` took `mixed`, so `### $rows ###` with an array was a `TypeError` from the signature rather than anything about the template. `raw()` now takes `mixed` and renders what `e()` renders: null to `''`, a scalar by cast, an array or object as JSON
- **中文**: `raw()` 只接受 `string` 而 `e()` 接受 `mixed`，`### $rows ###` 传数组时是签名层面的 `TypeError`，与模板本身无关。`raw()` 现接受 `mixed`，渲染规则与 `e()` 一致：null 为 `''`、标量直接转换、数组/对象转 JSON

### P2-2

- **Status / 状态**: Fixed / 已修 — `22f7890`, with the bare-float half in `a7d51da`
- **Where / 位置**: `src/Template.php:139-144,182-201`
- **Verification / 验证**: reproduced / 已复现
- **EN**: A failed `json_encode()` handed its `false` to `htmlspecialchars()`. `JSON_INVALID_UTF8_SUBSTITUTE` now keeps a value whose UTF-8 is broken, and a value no flag can encode (NAN, INF, a recursion) is named in a `RuntimeException` instead of passed on
- **中文**: `json_encode()` 失败时把 `false` 交给 `htmlspecialchars()`。现用 `JSON_INVALID_UTF8_SUBSTITUTE` 保住 UTF-8 破损的值，而任何 flag 都编码不了的值（NAN、INF、递归）由 `RuntimeException` 具名报出，不再往下传

### P2-3

- **Status / 状态**: Fixed / 已修 — `22f7890`
- **Where / 位置**: `src/Template.php:216-247`
- **Verification / 验证**: reproduced / 已复现
- **EN**: `end()` stored whatever `ob_get_clean()` answered. `start()` now records the buffer level it opened, and `end()` refuses a level that no longer matches, so a template calling native `ob_end_clean()` between the two gets a named failure instead of a `false` in a slot declared `string`
- **中文**: `end()` 直接存下 `ob_get_clean()` 的返回值。`start()` 现记录它开启的缓冲层级，`end()` 在该层级已不匹配时拒绝执行，因此模板若在两者之间调用原生 `ob_end_clean()`，得到的是具名失败，而不是把 `false` 存进声明为 `string` 的位置

### P2-4

- **Status / 状态**: Fixed / 已修 — `e17578a`
- **Where / 位置**: `src/TemplateCompiler.php:26-49,62-79`
- **Verification / 验证**: reproduced (compiled artefact) / 已复现（编译产物）
- **EN**: The section name was spliced between two quote characters, so `## section("a') . system('id') . ('b") ##` closed the string and ran as code; the name is now written with `var_export()` and lands as a literal — the compiled artefact parses, and the literal equals the payload. The escape mask was a fixed NUL+index+NUL, which a template carrying that byte sequence collided with; the marker is now a run of NULs longer than any the source holds
- **中文**: section 名原先被拼进两个引号之间，`## section("a') . system('id') . ('b") ##` 会闭合字符串并作为代码执行；现用 `var_export()` 写出，落为字面量——编译产物语法合法，且字面量与原文一致。转义掩码原为固定的 NUL+序号+NUL，模板中若含该字节序列会冲突；现改为比源码中最长 NUL 串更长的标记

### P2-5

- **Status / 状态**: Fixed / 已修 — `a7d51da`
- **Where / 位置**: `src/Template.php:165-180`
- **Verification / 验证**: reproduced / 已复现
- **EN**: Found while closing P2-2, not in the third-round report. A non-finite float is a scalar, so it took the cast and never reached the JSON branch: NAN warned "unexpected NAN value was coerced to string", and INF and -INF quietly became "INF" and "-INF". All three now go through `json()` and are refused the way an array holding one already was; the earlier tests covered only the array form
- **中文**: 关闭 P2-2 时发现，不在第三轮报告内。非有限浮点是标量，因此走转换分支、从未进入 JSON 分支：NAN 报「unexpected NAN value was coerced to string」，INF 与 -INF 静默变成「INF」「-INF」。三者现均经 `json()` 拒绝，与数组中含同类值的行为一致；此前测试只覆盖了数组形式

## P3

### P3-1

- **Status / 状态**: Fixed (README) · documented (mtime) / 已修（README）· 已写明（mtime） — `de20827`
- **Where / 位置**: `src/TemplateCompiler.php:105-122`, `README.md:191`
- **Verification / 验证**: reproduced (mtime), static (README) / 已复现（mtime）、静态（README）
- **EN**: The API table now shows `e()`'s `$encoding` parameter. The mtime rule is spelled out as second granularity, and `isStale()` keeps `>`: comparing `>=` would recompile on every render whenever a source and its cache share a timestamp, which is the steady state right after a compile
- **中文**: API 表已补上 `e()` 的 `$encoding` 参数。mtime 规则写明为秒级，`isStale()` 保留 `>`：改成 `>=` 会在源文件与缓存时间戳相同时每次渲染都重新编译，而那正是刚编译完的常态

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- A PHP warning counts as a test failure here (`failOnWarning` / `failOnNotice` / `failOnDeprecation` / `failOnRisky`).
- 请注意这些模块的 `phpunit.xml.dist` 会因警告、通知、弃用而失败，因此「无输出」也是验收条件之一。
- At this refresh / 本次刷新时: 98 tests · 197 assertions green · PHPStan level 6 clean · `composer validate` valid (its `version`-field warning predates these changes) / 98 项测试 · 197 条断言全绿 · PHPStan level 6 无错 · `composer validate` 通过（其 `version` 字段警告早于本次改动）
