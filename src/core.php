<?php

declare(strict_types=1);

namespace sigi;

/**
 * @internal
 */
class Consts
{
    /** @var array<string,true> attributes whose value is a URL */
    const URL_ATTRS = [
        'href' => true,
        'src' => true,
        'srcset' => true,
        'action' => true,
        'formaction' => true,
        'poster' => true,
        'cite' => true,
        'background' => true,
        'ping' => true,
        'manifest' => true,
        'data' => true,
        'xlink:href' => true,
    ];


    /** @var array<string,true> void elements: no children, no close tag */
    const VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    /** @var array<string,true> raw-text elements: body is not HTML-escaped */
    const RAWTEXT = [
        'script' => true,
        'style' => true
    ];
}

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** A rendered, already-safe HTML fragment (0..n sibling nodes). */
final readonly class Html implements \Stringable
{
    public function __construct(
        public string $html
    ) {}

    public function __toString(): string
    {
        return $this->html;
    }
}

/** A URL you vouch for: skips scheme sanitisation in a URL slot. */
final readonly class Url
{
    /** @internal mint through unsafe\url() or a url\ builder — direct construction bypasses the greppable audit surface */
    public function __construct(public string $value) {}
}

/** Trusted CSS for a style attribute. */
final readonly class Css
{
    /** @internal mint through css\props() or unsafe\css() — direct construction bypasses the greppable audit surface */
    public function __construct(public string $value) {}
}

/** Trusted JS for an event/handler slot. */
final readonly class Js
{
    /** @internal mint through js\data() or unsafe\js() — direct construction bypasses the greppable audit surface */
    public function __construct(public string $value) {}
}

/** A configured attribute. value true = boolean present; false/null = omitted. */
final readonly class Attr
{
    public function __construct(
        public string $name,
        public string|int|float|bool|Url|Css|Js|null $value = true,
    ) {
        // Deny only the characters that carry structural meaning in the HTML
        // tag tokenizer (whitespace/quotes/> / =) plus <, backtick and control
        // bytes, which no attribute name needs. Everything else stays legal so
        // hypermedia names survive: data-on:click, @click, :class, [x], (y).
        // strpbrk over a precomputed byte set is ~2x a regex on this hot path.
        static $illegal;
        $illegal ??= implode('', array_map('chr', range(0, 0x20))) . "\x7f<>\"'/=`";
        if ($name === '' || strpbrk($name, $illegal) !== false) {
            throw new \InvalidArgumentException("illegal attribute name: '{$name}'");
        }
    }
}

/**
 * The state of one render pass: component identity, and the stylesheet
 * unsafe\styled() collects. It exists only between sigi\pass() opening it and
 * that call returning — Env::$pass is null the rest of the time — so every
 * access goes through sigi\pass_state(), which throws when no pass is open.
 * @internal
 */
final class Pass
{
    /** Identity state for this render pass. */
    public IdState $ids;

    /**
     * CSS rules collected by unsafe\styled() this pass, keyed by their class
     * name. The key is a hash of the rule body, so the same rule from a hundred
     * component instances is one entry. Fresh each pass: each response carries
     * the rules that response actually used.
     */
    public array $sheet = [];

    public function __construct()
    {
        $this->ids = new IdState();
    }
}

// ---------------------------------------------------------------------------
// Environment — every piece of process-global state, declared in one place
// ---------------------------------------------------------------------------

/**
 * The environment: the one mutable root sigi has. Everything sigi keeps
 * between renders is a declared property here — nothing hides in function
 * statics — and nothing outside sigi writes a property directly: settings are
 * readonly, fixed when the Env is constructed, and the active environment
 * changes only through install(). Installing a fresh instance is a pristine
 * world (test isolation, or two configurations in one process):
 *
 *     sigi\Env::install(new sigi\Env(resolver: new sigi\DirResolver('views')));   // boot
 *     $prev = sigi\Env::install(new sigi\Env());   // pristine world; restore $prev later
 */
final class Env
{
    private static Env $current;

    /** The active environment. */
    public static function current(): Env
    {
        return self::$current;
    }

    /**
     * Make $env the active environment and return the previous one, so a
     * scoped caller (a test, an embedded second configuration) can restore it.
     */
    public static function install(Env $env): Env
    {
        $prev = isset(self::$current) ? self::$current : $env;
        self::$current = $env;
        return $prev;
    }

    /**
     * Default resolver for sigi\render(): turns a view name into its file path.
     * Leave null and pass a resolver explicitly, or construct with one (e.g. a
     * DirResolver over your views directory) so render($name) works bare.
     */
    public readonly ?ViewResolver $resolver;

    /**
     * Called when a URL slot rejects a dangerous scheme; receives the offending
     * URL. The value is flattened to '#' either way — the sink neutralizes, it
     * never throws, so stored bad data cannot take a page down. Wire it to
     * logging/alerting in production so a stored-XSS probe is seen rather than
     * silent; point it at a throw in development for a loud failure at the cause.
     */
    public readonly ?\Closure $on_rejected_url;

    /**
     * The render pass in flight, or null when none is open. A pass is opened
     * only by sigi\pass() and torn down when that call returns; every access to
     * ambient render state goes through sigi\pass_state(), which throws when
     * this is null. At most one pass exists process-wide at a time. @internal
     */
    public ?Pass $pass = null;

    /** @var array<string, string> load(): file path -> resolved entry function name. @internal */
    public array $entries = [];

    public function __construct(
        ?ViewResolver $resolver = null,
        ?\Closure $on_rejected_url = null,
    ) {
        $this->resolver = $resolver;
        $this->on_rejected_url = $on_rejected_url;
    }
}

Env::install(new Env());

// ---------------------------------------------------------------------------
// Escaping — one encoder per slot
// ---------------------------------------------------------------------------

/** @internal */
function esc_text(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** @internal */
function esc_attr(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Reject dangerous schemes, then attribute-escape. unsafe\url() bypasses this.
 *
 * @internal
 */
function esc_url(string $u): string
{
    $probe = strtolower(preg_replace('/[\s\x00-\x20]+/', '', $u) ?? $u);
    foreach (['javascript:', 'vbscript:', 'data:'] as $bad) {
        if (str_starts_with($probe, $bad)) {
            if ($report = Env::current()->on_rejected_url) {
                $report($u);
            }
            return '#';
        }
    }
    return esc_attr($u);
}

// ---------------------------------------------------------------------------
// Slots + attribute rendering
// ---------------------------------------------------------------------------

/** @internal */
function slot_of(string $name): string
{
    $l = strtolower($name);
    if (isset(Consts::URL_ATTRS[$l])) {
        return 'url';
    }
    if ($l === 'style') {
        return 'style';
    }
    if (str_starts_with($l, 'data-on:') || str_starts_with($l, 'data-on-')) {
        return 'event';
    }
    if (str_starts_with($l, 'on')) {
        return 'event';
    }
    return 'ordinary';
}

/** @internal */
function scalar(string|int|float|bool|Url|Css|Js $value): string
{
    if ($value instanceof Url || $value instanceof Css || $value instanceof Js) {
        return $value->value;
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return (string) $value;
}

/**
 * Render one attribute to ` name="value"`, ` name`, or `` (dropped).
 *
 * @internal
 */
function render_attr(string $name, string|int|float|bool|Url|Css|Js|null $value): string
{
    if ($value === false || $value === null) {
        return '';
    }
    if ($value === true) {
        return ' ' . $name;
    }

    $v = match (slot_of($name)) {
        'url'   => $value instanceof Url ? esc_attr($value->value) : esc_url(scalar($value)),
        'style' => $value instanceof Css
            ? esc_attr($value->value)
            : throw new \InvalidArgumentException("style attribute requires unsafe\\css() or css\\props(); a bare string is refused"),
        'event' => $value instanceof Js
            ? esc_attr($value->value)
            : throw new \InvalidArgumentException("event attribute '{$name}' requires unsafe\\js(); a bare string is refused"),
        default => esc_attr(scalar($value)),
    };

    return ' ' . $name . '="' . $v . '"';
}

// ---------------------------------------------------------------------------
// Element assembly
// ---------------------------------------------------------------------------

/**
 * Attributes that accumulate across repeats instead of overwriting.
 *
 * @internal
 */
function put(
    array  &$names,
    array  &$values,
    string $name,
    string|int|float|bool|Url|Css|Js|null $value,
): void {
    if (!array_key_exists($name, $values)) {
        $names[] = $name;
        $values[$name] = $value;
        return;
    }
    if ($name === 'class') {
        $values[$name] = merge_tokens((string) $values[$name], is_scalar($value) ? (string) $value : scalar($value));
        return;
    }
    if ($name === 'style') {
        $values[$name] = merge_style($values[$name], $value);
        return;
    }
    $values[$name] = $value; // last wins
}

/** @internal */
function merge_tokens(string $a, string $b): string
{
    $seen = [];
    foreach ([...preg_split('/\s+/', trim($a)) ?: [], ...preg_split('/\s+/', trim($b)) ?: []] as $t) {
        if ($t !== '') {
            $seen[$t] = true;
        }
    }
    return implode(' ', array_keys($seen));
}

/**
 * Merge two style values into one Css. Both sides are already vouched (only a
 * Css reaches the style slot), so the merged declaration list stays vouched.
 *
 * @internal
 */
function merge_style(
    string|int|float|bool|Url|Css|Js|null $a,
    string|int|float|bool|Url|Css|Js|null $b,
): Css {
    $sa = rtrim(trim($a === null ? '' : scalar($a)), ';');
    $sb = rtrim(trim($b === null ? '' : scalar($b)), ';');
    if ($sa === '') {
        return new Css($sb);
    }
    if ($sb === '') {
        return new Css($sa);
    }
    return new Css($sa . '; ' . $sb);
}

/**
 * Prevent a raw-text body from prematurely closing its element.
 *
 * @internal
 */
function guard_rawtext(string $tag, string $s): string
{
    return str_ireplace('</' . $tag, '<\\/' . $tag, $s);
}

/**
 * Walk the variadic arg list, sorting Attr into attributes and everything
 * else into escaped (or raw, for script/style) children.
 *
 * @internal
 */
function collect(
    iterable $args,
    array    &$names,
    array    &$values,
    string   &$body,
    string   $tag,
    bool     $raw,
): void {
    foreach ($args as $arg) {
        if ($arg === null || $arg === false || $arg === true) {
            continue;
        }
        if ($arg instanceof Attr) {
            put($names, $values, $arg->name, $arg->value);
            continue;
        }
        if ($arg instanceof Html) {
            $body .= $arg->html;
            continue;
        }
        if (is_array($arg) || $arg instanceof \Traversable) {
            collect($arg, $names, $values, $body, $tag, $raw);
            continue;
        }

        // A raw-text body (<script>/<style>) is a JS/CSS sink, not HTML. It
        // takes only its matching voucher, so the trust decision stays a grep:
        // unsafe\js()/js\data() for script, unsafe\css()/css\props() for style.
        if ($raw) {
            $lower = strtolower($tag);
            $ok = $lower === 'script' ? $arg instanceof Js : $arg instanceof Css;
            if (!$ok) {
                $want = $lower === 'script' ? 'unsafe\\js() or js\\data()' : 'unsafe\\css() or css\\props()';
                throw new \InvalidArgumentException("<{$lower}> body requires {$want}; a bare value is refused");
            }
            $body .= guard_rawtext($tag, $arg->value);
            continue;
        }

        if ($arg instanceof Url || $arg instanceof Css || $arg instanceof Js) {
            $s = $arg->value;
        } elseif (is_scalar($arg) || $arg instanceof \Stringable) {
            $s = (string) $arg;
        } else {
            throw new \InvalidArgumentException('unsupported child of type ' . get_debug_type($arg));
        }

        $body .= esc_text($s);
    }
}

/**
 * A selector string like ".card.big#main": starts with . or #, and holds only
 * class/id characters, no spaces. This shape is only ever honoured through the
 * explicit at\sel() call — a bare string in an element is always ordinary
 * content, whatever its shape, so user input can never inject class/id.
 * @internal
 */
function is_selector(string $s): bool
{
    return ($s[0] ?? '') !== '' && ($s[0] === '.' || $s[0] === '#')
        && preg_match('/^[.#][A-Za-z0-9_.#-]*$/', $s) === 1;
}

/** Parse ".a.b#id" into [classes, id]; a repeated id keeps the last. @internal */
function parse_selector(string $s): array
{
    $classes = [];
    $id = null;
    foreach (preg_split('/(?=[.#])/', $s, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
        if ($tok[0] === '#') {
            $id = substr($tok, 1);
        } elseif (($c = substr($tok, 1)) !== '') {
            $classes[] = $c;
        }
    }
    return [$classes, $id];
}

/**
 * Build any element. The curated el\ functions pass a constant tag and delegate
 * straight here; the untrusted path (el\tag()) validates the name first, so no
 * runtime tag check is needed on this hot path.
 *
 * Every string argument is content, whatever its shape — class/id shorthand is
 * the explicit at\sel() call, so nothing here depends on inspecting the caller's
 * source and user input can never inject class or id.
 *
 * @internal
 */
function element(string $tag, mixed ...$args): Html
{
    $lower = strtolower($tag);
    $names = [];
    $values = [];
    $body = '';
    collect($args, $names, $values, $body, $tag, isset(Consts::RAWTEXT[$lower]));

    $attrs = '';
    foreach ($names as $n) {
        $attrs .= render_attr($n, $values[$n]);
    }

    if (isset(Consts::VOID_ELEMENTS[$lower])) {
        return new Html("<{$tag}{$attrs}>");
    }
    return new Html("<{$tag}{$attrs}>{$body}</{$tag}>");
}

// ---------------------------------------------------------------------------
// Component identity: sigi\id() / sigi\with_id() / sigi\pass()
//
// id() is a READ of the current component's identity, not a mint: every call
// inside one activation of a component answers the same id, and the id is the
// component's function name — never a line number, never a hash. Activations
// are told apart by how they were reached (the call sites above the component),
// so two sibling calls to the same widget get 'widget' and 'widget-2' without
// anyone writing a key. A loop rendering the same component through the same
// call site has nothing to tell iterations apart, so id() throws and names the
// fix: with_id($key, fn() => ...) around each iteration.
//
// A render is one synchronous pass: no blocking IO inside views — load your
// data first, then render over it. Identity lives for one pass and is opened by
// sigi\pass() — render() wraps the view in it; a host driving renders directly
// brackets each one with pass(fn() => ...). Read it outside a pass and it throws.
// ---------------------------------------------------------------------------

/** Mutable identity state for one render pass. @internal */
final class IdState
{
    /** @var list<string> explicit scope keys, innermost last */
    public array $scopes = [];
    /** @var array<string,int> occurrence per (scope path, component) */
    public array $occ = [];
    /** @var array<string,string> activation key -> assigned id */
    public array $memo = [];
    /** @var array<string,true> activation key "\0" read site pairs seen */
    public array $seen = [];
    /** The current component id: the most recent read. */
    public ?string $last = null;
}

/**
 * The render pass in flight, or a thrown error when none is open. This is the
 * single funnel every access to ambient render state passes through — identity
 * here, the stylesheet in unsafe\styled()/sigi\styles() — so touching any of
 * them outside a sigi\pass() is a loud, immediate error rather than silent
 * corruption of a neighbouring pass's state. @internal
 */
function pass_state(): Pass
{
    return Env::current()->pass ?? throw new \LogicException(
        'sigi: no render pass is open — component identity and the unsafe\\styled() '
            . 'sheet are pass state and may only be touched inside sigi\\pass(fn() => ...). '
            . 'Wrap your top-level render in it; sigi\\render() already does.',
    );
}

/** The current pass's identity state. @internal */
function id_state(): IdState
{
    return pass_state()->ids;
}

/**
 * Run $fn as one render pass and return its result: component identity and the
 * unsafe\styled() sheet start fresh, and are torn down when $fn returns or
 * throws. This is the one place a render pass is opened — sigi\id(),
 * sigi\styles() and unsafe\styled() are pass state and throw outside it.
 * render() wraps the view in pass() for you; a host calling view functions
 * directly brackets each render:
 *
 *     echo sigi\pass(fn() => views\app('Home', $slot));
 *
 * Passes are mutually exclusive: at most one is in flight process-wide, so a
 * view that reenters pass() (or render()) is a loud error, not a corrupted
 * sheet. A render is pure CPU — load your data first, then render over it.
 *
 * The scope closes even when $fn throws, so a pass can never be left open and
 * an aborted render cannot leak state into whatever renders next.
 *
 * @template T
 * @param  callable(): T $fn
 * @return T
 */
function pass(callable $fn): mixed
{
    $env = Env::current();
    if ($env->pass !== null) {
        throw new \LogicException(
            'sigi: a render pass is already in flight — passes are mutually exclusive, '
                . 'so a view must not reenter sigi\\pass() or sigi\\render(). Load your '
                . 'data before rendering, then render over it in one pass.',
        );
    }
    $env->pass = new Pass();
    try {
        return $fn();
    } finally {
        $env->pass = null;
    }
}

/**
 * The CSS collected by unsafe\styled() this pass, as one <style> element —
 * every rule any component used, each emitted once. Empty when nothing styled.
 *
 * Children render before their parent (they are its arguments), so building the
 * body into a variable and then wrapping it means the sheet is complete by the
 * time the layout asks for it:
 *
 *     $body = el\MAIN(...);                    // components register their rules
 *     return el\HTML(el\HEAD(sigi\styles()), el\BODY($body));
 *
 * Rendering a fragment (htmx, Turbo)? Put it in the fragment: a repeated
 * identical rule is idempotent, so re-sending one costs nothing but bytes.
 */
function styles(): Html
{
    $sheet = pass_state()->sheet;
    if ($sheet === []) {
        return new Html('');
    }
    return new Html('<style>' . guard_rawtext('style', implode('', $sheet)) . '</style>');
}

/**
 * The current component's identity: its function name, plus '-2', '-3', ... when
 * the same component is reached again through a different call site this pass.
 * Idempotent — every id() read inside one activation returns the same string —
 * so pass it to a state store and stamp it on the element and they agree:
 *
 *     function counter(): Html
 *     {
 *         $id = sigi\id();
 *         return el\DIV(at\ID($id), ...);
 *     }
 *
 * Throws when a loop re-renders a component with nothing to tell iterations
 * apart; with_id($key, fn() => ...) around each iteration.
 */
function id(): string
{
    // Walk to the nearest NAMED enclosing function: that is the component this
    // read belongs to. Closures (and sigi's own frames) are skipped — a closure
    // has no stable name, and in PHP 8.4 its synthesized name embeds file:line.
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $leaf = 'root';
    $depth = count($bt);
    $i = 1;
    for (; $i < $depth; $i++) {
        $fn = $bt[$i]['function'];
        if (isset($bt[$i]['class'])) {
            $leaf = $bt[$i]['class'] . '-' . $fn;   // a method component keeps its class
            break;
        }
        // Skip anonymous frames, sigi's own, and internal callback-invokers
        // (array_map and friends) — none of them is the component.
        if (str_contains($fn, '{closure') || str_starts_with($fn, 'sigi\\') || is_internal_fn($fn)) {
            continue;
        }
        $leaf = $fn;
        break;
    }
    // The read site (where this id() call sits) detects loop iterations; the
    // caller chain above the component identifies the activation. Both are
    // internal — no line number ever reaches the id text.
    $read = ($bt[0]['file'] ?? '?') . ':' . ($bt[0]['line'] ?? 0);
    $chain = '';
    for ($j = $i; $j < $depth; $j++) {
        $chain .= ($bt[$j]['file'] ?? '?') . ':' . ($bt[$j]['line'] ?? 0) . '>';
    }
    return resolve_id($leaf, $chain, $read);
}

/**
 * Render $fn under an identity scope: ids read inside it become
 * "<key>--<component...>", and activations under different keys are distinct.
 * This is how a loop gives each iteration a stable identity — and how two
 * activations that share a source line tell themselves apart, since positional
 * identity cannot see two calls on one line:
 *
 *     foreach ($users as $u) {
 *         $cards[] = sigi\with_id($u->id, fn() => card($u));
 *     }
 *
 *     el\DIV(                                        // one line, two counters:
 *         sigi\with_id('cart', counter(...)),        // the keys say which is
 *         sigi\with_id('wishlist', counter(...)),    // which, so a reorder
 *     );                                             // cannot reassign them
 *
 * The scope closes when $fn returns or throws, so it can never be left open
 * and there is no unbalanced-scope error to make.
 *
 * @template T
 * @param  callable(): T $fn
 * @return T
 */
function with_id(string|int $key, callable $fn): mixed
{
    $s = id_state();
    $s->scopes[] = id_token((string) $key);
    try {
        return $fn();
    } finally {
        array_pop($s->scopes);
    }
}

/**
 * The identity of the innermost ENCLOSING component that has read its id this
 * pass — how an external helper (a state store, an event binder) uses its
 * caller's id instead of minting one of its own:
 *
 *     function state(mixed $default): mixed
 *     {
 *         $key = sigi\current_id();   // the component that called us
 *         ...
 *     }
 *
 * "Enclosing" is the call stack, not render order: a helper called after a
 * child component has rendered still answers the caller, never the child.
 * A read, never a mint: it does not create ids, count occurrences, or trip
 * loop detection. Throws when no enclosing component has read its identity
 * this pass.
 */
function current_id(): string
{
    $s = id_state();
    if ($s->last === null) {
        throw new \LogicException(
            'sigi: no component identity has been read this pass — call sigi\\id() in the component first.',
        );
    }

    // Walk the stack the way id() does, but probe the activation memo at every
    // named frame instead of stopping at the first — the innermost frame that
    // has read its identity is the component this helper belongs to.
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $depth = count($bt);
    // Frame chains share suffixes, so build them once from the top down.
    $chains = array_fill(0, $depth + 1, '');
    for ($i = $depth - 1; $i >= 1; $i--) {
        $chains[$i] = ($bt[$i]['file'] ?? '?') . ':' . ($bt[$i]['line'] ?? 0) . '>' . $chains[$i + 1];
    }
    for ($i = 1; $i < $depth; $i++) {
        $fn = $bt[$i]['function'];
        if (str_contains($fn, '{closure')) {
            continue;
        }
        if (isset($bt[$i]['class'])) {
            $leaf = $bt[$i]['class'] . '-' . $fn;
        } elseif (str_starts_with($fn, 'sigi\\') || is_internal_fn($fn)) {
            continue;
        } else {
            $leaf = $fn;
        }
        $token = id_token($leaf);
        // Scope keys pushed after the component's own read are innermost by
        // construction: strip innermost-first, longest surviving prefix wins.
        for ($k = count($s->scopes); $k >= 0; $k--) {
            $prefix = $k === 0 ? '' : implode('--', array_slice($s->scopes, 0, $k)) . '--';
            $found = $s->memo[$prefix . $token . '|' . $chains[$i]] ?? null;
            if ($found !== null) {
                return $found;
            }
        }
    }
    throw new \LogicException(
        'sigi: current_id() with no enclosing component — no function on the call stack '
            . 'has read sigi\\id() this pass. Read sigi\\id() in the component before calling helpers.',
    );
}

/** Fold a name into an id-safe token: [A-Za-z0-9_-] only. @internal */
function id_token(string $name): string
{
    return trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name) ?? $name, '_');
}

/** True for a PHP-internal function (array_map, ...), memoized per name. @internal */
function is_internal_fn(string $fn): bool
{
    static $memo = [];
    if (isset($memo[$fn])) {
        return $memo[$fn];
    }
    try {
        return $memo[$fn] = (new \ReflectionFunction($fn))->isInternal();
    } catch (\ReflectionException) {
        return $memo[$fn] = false;
    }
}

/**
 * Assign (or re-read) the id for one activation. id() derives leaf/chain/read
 * from the backtrace and hands them here. @internal
 */
function resolve_id(string $leaf, string $chain, string $read): string
{
    $s = id_state();
    // Separator is '--' for scopes and '-' for the occurrence suffix: the same
    // shape current_id() rebuilds when it probes this memo, so a write here and
    // a read there always agree.
    $prefix = $s->scopes === [] ? '' : implode('--', $s->scopes) . '--';
    $token = $prefix . id_token($leaf);
    $activation = $token . '|' . $chain;

    $pair = $activation . "\0" . $read;
    if (isset($s->memo[$activation])) {
        if (isset($s->seen[$pair])) {
            // Same complaint, two causes: with no scope open the loop simply has
            // no key; with a scope open the user DID give keys and they collided.
            if ($s->scopes !== []) {
                $path = implode('--', $s->scopes);
                throw new \LogicException(
                    "sigi: id() read '{$s->memo[$activation]}' twice under the identical scope "
                        . "path '{$path}' — two activations got the same key (two entities, one "
                        . 'identity: fix the key), or an inner loop is missing its own '
                        . 'per-iteration sigi\\with_id($key, fn() => ...).',
                );
            }
            throw new \LogicException(
                "sigi: id() read '{$s->memo[$activation]}' twice through the identical call "
                    . 'path — a loop is re-rendering this component with nothing to tell '
                    . 'iterations apart. Give each activation a stable key: '
                    . 'sigi\\with_id($key, fn() => ...). (Two calls on one source line look '
                    . 'identical to a loop — key them too. Rendering a fresh page? Wrap '
                    . 'it in sigi\\pass(fn() => ...); render() does that for you.)',
            );
        }
        $s->seen[$pair] = true;
        return $s->last = $s->memo[$activation];
    }

    $n = $s->occ[$token] = ($s->occ[$token] ?? 0) + 1;
    $id = $n === 1 ? $token : $token . '-' . $n;
    $s->memo[$activation] = $id;
    $s->seen[$pair] = true;
    return $s->last = $id;
}
