<?php

declare(strict_types=1);

namespace MiGears\Template\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Template\TemplateCompiler;

class TemplateCompilerTest extends TestCase
{
    private TemplateCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new TemplateCompiler();
    }

    // --- \## literal hashes ---

    public function testEscapedDoubleHashStaysLiteral(): void
    {
        $this->assertSame('## 说明 ##', $this->compiler->compile('\## 说明 \##'));
    }

    public function testEscapedTripleHashStaysLiteral(): void
    {
        $this->assertSame('### 详情 ###', $this->compiler->compile('\### 详情 \###'));
    }

    public function testEscapeWorksNextToRealInterpolation(): void
    {
        $this->assertSame(
            '## 标题 ## | <?= $this->e($name) ?>',
            $this->compiler->compile('\## 标题 \## | ## $name ##')
        );
    }

    public function testEscapedHashInsidePhpCodeIsLeftAlone(): void
    {
        // The passes are text-level, so the escape works inside PHP source too — that is
        // what lets a page compiler put a literal "##" into a generated string literal.
        $this->assertSame(
            "<?= \$this->component('card', ['title' => '## x ##']) ?>",
            $this->compiler->compile("<?= \$this->component('card', ['title' => '\\## x \\##']) ?>")
        );
    }

    public function testBackslashBeforeTheEscapeIsKept(): void
    {
        $this->assertSame('\##', $this->compiler->compile('\\\\##'));
    }

    // --- ## ## escaped output ---

    public function testVariableOutput(): void
    {
        $result = $this->compiler->compile('## $name ##');
        $this->assertSame('<?= $this->e($name) ?>', $result);
    }

    public function testPropertyAccess(): void
    {
        $result = $this->compiler->compile('## $user->name ##');
        $this->assertSame('<?= $this->e($user->name) ?>', $result);
    }

    public function testArrayAccess(): void
    {
        $result = $this->compiler->compile('## $user["name"] ##');
        $this->assertSame('<?= $this->e($user["name"]) ?>', $result);
    }

    public function testMethodCall(): void
    {
        $result = $this->compiler->compile('## $user->getName() ##');
        $this->assertSame('<?= $this->e($user->getName()) ?>', $result);
    }

    public function testChainedCall(): void
    {
        $result = $this->compiler->compile('## $user->profile->name ##');
        $this->assertSame('<?= $this->e($user->profile->name) ?>', $result);
    }

    public function testStringLiteral(): void
    {
        $result = $this->compiler->compile('## "hello" ##');
        $this->assertSame('<?= $this->e("hello") ?>', $result);
    }

    public function testTernaryExpression(): void
    {
        $result = $this->compiler->compile('## $name ?? "guest" ##');
        $this->assertSame('<?= $this->e($name ?? "guest") ?>', $result);
    }

    public function testMultipleExpressionsOnSameLine(): void
    {
        $result = $this->compiler->compile('<span>## $first ##</span> <span>## $last ##</span>');
        $this->assertSame(
            '<span><?= $this->e($first) ?></span> <span><?= $this->e($last) ?></span>',
            $result
        );
    }

    // --- ### ### raw output ---

    public function testRawOutput(): void
    {
        $result = $this->compiler->compile('### $html ###');
        $this->assertSame('<?= $this->raw($html) ?>', $result);
    }

    public function testRawWithPropertyAccess(): void
    {
        $result = $this->compiler->compile('### $page->content ###');
        $this->assertSame('<?= $this->raw($page->content) ?>', $result);
    }

    // --- ## section('name') ## ---

    public function testSectionOutput(): void
    {
        $result = $this->compiler->compile('## section("content") ##');
        $this->assertSame("<?= \$this->section('content') ?>", $result);
    }

    public function testSectionOutputSingleQuotes(): void
    {
        $result = $this->compiler->compile("## section('sidebar') ##");
        $this->assertSame("<?= \$this->section('sidebar') ?>", $result);
    }

    // --- Native PHP preserved ---

    public function testNativePhpTagsPreserved(): void
    {
        $result = $this->compiler->compile('<?php echo $name; ?>');
        $this->assertSame('<?php echo $name; ?>', $result);
    }

    public function testShortEchoTagsPreserved(): void
    {
        $result = $this->compiler->compile('<?= $name ?>');
        $this->assertSame('<?= $name ?>', $result);
    }

    public function testForeachPreserved(): void
    {
        $source = '<?php foreach ($items as $item): ?><li><?= $item ?></li><?php endforeach; ?>';
        $result = $this->compiler->compile($source);
        $this->assertSame($source, $result);
    }

    public function testIfElsePreserved(): void
    {
        $source = '<?php if ($x): ?>A<?php else: ?>B<?php endif; ?>';
        $result = $this->compiler->compile($source);
        $this->assertSame($source, $result);
    }

    // --- Mixed syntax ---

    public function testMixedNativeAndNewSyntax(): void
    {
        $source = '<?php foreach ($items as $item): ?>' . "\n" .
                  '    <li>## $item->name ##</li>' . "\n" .
                  '<?php endforeach; ?>';
        $expected = '<?php foreach ($items as $item): ?>' . "\n" .
                   '    <li><?= $this->e($item->name) ?></li>' . "\n" .
                   '<?php endforeach; ?>';
        $result = $this->compiler->compile($source);
        $this->assertSame($expected, $result);
    }

    public function testRawAndEscapedOnSameLine(): void
    {
        $result = $this->compiler->compile('## $title ## ### $body ###');
        $this->assertSame('<?= $this->e($title) ?> <?= $this->raw($body) ?>', $result);
    }

    // --- Whitespace handling ---

    public function testWhitespaceInBraces(): void
    {
        $result = $this->compiler->compile('##  $name  ##');
        $this->assertSame('<?= $this->e($name) ?>', $result);
    }

    public function testNoSpacesInBraces(): void
    {
        $result = $this->compiler->compile('##$name##');
        $this->assertSame('<?= $this->e($name) ?>', $result);
    }

    public function testMultilineExpression(): void
    {
        $source = "## \$user->profile\n    ->name ##";
        $result = $this->compiler->compile($source);
        $this->assertStringContainsString('$this->e(', $result);
        $this->assertStringContainsString('$user->profile', $result);
    }

    // --- compileFile ---

    public function testCompileFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_test_') . '.tpl.php';
        file_put_contents($tmpFile, '<h1>## $title ##</h1>');

        $result = $this->compiler->compileFile($tmpFile);
        $this->assertSame('<h1><?= $this->e($title) ?></h1>', $result);

        unlink($tmpFile);
    }

    public function testCompileFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->compiler->compileFile('/nonexistent/file.tpl.php');
    }

    // --- Caching ---

    public function testCompileToCache(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'tpl_src_') . '.tpl.php';
        file_put_contents($source, '## $name ##');
        $cacheDir = sys_get_temp_dir() . '/migears_test_cache_' . uniqid();

        $cachePath = $this->compiler->compileToCache($source, $cacheDir);

        $this->assertFileExists($cachePath);
        $this->assertSame('<?= $this->e($name) ?>', file_get_contents($cachePath));

        // Second call should use cache (not recompile)
        $cachePath2 = $this->compiler->compileToCache($source, $cacheDir);
        $this->assertSame($cachePath, $cachePath2);

        // Modify source → should recompile
        file_put_contents($source, '## $other ##');
        // Ensure source mtime is strictly newer than cache
        touch($source, time() + 1);
        $cachePath3 = $this->compiler->compileToCache($source, $cacheDir);
        $this->assertSame('<?= $this->e($other) ?>', file_get_contents($cachePath3));

        unlink($source);
        array_map('unlink', glob($cacheDir . '/*') ?: []);
        rmdir($cacheDir);
    }

    public function testIsStaleNoCache(): void
    {
        $this->assertTrue($this->compiler->isStale('/any/source', '/nonexistent/cache'));
    }

    public function testIsStaleCacheNewer(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'tpl_src_');
        $cache = tempnam(sys_get_temp_dir(), 'tpl_cache_');
        // Cache was created after source (both just now, but cache is newer)
        // Sleep to ensure source is older
        touch($source, time() - 10);
        touch($cache, time());

        $this->assertFalse($this->compiler->isStale($source, $cache));

        unlink($source);
        unlink($cache);
    }

    public function testIsStaleSourceNewer(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'tpl_src_');
        $cache = tempnam(sys_get_temp_dir(), 'tpl_cache_');
        touch($cache, time() - 10);
        touch($source, time());

        $this->assertTrue($this->compiler->isStale($source, $cache));

        unlink($source);
        unlink($cache);
    }

    public function testGetCachePath(): void
    {
        $path = $this->compiler->getCachePath('/views/home.tpl.php', '/cache');
        $this->assertStringStartsWith('/cache/home_', $path);
        $this->assertStringEndsWith('.php', $path);
    }

    // --- Edge cases ---

    public function testPlainTextUnchanged(): void
    {
        $result = $this->compiler->compile('<p>Just plain HTML</p>');
        $this->assertSame('<p>Just plain HTML</p>', $result);
    }

    public function testEmptyString(): void
    {
        $result = $this->compiler->compile('');
        $this->assertSame('', $result);
    }

    public function testCurlyBracesNotInTemplateSyntax(): void
    {
        // CSS-like braces should not be affected
        $result = $this->compiler->compile('.class { color: red; }');
        $this->assertSame('.class { color: red; }', $result);
    }

    public function testJavaScriptObjectNotAffected(): void
    {
        // JS object syntax should not be affected (no matching pattern)
        $result = $this->compiler->compile('var x = { a: 1 };');
        $this->assertSame('var x = { a: 1 };', $result);
    }

    public function testSingleHashNotTemplateSyntax(): void
    {
        // A lone # (CSS id, color hex) should not be compiled
        $result = $this->compiler->compile('id="#main" color="#fff"');
        $this->assertSame('id="#main" color="#fff"', $result);
    }
}
