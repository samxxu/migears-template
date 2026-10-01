<?php

declare(strict_types=1);

namespace MiGears\Template\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Template\Template;

class TemplateTest extends TestCase
{
    private string $fixtures;
    private Template $tpl;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__ . '/fixtures';
        $this->tpl = new Template($this->fixtures);
    }

    // --- Basic rendering ---

    public function testBasicRender(): void
    {
        $html = $this->tpl->render('hello', [
            'name' => 'Alice',
            'items' => ['apple', 'banana', 'cherry'],
        ]);

        $this->assertStringContainsString('<h1>Hello, Alice!</h1>', $html);
        $this->assertStringContainsString('<li>apple</li>', $html);
        $this->assertStringContainsString('<li>banana</li>', $html);
        $this->assertStringContainsString('<li>cherry</li>', $html);
    }

    public function testRenderReturnsString(): void
    {
        $html = $this->tpl->render('hello', ['name' => 'x', 'items' => []]);
        $this->assertIsString($html);
    }

    // --- Auto-escaping ---

    public function testEEscapesHtml(): void
    {
        $html = $this->tpl->render('hello', [
            'name' => '<script>alert(1)</script>',
            'items' => [],
        ]);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testEHandlesNull(): void
    {
        $tpl = new Template($this->fixtures);
        $result = $tpl->e(null);
        $this->assertSame('', $result);
    }

    public function testEHandlesInt(): void
    {
        $tpl = new Template($this->fixtures);
        $result = $tpl->e(42);
        $this->assertSame('42', $result);
    }

    public function testEHandlesArrayAsJson(): void
    {
        $tpl = new Template($this->fixtures);
        $result = $tpl->e(['a' => '<b>']);
        $this->assertStringContainsString('&quot;a&quot;', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    public function testRawOutputsUnescaped(): void
    {
        $tpl = new Template($this->fixtures);
        $html = $tpl->raw('<b>bold</b>');
        $this->assertSame('<b>bold</b>', $html);
    }

    public function testRawAcceptsWhatEAccepts(): void
    {
        // `### $expr ###` and `## $expr ##` are one construct apart from the
        // escaping, so the two methods take the same values. raw() declared a
        // string parameter, and an array reaching it was a TypeError from the
        // signature rather than anything about the template.
        $tpl = new Template($this->fixtures);

        $this->assertSame('{"a":"<b>"}', $tpl->raw(['a' => '<b>']));
        $this->assertSame('', $tpl->raw(null));
        $this->assertSame('42', $tpl->raw(42));
    }

    public function testEKeepsAValueWhoseUtf8IsBroken(): void
    {
        // One bad byte used to make json_encode() answer false, and that false
        // reached htmlspecialchars() where a string was due. The value is now kept,
        // with the bad byte replaced, so nothing about it is lost.
        $tpl = new Template($this->fixtures);
        $result = $tpl->e(['bad' => "\xB1\x31"]);

        $this->assertStringContainsString('&quot;bad&quot;', $result);
        $this->assertStringContainsString("\u{FFFD}", $result);
    }

    public function testEAndRawNameTheValueNoFlagCanEncode(): void
    {
        // NAN has no JSON representation, so json_encode() answers false whatever
        // flags are set. Both methods name the failure instead of passing the false
        // on to a caller that asked for a string.
        $tpl = new Template($this->fixtures);

        try {
            $tpl->e(['x' => NAN]);
            $this->fail('e() should have refused a value it cannot encode');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('as JSON', $e->getMessage());
        }

        try {
            $tpl->raw(['x' => NAN]);
            $this->fail('raw() should have refused a value it cannot encode');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('as JSON', $e->getMessage());
        }
    }

    public function testEAndRawNameABareNonFiniteFloat(): void
    {
        // A non-finite float is a scalar, so it took the cast rather than reaching
        // json(): NAN warned "unexpected NAN value was coerced to string", and INF
        // and -INF became "INF" and "-INF" without a word. JSON refuses all three,
        // so all three are named the way an array holding one already was.
        $tpl = new Template($this->fixtures);

        foreach ([NAN, INF, -INF] as $value) {
            try {
                $tpl->e($value);
                $this->fail('e() should have refused ' . var_export($value, true));
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('as JSON', $e->getMessage());
            }

            try {
                $tpl->raw($value);
                $this->fail('raw() should have refused ' . var_export($value, true));
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('as JSON', $e->getMessage());
            }
        }
    }

    // --- Layout inheritance ---

    public function testLayoutExtendsAndSection(): void
    {
        $html = $this->tpl->render('profile', [
            'name' => 'Bob',
            'age' => 30,
            'bio' => '<p>Trusted bio</p>',
        ]);

        // Layout structure
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('Layout Header', $html);
        $this->assertStringContainsString('Layout Footer', $html);

        // Section: title
        $this->assertStringContainsString('Hello, Bob', $html);

        // Section: content
        $this->assertStringContainsString('Profile of Bob', $html);
        $this->assertStringContainsString('Age: 30', $html);

        // raw() content
        $this->assertStringContainsString('<p>Trusted bio</p>', $html);
    }

    public function testANestedRenderKeepsTheOuterLayoutAndSections(): void
    {
        // The outer template nests a render after capturing its sections. render() used to clear the sections
        // and the layout outright, so the outer layout was silently not applied and the page came back with
        // the inner template's output alone.
        // 外层模板在捕获自己的 sections 之后嵌了一次渲染。render() 此前会把 sections 与 layout 直接清空，
        // 于是外层的 layout 被无声地不套用，页面只带着内层模板的输出返回。
        $html = $this->tpl->render('nested/outer');

        $this->assertStringContainsString('Layout Header', $html);
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('Outer title', $html);
        $this->assertStringContainsString('<p>Outer content</p>', $html);
    }

    public function testANestedRenderInsideASectionCaptureKeepsTheOuterSection(): void
    {
        // The same nesting from inside a section capture: clearing the stack made the outer end() report
        // "end() called without a matching start()", so the page failed instead of rendering.
        // 同样的嵌套发生在 section 捕获内部：清空栈会让外层的 end() 报出「end() called without a matching
        // start()」，整页因此报错，而不是渲染出来。
        $html = $this->tpl->render('nested/in-section');

        // The inner render's output sits inside the outer section's brackets, which is what shows the outer
        // capture survived and closed normally. The fixture's own trailing newline is why the two halves are
        // asserted apart rather than as one string.
        // 内层渲染的输出落在外层 section 的方括号之间，这正说明外层的捕获保住了、也正常闭合了。夹具自带的
        // 结尾换行，就是这两半分开断言、而不是拼成一个字符串的原因。
        $this->assertStringContainsString('Layout Header', $html);
        $this->assertStringContainsString('A[<p>Inner: INSIDE</p>', $html);
        $this->assertStringContainsString(']B', $html);
    }

    public function testAFailingInnerRenderIsIsolatedFromTheOuterOne(): void
    {
        // A nested render that throws must leave the outer render's own state where it was, or the outer
        // template cannot catch the failure and carry on: without the restore, its end() meets an empty stack
        // and the page dies for an unrelated reason.
        // 嵌套的渲染抛异常时，必须把外层渲染自己的状态留在原处，否则外层模板无法捕获失败后继续：没有还原的话，
        // 它的 end() 会撞上一个空栈，整页会因为一个不相干的理由倒掉。
        $html = $this->tpl->render('nested/rescue');

        $this->assertStringContainsString('caught: inner render blew up', $html);
        $this->assertStringContainsString('Layout Header', $html);
    }

    public function testSectionWithDefaultValue(): void
    {
        $tpl = new Template($this->fixtures);
        $html = $tpl->render('layout/main');
        $this->assertStringContainsString('<title>Default Title</title>', $html);
    }

    public function testLayoutsAreSingleLevel(): void
    {
        // A layout that calls extends() again has that call silently ignored: the
        // one-level rule is documented in the README, and it is pinned here so a
        // future change cannot turn it into nesting — or into a silently empty
        // page — without a test going red.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/outer.php', 'OUTER[<?= $this->section("body", "none") ?>]');
        file_put_contents($dir . '/middle.php', '<?php $this->extends("outer") ?>MID[<?= $this->section("body", "none") ?>]');
        file_put_contents($dir . '/child.php', '<?php $this->extends("middle") ?><?php $this->start("body") ?>CHILD<?php $this->end() ?>');

        try {
            $html = (new Template($dir))->render('child');
            $this->assertSame('MID[CHILD]', $html);
            $this->assertStringNotContainsString('OUTER', $html);
        } finally {
            unlink($dir . '/outer.php');
            unlink($dir . '/middle.php');
            unlink($dir . '/child.php');
            rmdir($dir);
        }
    }

    // --- Cache directory ---

    public function testSetCacheDirPlacesTheCompiledTemplateThere(): void
    {
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/plain.tpl.php', 'Hello ## $name ##');

        $cacheDir = $dir . '/cache';
        $html = (new Template($dir))->setCacheDir($cacheDir)->render('plain', ['name' => 'Alice']);

        $this->assertSame('Hello Alice', $html);
        $this->assertCount(1, glob($cacheDir . '/*.php') ?: []);

        array_map('unlink', glob($cacheDir . '/*.php') ?: []);
        rmdir($cacheDir);
        unlink($dir . '/plain.tpl.php');
        rmdir($dir);
    }

    // --- Components ---

    public function testComponent(): void
    {
        $tpl = new Template($this->fixtures);
        $html = $tpl->component('components/card', [
            'title' => 'My Card',
            'body' => 'Card content',
        ]);

        $this->assertStringContainsString('<div class="card">', $html);
        $this->assertStringContainsString('<h3>My Card</h3>', $html);
        $this->assertStringContainsString('<p>Card content</p>', $html);
    }

    public function testComponentEscapesInput(): void
    {
        $tpl = new Template($this->fixtures);
        $html = $tpl->component('components/card', [
            'title' => '<b>XSS</b>',
            'body' => 'body',
        ]);

        $this->assertStringContainsString('&lt;b&gt;XSS&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>XSS</b>', $html);
    }

    public function testComponentIgnoresItsOwnLayout(): void
    {
        // A component is a self-contained fragment: an extends() inside it is
        // silently ignored (the missing layout is never even looked up), and it
        // must not disturb the layout of the render it was called from. Both
        // halves of that promise are pinned here.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/layout.php', 'L[<?= $this->section("body", "none") ?>]');
        file_put_contents($dir . '/parent.php', '<?php $this->extends("layout") ?><?php $this->start("body") ?>[<?= $this->component("comp") ?>]<?php $this->end() ?>');
        file_put_contents($dir . '/comp.php', '<?php $this->extends("elsewhere") ?>FRAG');

        $tpl = new Template($dir);

        try {
            $this->assertSame('FRAG', $tpl->component('comp'));
            $this->assertSame('L[[FRAG]]', $tpl->render('parent'));
        } finally {
            unlink($dir . '/layout.php');
            unlink($dir . '/parent.php');
            unlink($dir . '/comp.php');
            rmdir($dir);
        }
    }

    // --- Multiple paths (theme support) ---

    public function testAddPathOverridesTemplate(): void
    {
        $tpl = new Template($this->fixtures);
        $tpl->addPath($this->fixtures . '/themes/dark');

        $html = $tpl->render('hello', ['name' => 'Sam', 'items' => []]);
        $this->assertStringContainsString('Dark Theme:', $html);
        $this->assertStringNotContainsString('Hello,', $html);
    }

    public function testAddPathFallsBackToBase(): void
    {
        $tpl = new Template($this->fixtures);
        $tpl->addPath($this->fixtures . '/themes/dark');

        // profile.php is not in themes/dark, should fall back to base
        $html = $tpl->render('profile', [
            'name' => 'x',
            'age' => 1,
            'bio' => '',
        ]);

        $this->assertStringContainsString('Layout Header', $html);
    }

    // --- Error handling ---

    public function testTemplateNotFoundThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template not found');
        $this->tpl->render('nonexistent');
    }

    public function testComponentNotFoundThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Component not found');
        $this->tpl->component('nonexistent');
    }

    public function testMissingLayoutIsNamedAsALayout(): void
    {
        // A child template whose layout resolves to nothing is a different failure
        // from the child template itself being missing, and only this one says so.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/orphan.php', '<?php $this->extends("layout/missing") ?>body');

        try {
            (new Template($dir))->render('orphan');
            $this->fail('should have reported the layout it cannot find');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Layout template not found', $e->getMessage());
        } finally {
            unlink($dir . '/orphan.php');
            rmdir($dir);
        }
    }

    public function testAFailingTemplateLeavesNoBuffersOrSectionsOpen(): void
    {
        // start() opens a buffer and remembers the section; the template then
        // fails. Both have to be unwound, or the next render inherits them.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/boom.php',
            '<?php $this->start("content") ?>captured<?php throw new \RuntimeException("boom") ?>'
        );

        $level = ob_get_level();
        try {
            (new Template($dir))->render('boom');
            $this->fail('should have propagated the template failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        } finally {
            unlink($dir . '/boom.php');
            rmdir($dir);
        }

        $this->assertSame($level, ob_get_level());

        // The text the failing template had captured must not reach another render.
        $html = $this->tpl->render('layout/main');
        $this->assertStringContainsString('<title>Default Title</title>', $html);
        $this->assertStringNotContainsString('captured', $html);
    }

    // --- Buffer hygiene and component state isolation ---

    public function testUnpairedStartLeavesNoBufferBehind(): void
    {
        // start() without end() used to leave the evaluate buffer open, so every
        // render raised the global buffer level by one. The stray buffer must be
        // closed with whatever output it held, and nothing may leak forward.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/open.tpl.php', '<?php $this->start("x") ?>captured');
        file_put_contents($dir . '/clean.tpl.php', 'clean');

        $level = ob_get_level();
        $tpl = new Template($dir);

        $this->assertStringContainsString('captured', $tpl->render('open'));
        $this->assertSame($level, ob_get_level());
        $this->assertSame('clean', $tpl->render('clean'));

        unlink($dir . '/open.tpl.php');
        unlink($dir . '/clean.tpl.php');
        rmdir($dir);
    }

    public function testComponentDoesNotLeakSections(): void
    {
        // A component must not read sections captured by a previous render.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/a.tpl.php', '<?php $this->start("t") ?>AAA<?php $this->end() ?>');
        file_put_contents($dir . '/c.tpl.php', '<?= $this->section("t", "DEFAULT") ?>');

        $tpl = new Template($dir);
        $tpl->render('a'); // captures section t = AAA

        $this->assertSame('DEFAULT', $tpl->component('c'));

        unlink($dir . '/a.tpl.php');
        unlink($dir . '/c.tpl.php');
        rmdir($dir);
    }

    public function testComponentInsideRenderKeepsLayoutIntact(): void
    {
        // A component rendered inside a start/end pair must not swallow the
        // section being captured around it, nor the layout set by the child.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/layout.php',
            '<title><?= $this->section("title", "Default") ?></title>[<?= $this->section("body", "no body") ?>]'
        );
        file_put_contents(
            $dir . '/child.php',
            '<?php $this->extends("layout") ?>'
            . '<?php $this->start("title") ?>T<?php $this->end() ?>'
            . '<?php $this->start("body") ?><?= $this->component("comp") ?>B<?php $this->end() ?>'
        );
        file_put_contents($dir . '/comp.tpl.php', 'COMP');

        $html = (new Template($dir))->render('child');

        $this->assertStringContainsString('<title>T</title>', $html);
        $this->assertStringContainsString('[COMPB]', $html);

        unlink($dir . '/layout.php');
        unlink($dir . '/child.php');
        unlink($dir . '/comp.tpl.php');
        rmdir($dir);
    }

    public function testTemplateNamesCannotClimbOutOfTheRegisteredPaths(): void
    {
        // A name is joined with the registered roots, so ".." used to reach a file
        // beside them and include it.
        $dir = sys_get_temp_dir() . '/migears_template_' . uniqid();
        mkdir($dir . '/views', 0755, true);
        file_put_contents($dir . '/outside.tpl.php', 'escaped');

        $tpl = new Template($dir . '/views');
        try {
            foreach (['render' => 'Template not found', 'component' => 'Component not found'] as $method => $expected) {
                try {
                    $tpl->{$method}('../outside');
                    $this->fail("{$method}() should not reach a template outside the registered paths");
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString($expected, $e->getMessage());
                    $this->assertStringContainsString('../outside', $e->getMessage());
                }
            }
        } finally {
            unlink($dir . '/outside.tpl.php');
            rmdir($dir . '/views');
            rmdir($dir);
        }
    }

    public function testExistsReturnsTrueForExistingTemplate(): void
    {
        $this->assertTrue($this->tpl->exists('hello'));
        $this->assertTrue($this->tpl->exists('layout/main'));
    }

    public function testExistsReturnsFalseForMissingTemplate(): void
    {
        $this->assertFalse($this->tpl->exists('nonexistent'));
    }

    public function testExceptionInTemplateIsThrown(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template error');
        $this->tpl->render('error');
    }

    // --- escaping is a property of the syntax, not a runtime switch ---

    public function testRawCallInsideSugarIsEscapedAnyway(): void
    {
        // `## ##` always wraps its expression in $this->e(), so asking for raw
        // inside it is a silent no-op: `### ###` is the raw form. This is the
        // trap worth a test, because the two look almost identical.
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('raw-trap', ['html' => '<strong>Bold</strong>']);

        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringContainsString('&lt;strong&gt;', $html);
    }

    // --- end without start ---

    public function testEndWithoutStartThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('end() called without a matching start()');

        $tpl = new Template($this->fixtures);
        $tpl->end();
    }

    public function testEndNamesABufferThatIsAlreadyClosed(): void
    {
        // start() opens a buffer and end() closes it. A template is free to close it
        // itself in between, and then the buffer on top belongs to someone else:
        // ob_get_clean() used to clean that one — the caller's, PHPUnit's in a test —
        // and store it as this section's content from a property declared string.
        $tpl = new Template($this->fixtures);
        $tpl->start('content');
        ob_end_clean();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the buffer start() opened is gone');

        $tpl->end();
    }

    // --- {{ }} syntax sugar (.tpl.php files) ---

    public function testTplSyntaxBasicRender(): void
    {
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('hello', ['title' => 'Welcome', 'name' => 'Alice']);

        $this->assertStringContainsString('<h1>Welcome</h1>', $html);
        $this->assertStringContainsString('<p>Alice</p>', $html);
    }

    public function testTplSyntaxDefaultFallback(): void
    {
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('hello', ['title' => 'Hi']);

        $this->assertStringContainsString('<p>Guest</p>', $html);
    }

    public function testTplSyntaxHtmlEscaping(): void
    {
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('hello', ['title' => '<script>', 'name' => '<b>Bold</b>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }

    public function testTplSyntaxWithForeach(): void
    {
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('loop', ['items' => ['apple', 'banana', 'cherry']]);

        $this->assertStringContainsString('<li>apple</li>', $html);
        $this->assertStringContainsString('<li>banana</li>', $html);
        $this->assertStringContainsString('<li>cherry</li>', $html);
    }

    public function testTplSyntaxRawOutput(): void
    {
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('raw', [
            'html' => '<strong>Bold</strong>',
            'title' => '<script>',
        ]);

        // Raw output should NOT be escaped
        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        // Regular output SHOULD be escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTplSyntaxWithCustomCacheDir(): void
    {
        $cacheDir = sys_get_temp_dir() . '/migears_tpl_test_' . uniqid();
        $tpl = new Template($this->fixtures . '/syntax', $cacheDir);

        $html = $tpl->render('hello', ['title' => 'Cached', 'name' => 'Bob']);

        $this->assertStringContainsString('<h1>Cached</h1>', $html);
        $this->assertStringContainsString('<p>Bob</p>', $html);

        // Cache file should exist
        $cacheFiles = glob($cacheDir . '/*') ?: [];
        $this->assertNotEmpty($cacheFiles);

        // Second render should use cache
        $html2 = $tpl->render('hello', ['title' => 'Cached', 'name' => 'Bob']);
        $this->assertSame($html, $html2);

        // Cleanup
        array_map('unlink', $cacheFiles);
        rmdir($cacheDir);
    }

    public function testPhpTemplateStillWorksAfterCompilerAdded(): void
    {
        // Existing .php fixtures should still work unchanged
        $html = $this->tpl->render('hello', ['name' => 'Alice', 'items' => ['a']]);
        $this->assertStringContainsString('<h1>Hello, Alice!</h1>', $html);
    }

    public function testTplSyntaxTakesPriorityOverPhp(): void
    {
        // If both hello.tpl.php and hello.php exist, .tpl.php should win
        $tpl = new Template($this->fixtures . '/syntax');
        $html = $tpl->render('hello', ['title' => 'T', 'name' => 'N']);
        $this->assertStringContainsString('<h1>T</h1>', $html);
    }
}
