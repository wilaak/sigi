<?php

declare(strict_types=1);

/**
 * Component identity: sigi\id() reads the backtrace to answer "what component
 * am I, here, in this render?". Every view here asserts the invariants that
 * make an id usable as a state key — reads are idempotent within an
 * activation, sibling activations are distinct, helpers see their caller —
 * and that the ambiguous cases fail loudly. Run: php id.test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use sigi\{el, at};
use sigi\Html;
use function sigi\test\{record, throws, summary};

/* ---- views under test ---- */

function idv_widget(): Html
{
    $id = sigi\id();
    $again = sigi\id();   // idempotent read
    return el\SPAN(at\ID($id), $id === $again ? 'ok' : 'BAD');
}
function idv_siblings(): Html
{
    $a = idv_widget();
    $b = idv_widget();   // two call sites -> two activations
    return el\DIV($a, $b);
}
function idv_inline(): Html
{
    return el\DIV(at\ID(sigi\id()), 'x');   // read in attribute position
}
function idv_scoped(array $keys): Html
{
    $out = [];
    foreach ($keys as $k) {
        $out[] = sigi\with_id($k, idv_widget(...));   // keyed loop
    }
    return el\DIV(...$out);
}
function idv_loop(int $n): Html
{
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = idv_widget();   // keyless loop: must throw for n > 1
    }
    return el\DIV(...$out);
}
function idv_nested(): Html
{
    return el\DIV(at\ID(sigi\id()), idv_siblings());   // parent + routed children
}
function idv_state(mixed $default): array
{
    return [sigi\current_id(), $default];   // an external helper: reads, never mints
}
function idv_with_helper(): Html
{
    $id = sigi\id();
    [$key] = idv_state(0);
    return el\SPAN(at\ID($id), $id === $key ? 'ok' : 'BAD');
}
function idv_child_then_helper(): Html
{
    $id = sigi\id();
    $child = idv_widget();     // child reads its own id...
    [$key] = idv_state(0);     // ...but a helper after it still answers this component
    return el\DIV(at\ID($id), $child, $id === $key ? 'ok' : 'BAD');
}
function idv_helper_in_scope(): Html
{
    $id = sigi\id();
    // scope opened AFTER the read: the helper inside it must still answer this
    // component, whose id was minted with no prefix
    [$key] = sigi\with_id('inner', fn() => idv_state(0));
    return el\SPAN(at\ID($id), $id === $key ? 'ok' : 'BAD');
}
function idv_nested_helper(): Html
{
    $id = sigi\id();
    $inner = idv_with_helper();   // helper inside the child answers the child
    [$key] = idv_state(0);        // helper out here answers this component again
    return el\DIV(at\ID($id), $inner, $id === $key ? 'ok' : 'BAD');
}
function idv_scoped_helper(array $keys): Html
{
    $id = sigi\id();
    $out = [];
    foreach ($keys as $k) {
        $out[] = sigi\with_id($k, idv_with_helper(...));   // helper inside a keyed child
    }
    [$key] = idv_state(0);            // after the loop: back to this component
    return el\DIV(at\ID($id), el\DIV(...$out), $id === $key ? 'ok' : 'BAD');
}

/* ---- every id() read inside one activation agrees with every other ---- */

$cases = [
    ['idv_widget',   []],
    ['idv_siblings', []],
    ['idv_inline',   []],
    ['idv_scoped',   [['u1', 'u2', 'u3']]],
    ['idv_loop',     [1]],
    ['idv_nested',   []],
    ['idv_with_helper', []],
    ['idv_child_then_helper', []],
    ['idv_helper_in_scope', []],
    ['idv_nested_helper', []],
    ['idv_scoped_helper', [['u1', 'u2']]],
];

foreach ($cases as [$fn, $args]) {
    // Each view stamps 'ok' when its own reads agreed and 'BAD' when they did
    // not, so one pass proves every read inside it.
    $out = (string) sigi\pass(fn() => $fn(...$args));
    record("{$fn} reads agree within the render", !str_contains($out, 'BAD'), $out);
}

/* ---- the ambiguous cases fail loudly, never guess ---- */

throws('keyless loop throws', fn() => sigi\pass(fn() => idv_loop(2)));

throws('duplicate scope keys throw', fn() => sigi\pass(fn() => idv_scoped(['same', 'same'])));

/* ---- the two loop failures name their actual cause ---- */

try {
    sigi\pass(fn() => idv_loop(2));
    record('keyless loop error says to add a key', false, 'no exception');
} catch (\LogicException $e) {
    record('keyless loop error says to add a key', str_contains($e->getMessage(), 'with_id'), $e->getMessage());
}

try {
    sigi\pass(fn() => idv_scoped(['same', 'same']));
    record('duplicate key error names the colliding scope path', false, 'no exception');
} catch (\LogicException $e) {
    record(
        'duplicate key error names the colliding scope path',
        str_contains($e->getMessage(), "scope path 'same'") && str_contains($e->getMessage(), 'same key'),
        $e->getMessage(),
    );
}

/* ---- current_id() answers the enclosing component, or throws ---- */

throws('current_id() before any read throws', fn() => sigi\pass(fn() => sigi\current_id()));

throws('current_id() outside any component throws', fn() => sigi\pass(function () {
    idv_widget();      // a component rendered and returned...
    idv_state(0);      // ...but the helper out here has no enclosing component
}));

/* ---- the ids are the readable, name-derived ones we promised ---- */

sigi\pass(fn() => record(
    'ids are function names, no lines, no hashes',
    (string) idv_siblings() === '<div><span id="idv_widget">ok</span><span id="idv_widget-2">ok</span></div>',
    (string) idv_siblings(),
));

sigi\pass(fn() => record(
    'scope keys prefix the id',
    str_contains((string) idv_scoped(['u42']), 'id="u42--idv_widget"'),
    (string) idv_scoped(['u42']),
));

exit(summary());
