<?php

declare(strict_types=1);

/**
 * Example of testing sigi views with the runner. Run: php views.test.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/fixtures/ns_view.php';

use sigi\{el, at, unsafe};
use sigi\Html;
use function sigi\test\{view, throws, summary};

final class Author
{
    public function __construct(public string $name, public string $profile) {}
}

/* the views under test — plain functions, no test hooks */

function byline(Author $a): Html
{
    return el\P(at\CLS('byline'), 'by ', el\A(at\HREF($a->profile), $a->name));
}

function badge(string $text): Html
{
    return el\SPAN(at\CLS('badge'), at\CLS('badge'), $text);   // repeated class attr -> merged + deduped
}

// Class/id shorthand is the explicit at\sel() call; a bare string is always
// content, whatever its shape.
function sel_explicit(): Html
{
    return el\DIV(at\sel('.admin'), 'x');   // explicit call -> class
}

function sel_dynamic(string $s): Html
{
    return el\DIV($s, 'x');                 // a string arg is content, never a class
}

view('byline renders name + link', 'byline', new Author('Ada', '/u/ada'))
    ->contains('by ')
    ->contains('>Ada</a>')
    ->matches('#<a href="/u/ada">#');

view('byline escapes hostile input', 'byline', new Author('<script>', 'javascript:alert(1)'))
    ->lacks('<script>')                     // name is escaped
    ->contains('href="#"')                  // dangerous scheme rejected
    ->contains('&lt;script&gt;');

view('badge merges repeated class', 'badge', 'new')
    ->equals('<span class="badge">new</span>');   // one class attribute, deduped

// a view in a real namespace renders like any other
view('namespaced view renders', 'sigi\testfix\card', 'Ada')
    ->equals('<div class="card"><h2>Ada</h2></div>');

// at\sel: the explicit call expands to class/id
view('at\\sel expands to class', 'sel_explicit')
    ->equals('<div class="admin">x</div>');

// a string that looks like a selector is content, never a class — the
// injection guard
view('selector-shaped string is content, not a class', 'sel_dynamic', '.admin')
    ->equals('<div>.adminx</div>')
    ->lacks('class="admin"');

// safety assertion: a bare string in a script body is refused
throws('script body needs a voucher', fn() => (string) el\SCRIPT('alert(1)'));

exit(summary());
