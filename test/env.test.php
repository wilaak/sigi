<?php

declare(strict_types=1);

/**
 * Env: the one mutable root, encapsulated. Settings are readonly (fixed at
 * construction), the active environment is read through current() and changes
 * only through install(), which returns the previous instance so a scoped
 * caller can restore it. Installing a fresh Env is a pristine world.
 * Run: php env.test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use function sigi\test\{record, summary};
use sigi\{el, at, Env, DirResolver};

// configuration is construction; install() activates and returns the previous env
$resolver = new DirResolver(__DIR__ . '/fixtures');
$boot = Env::install(new Env(resolver: $resolver, on_rejected_url: fn(string $u) => null));
record('current() reflects the installed env', Env::current()->resolver === $resolver && Env::current()->on_rejected_url !== null);

// settings are readonly: a direct write is an engine error, not a convention
// (dynamic property name so the deliberate violation doesn't trip static analysis)
$threw = false;
try {
    $prop = 'resolver';
    Env::current()->$prop = null;
} catch (\Error) {
    $threw = true;
}
record('a direct settings write throws', $threw && Env::current()->resolver === $resolver);

// a fresh env is a pristine world: settings and registries reset together
$dirty = Env::install(new Env());
record('a fresh env has default settings', Env::current()->resolver === null && Env::current()->on_rejected_url === null);
record('a fresh env has empty registries', Env::current()->entries === []);
record('install returned the swapped-out env intact', $dirty->resolver === $resolver);

// identity is pass state: each pass starts a component's activations over
function envtest_counter(): sigi\Html
{
    return el\DIV(at\ID(sigi\id()));
}

$first = (string) sigi\pass(fn() => envtest_counter());   // first activation in this pass
$again = (string) sigi\pass(fn() => envtest_counter());   // same component, fresh pass: first again
record('identity resets each pass', $first === $again && str_contains($first, 'envtest_counter'), "{$first} vs {$again}");

// restore semantics: install() hands back a scope's previous world
Env::install($boot);
record('the boot env is restorable', Env::current() === $boot);

exit(summary());
