<?php

declare(strict_types=1);

namespace sigi\unsafe;

use sigi;

//
// The escape hatches — the complete, greppable list of ways to bypass escaping.
// A security review is `grep -rn 'unsafe\\'` and nothing else. Each names the
// exact context it vouches for, so the bypass is never silent.
//

/** Trusted HTML, inserted verbatim. */
function html(string $html): sigi\Html
{
    return new sigi\Html($html);
}

/** A URL you vouch for: skips scheme rejection in a URL slot. */
function url(string $value): sigi\Url
{
    return new sigi\Url($value);
}

/** Trusted CSS for a style attribute. */
function css(string $value): sigi\Css
{
    return new sigi\Css($value);
}

/** Trusted JS for an event/handler slot. */
function js(string $value): sigi\Js
{
    return new sigi\Js($value);
}

/**
 * Trusted CSS rules for a component, collected into this pass's stylesheet.
 * Returns the class name to put them on — a hash of the rules themselves, so
 * you never name a class and never invent a naming convention:
 *
 *     function card(string $name): sigi\Html
 *     {
 *         $cls = unsafe\styled('
 *             display: flex; gap: 1rem;
 *             &:hover { background: #fafafa; }   // native CSS nesting, no build step
 *         ');
 *         return el\DIV(at\CLS($cls), $name);
 *     }
 *
 * The hash is the dedup key: a hundred instances of a component contribute one
 * rule. Two components that happen to write byte-identical rules share a class,
 * which is correct — the class *is* the rules. Emit the result with
 * sigi\styles(); vary a single instance with a custom property
 * (at\STYLE(css\props(['--w' => "{$pct}%"])) against width:var(--w)) rather
 * than by styling its id, so the rule stays shared.
 *
 * Unsafe, and here for that reason: the rules are written into a <style> with
 * no sanitisation, exactly like unsafe\css(). A literal in your view is source
 * code and is fine; unsafe\styled("width:{$fromDb}") is CSS injection. Keep the
 * argument a literal and the grep stays honest.
 */
function styled(string $rules): string
{
    $class = 'c' . substr(hash('xxh128', $rules), 0, 8);
    sigi\pass_state()->sheet[$class] ??= '.' . $class . '{' . $rules . '}';
    return $class;
}
