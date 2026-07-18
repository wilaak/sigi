<?php

declare(strict_types=1);

namespace sigi\js;

use sigi;

/**
 * A validated JS builder. Unlike unsafe\js(), which vouches for a raw expression,
 * data() serialises a PHP value to JSON that is safe to embed in a <script> or
 * an event slot by construction: `<`, `>` and `&` are hex-escaped so a string
 * value can never carry `</script>` or start a markup context, and json_encode
 * guarantees the rest is well-formed. The result is a Js.
 *
 *   el\SCRIPT(at\TYPE('application/json'), at\ID('state'), js\data($model));
 *   el\BUTTON(at\DATA('on:click', unsafe\js('count = ' . js\data($start)->value)), '+');
 */
function data(mixed $value, int $flags = 0): sigi\Js
{
    $json = json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | $flags,
    );
    return new sigi\Js($json);
}
