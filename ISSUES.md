# migears-template — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 304 lines (net) · 108 tests · 2 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 0 · P3 0 · other 0 |
| Settled | 4 of 4 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Waiting on the reviewer | _nothing_ |
| Deferred, owing nobody | _nothing_ |

| id | level | status | title |
|---|---|---|---|
| [`P3-3`](issues/P3-3.md) | P2 | **verified** | When a ## section('name') ## block is not recognized (e.g. typo or … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | Layouts are single-level: a layout template calling `extends()` again … |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | `component()` forces `$this->layout` to null for the duration and … |
| [`P3-4`](issues/P3-4.md) | P3 | **verified** | `render()` is not reentrant: a template that calls … |

## Unclosed

_Nothing unclosed — every item in this module is `verified` or `closed`._

## Verdict

Both fixes are in, load-bearing and pinned by regression tests — the compile-time refusal and the nested-render state restore are exactly what the record claims.

## Fixed since the last round

P3-3 and P3-4 verified by mutation: an unrecognised section() form now fails at compile time naming the template and the line (option D), and render() saves and restores its sections and layout in a finally block. Reverting either turns five and three tests red respectively.

## Test gaps

No skips. Not covered, and by design: a $this->section(...) call inside a ## ## block and a section( form inside a ### ### raw block are on different paths and are not meant to be refused; no test states that boundary.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-template — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 304 行（净）· 108 个用例 · 2 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 0 · P3 0 · 其他 0 |
| 已了结 | 4 / 4 |
| 等模块主 | _无_ |
| 等协调人 | _无_ |
| 等评审方 | _无_ |
| 已暂缓，不欠谁 | _无_ |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P3-3`](issues/P3-3.md) | P2 | **verified** | 当 ## section('name') ## 块未被识别时（如拼写错误或语法不对），生成的原始 PHP 代码会把 section() … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | 布局只有一层：布局模板再次 extends() 不生效也无提示；层级上限未文档化。 |
| [`P3-2`](issues/P3-2.md) | P3 | **verified** | component() 在渲染期间强制把 $this->layout 置 null 并在 finally 还原，因此组件模板若调用 … |
| [`P3-4`](issues/P3-4.md) | P3 | **verified** | `render()` 不可重入：模板调用 `$this->render('inner')` 会重置外层渲染的 `sections` 与 … |

## 未关闭

_无未关闭条目——本模块每条都已是 `verified` 或 `closed`。_

## 结论

两处修复都在位、都承重、都有回归用例钉住——编译期拒绝与嵌套渲染的状态还原都与记录所述一致。

## 本轮已修复确认

P3-3 and P3-4 verified by mutation: an unrecognised section() form now fails at compile time naming the template and the line (option D), and render() saves and restores its sections and layout in a finally block. Reverting either turns five and three tests red respectively.

## 测试盲区

无跳过。按设计未覆盖：## ## 块内的 $this->section(...) 调用，以及 ### ### 原始块内出现的 section(，走的是另一条路径、本就不该被拒；无用例点明这层边界。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
