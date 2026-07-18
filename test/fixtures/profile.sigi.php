<?php

declare(strict_types=1);

namespace sigi\testfix;

use sigi\{el, at};

function profile(string $name, string $role): \sigi\Html
{
    return el\DIV(
        at\sel('.profile'),
        el\SPAN(at\sel('.name'), $name),
        el\SPAN(at\sel('.role'), $role),
    );
}
