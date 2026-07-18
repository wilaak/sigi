<?php

declare(strict_types=1);

namespace sigi\el;

use sigi;

//
// Elements and the helpers that produce content. Element functions are ALL CAPS,
// the way the original HTML spec wrote tags (<BODY>, <TABLE>): the shape jumps
// out of surrounding PHP at a glance. PHP function names are case-insensitive, so
// this is a convention (enforce it with a linter), not a language rule. Reserved
// words still take a trailing underscore (VAR_). Web components and anything not
// curated here go through tag('my-widget', ...).
//
// Content helpers (tag, text, frag, comment, doctype) stay lowercase — they
// are not HTML tag names, so they read as machinery, not markup.
//

// ---------------------------------------------------------------------------
// Node helpers (lowercase: these make content but are not element tags)
// ---------------------------------------------------------------------------

/** Any element by name (web components, uncurated tags). `<my-widget></my-widget>` */
function tag(string $name, mixed ...$a): sigi\Html
{
    // The one place a tag name can be untrusted, so the one place it is checked.
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]*$/', $name)) {
        throw new \InvalidArgumentException("illegal tag name: '{$name}'");
    }
    return sigi\element($name, ...$a);
}

/** Escaped text node. */
function text(string ...$parts): sigi\Html
{
    $out = '';
    foreach ($parts as $p) {
        $out .= sigi\esc_text($p);
    }
    return new sigi\Html($out);
}

/** Fragment: concatenated siblings, no wrapper. */
function frag(mixed ...$nodes): sigi\Html
{
    $names = [];
    $values = [];
    $body = '';
    sigi\collect($nodes, $names, $values, $body, '', false);
    return new sigi\Html($body);
}

/** HTML comment. `<!-- ... -->` */
function comment(string $s): sigi\Html
{
    return new sigi\Html('<!--' . str_replace('--', '- -', $s) . '-->');
}

/** HTML5 doctype. `<!DOCTYPE html>` */
function doctype(): sigi\Html
{
    return new sigi\Html('<!DOCTYPE html>');
}

// ---------------------------------------------------------------------------
// Document + sections
// ---------------------------------------------------------------------------

/** Root document element. `<html></html>` */
function HTML(mixed ...$a): sigi\Html
{
    return sigi\element('html', ...$a);
}
/** Document metadata container. `<head></head>` */
function HEAD(mixed ...$a): sigi\Html
{
    return sigi\element('head', ...$a);
}
/** Document body. `<body></body>` */
function BODY(mixed ...$a): sigi\Html
{
    return sigi\element('body', ...$a);
}
/** Document title. `<title></title>` */
function TITLE(mixed ...$a): sigi\Html
{
    return sigi\element('title', ...$a);
}
/** Document metadata. `<meta>` */
function META(mixed ...$a): sigi\Html
{
    return sigi\element('meta', ...$a);
}
/** External resource link. `<link>` */
function LINK(mixed ...$a): sigi\Html
{
    return sigi\element('link', ...$a);
}
/** Document base URL. `<base>` */
function BASE(mixed ...$a): sigi\Html
{
    return sigi\element('base', ...$a);
}
/** Style information. `<style></style>` */
function STYLE(mixed ...$a): sigi\Html
{
    return sigi\element('style', ...$a);
}
/** Embedded script. `<script></script>` */
function SCRIPT(mixed ...$a): sigi\Html
{
    return sigi\element('script', ...$a);
}
/** Fallback for no scripting. `<noscript></noscript>` */
function NOSCRIPT(mixed ...$a): sigi\Html
{
    return sigi\element('noscript', ...$a);
}
/** Introductory content. `<header></header>` */
function HEADER(mixed ...$a): sigi\Html
{
    return sigi\element('header', ...$a);
}
/** Footer content. `<footer></footer>` */
function FOOTER(mixed ...$a): sigi\Html
{
    return sigi\element('footer', ...$a);
}
/** Main content. `<main></main>` */
function MAIN(mixed ...$a): sigi\Html
{
    return sigi\element('main', ...$a);
}
/** Navigation links. `<nav></nav>` */
function NAV(mixed ...$a): sigi\Html
{
    return sigi\element('nav', ...$a);
}
/** Generic section. `<section></section>` */
function SECTION(mixed ...$a): sigi\Html
{
    return sigi\element('section', ...$a);
}
/** Search controls container. `<search></search>` */
function SEARCH(mixed ...$a): sigi\Html
{
    return sigi\element('search', ...$a);
}
/** Self-contained article. `<article></article>` */
function ARTICLE(mixed ...$a): sigi\Html
{
    return sigi\element('article', ...$a);
}
/** Tangential aside content. `<aside></aside>` */
function ASIDE(mixed ...$a): sigi\Html
{
    return sigi\element('aside', ...$a);
}
/** Contact address. `<address></address>` */
function ADDRESS(mixed ...$a): sigi\Html
{
    return sigi\element('address', ...$a);
}
/** Heading group. `<hgroup></hgroup>` */
function HGROUP(mixed ...$a): sigi\Html
{
    return sigi\element('hgroup', ...$a);
}

// ---------------------------------------------------------------------------
// Headings
// ---------------------------------------------------------------------------

/** Level 1 heading. `<h1></h1>` */
function H1(mixed ...$a): sigi\Html
{
    return sigi\element('h1', ...$a);
}
/** Level 2 heading. `<h2></h2>` */
function H2(mixed ...$a): sigi\Html
{
    return sigi\element('h2', ...$a);
}
/** Level 3 heading. `<h3></h3>` */
function H3(mixed ...$a): sigi\Html
{
    return sigi\element('h3', ...$a);
}
/** Level 4 heading. `<h4></h4>` */
function H4(mixed ...$a): sigi\Html
{
    return sigi\element('h4', ...$a);
}
/** Level 5 heading. `<h5></h5>` */
function H5(mixed ...$a): sigi\Html
{
    return sigi\element('h5', ...$a);
}
/** Level 6 heading. `<h6></h6>` */
function H6(mixed ...$a): sigi\Html
{
    return sigi\element('h6', ...$a);
}

// ---------------------------------------------------------------------------
// Grouping
// ---------------------------------------------------------------------------

/** Content division. `<div></div>` */
function DIV(mixed ...$a): sigi\Html
{
    return sigi\element('div', ...$a);
}
/** Inline text span. `<span></span>` */
function SPAN(mixed ...$a): sigi\Html
{
    return sigi\element('span', ...$a);
}
/** Paragraph. `<p></p>` */
function P(mixed ...$a): sigi\Html
{
    return sigi\element('p', ...$a);
}
/** Preformatted text. `<pre></pre>` */
function PRE(mixed ...$a): sigi\Html
{
    return sigi\element('pre', ...$a);
}
/** Block quotation. `<blockquote></blockquote>` */
function BLOCKQUOTE(mixed ...$a): sigi\Html
{
    return sigi\element('blockquote', ...$a);
}
/** Figure with optional caption. `<figure></figure>` */
function FIGURE(mixed ...$a): sigi\Html
{
    return sigi\element('figure', ...$a);
}
/** Figure caption. `<figcaption></figcaption>` */
function FIGCAPTION(mixed ...$a): sigi\Html
{
    return sigi\element('figcaption', ...$a);
}
/** Thematic break. `<hr>` */
function HR(mixed ...$a): sigi\Html
{
    return sigi\element('hr', ...$a);
}

// Lists
/** Unordered list. `<ul></ul>` */
function UL(mixed ...$a): sigi\Html
{
    return sigi\element('ul', ...$a);
}
/** Ordered list. `<ol></ol>` */
function OL(mixed ...$a): sigi\Html
{
    return sigi\element('ol', ...$a);
}
/** List item. `<li></li>` */
function LI(mixed ...$a): sigi\Html
{
    return sigi\element('li', ...$a);
}
/** Menu list. `<menu></menu>` */
function MENU(mixed ...$a): sigi\Html
{
    return sigi\element('menu', ...$a);
}
/** Description list. `<dl></dl>` */
function DL(mixed ...$a): sigi\Html
{
    return sigi\element('dl', ...$a);
}
/** Description term. `<dt></dt>` */
function DT(mixed ...$a): sigi\Html
{
    return sigi\element('dt', ...$a);
}
/** Description details. `<dd></dd>` */
function DD(mixed ...$a): sigi\Html
{
    return sigi\element('dd', ...$a);
}

// ---------------------------------------------------------------------------
// Text-level
// ---------------------------------------------------------------------------

/** Hyperlink anchor. `<a></a>` */
function A(mixed ...$a): sigi\Html
{
    return sigi\element('a', ...$a);
}
/** Emphasis. `<em></em>` */
function EM(mixed ...$a): sigi\Html
{
    return sigi\element('em', ...$a);
}
/** Strong importance. `<strong></strong>` */
function STRONG(mixed ...$a): sigi\Html
{
    return sigi\element('strong', ...$a);
}
/** Side comment, small print. `<small></small>` */
function SMALL(mixed ...$a): sigi\Html
{
    return sigi\element('small', ...$a);
}
/** Strikethrough, no longer accurate. `<s></s>` */
function S(mixed ...$a): sigi\Html
{
    return sigi\element('s', ...$a);
}
/** Cited title. `<cite></cite>` */
function CITE(mixed ...$a): sigi\Html
{
    return sigi\element('cite', ...$a);
}
/** Inline quotation. `<q></q>` */
function Q(mixed ...$a): sigi\Html
{
    return sigi\element('q', ...$a);
}
/** Inline code. `<code></code>` */
function CODE(mixed ...$a): sigi\Html
{
    return sigi\element('code', ...$a);
}
/** Keyboard input. `<kbd></kbd>` */
function KBD(mixed ...$a): sigi\Html
{
    return sigi\element('kbd', ...$a);
}
/** Sample output. `<samp></samp>` */
function SAMP(mixed ...$a): sigi\Html
{
    return sigi\element('samp', ...$a);
}
/** Variable. `<var></var>` */
function VAR_(mixed ...$a): sigi\Html
{
    return sigi\element('var', ...$a);
}
/** Highlighted text. `<mark></mark>` */
function MARK(mixed ...$a): sigi\Html
{
    return sigi\element('mark', ...$a);
}
/** Subscript. `<sub></sub>` */
function SUB(mixed ...$a): sigi\Html
{
    return sigi\element('sub', ...$a);
}
/** Superscript. `<sup></sup>` */
function SUP(mixed ...$a): sigi\Html
{
    return sigi\element('sup', ...$a);
}
/** Date or time. `<time></time>` */
function TIME(mixed ...$a): sigi\Html
{
    return sigi\element('time', ...$a);
}
/** Bring attention, bold. `<b></b>` */
function B(mixed ...$a): sigi\Html
{
    return sigi\element('b', ...$a);
}
/** Alternate voice, italic. `<i></i>` */
function I(mixed ...$a): sigi\Html
{
    return sigi\element('i', ...$a);
}
/** Unarticulated annotation, underline. `<u></u>` */
function U(mixed ...$a): sigi\Html
{
    return sigi\element('u', ...$a);
}
/** Abbreviation. `<abbr></abbr>` */
function ABBR(mixed ...$a): sigi\Html
{
    return sigi\element('abbr', ...$a);
}
/** Defining term. `<dfn></dfn>` */
function DFN(mixed ...$a): sigi\Html
{
    return sigi\element('dfn', ...$a);
}
/** Deleted text. `<del></del>` */
function DEL(mixed ...$a): sigi\Html
{
    return sigi\element('del', ...$a);
}
/** Inserted text. `<ins></ins>` */
function INS(mixed ...$a): sigi\Html
{
    return sigi\element('ins', ...$a);
}
/** Bidirectional isolate. `<bdi></bdi>` */
function BDI(mixed ...$a): sigi\Html
{
    return sigi\element('bdi', ...$a);
}
/** Bidirectional override. `<bdo></bdo>` */
function BDO(mixed ...$a): sigi\Html
{
    return sigi\element('bdo', ...$a);
}
/** Ruby annotation. `<ruby></ruby>` */
function RUBY(mixed ...$a): sigi\Html
{
    return sigi\element('ruby', ...$a);
}
/** Ruby text. `<rt></rt>` */
function RT(mixed ...$a): sigi\Html
{
    return sigi\element('rt', ...$a);
}
/** Ruby fallback parenthesis. `<rp></rp>` */
function RP(mixed ...$a): sigi\Html
{
    return sigi\element('rp', ...$a);
}
/** Line break. `<br>` */
function BR(mixed ...$a): sigi\Html
{
    return sigi\element('br', ...$a);
}
/** Line break opportunity. `<wbr>` */
function WBR(mixed ...$a): sigi\Html
{
    return sigi\element('wbr', ...$a);
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

/** Table. `<table></table>` */
function TABLE(mixed ...$a): sigi\Html
{
    return sigi\element('table', ...$a);
}
/** Table caption. `<caption></caption>` */
function CAPTION(mixed ...$a): sigi\Html
{
    return sigi\element('caption', ...$a);
}
/** Column group. `<colgroup></colgroup>` */
function COLGROUP(mixed ...$a): sigi\Html
{
    return sigi\element('colgroup', ...$a);
}
/** Table column. `<col>` */
function COL(mixed ...$a): sigi\Html
{
    return sigi\element('col', ...$a);
}
/** Table header group. `<thead></thead>` */
function THEAD(mixed ...$a): sigi\Html
{
    return sigi\element('thead', ...$a);
}
/** Table body group. `<tbody></tbody>` */
function TBODY(mixed ...$a): sigi\Html
{
    return sigi\element('tbody', ...$a);
}
/** Table footer group. `<tfoot></tfoot>` */
function TFOOT(mixed ...$a): sigi\Html
{
    return sigi\element('tfoot', ...$a);
}
/** Table row. `<tr></tr>` */
function TR(mixed ...$a): sigi\Html
{
    return sigi\element('tr', ...$a);
}
/** Table data cell. `<td></td>` */
function TD(mixed ...$a): sigi\Html
{
    return sigi\element('td', ...$a);
}
/** Table header cell. `<th></th>` */
function TH(mixed ...$a): sigi\Html
{
    return sigi\element('th', ...$a);
}

// ---------------------------------------------------------------------------
// Forms
// ---------------------------------------------------------------------------

/** Form. `<form></form>` */
function FORM(mixed ...$a): sigi\Html
{
    return sigi\element('form', ...$a);
}
/** Grouped form controls. `<fieldset></fieldset>` */
function FIELDSET(mixed ...$a): sigi\Html
{
    return sigi\element('fieldset', ...$a);
}
/** Fieldset caption. `<legend></legend>` */
function LEGEND(mixed ...$a): sigi\Html
{
    return sigi\element('legend', ...$a);
}
/** Control label. `<label></label>` */
function LABEL(mixed ...$a): sigi\Html
{
    return sigi\element('label', ...$a);
}
/** Button. `<button></button>` */
function BUTTON(mixed ...$a): sigi\Html
{
    return sigi\element('button', ...$a);
}
/** Selection dropdown. `<select></select>` */
function SELECT(mixed ...$a): sigi\Html
{
    return sigi\element('select', ...$a);
}
/** Select option. `<option></option>` */
function OPTION(mixed ...$a): sigi\Html
{
    return sigi\element('option', ...$a);
}
/** Option group. `<optgroup></optgroup>` */
function OPTGROUP(mixed ...$a): sigi\Html
{
    return sigi\element('optgroup', ...$a);
}
/** Multiline text input. `<textarea></textarea>` */
function TEXTAREA(mixed ...$a): sigi\Html
{
    return sigi\element('textarea', ...$a);
}
/** Autocomplete option list. `<datalist></datalist>` */
function DATALIST(mixed ...$a): sigi\Html
{
    return sigi\element('datalist', ...$a);
}
/** Calculation output. `<output></output>` */
function OUTPUT(mixed ...$a): sigi\Html
{
    return sigi\element('output', ...$a);
}
/** Progress bar. `<progress></progress>` */
function PROGRESS(mixed ...$a): sigi\Html
{
    return sigi\element('progress', ...$a);
}
/** Scalar gauge. `<meter></meter>` */
function METER(mixed ...$a): sigi\Html
{
    return sigi\element('meter', ...$a);
}
/** Form input. `<input>` */
function INPUT(mixed ...$a): sigi\Html
{
    return sigi\element('input', ...$a);
}

// ---------------------------------------------------------------------------
// Interactive + embedded
// ---------------------------------------------------------------------------

/** Disclosure widget. `<details></details>` */
function DETAILS(mixed ...$a): sigi\Html
{
    return sigi\element('details', ...$a);
}
/** Disclosure summary. `<summary></summary>` */
function SUMMARY(mixed ...$a): sigi\Html
{
    return sigi\element('summary', ...$a);
}
/** Dialog box. `<dialog></dialog>` */
function DIALOG(mixed ...$a): sigi\Html
{
    return sigi\element('dialog', ...$a);
}
/** Responsive image container. `<picture></picture>` */
function PICTURE(mixed ...$a): sigi\Html
{
    return sigi\element('picture', ...$a);
}
/** Media source. `<source>` */
function SOURCE(mixed ...$a): sigi\Html
{
    return sigi\element('source', ...$a);
}
/** Image. `<img>` */
function IMG(mixed ...$a): sigi\Html
{
    return sigi\element('img', ...$a);
}
/** Video. `<video></video>` */
function VIDEO(mixed ...$a): sigi\Html
{
    return sigi\element('video', ...$a);
}
/** Audio. `<audio></audio>` */
function AUDIO(mixed ...$a): sigi\Html
{
    return sigi\element('audio', ...$a);
}
/** Text track. `<track>` */
function TRACK(mixed ...$a): sigi\Html
{
    return sigi\element('track', ...$a);
}
/** Drawing canvas. `<canvas></canvas>` */
function CANVAS(mixed ...$a): sigi\Html
{
    return sigi\element('canvas', ...$a);
}
/** Scalable vector graphics. `<svg></svg>` */
function SVG(mixed ...$a): sigi\Html
{
    return sigi\element('svg', ...$a);
}
/** Inline frame. `<iframe></iframe>` */
function IFRAME(mixed ...$a): sigi\Html
{
    return sigi\element('iframe', ...$a);
}
/** Embedded external content. `<embed>` */
function EMBED(mixed ...$a): sigi\Html
{
    return sigi\element('embed', ...$a);
}
/** External object. `<object></object>` */
function OBJECT(mixed ...$a): sigi\Html
{
    return sigi\element('object', ...$a);
}
/** Image map area. `<area>` */
function AREA(mixed ...$a): sigi\Html
{
    return sigi\element('area', ...$a);
}
/** Image map. `<map></map>` */
function MAP(mixed ...$a): sigi\Html
{
    return sigi\element('map', ...$a);
}
/** Machine-readable value. `<data></data>` */
function DATA(mixed ...$a): sigi\Html
{
    return sigi\element('data', ...$a);
}
/** Inert template fragment. `<template></template>` */
function TEMPLATE(mixed ...$a): sigi\Html
{
    return sigi\element('template', ...$a);
}
/** Shadow DOM slot. `<slot></slot>` */
function SLOT(mixed ...$a): sigi\Html
{
    return sigi\element('slot', ...$a);
}
