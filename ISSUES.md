# migears-template — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **P2 open** |
| Size | src 278 lines (net) · 98 tests · 2 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 3 · other 0 |
| Settled | 0 of 3 |
| Waiting on the owner | `P3-1`, `P3-2`, `P3-3` |
| Waiting on the reviewer | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P3-1`](issues/P3-1.md) | P3 | **open** | Layouts are single-level: a layout template calling `extends()` again … |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | `component()` forces `$this->layout` to null for the duration and … |
| [`P3-3`](issues/P3-3.md) | P3 | **open** | The `## section(...) ##` special case only recognises a single-line … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **3** of 3 |
| By status | `open` 3 |
| Waiting on | owner 3 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P3** | [`P3-1`](issues/P3-1.md) | `open` | owner | Layouts are single-level: a layout template calling `extends()` again … |
| **P3** | [`P3-2`](issues/P3-2.md) | `open` | owner | `component()` forces `$this->layout` to null for the duration and … |
| **P3** | [`P3-3`](issues/P3-3.md) | `open` | owner | The `## section(...) ##` special case only recognises a single-line … |

## Verdict

A compact template engine with a clean compiler and CLI tool; the ## section('name') ## syntax degrades to a call to an undefined global function section() — a fatal error with an unhelpful message.

## Fixed since the last round

All prior P3 items confirmed fixed: P3-1/P3-2/P3-3 documentation and metadata drift addressed; G2 strict flags complete.

## Test gaps

No test for template with undefined variable (what happens with strict_types); no test for deeply nested includes (recursion depth limit); no test for compile CLI with non-existent input file.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-template — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **P2 待修** |
| 体量 | src 278 行（净）· 98 个用例 · 2 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 3 · 其他 0 |
| 已了结 | 0 / 3 |
| 等负责人 | `P3-1`, `P3-2`, `P3-3` |
| 等评审方 | _无_ |
| 等协调人 | _无_ |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P3-1`](issues/P3-1.md) | P3 | **open** | 布局只有一层：布局模板再次 extends() 不生效也无提示；层级上限未文档化。 |
| [`P3-2`](issues/P3-2.md) | P3 | **open** | component() 在渲染期间强制把 $this->layout 置 null 并在 finally 还原，因此组件模板若调用 … |
| [`P3-3`](issues/P3-3.md) | P3 | **open** | ## section(...) ## 特判只识别单行引号字面量。section 名含换行时正则不匹配，整个表达式退化为 <?= … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **3** / 3 |
| 按状态 | `open` 3 |
| 等在谁 | 负责人 3 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P3** | [`P3-1`](issues/P3-1.md) | `open` | 负责人 | 布局只有一层：布局模板再次 extends() 不生效也无提示；层级上限未文档化。 |
| **P3** | [`P3-2`](issues/P3-2.md) | `open` | 负责人 | component() 在渲染期间强制把 $this->layout 置 null 并在 finally 还原，因此组件模板若调用 … |
| **P3** | [`P3-3`](issues/P3-3.md) | `open` | 负责人 | ## section(...) ## 特判只识别单行引号字面量。section 名含换行时正则不匹配，整个表达式退化为 <?= … |

## 结论

一个精简的模板引擎，编译器与 CLI 工具干净；## section('name') ## 语法退化时会调用未定义的全局函数 section()——致命错误且错误信息不友好。

## 本轮已修复确认

All prior P3 items confirmed fixed: P3-1/P3-2/P3-3 documentation and metadata drift addressed; G2 strict flags complete.

## 测试盲区

无未定义变量模板测试（strict_types 下会怎样）；无深层嵌套 include 测试（递归深度限制）；无输入文件不存在时 compile CLI 的测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
