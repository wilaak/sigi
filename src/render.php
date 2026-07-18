<?php

declare(strict_types=1);

namespace sigi;

/*
 * View loading front door, framework-agnostic. A host names a view; a
 * ViewResolver turns that name into a *.sigi.php file; load() turns the file
 * into its typed entry closure; render() calls it with the matching data.
 *
 * The seam is name -> path (many shapes: a directory, a framework's finder, a
 * database), so that is the interface. path -> entry has one sensible form and
 * stays the free function load().
 */

/** Resolves a view name to the absolute path of its *.sigi.php file. */
interface ViewResolver
{
    /** Absolute path to the view's file, or null if it has none. */
    public function pathFor(string $name): ?string;
}

/**
 * The default resolver: dotted (or namespaced) names under one or more roots,
 * `admin.users` -> `<root>/admin/users.sigi.php`. First matching root wins.
 */
readonly class DirResolver implements ViewResolver
{
    /** @var list<string> */
    public array $roots;

    public function __construct(string|array $roots, public string $ext = '.sigi.php')
    {
        $this->roots = array_values((array) $roots);
    }

    public function pathFor(string $name): ?string
    {
        $rel = str_replace(['.', '\\'], DIRECTORY_SEPARATOR, $name) . $this->ext;
        foreach ($this->roots as $root) {
            $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $rel;
            if (is_file($path)) {
                return realpath($path);
            }
        }
        return null;
    }
}

/**
 * Render a named view to HTML. The resolver defaults to Env::current()->resolver.
 * Data is passed as named arguments, filtered to the view's declared params, so
 * a host may hand over more than the view needs (framework globals, shared data)
 * without a TypeError — and the view keeps its real typed signature.
 */
function render(string $name, array $data = [], ?ViewResolver $resolver = null): string
{
    $resolver ??= Env::current()->resolver
        ?? throw new \RuntimeException('sigi: no view resolver; pass one or set Env::current()->resolver');
    $file = $resolver->pathFor($name)
        ?? throw new \RuntimeException("sigi: no view '{$name}'");

    // A render is one pass: pass() opens identity + the styled() sheet, runs the
    // view, and tears the pass down on the way out.
    return pass(fn() => invoke(load($file), $data));
}

/**
 * Resolve a `*.sigi.php` file to its entry view by convention: `app.sigi.php`
 * holds an `app(...)` function (in whatever namespace it declares). Requires the
 * file once, then finds the function whose short name matches the basename and
 * returns its closure. The typed signature you wrote on that function is what
 * your IDE checks.
 *
 * This is the file-keyed entry for framework-style hosts that render by path.
 * A persistent app that already autoloads its views can skip it and call the
 * view function directly.
 */
function load(string $file): \Closure
{
    $entry = &Env::current()->entries;
    if (!isset($entry[$file])) {
        require_once $file;
        $want = strtolower(basename($file, '.sigi.php'));
        $real = realpath($file);
        foreach (get_defined_functions()['user'] as $fn) {
            if (strtolower(substr((string) strrchr('\\' . $fn, '\\'), 1)) !== $want) {
                continue;
            }
            try {
                if ((new \ReflectionFunction($fn))->getFileName() === $real) {
                    $entry[$file] = $fn;
                    break;
                }
            } catch (\ReflectionException) {
            }
        }
        if (!isset($entry[$file])) {
            throw new \RuntimeException("sigi: {$file} defines no entry function '{$want}()'");
        }
    }
    return \Closure::fromCallable($entry[$file]);
}

/**
 * Call a view entry, passing $data as named arguments filtered to the entry's
 * declared parameters. The seam an adapter uses once it already holds the file:
 * a framework engine calls invoke(load($path), $data) directly.
 */
function invoke(\Closure $entry, array $data): string
{
    return (string) $entry(...declared_args($entry, $data));
}

/** Keep only the data keys that name a (non-variadic) parameter of $entry. @internal */
function declared_args(\Closure $entry, array $data): array
{
    $names = [];
    foreach ((new \ReflectionFunction($entry))->getParameters() as $param) {
        if (!$param->isVariadic()) {
            $names[$param->getName()] = true;
        }
    }
    return array_intersect_key($data, $names);
}
