<?php

declare(strict_types=1);

namespace MiGears\Template\Tests;

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $bin;

    protected function setUp(): void
    {
        $this->bin = dirname(__DIR__) . '/bin/compile.php';
    }

    public function testHelp(): void
    {
        [$output, $code] = $this->runCli(['--help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('cache-dir', $output);
    }

    public function testCompileFileToStdout(): void
    {
        $dir = $this->tempDir();
        $source = $dir . '/hello.tpl.php';
        file_put_contents($source, 'Hello ## $name ##');

        [$output, $code] = $this->runCli([$source]);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('<?= $this->e($name) ?>', $output);
        $this->assertStringContainsString('--- End ---', $output);
    }

    public function testCompileFileToOutput(): void
    {
        $dir = $this->tempDir();
        $source = $dir . '/hello.tpl.php';
        file_put_contents($source, 'Hello ## $name ##');

        [$output, $code] = $this->runCli([$source, $dir . '/cache/hello.php']);

        $this->assertSame(0, $code, $output);
        $this->assertFileExists($dir . '/cache/hello.php');
        $this->assertStringContainsString('<?= $this->e($name) ?>', file_get_contents($dir . '/cache/hello.php'));
    }

    public function testCompileDirectory(): void
    {
        $dir = $this->tempDir();
        mkdir($dir . '/views', 0755, true);
        file_put_contents($dir . '/views/a.tpl.php', 'A');
        file_put_contents($dir . '/views/b.tpl.php', 'B');
        file_put_contents($dir . '/views/ignore.txt', 'not a template');

        [$output, $code] = $this->runCli([$dir . '/views', $dir . '/cache']);

        $this->assertSame(0, $code, $output);
        $this->assertCount(1, glob($dir . '/cache/a_*.php'));
        $this->assertCount(1, glob($dir . '/cache/b_*.php'));
        $this->assertCount(0, glob($dir . '/cache/ignore_*.php'));
        $this->assertStringContainsString('Done. 2 file(s) compiled.', $output);
    }

    public function testBatchModeNeedsAnOutputDirectory(): void
    {
        $dir = $this->tempDir();
        mkdir($dir . '/views', 0755, true);
        file_put_contents($dir . '/views/a.tpl.php', 'A');

        [$output, $code] = $this->runCli([$dir . '/views']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('batch mode needs an output directory', $output);
    }

    public function testMissingInputIsReported(): void
    {
        [$output, $code] = $this->runCli(['/nonexistent/hello.tpl.php']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('not found', $output);
    }

    public function testUnknownOptionIsRejectedInsteadOfBecomingTheCacheDir(): void
    {
        $dir = $this->tempDir();
        mkdir($dir . '/views', 0755, true);
        file_put_contents($dir . '/views/a.tpl.php', 'A');

        // A mistyped option used to fall through to the positional list and
        // become the *cache directory*: the run succeeded and wrote
        // "--cache/a_<hash>.php" relative to the working directory.
        [$output, $code] = $this->runCli([$dir . '/views', '--cache'], cwd: $dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('unknown option: --cache', $output);
        $this->assertDirectoryDoesNotExist($dir . '/--cache');
    }

    public function testUnknownOptionIsRejectedBeforeTheInputIsRead(): void
    {
        [$output, $code] = $this->runCli(['/nonexistent/hello.tpl.php', '--out']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('unknown option: --out', $output);
    }

    public function testUnwritableTargetIsNotReportedAsSuccess(): void
    {
        $dir = $this->tempDir();
        $source = $dir . '/hello.tpl.php';
        file_put_contents($source, 'Hello ## $name ##');
        // A directory in the target's place: file_put_contents fails, and the
        // result used to be dropped, so the run printed "Compiled to: ..." and
        // exited 0 while nothing had been written.
        mkdir($dir . '/hello.php', 0755, true);

        [$output, $code] = $this->runCli([$source, $dir . '/hello.php']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('cannot write compiled file', $output);
        $this->assertStringNotContainsString('Compiled to:', $output);
    }

    public function testMissingAutoloaderIsReportedInsteadOfAFatalError(): void
    {
        // A copy of the script whose autoloader lookup finds nothing: the state
        // of a checkout where "composer install" never ran. It used to die with
        // "Uncaught Error: Failed opening required ..." and exit code 255.
        $dir = $this->tempDir();
        $source = $dir . '/hello.tpl.php';
        file_put_contents($source, 'Hello');

        [$output, $code] = $this->runCli([$source], bin: $this->binWithoutAutoloader());

        $this->assertSame(1, $code);
        $this->assertStringContainsString('composer autoloader', $output);
        $this->assertStringNotContainsString('Uncaught Error', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
    }

    public function testHelpNeedsNoAutoloader(): void
    {
        // The preflight sits after the argument parsing on purpose: asking for
        // help must not depend on the installation being complete.
        [$output, $code] = $this->runCli(['--help'], bin: $this->binWithoutAutoloader());

        $this->assertSame(0, $code);
        $this->assertStringContainsString('cache-dir', $output);
    }

    public function testUnexpectedErrorIsReportedWithTheDocumentedExitCode(): void
    {
        $dir = $this->tempDir();
        $source = $dir . '/hello.tpl.php';
        file_put_contents($source, 'Hello');

        // Reading the source stands in for any Error raised inside the compiler:
        // it must not escape as an uncaught fatal, which prints a stack trace
        // and reports 255 instead of the documented 1.
        [$output, $code] = $this->runCli([$source], ini: ['disable_functions=file_get_contents']);

        $this->assertSame(1, $code);
        $this->assertStringStartsWith('fatal: ', $output);
        $this->assertStringNotContainsString('Uncaught Error', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
    }

    public function testBatchKeepsGoingWhenOneTemplateCannotBeRead(): void
    {
        $dir = $this->tempDir();
        mkdir($dir . '/views', 0755, true);
        file_put_contents($dir . '/views/a.tpl.php', 'A');
        file_put_contents($dir . '/views/b.tpl.php', 'B');
        file_put_contents($dir . '/views/broken.tpl.php', 'C');
        chmod($dir . '/views/broken.tpl.php', 0000);
        if (is_readable($dir . '/views/broken.tpl.php')) {
            $this->markTestSkipped('this user reads files regardless of their mode');
        }

        [$output, $code] = $this->runCli([$dir . '/views', $dir . '/cache']);

        // The unreadable template used to end the run through the top-level
        // handler: neither a nor b was compiled and nothing was said about them.
        $this->assertSame(1, $code);
        $this->assertStringContainsString('broken.tpl.php', $output);
        $this->assertCount(1, glob($dir . '/cache/a_*.php'));
        $this->assertCount(1, glob($dir . '/cache/b_*.php'));
        $this->assertStringContainsString('Done. 2 file(s) compiled.', $output);

        chmod($dir . '/views/broken.tpl.php', 0644);
    }

    public function testUnreadableTemplateDoesNotLeakTheNativeWarning(): void
    {
        $dir = $this->tempDir();
        $source = $dir . '/broken.tpl.php';
        file_put_contents($source, 'C');
        chmod($source, 0000);
        if (is_readable($source)) {
            $this->markTestSkipped('this user reads files regardless of their mode');
        }

        [$output, $code] = $this->runCli([$source]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Failed to read template', $output);
        // file_get_contents() printed its own "Failed to open stream" diagnostic
        // first, so one failure was reported twice.
        $this->assertStringNotContainsString('Warning', $output);

        chmod($source, 0644);
    }

    /**
     * @param list<string> $args
     * @param list<string> $ini
     * @return array{string, int}
     */
    private function runCli(array $args, array $ini = [], ?string $bin = null, ?string $cwd = null): array
    {
        $php = escapeshellarg(PHP_BINARY);
        foreach ($ini as $setting) {
            $php .= ' -d ' . escapeshellarg($setting);
        }
        $cmd = ($cwd !== null ? 'cd ' . escapeshellarg($cwd) . ' && ' : '')
            . $php . ' ' . escapeshellarg($bin ?? $this->bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        return [implode("\n", $output), $code];
    }

    /**
     * The script copied where its autoloader lookup finds nothing: a directory in
     * the temp dir, with neither the package's own vendor/ nor a parent vendor/
     * above it.
     */
    private function binWithoutAutoloader(): string
    {
        $bin = $this->tempDir() . '/bin';
        mkdir($bin, 0755, true);
        copy($this->bin, $bin . '/compile.php');
        return $bin . '/compile.php';
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/template-cli-' . uniqid();
        mkdir($dir, 0755, true);
        $this->assertDirectoryExists($dir);
        return $dir;
    }
}
