<?php

declare(strict_types=1);

namespace sigi\css;

use sigi;

/**
 * A validated CSS builder. Unlike unsafe\css(), which vouches for a raw string,
 * props() constructs a declaration list by construction and can never inject:
 * property names are allow-listed, and any value carrying a declaration-, rule-,
 * or element-breaking character (`; { } < >`) is refused. The result is a Css.
 *
 * props() builds an inline declaration list for the style attribute. It cannot
 * express selectors or braces (they are refused), so a full <style> stylesheet
 * still goes through unsafe\css().
 *
 * This is not a full CSS semantic sanitiser — url()/calc() still pass, and a
 * malicious-but-valid value (e.g. an exfiltrating background) is your call. It
 * closes injection, not intent. For anything it refuses, reach for unsafe\css().
 *
 *   at\STYLE(css\props(['color' => $c, 'width' => '50%']));
 */
function props(array $decls): sigi\Css
{
    $out = [];
    foreach ($decls as $prop => $value) {
        $prop = (string) $prop;
        if (!preg_match('/^-{0,2}[a-zA-Z][a-zA-Z0-9-]*$/', $prop)) {
            throw new \InvalidArgumentException("illegal css property name: '{$prop}'");
        }
        if ($value === null || $value === false) {
            continue;
        }
        $v = trim((string) $value);
        if ($v === '') {
            continue;
        }
        if (preg_match('/[;{}<>]/', $v)) {
            throw new \InvalidArgumentException("illegal css value for '{$prop}': contains one of ; { } < >");
        }
        $out[] = $prop . ':' . $v;
    }
    return new sigi\Css(implode(';', $out));
}
