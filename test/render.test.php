<?php

declare(strict_types=1);

/**
 * Framework-agnostic loading front door: DirResolver, render(), and the
 * named-argument filtering that lets a host over-supply data. Run: php render.test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use function sigi\test\{record, throws, summary};

const FIX = __DIR__ . '/fixtures';

$resolver = new sigi\DirResolver(FIX);

// --- DirResolver maps a dotted name to a file, null when absent ---
record('resolves a name to its file', $resolver->pathFor('greeting') === realpath(FIX . '/greeting.sigi.php'));
record('returns null for an unknown name', $resolver->pathFor('nope') === null);

// --- render passes data as named args to the typed entry ---
record(
    'render calls the view with named args',
    sigi\render('greeting', ['who' => 'Ada'], $resolver) === '<div class="hi"><h1>Ada</h1></div>',
);

// --- named args are order-independent (mapped by name, not position) ---
record(
    'named args map by name, not order',
    sigi\render('profile', ['role' => 'admin', 'name' => 'Ada'], $resolver)
        === '<div class="profile"><span class="name">Ada</span><span class="role">admin</span></div>',
);

// --- extra data keys (framework globals) are filtered out, not a TypeError ---
record(
    'extra data keys are ignored',
    sigi\render('greeting', ['who' => 'Ada', '__env' => 'x', 'errors' => []], $resolver) === '<div class="hi"><h1>Ada</h1></div>',
);

// --- an unknown view name is refused ---
throws('render refuses an unknown view', fn() => sigi\render('missing', [], $resolver));

// --- the resolver defaults to Env::current()->resolver when omitted ---
$prev = sigi\Env::install(new sigi\Env(resolver: $resolver));
record('render uses Env::current()->resolver by default', sigi\render('greeting', ['who' => 'Bo']) === '<div class="hi"><h1>Bo</h1></div>');

// --- no resolver at all is an error, not a guess ---
sigi\Env::install($prev);
throws('render without a resolver is an error', fn() => sigi\render('greeting', ['who' => 'x']));

exit(summary());
