# migears-template — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Size / 体量 | src 549 lines (278 net) · 98 tests · 2 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 0 · P2 0 · P3 3 · other 0 |
| Answered / 已回复 | 0 of 3 |
| Waiting / 等待回复 | `P3-1`, `P3-2`, `P3-3` |

| id | level | status | title |
|---|---|---|---|
| [`P3-1`](issues/P3-1.md) | P3 | **open** | Layouts are single-level: a layout template calling `extends()` again … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | `component()` forces `$this->layout` to null for the duration and … |
| [`P3-3`](issues/P3-3.md) | P3 | **open** | The `## section(...) ##` special case only recognises a single-line … |

## Verdict / 结论

The most thorough fix set of the round, and the highest comment quality in the workspace — each fix explains why the old behaviour lost data. Three narrow edges remain around the `## section()` special case.

本轮修得最彻底的一组，注释质量也是全仓最高——每处修复都解释了旧行为为什么会丢数据。剩三处围绕 ## section() 这个特判的窄边角。

## Fixed since the last round / 本轮已修复确认

上一轮全部 7 项闭环且有回归覆盖：section 的不转义行为写进中英语法表并解释原因、raw() 与 e() 参数规则对齐、json_encode 失败改用具名 RuntimeException（JSON_INVALID_UTF8_SUBSTITUTE）、start()/end() 记录缓冲层级且层级不符即抛、section 名改 var_export 消除引号注入、非有限浮点改走 json() 抛错、API 表补 e() 的 $encoding 并写明 mtime 秒级粒度。 

## Test gaps / 测试盲区

No test pins "section output is unescaped" (only the README states it); no case for a section name containing a newline or a non-literal expression; no case for extends() inside a component or for multi-level layouts.

无「section 输出不转义」的钉子用例（只有 README 声明）；无「section 名含换行或非字面量表达式」用例；无「组件内 extends()」与多级布局的用例。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
