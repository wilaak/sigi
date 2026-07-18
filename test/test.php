<?php

declare(strict_types=1);

/**
 * Smoke test. Runs without composer: require the sources and assert the rendered
 * strings. Run: php test.php
 */

require __DIR__ . '/../src/core.php';
require __DIR__ . '/../src/el.php';
require __DIR__ . '/../src/at.php';
require __DIR__ . '/../src/unsafe.php';
require __DIR__ . '/../src/css.php';
require __DIR__ . '/../src/js.php';

use sigi\{el, at, unsafe, css, js};

$pass = 0;
$fail = 0;

function is(string $label, string|\Stringable $got, string $want): void
{
    global $pass, $fail;
    $got = (string) $got;
    if ($got === $want) {
        $pass++;
        echo "ok   {$label}\n";
    } else {
        $fail++;
        echo "FAIL {$label}\n     got:  {$got}\n     want: {$want}\n";
    }
}

function throws(string $label, callable $fn): void
{
    global $pass, $fail;
    try {
        $fn();
        $fail++;
        echo "FAIL {$label} (no exception)\n";
    } catch (\Throwable) {
        $pass++;
        echo "ok   {$label}\n";
    }
}

// --- text escaping and nesting is trust ---
is('escape + nest',
    el\P('2 < 3 is ', el\STRONG('true')),
    '<p>2 &lt; 3 is <strong>true</strong></p>');

// --- attributes, url slot, ordinary slot ---
is('anchor',
    el\A(at\HREF('/invite'), at\CLS('btn'), 'Send invite'),
    '<a href="/invite" class="btn">Send invite</a>');

// --- url slot neutralises dangerous schemes, unsafe\url vouches ---
is('url scheme rejected', el\A(at\HREF('javascript:alert(1)'), 'x'), '<a href="#">x</a>');
is('url vouched', el\A(at\HREF(unsafe\url('javascript:ok()')), 'x'), '<a href="javascript:ok()">x</a>');

// --- reserved words: trailing-underscore convention (var, for, list, default, as) ---
is('for_ + Var_',
    el\frag(el\LABEL(at\FOR_('email'), 'Email'), el\VAR_('x')),
    '<label for="email">Email</label><var>x</var>');
is('as_ reserved word',
    el\LINK(at\REL('preload'), at\AS_('script'), at\HREF('/app.js')),
    '<link rel="preload" as="script" href="/app.js">');

// --- class: CLS short form and CLASS_ alias render identically ---
is('cls and class_ alias agree',
    (string) el\DIV(at\CLS('a', 'b')),
    (string) el\DIV(at\CLASS_('a', 'b')));

// --- the collision is gone: el\TITLE element AND at\TITLE attribute ---
is('title element and attribute coexist',
    el\frag(el\TITLE('Page'), el\H1(at\TITLE('tip'), 'Hi')),
    '<title>Page</title><h1 title="tip">Hi</h1>');

// --- fragments: null/false drop, arrays splat ---
$rows = [el\TR(el\TD('a')), el\TR(el\TD('b'))];
is('table splat',
    el\TABLE(at\CLS('list'), ...$rows),
    '<table class="list"><tr><td>a</td></tr><tr><td>b</td></tr></table>');
is('null and false children drop', el\DIV('x', null, false, 'y'), '<div>xy</div>');

// --- class + style merge ---
is('class merge dedupes', el\DIV(at\CLS('a b'), at\CLS('b c')), '<div class="a b c"></div>');
is('style merge',
    el\DIV(at\STYLE(unsafe\css('color:red')), at\STYLE(unsafe\css('font-weight:bold'))),
    '<div style="color:red; font-weight:bold"></div>');

// --- minted custom element (web component) + custom attr inherits slot safety ---
is('custom element via tag()',
    el\tag('sl-button', at\attr('variant', 'primary'), at\attr('hx-get', unsafe\url('/x')), at\DATA('loading', 'true'), at\REQUIRED(), 'Save'),
    '<sl-button variant="primary" hx-get="/x" data-loading="true" required>Save</sl-button>');

// --- boolean + last-wins ---
is('boolean present', el\INPUT(at\TYPE('checkbox'), at\CHECKED()), '<input type="checkbox" checked>');
is('last wins for ordinary attr', el\DIV(at\ID('a'), at\ID('b')), '<div id="b"></div>');

// --- event slot refuses bare strings, accepts unsafe\js ---
throws('event bare string refused', fn() => (string) el\BUTTON(at\DATA('on:click', 'doThing()')));
is('event js() accepted (quotes attr-escaped, browser decodes them back)',
    el\BUTTON(at\DATA('on:click', unsafe\js("@post('/x')")), '+'),
    '<button data-on:click="@post(&apos;/x&apos;)">+</button>');

// --- raw text element: body requires a voucher, breakout still guarded ---
is('script vouched body', el\SCRIPT(unsafe\js('if (a < b) { x() }')), '<script>if (a < b) { x() }</script>');
is('script breakout guarded', el\SCRIPT(unsafe\js('var s = "</script>"')), '<script>var s = "<\\/script>"</script>');
is('style vouched body', el\STYLE(unsafe\css('a{color:red}')), '<style>a{color:red}</style>');
throws('script bare body refused', fn() => (string) el\SCRIPT('alert(1)'));
throws('style bare body refused', fn() => (string) el\STYLE('a{}'));

// --- void element ignores would-be close ---
is('void element', el\IMG(at\SRC('/a.png'), at\ALT('a & b')), '<img src="/a.png" alt="a &amp; b">');

// --- unsafe\html is the explicit hole ---
is('raw passthrough', el\DIV(unsafe\html('<b>ok</b>'), ' & ', 'plain <'), '<div><b>ok</b> &amp; plain &lt;</div>');

// --- new attributes render in the right slot ---
is('list attr wires to datalist',
    el\INPUT(at\LIST_('cities'), at\NAME('city')),
    '<input list="cities" name="city">');
is('popover + target',
    el\frag(el\BUTTON(at\POPOVERTARGET('menu'), 'Open'), el\DIV(at\ID('menu'), at\POPOVER(), 'hi')),
    '<button popovertarget="menu">Open</button><div id="menu" popover>hi</div>');
is('content attr (no more attr() fallback)',
    el\META(at\NAME('viewport'), at\CONTENT('width=device-width')),
    '<meta name="viewport" content="width=device-width">');
is('formaction is a url slot',
    el\BUTTON(at\FORMACTION('javascript:alert(1)'), 'go'),
    '<button formaction="#">go</button>');
is('srcdoc attribute-escaped',
    el\IFRAME(at\SRCDOC('<b>hi & "bye"</b>')),
    '<iframe srcdoc="&lt;b&gt;hi &amp; &quot;bye&quot;&lt;/b&gt;"></iframe>');
is('object + noscript + search elements',
    el\frag(el\SEARCH(el\NOSCRIPT('no js')), el\OBJECT(at\attr('data', unsafe\url('/x.pdf')))),
    '<search><noscript>no js</noscript></search><object data="/x.pdf"></object>');
is('ol start',
    el\OL(at\START(5), el\LI('a')),
    '<ol start="5"><li>a</li></ol>');

// --- style attribute requires a voucher; css\props builds one safely ---
throws('style attr bare string refused', fn() => (string) el\DIV(at\attr('style', 'color:red')));
is('css\\props builds a style',
    el\DIV(at\STYLE(css\props(['color' => 'red', 'width' => '50%']))),
    '<div style="color:red;width:50%"></div>');
throws('css\\props rejects value injection', fn() => css\props(['color' => 'red; background:url(x)']));
throws('css\\props rejects bad property', fn() => css\props(['x;y' => 'red']));
is('css\\props drops null, keeps order',
    css\props(['margin' => null, 'padding' => '4px'])->value,
    'padding:4px');

// --- js\data serialises safely for a <script> body: no literal < survives,
//     so a string value can never start </script> or any markup context ---
is('js\\data hex-escapes markup',
    str_contains(js\data(['msg' => '</script><img onerror=alert(1)>'])->value, '<') ? 'leaked' : 'escaped',
    'escaped');
is('js\\data round-trips a value',
    js\data(['n' => 3, 'ok' => true])->value,
    '{"n":3,"ok":true}');

// --- every bare string is content, whatever its shape ---
// Class/id shorthand is only ever the explicit at\sel() call, so a
// selector-shaped string in an element is ordinary escaped text — no source
// inspection, and user input can never inject class or id.
is('a selector-shaped string is content, not class/id',
    el\DIV('.card.shadow#main', 'x'),
    '<div>.card.shadow#mainx</div>');
is('a string with spaces is content too',
    el\P('. leading dot is text'),
    '<p>. leading dot is text</p>');
is('a hostile first-arg string is escaped as content',
    el\P('<script>alert(1)</script>'),
    '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');

// --- at\sel / at\set: the compact forms as explicit calls — the call is the
//     opt-in, so no source inspection is needed at all ---
is('sel expands classes and id',
    el\DIV(at\sel('.card.wide#main'), 'x'),
    '<div class="card wide" id="main">x</div>');
is('sel merges with other class attrs',
    el\DIV(at\sel('.a'), at\CLS('b')),
    '<div class="a b"></div>');
$dyn = '.user-' . 'ada';
is('sel over a dynamic value expands (explicit intent, like at\\CLS($x))',
    el\SPAN(at\sel($dyn), 'hi'),
    '<span class="user-ada">hi</span>');
throws('sel refuses a non-selector string', fn() => at\sel('hello world'));
is('set renders a map; true is bare, false/null drop',
    el\INPUT(at\set(['type' => 'search', 'name' => 'q', 'required' => true, 'autofocus' => false, 'placeholder' => null])),
    '<input type="search" name="q" required>');
is('set values keep slot safety (url slot sanitised)',
    el\A(at\set(['href' => 'javascript:alert(1)']), 'x'),
    '<a href="#">x</a>');
throws('set style slot refuses a bare string', fn() => (string) el\DIV(at\set(['style' => 'color:red'])));
throws('set illegal attr name refused', fn() => at\set(['x=1 onload' => 'y']));
is('sel and set compose, class merges across them',
    el\DIV(at\sel('.a#i'), at\set(['class' => 'b'])),
    '<div class="a b" id="i"></div>');
// Conditional attributes are just PHP: a ternary yielding null drops out, and
// that holds for a whole attr list (at\sel returns one), not only a single Attr.
is('a ternary guards a whole sel list',
    el\frag(el\BUTTON(true ? at\sel('.busy#b') : null, 'Go'), el\BUTTON(false ? at\sel('.busy#b') : null, 'Go')),
    '<button class="busy" id="b">Go</button><button>Go</button>');

// --- component identity: sigi\id() is a read, siblings number, loops throw ---
function id_widget(): sigi\Html
{
    $a = sigi\id();
    $b = sigi\id();   // a second read answers the same id, never a new one
    return el\DIV(at\ID($a), $a === $b ? 'same' : 'DIFFERENT');
}
function id_pair(): sigi\Html
{
    $first = id_widget();    // distinct call sites (lines) are distinct
    $second = id_widget();   // activations: no keys, no scopes needed
    return el\frag($first, $second);
}

sigi\pass(fn() => is('id() reads are idempotent; sibling activations number themselves',
    id_pair(),
    '<div id="id_widget">same</div><div id="id_widget-2">same</div>'));

throws('a keyless loop over a component throws, not guesses', fn() => sigi\pass(function () {
    foreach ([1, 2] as $ignored) {
        id_widget();
    }
}));

sigi\pass(function () {
    $cards = [];
    foreach (['u7', 'u9'] as $key) {
        $cards[] = sigi\with_id($key, id_widget(...));
    }
    is('with_id() keys give loop iterations stable identities',
        el\frag(...$cards),
        '<div id="u7--id_widget">same</div><div id="u9--id_widget">same</div>');
});

// Two activations on ONE source line are indistinguishable from a loop to a
// backtrace, so they need keys too — and with_id() is an expression, so they
// can say which is which without leaving the line.
sigi\pass(fn() => is('with_id() tells apart two activations sharing a source line',
    el\DIV(sigi\with_id('cart', id_widget(...)), sigi\with_id('wish', id_widget(...))),
    '<div><div id="cart--id_widget">same</div><div id="wish--id_widget">same</div></div>'));

throws('duplicate scope keys throw (two entities, one identity)', fn() => sigi\pass(function () {
    foreach (['dup', 'dup'] as $key) {
        sigi\with_id($key, id_widget(...));
    }
}));

// with_id() closes its scope on the way out even when the body throws, so an
// aborted render cannot leak a key into whatever renders next.
sigi\pass(function () {
    try {
        sigi\with_id('boom', function () { throw new \RuntimeException('x'); });
    } catch (\RuntimeException) {
    }
    is('a scope closes even when its body throws',
        id_widget(),
        '<div id="id_widget">same</div>');   // no 'boom--' prefix leaked
});

$before = (string) sigi\pass(fn() => id_widget());
is('a fresh pass starts identity over', (string) sigi\pass(fn() => id_widget()), $before);

// --- current_id(): an external helper reads its caller's identity, never mints ---
function id_probe_helper(): string
{
    return sigi\current_id();   // not a component: no new id, no loop trip
}
function id_with_helper(): sigi\Html
{
    $id = sigi\id();
    return el\DIV(at\ID($id), $id === id_probe_helper() ? 'same' : 'DIFFERENT');
}

sigi\pass(fn() => is('current_id() hands helpers the caller identity',
    id_with_helper(),
    '<div id="id_with_helper">same</div>'));
throws('current_id() before any read throws', fn() => sigi\pass(fn() => sigi\current_id()));

// --- injection blocked by construction ---
throws('illegal attr name', fn() => at\attr('x=1 onload', 'y'));
throws('illegal tag name', fn() => el\tag('div onmouseover=alert(1)'));
throws('attr name with <', fn() => at\attr('a<b', 'c'));
throws('attr name with backtick', fn() => at\attr('a`b', 'c'));
throws('attr name with NUL', fn() => at\attr("a\0b", 'c'));
throws('attr name with control byte', fn() => at\attr("a\x01b", 'c'));
// the tighter filter still lets hypermedia attribute names through
is('hypermedia attr names allowed',
    el\DIV(at\DATA('on:click', unsafe\js('x()')), at\attr(':class', 'y'), at\attr('@click', unsafe\js('z()'))),
    '<div data-on:click="x()" :class="y" @click="z()"></div>');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
