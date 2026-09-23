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

    public function testSectionWithDefaultValue(): void
    {
        $tpl = new Template($this->fixtures);
        $html = $tpl->render('layout/main');
        $this->assertStringContainsString('<title>Default Title</title>', $html);
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
