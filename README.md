# <img src="./assets/sigi.svg" alt="sigi" width="150">

An embedded DSL for HTML. Compose HTML from functions. Safe by construction.

## Usage

```
namespace app\layout;

use sigi;
use sigi\{el, at};

function app(?string $title, ?sigi\Html $slot): sigi\Html
{
    $html = el\HTML(
        at\sel('.h-dvh.flex.flex-col'),
        at\LANG('en'),
        el\HEAD(
            el\TITLE(isset($title) ? "{$title} | site" : 'site'),
        ),
        el\BODY(
            at\sel('.antialiased.flex.flex-col.grow'),
            el\comment('Hello, HTML!'),
            $slot,
        ),
    );
    return el\frag(el\doctype(), $html);
}

echo app('hello', el\H1('Hello, World!'));
// <!DOCTYPE html>
// <html class="h-dvh flex flex-col" lang="en">
//     <head>
//         <title>hello | site</title>
//     </head>
//     <body class="antialiased flex flex-col grow">
//         <!--Hello, HTML!-->
//         <h1>Hello, World!</h1>
//     </body>
// </html>
```

## Types

Sigi values sort themselves by type: attributes configure the tag, everything else is content. Values are escaped, elements nest and `null` and `false` disappear.

```
use sigi\{el, at};

el\P('2 < 3 is ', el\STRONG('true'));
// <p>2 &lt; 3 is <strong>true</strong></p>

function table(array $users): sigi\Html
{
    $rows = [];
    foreach ($users as $u) {
        $rows[] = el\TR(el\TD($u->name), el\TD($u->email));
    }
    return el\TABLE(at\sel('.list'), ...$rows);
}
```

Naming convention is uppercase for HTML spec. Sigi's own helpers are lowercase.

```
// any element by name
el\tag('my-widget', ...);

// children with no wrapper
el\frag($a, $b);

// escaped text node
el\text('plain text');

// <!--note-->
el\comment('note');

// <!DOCTYPE html>
el\doctype();
```

## Escaping

Sigi knows each slot and encodes it.

```
// href escaped; javascript: -> href="#"
el\A(at\HREF($user_input), 'Profile'); 

sigi\Env::install(new sigi\Env(
    on_rejected_url: fn(string $u) => $log->warning("rejected url: {$u}"),
));
```

Dangerous slots refuse bare strings: style takes `Css` types, event/script slots take `Js`. Build those values safely:

```
use sigi\{css, js, url};

// style="color:red"
at\STYLE(css\props(['color' => 'red']))        

// PHP value -> JSON safe to embed
js\data($model)

// (< > & hex-escaped; no </script> breakout)
el\SCRIPT(at\TYPE('application/json'), at\ID('state'), js\data($model));

// a data: URI safe by construction (base64 payload, validated mime) —
// for small per-render images; text/html is refused
el\IMG(at\SRC(url\data('image/png', $qr)), at\ALT('2FA setup'));

// boundary validator for user-submitted links: absolute http(s) only,
// no userinfo, optional host allowlist — throws, so validate at input time
$link = url\external($input, hosts: ['.wikipedia.org']);
```

To bypass escaping you go through `unsafe\`.

```
use sigi\unsafe;

unsafe\html($trusted_markup);   // raw HTML, verbatim
unsafe\url($value);             // skips scheme rejection in a URL slot
unsafe\css($value);             // raw CSS for a style attribute
unsafe\js($value);              // raw JS expression
```

## Control flow

Control flow is just PHP. `null` and `false` vanish from a slot, a branch that says no simply disappears:

```
function nav(bool $is_admin): sigi\Html
{
    return el\NAV(
        el\A(at\HREF('/'), 'Home'),
        $is_admin ? el\A(at\HREF('/admin'), 'Admin') : null,
    );
}
```

Loops build an array and splat it in; `match`, `?:` and `??` drop in wherever they read well:

```
function menu(array $links, ?object $user): sigi\Html
{
    $items = [];
    foreach ($links as $l) {
        $items[] = el\A(at\HREF($l->url), $l->label);
    }
    return el\NAV(
        ...$items,
        $user ? el\SPAN(at\CLS('me'), $user->nick ?? $user->name) : null,
    );
}
```

## Attributes

### Curated helpers

Every spec-defined attribute has an ALL-CAPS helper with a typed signature:

```
at\HREF('/x');
at\CLS('a', 'b');
at\ID('main');
at\DISABLED();
at\TABINDEX(2);
at\DATA('user-id', $id);    // data-user-id="..."
at\ARIA('label', 'Close');  // aria-label="Close"
at\attr('x-on:click', $v);  // any attribute by name (same slot pipeline)
```

Names that collide with PHP keywords take a trailing underscore: `at\CLASS_`, `at\FOR_`, `at\LIST_`, `at\AS_`.

### sel: selector shorthand

`at\sel()` reads a CSS-style selector and fills in `class` and `id`:

```
// <div class="card">
el\DIV(at\sel('.card'));

// <div id="main">
el\DIV(at\sel('#main'));

// <div class="card wide" id="main">
el\DIV(at\sel('.card.wide#main'));
```

### set: attribute map

```
el\INPUT(at\set([
    'type' => 'search',
    'name' => 'q',
    // true -> bare boolean attribute
    'required' => true,        
    // false/null drops the entry
    'autofocus' => $is_first,
]));
// <input type="search" name="q" required>
```

Values go through the same slot pipeline as the curated helpers, so style, event, and URL slots keep their protections.

### Conditional attributes

Conditional attributes are just PHP, the same way conditional content is: an attribute slot drops `null` and `false`, so a ternary that says no disappears.

```
function save_button(bool $busy): sigi\Html
{
    return el\BUTTON(
        // include or drop
        $busy ? at\DISABLED() : null,
        // either/or
        $busy ? at\CLS('on') : at\CLS('off'),
        // conditional class map
        at\classes('btn', ['btn--busy' => $busy]),
        'Save',
    );
}
// idle -> <button class="off btn">Save</button>
// busy -> <button disabled class="on btn btn--busy">Save</button>
```

## Component identity

`sigi\id()` answers "what component am I, here, in this render?". It is a unique, stable element id with nothing written by hand.

```
sigi\id(): string {}             // this component's identity (a read, not a mint)
sigi\current_id(): string {}    // the enclosing component's id — for helpers
sigi\with_id($key, $fn);       // render $fn under an identity scope; returns its value
sigi\pass($fn): mixed {}      // run $fn as one render pass (render() wraps the view in it)
```

### Reads and activations

Every `id()` call inside one activation returns the same string — key state with it and stamp it on the element, and they always agree:

```
function counter(): Html
{
    $id = sigi\id();                      // 'counter'
    $count = state($id, 0);               // same string...
    return el\DIV(at\ID($id), $count);    // ...same string
}

function toolbar(): Html
{
    $a = counter();          // id="counter"
    $b = counter();          // id="counter-2" — distinct call sites are
    return el\DIV($a, $b);   // distinct activations; no keys needed
}
```

### Loops need keys

The same call site repeated is a loop, so it throws rather than guesses. Key each iteration:

```
foreach ($users as $u) {
    $cards[] = sigi\with_id($u->id, fn() => card($u));   // id="u42--card"
}

$cards = array_map(fn($u) => sigi\with_id($u->id, fn() => card($u)), $users);
```

```
el\DIV(
    sigi\with_id('cart', counter(...)),       // id="cart--counter"
    sigi\with_id('wishlist', counter(...)),   // id="wishlist--counter"
);
```

### Helpers: current_id()

This is the seam for building on top of Sigi.

```
function state(mixed $default): mixed
{
    $key = sigi\current_id();   // the component that called us
    // ...
}

function counter(): Html
{
    $id = sigi\id();            // read your identity first...
    $count = state(0);          // ...then helpers can find it
    return el\DIV(at\ID($id), $count);
}
```

### The pass

## Styling components

Write CSS. Don't name it. `unsafe\styled()` takes a component's rules and returns the class to hang them on.

```
use sigi\{el, at, css, unsafe};

function card(string $name, int $pct): sigi\Html
{
    $cls = unsafe\styled('
        display: flex; gap: 1rem; padding: 1rem;
        &:hover { background: #fafafa; }
        & .bar { height: 8px; background: #ddd; width: var(--pct); }
    ');
    return el\DIV(at\CLS($cls),
        el\SPAN($name),
        el\DIV(at\CLS('bar'), at\STYLE(css\props(['--pct' => "{$pct}%"]))),
    );
}
```

```
function page(array $users): sigi\Html
{
    $body = el\MAIN(...array_map(
        fn($u) => sigi\with_id($u->id, fn() => card($u->name, $u->pct)),
        $users,
    ));
    return el\frag(el\doctype(), el\HTML(
        el\HEAD(sigi\styles()), // the body already rendered: the sheet is complete
        el\BODY($body),
    ));
}
// two cards -> one rule:
// <head><style>.c55ba908b{display:flex; gap:1rem; ...}</style></head>
// <body><main><div class="c55ba908b">…</div><div class="c55ba908b">…</div></main></body>
```

## Rendering and hosting

### Call it directly

For a persistent app that autoloads its views, nothing else is required.

```php
echo sigi\pass(fn() => views\app('Home', $slot));

// a fresh pass per render — identity and the sheet start clean each time
echo sigi\pass(fn() => views\app('Home', $slot));
```

### Render by name

For a host that maps a route to a template:

```
sigi\Env::install(new sigi\Env(
    cache: __DIR__ . '/var/sigi',
    resolver: new sigi\DirResolver('resources/views'),
));

echo sigi\render('admin.users', ['rows' => $rows]);
// resolves resources/views/admin/users.sigi.php,
// expects a users(...) function inside (entry keyed to the filename),
// passes the data as named arguments
```

```
// resources/views/admin/users.sigi.php
namespace app\views;

use sigi\{el, at};

function users(array $rows): \sigi\Html   // real typed signature —
{                                          // your IDE checks the arguments
    return el\TABLE(at\sel('.w-full'), ...array_map(
        fn($r) => el\TR(el\TD($r->name), el\TD($r->email)),
        $rows,
    ));
}
```

The seam is one interface, replace any piece:

```
interface ViewResolver
{
    public function pathFor(string $name): ?string;   // absolute path, or null
}

new sigi\DirResolver('resources/views');            // one root
new sigi\DirResolver(['app/views', 'pkg/views']);   // several, first match wins
new sigi\DirResolver('views', ext: '.sigi.php');    // extension is configurable
```

### Laravel

```
// in a service provider
$this->app['view']->addExtension('sigi.php', 'sigi');
$this->app['view']->getEngineResolver()->register('sigi', fn () => new SigiEngine());

final class SigiEngine implements \Illuminate\Contracts\View\Engine
{
    public function get($path, array $data = []): string
    {
        // one render pass per view: pass() opens identity + the sheet and closes it
        return sigi\pass(fn() => sigi\invoke(sigi\load($path), $data));
    }
}
```

Now `view('admin.users', ['rows' => $rows])` renders through sigi, and the view author gets full editor type checking on the data.
