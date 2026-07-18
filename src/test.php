<?php

declare(strict_types=1);

namespace sigi\test;

/**
 * A tiny test runner for sigi views. view() renders a view as one identity
 * pass and hands back an Expect to assert over its HTML.
 *
 *   use function sigi\test\{view, throws, summary};
 *
 *   view('row', 'user_row', $user)
 *       ->contains('Ada')
 *       ->matches('#<a href="/u/1">#');
 *
 *   throws('bad script body', fn() => el\SCRIPT('alert(1)'));
 *   exit(summary());
 */

final class Runner
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static bool $reported = false;
}

/** @internal */
function record(string $label, bool $ok, string $detail = ''): void
{
    if ($ok) {
        Runner::$pass++;
        echo "ok   {$label}\n";
        return;
    }
    Runner::$fail++;
    echo "FAIL {$label}\n";
    if ($detail !== '') {
        echo '     ' . str_replace("\n", "\n     ", $detail) . "\n";
    }
}

/** Render a view and return an Expect over its HTML. */
function view(string $label, string|callable $view, mixed ...$args): Expect
{
    // each render is one pass; pass() opens identity + the sheet and tears it down
    return new Expect($label, (string) \sigi\pass(fn() => $view(...$args)));
}

/** Assert that rendering throws — e.g. an unsafe value being refused. */
function throws(string $label, callable $fn): void
{
    try {
        $fn();
        record($label, false, 'expected an exception, none thrown');
    } catch (\Throwable) {
        record($label, true);
    }
}

/** Print the tally and return a process exit code (0 = all passed). */
function summary(): int
{
    Runner::$reported = true;
    $total = Runner::$pass + Runner::$fail;
    echo "\n{$total} checks, " . Runner::$pass . ' passed'
        . (Runner::$fail ? ', ' . Runner::$fail . ' FAILED' : '') . "\n";
    return Runner::$fail > 0 ? 1 : 0;
}

// If the file ends without an explicit summary(), still print one.
register_shutdown_function(static function (): void {
    if (!Runner::$reported && (Runner::$pass || Runner::$fail)) {
        summary();
    }
});

/** Fluent assertions over a rendered view's HTML. */
final class Expect
{
    public function __construct(private string $label, public readonly string $html) {}

    public function equals(string $want): self
    {
        record("{$this->label} equals", $this->html === $want, $this->html === $want ? '' : "got:  {$this->html}\nwant: {$want}");
        return $this;
    }

    public function contains(string $needle): self
    {
        record("{$this->label} contains " . var_export($needle, true), str_contains($this->html, $needle), "in: {$this->html}");
        return $this;
    }

    public function lacks(string $needle): self
    {
        record("{$this->label} lacks " . var_export($needle, true), !str_contains($this->html, $needle), "in: {$this->html}");
        return $this;
    }

    public function matches(string $regex): self
    {
        record("{$this->label} matches {$regex}", (bool) preg_match($regex, $this->html), "in: {$this->html}");
        return $this;
    }
}
