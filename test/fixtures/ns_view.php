<?php

declare(strict_types=1);

/*
 * A view in a real namespace: its identity folds the namespace separators,
 * so sigi\testfix\card is 'sigi_testfix_card'.
 */

namespace sigi\testfix;

use sigi\{el, at};

function card(string $name): \sigi\Html
{
    return el\DIV(at\sel('.card'), el\H2($name));
}
