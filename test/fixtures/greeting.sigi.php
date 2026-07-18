<?php

declare(strict_types=1);

/*
 * A view file whose entry function matches its basename (greeting.sigi.php ->
 * greeting), the convention sigi\load() resolves. Namespaced on purpose, so the
 * load() tests also exercise a namespaced view.
 */

namespace sigi\testfix;

use sigi\{el, at};

function greeting(string $who): \sigi\Html
{
    return el\DIV(at\sel('.hi'), el\H1($who));
}
