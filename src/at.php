<?php

declare(strict_types=1);

namespace sigi\at;

use sigi;

/**
 * Any attribute by name; slot inferred from the name. `name="..."`
 * Lowercase: this is a sigi escape hatch, not a spec attribute name.
 */
function attr(
    string $name,
    string|int|float|bool|sigi\Url|sigi\Css|sigi\Js|null $value = true,
): sigi\Attr {
    return new sigi\Attr($name, $value);
}
/** data-* attribute. `data-key="..."` */
function DATA(
    string $key,
    string|int|float|bool|sigi\Url|sigi\Css|sigi\Js|null $value = true,
): sigi\Attr {
    return new sigi\Attr('data-' . $key, $value);
}
/** aria-* attribute. `aria-key="..."` */
function ARIA(string $key, string|int|float|bool|null $value): sigi\Attr
{
    return new sigi\Attr('aria-' . $key, $value);
}

// ---------------------------------------------------------------------------
// Helpers. Lowercase on purpose: ALL-CAPS names are reserved for things the
// HTML spec names (elements, attributes, and their data-*/aria-* families);
// sigi's own conveniences stay lowercase, like el\'s content helpers
// (frag, text, tag, ...).
// ---------------------------------------------------------------------------

/**
 * Class list with optional conditions. A plain string token is always kept; an
 * array is a `[token => condition]` map (Elm's `classList`) keeping only the
 * truthy ones. Mix freely. Merges across repeats like `CLS`. `class="..."`
 *
 *     at\classes('card', 'shadow', ['card--active' => $is_active]);
 */
function classes(string|array ...$items): sigi\Attr
{
    $on = [];
    foreach ($items as $item) {
        if (is_string($item)) {
            $on[] = $item;
            continue;
        }
        foreach ($item as $token => $cond) {
            if ($cond) {
                $on[] = $token;
            }
        }
    }
    return new sigi\Attr('class', implode(' ', $on));
}
/**
 * Several attributes at once from a name => value map. Values follow at\attr's
 * rules: true is a bare boolean attribute, false/null drops the entry, and each
 * value renders through the same slot pipeline as the curated helpers, so the
 * style/event/url slots keep their protections.
 *
 *     el\INPUT(at\set(['type' => 'search', 'name' => 'q', 'required' => true]))
 *
 * @param array<string, string|int|float|bool|sigi\Url|sigi\Css|sigi\Js|null> $map
 * @return list<sigi\Attr>
 */
function set(array $map): array
{
    $out = [];
    foreach ($map as $name => $value) {
        $out[] = new sigi\Attr($name, $value);
    }
    return $out;
}

/**
 * The ".a.b#id" selector shorthand as an explicit attribute: classes and id
 * from one compact string. The call itself is the opt-in, so it works with a
 * dynamic value too (your stated intent, like at\CLS($x)), and a malformed
 * selector throws instead of silently becoming content. A bare string in an
 * element is always ordinary content, whatever its shape. `class="..." id="..."`
 *
 *     el\DIV(at\sel('.card.wide#main'))
 *
 * @return list<sigi\Attr>
 */
function sel(string $selector): array
{
    if (!sigi\is_selector($selector)) {
        throw new \InvalidArgumentException(
            "at\\sel: '{$selector}' is not a selector (want e.g. '.class.other#id')",
        );
    }
    [$classes, $id] = sigi\parse_selector($selector);
    $out = [];
    if ($classes) {
        $out[] = new sigi\Attr('class', implode(' ', $classes));
    }
    if ($id !== null && $id !== '') {
        $out[] = new sigi\Attr('id', $id);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Identity + linking
// ---------------------------------------------------------------------------

/** Class list (merges across repeats). `class="..."` */
function CLS(string ...$tokens): sigi\Attr
{
    return new sigi\Attr('class', implode(' ', $tokens));
}
/** Class list alias (merges across repeats). `class="..."` */
function CLASS_(string ...$tokens): sigi\Attr
{
    return CLS(...$tokens);
}
/** Element identifier. `id="..."` */
function ID(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('id', (string) $v);
}
/** Hyperlink target. `href="..."` */
function HREF(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('href', $v);
}
/** Resource source URL. `src="..."` */
function SRC(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('src', $v);
}
/** Responsive image sources. `srcset="..."` */
function SRCSET(string $v): sigi\Attr
{
    return new sigi\Attr('srcset', $v);
}
/** Link relationship. `rel="..."` */
function REL(string $v): sigi\Attr
{
    return new sigi\Attr('rel', $v);
}
/** Preload resource type. `as="..."` */
function AS_(string $v): sigi\Attr
{
    return new sigi\Attr('as', $v);
} // reserved word: <link rel="preload" as="...">

/** Link browsing context. `target="..."` */
function TARGET(string $v): sigi\Attr
{
    return new sigi\Attr('target', $v);
}
/** Download link target. `download="..."` */
function DOWNLOAD(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('download', $v);
}
/** Link language. `hreflang="..."` */
function HREFLANG(string $v): sigi\Attr
{
    return new sigi\Attr('hreflang', $v);
}
/** Referrer policy. `referrerpolicy="..."` */
function REFERRERPOLICY(string $v): sigi\Attr
{
    return new sigi\Attr('referrerpolicy', $v);
}
/** Ping notification URLs. `ping="..."` */
function PING(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('ping', $v);
}
/** CORS request mode. `crossorigin="..."` */
function CROSSORIGIN(string $v = 'anonymous'): sigi\Attr
{
    return new sigi\Attr('crossorigin', $v);
}
/** Subresource integrity hash. `integrity="..."` */
function INTEGRITY(string $v): sigi\Attr
{
    return new sigi\Attr('integrity', $v);
}

// ---------------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------------

/** Inline CSS. `style="..."` */
function STYLE(sigi\Css $v): sigi\Attr
{
    return new sigi\Attr('style', $v);
}
/** Advisory tooltip text. `title="..."` */
function TITLE(string $v): sigi\Attr
{
    return new sigi\Attr('title', $v);
}
/** Alternative text. `alt="..."` */
function ALT(string $v): sigi\Attr
{
    return new sigi\Attr('alt', $v);
}
/** ARIA role. `role="..."` */
function ROLE(string $v): sigi\Attr
{
    return new sigi\Attr('role', $v);
}
/** Language code. `lang="..."` */
function LANG(string $v): sigi\Attr
{
    return new sigi\Attr('lang', $v);
}
/** Text direction. `dir="..."` */
function DIR(string $v): sigi\Attr
{
    return new sigi\Attr('dir', $v);
}
/** Tab order index. `tabindex="..."` */
function TABINDEX(int $v): sigi\Attr
{
    return new sigi\Attr('tabindex', $v);
}
/** Element width. `width="..."` */
function WIDTH(string|int $v): sigi\Attr
{
    return new sigi\Attr('width', $v);
}
/** Element height. `height="..."` */
function HEIGHT(string|int $v): sigi\Attr
{
    return new sigi\Attr('height', $v);
}
/** Character encoding. `charset="..."` */
function CHARSET(string $v): sigi\Attr
{
    return new sigi\Attr('charset', $v);
}
/** Editable content. `contenteditable="..."` */
function CONTENTEDITABLE(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('contenteditable', $v);
}
/** Draggable element. `draggable="..."` */
function DRAGGABLE(string $v): sigi\Attr
{
    return new sigi\Attr('draggable', $v);
}
/** Spellcheck hint. `spellcheck="..."` */
function SPELLCHECK(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('spellcheck', $v);
}
/** Translation hint. `translate="..."` */
function TRANSLATE(string $v): sigi\Attr
{
    return new sigi\Attr('translate', $v);
}
/** Autocapitalization hint. `autocapitalize="..."` */
function AUTOCAPITALIZE(string $v): sigi\Attr
{
    return new sigi\Attr('autocapitalize', $v);
}
/** Keyboard shortcut key. `accesskey="..."` */
function ACCESSKEY(string $v): sigi\Attr
{
    return new sigi\Attr('accesskey', $v);
}
/** Virtual keyboard mode. `inputmode="..."` */
function INPUTMODE(string $v): sigi\Attr
{
    return new sigi\Attr('inputmode', $v);
}
/** Enter key label. `enterkeyhint="..."` */
function ENTERKEYHINT(string $v): sigi\Attr
{
    return new sigi\Attr('enterkeyhint', $v);
}
/** CSP nonce. `nonce="..."` */
function NONCE(string $v): sigi\Attr
{
    return new sigi\Attr('nonce', $v);
}

// ---------------------------------------------------------------------------
// Popover API (buttonless menus/dialogs, no JS)
// ---------------------------------------------------------------------------

/** Popover behavior. `popover="..."` */
function POPOVER(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('popover', $v);
}
/** Popover target id. `popovertarget="..."` */
function POPOVERTARGET(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('popovertarget', (string) $v);
}
/** Popover target action. `popovertargetaction="..."` */
function POPOVERTARGETACTION(string $v): sigi\Attr
{
    return new sigi\Attr('popovertargetaction', $v);
}

// ---------------------------------------------------------------------------
// Media / embedded
// ---------------------------------------------------------------------------

/** Video poster image. `poster="..."` */
function POSTER(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('poster', $v);
}
/** Loading strategy. `loading="..."` */
function LOADING(string $v): sigi\Attr
{
    return new sigi\Attr('loading', $v);
}
/** Image size hints. `sizes="..."` */
function SIZES(string $v): sigi\Attr
{
    return new sigi\Attr('sizes', $v);
}
/** Media preload hint. `preload="..."` */
function PRELOAD(string $v): sigi\Attr
{
    return new sigi\Attr('preload', $v);
}
/** Image decoding hint. `decoding="..."` */
function DECODING(string $v): sigi\Attr
{
    return new sigi\Attr('decoding', $v);
}
/** Fetch priority. `fetchpriority="..."` */
function FETCHPRIORITY(string $v): sigi\Attr
{
    return new sigi\Attr('fetchpriority', $v);
}
/** Media controls list. `controlslist="..."` */
function CONTROLSLIST(string $v): sigi\Attr
{
    return new sigi\Attr('controlslist', $v);
}

// ---------------------------------------------------------------------------
// Forms
// ---------------------------------------------------------------------------

/** Input/control type. `type="..."` */
function TYPE(string $v): sigi\Attr
{
    return new sigi\Attr('type', $v);
}
/** Field name. `name="..."` */
function NAME(string $v): sigi\Attr
{
    return new sigi\Attr('name', $v);
}
/** Field value. `value="..."` */
function VALUE(string|int|float $v): sigi\Attr
{
    return new sigi\Attr('value', $v);
}
/** Placeholder text. `placeholder="..."` */
function PLACEHOLDER(string $v): sigi\Attr
{
    return new sigi\Attr('placeholder', $v);
}
/** Label target id. `for="..."` */
function FOR_(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('for', (string) $v);
}
/** Form submit URL. `action="..."` */
function ACTION(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('action', $v);
}
/** Form submit method. `method="..."` */
function METHOD(string $v): sigi\Attr
{
    return new sigi\Attr('method', $v);
}
/** Form encoding type. `enctype="..."` */
function ENCTYPE(string $v): sigi\Attr
{
    return new sigi\Attr('enctype', $v);
}
/** Autocomplete hint. `autocomplete="..."` */
function AUTOCOMPLETE(string $v): sigi\Attr
{
    return new sigi\Attr('autocomplete', $v);
}
/** Accepted file types. `accept="..."` */
function ACCEPT(string $v): sigi\Attr
{
    return new sigi\Attr('accept', $v);
}
/** Validation pattern. `pattern="..."` */
function PATTERN(string $v): sigi\Attr
{
    return new sigi\Attr('pattern', $v);
}
/** Minimum value. `min="..."` */
function MIN(string|int|float $v): sigi\Attr
{
    return new sigi\Attr('min', $v);
}
/** Maximum value. `max="..."` */
function MAX(string|int|float $v): sigi\Attr
{
    return new sigi\Attr('max', $v);
}
/** Value step increment. `step="..."` */
function STEP(string|int|float $v): sigi\Attr
{
    return new sigi\Attr('step', $v);
}
/** Maximum length. `maxlength="..."` */
function MAXLENGTH(int $v): sigi\Attr
{
    return new sigi\Attr('maxlength', $v);
}
/** Minimum length. `minlength="..."` */
function MINLENGTH(int $v): sigi\Attr
{
    return new sigi\Attr('minlength', $v);
}
/** Visible size. `size="..."` */
function SIZE(int $v): sigi\Attr
{
    return new sigi\Attr('size', $v);
}
/** Textarea columns. `cols="..."` */
function COLS(int $v): sigi\Attr
{
    return new sigi\Attr('cols', $v);
}
/** Textarea rows. `rows="..."` */
function ROWS(int $v): sigi\Attr
{
    return new sigi\Attr('rows', $v);
}
/** Datalist id. `list="..."` */
function LIST_(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('list', (string) $v);
}
/** Owner form id. `form="..."` */
function FORM(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('form', (string) $v);
}
/** Submit override URL. `formaction="..."` */
function FORMACTION(string|sigi\Url $v): sigi\Attr
{
    return new sigi\Attr('formaction', $v);
}
/** Submit override method. `formmethod="..."` */
function FORMMETHOD(string $v): sigi\Attr
{
    return new sigi\Attr('formmethod', $v);
}
/** Submit override encoding. `formenctype="..."` */
function FORMENCTYPE(string $v): sigi\Attr
{
    return new sigi\Attr('formenctype', $v);
}
/** Submit override target. `formtarget="..."` */
function FORMTARGET(string $v): sigi\Attr
{
    return new sigi\Attr('formtarget', $v);
}
/** Media capture source. `capture="..."` */
function CAPTURE(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('capture', $v);
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

/** Column span. `colspan="..."` */
function COLSPAN(int $v): sigi\Attr
{
    return new sigi\Attr('colspan', $v);
}
/** Row span. `rowspan="..."` */
function ROWSPAN(int $v): sigi\Attr
{
    return new sigi\Attr('rowspan', $v);
}
/** Header cell scope. `scope="..."` */
function SCOPE(string $v): sigi\Attr
{
    return new sigi\Attr('scope', $v);
}
/** Associated header ids. `headers="..."` */
function HEADERS(string $v): sigi\Attr
{
    return new sigi\Attr('headers', $v);
}

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

/** List start number. `start="..."` */
function START(int $v): sigi\Attr
{
    return new sigi\Attr('start', $v);
}

// ---------------------------------------------------------------------------
// Head / metadata
// ---------------------------------------------------------------------------

/** Metadata content. `content="..."` */
function CONTENT(string|int $v): sigi\Attr
{
    return new sigi\Attr('content', $v);
}
/** Metadata property. `property="..."` */
function PROPERTY(string $v): sigi\Attr
{
    return new sigi\Attr('property', $v);
}
/** Pragma directive. `http-equiv="..."` */
function HTTP_EQUIV(string $v): sigi\Attr
{
    return new sigi\Attr('http-equiv', $v);
}

// ---------------------------------------------------------------------------
// Embedding (iframe / object). srcdoc holds a full HTML document, attribute-escaped.
// ---------------------------------------------------------------------------

/** Iframe sandbox rules. `sandbox="..."` */
function SANDBOX(string|bool $v = true): sigi\Attr
{
    return new sigi\Attr('sandbox', $v);
}
/** Iframe feature policy. `allow="..."` */
function ALLOW(string $v): sigi\Attr
{
    return new sigi\Attr('allow', $v);
}
/** Inline iframe document. `srcdoc="..."` */
function SRCDOC(string|\Stringable $v): sigi\Attr
{
    return new sigi\Attr('srcdoc', (string) $v);
}

// ---------------------------------------------------------------------------
// Microdata (companions to the boolean ITEMSCOPE)
// ---------------------------------------------------------------------------

/** Microdata item type. `itemtype="..."` */
function ITEMTYPE(string $v): sigi\Attr
{
    return new sigi\Attr('itemtype', $v);
}
/** Microdata property. `itemprop="..."` */
function ITEMPROP(string $v): sigi\Attr
{
    return new sigi\Attr('itemprop', $v);
}
/** Microdata item id. `itemid="..."` */
function ITEMID(string $v): sigi\Attr
{
    return new sigi\Attr('itemid', $v);
}
/** Microdata references. `itemref="..."` */
function ITEMREF(string $v): sigi\Attr
{
    return new sigi\Attr('itemref', $v);
}

// ---------------------------------------------------------------------------
// Web components
// ---------------------------------------------------------------------------

/** Slot assignment. `slot="..."` */
function SLOT(string $v): sigi\Attr
{
    return new sigi\Attr('slot', $v);
}
/** Shadow part names. `part="..."` */
function PART(string ...$tokens): sigi\Attr
{
    return new sigi\Attr('part', implode(' ', $tokens));
}
/** Custom element name. `is="..."` */
function IS(string $v): sigi\Attr
{
    return new sigi\Attr('is', $v);
}

// ---------------------------------------------------------------------------
// Boolean attributes (presence only)
// ---------------------------------------------------------------------------

/** Required field. `required` */
function REQUIRED(): sigi\Attr
{
    return new sigi\Attr('required');
}
/** Disabled control. `disabled` */
function DISABLED(): sigi\Attr
{
    return new sigi\Attr('disabled');
}
/** Checked state. `checked` */
function CHECKED(): sigi\Attr
{
    return new sigi\Attr('checked');
}
/** Selected option. `selected` */
function SELECTED(): sigi\Attr
{
    return new sigi\Attr('selected');
}
/** Read-only field. `readonly` */
function READONLY_(): sigi\Attr
{
    return new sigi\Attr('readonly');
}
/** Allows multiple values. `multiple` */
function MULTIPLE(): sigi\Attr
{
    return new sigi\Attr('multiple');
}
/** Autofocus on load. `autofocus` */
function AUTOFOCUS(): sigi\Attr
{
    return new sigi\Attr('autofocus');
}
/** Hidden element. `hidden` */
function HIDDEN(): sigi\Attr
{
    return new sigi\Attr('hidden');
}
/** Open state. `open` */
function OPEN(): sigi\Attr
{
    return new sigi\Attr('open');
}
/** Inert subtree. `inert` */
function INERT(): sigi\Attr
{
    return new sigi\Attr('inert');
}
/** Skip form validation. `novalidate` */
function NOVALIDATE(): sigi\Attr
{
    return new sigi\Attr('novalidate');
}
/** Skip validation on submit. `formnovalidate` */
function FORMNOVALIDATE(): sigi\Attr
{
    return new sigi\Attr('formnovalidate');
}
/** Default track. `default` */
function DEFAULT_(): sigi\Attr
{
    return new sigi\Attr('default');
}
/** Async script. `async` */
function ASYNC(): sigi\Attr
{
    return new sigi\Attr('async');
}
/** Deferred script. `defer` */
function DEFER(): sigi\Attr
{
    return new sigi\Attr('defer');
}
/** Loop playback. `loop` */
function LOOP(): sigi\Attr
{
    return new sigi\Attr('loop');
}
/** Muted audio. `muted` */
function MUTED(): sigi\Attr
{
    return new sigi\Attr('muted');
}
/** Show media controls. `controls` */
function CONTROLS(): sigi\Attr
{
    return new sigi\Attr('controls');
}
/** Autoplay media. `autoplay` */
function AUTOPLAY(): sigi\Attr
{
    return new sigi\Attr('autoplay');
}
/** Reversed list. `reversed` */
function REVERSED(): sigi\Attr
{
    return new sigi\Attr('reversed');
}
/** Inline playback. `playsinline` */
function PLAYSINLINE(): sigi\Attr
{
    return new sigi\Attr('playsinline');
}
/** Allow fullscreen. `allowfullscreen` */
function ALLOWFULLSCREEN(): sigi\Attr
{
    return new sigi\Attr('allowfullscreen');
}
/** Non-module fallback. `nomodule` */
function NOMODULE(): sigi\Attr
{
    return new sigi\Attr('nomodule');
}
/** Microdata item scope. `itemscope` */
function ITEMSCOPE(): sigi\Attr
{
    return new sigi\Attr('itemscope');
}
