<?php

declare(strict_types=1);

/**
 * unsafe\styled(): write CSS, never name a class. The class name is a hash of
 * the rules, so it is also the dedup key — a hundred instances of a component
 * contribute one rule. sigi\styles() emits what the pass collected.
 * Run: php styled.test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use sigi\{el, at, unsafe, Html};
use function sigi\test\{record, summary};

function is(string $label, string|\Stringable $got, string $want): void
{
    $got = (string) $got;
    record($label, $got === $want, $got === $want ? '' : "got:  {$got}\nwant: {$want}");
}

/* ---- the class is derived from the rules, not written by hand ---- */

sigi\pass(function () {
    $a = unsafe\styled('color:red');
    $b = unsafe\styled('color:red');
    $c = unsafe\styled('color:blue');

    record('identical rules get the same class', $a === $b, "{$a} vs {$b}");
    record('different rules get different classes', $a !== $c, "{$a} vs {$c}");
    record('the class is a usable css identifier', preg_match('/^c[0-9a-f]{8}$/', $a) === 1, $a);
    record('the sheet holds one entry per distinct rule', count(sigi\pass_state()->sheet) === 2);
});

/* ---- a component's rules are collected once, however many instances ---- */

function styled_card(string $name): Html
{
    $cls = unsafe\styled('display:flex;gap:1rem');
    return el\DIV(at\CLS($cls), $name);
}

sigi\pass(function () {
    $cards = [];
    foreach (['Ada', 'Lin', 'Bo'] as $i => $n) {
        $cards[] = sigi\with_id("c{$i}", fn() => styled_card($n));
    }
    $html = (string) el\DIV(...$cards);

    record('every instance carries the same class', substr_count($html, 'class="c') === 3, $html);
    record('three instances contribute one rule', count(sigi\pass_state()->sheet) === 1);
    is('the sheet emits that rule once',
        sigi\styles(),
        '<style>.' . unsafe\styled('display:flex;gap:1rem') . '{display:flex;gap:1rem}</style>');
});

/* ---- per-instance variance rides a custom property, so the rule stays shared ---- */

function styled_bar(int $pct): Html
{
    $cls = unsafe\styled('height:8px;width:var(--pct)');
    return el\DIV(at\CLS($cls), at\STYLE(sigi\css\props(['--pct' => "{$pct}%"])));
}

sigi\pass(function () {
    $bars = (string) el\DIV(styled_bar(10), styled_bar(90));
    $cls = unsafe\styled('height:8px;width:var(--pct)');   // the class the rule hashes to
    is('instances vary by custom property, not by rule',
        $bars,
        '<div><div class="' . $cls . '" style="--pct:10%"></div>'
            . '<div class="' . $cls . '" style="--pct:90%"></div></div>');
    record('...and still only one rule', count(sigi\pass_state()->sheet) === 1);
});

/* ---- the sheet is pass state ---- */

sigi\pass(function () {
    record('a fresh pass starts with an empty sheet', sigi\pass_state()->sheet === []);
    is('an empty sheet emits nothing at all', sigi\styles(), '');

    unsafe\styled('color:red');
    record('a pass collects into its own sheet', count(sigi\pass_state()->sheet) === 1);
});
sigi\pass(fn() => record('the next pass does not inherit it', sigi\pass_state()->sheet === []));

// A long-lived worker renders the same page twice: the second response must
// carry the styles too, not assume the first one warmed something.
function styled_page(): Html
{
    $body = el\MAIN(styled_card('Ada'));
    return el\HTML(el\HEAD(sigi\styles()), el\BODY($body));
}
$first = (string) sigi\pass(fn() => styled_page());
$second = (string) sigi\pass(fn() => styled_page());
record('a second render re-emits the sheet', $first === $second && str_contains($second, '<style>'), $second);

/* ---- unsafe\ means unsafe, but a rawtext breakout is still shut ---- */

$out = (string) sigi\pass(function () {
    unsafe\styled('a:b</style><script>alert(1)</script>');
    return sigi\styles();
});
record('</style> cannot break out of the sheet', !str_contains($out, '</style><script>'), $out);
record('...it is neutralised, not stripped', str_contains($out, '<\\/style>'), $out);

exit(summary());
