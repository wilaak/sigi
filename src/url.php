<?php

declare(strict_types=1);

namespace sigi\url;

use sigi;

/**
 * A validated data: URI builder. Unlike unsafe\url(), which vouches for a raw
 * string, data() is safe by construction: the payload is base64-encoded — an
 * alphabet with no quotes, whitespace, or scheme characters — and the mime is
 * validated to token/token shape, so the result can never smuggle a scheme or
 * break the attribute. The result is a Url, so it passes a URL slot with no
 * vouch and no unsafe\ entry in the audit.
 *
 * text/html is refused: a data:text/html URI executes as a document in an
 * iframe/object/embed slot, so that trust decision stays behind unsafe\url().
 * (image/svg+xml passes — inert in <img>; embedding one in an <iframe> or
 * <object> is your call.)
 *
 * data: URIs re-ship with every render and defeat HTTP caching — use for
 * small, per-render images (a QR code, a chart, a DB-blob thumbnail), not
 * for assets that belong behind a CDN.
 *
 *   el\IMG(at\SRC(url\data('image/png', $qr)), at\ALT('2FA setup'));
 */
function data(string $mime, string $bytes): sigi\Url
{
    if (!preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~i', $mime)) {
        throw new \InvalidArgumentException("not a mime type: '{$mime}'");
    }
    if (strtolower($mime) === 'text/html') {
        throw new \InvalidArgumentException('data:text/html executes as a document; it requires unsafe\url()');
    }
    return new sigi\Url('data:' . $mime . ';base64,' . base64_encode($bytes));
}

/**
 * A boundary validator for user-submitted absolute links. Throws on anything
 * that is not a plain absolute http(s) URL — this is input validation, where
 * a throw is correct (turn it into your 422), not a render-time guard. The
 * result is a Url: the decision is made once, where the link enters, and the
 * value stays vouched through any plumbing.
 *
 * URLs carrying userinfo (https://trusted.com@evil.com/) are refused — a
 * classic phishing shape browsers themselves deprecate.
 *
 * $hosts, when given, allowlists the host: a bare entry matches exactly, a
 * leading-dot entry matches the apex and any subdomain (cookie convention).
 *
 *   url\external($input);                                  // any http(s) host
 *   url\external($input, hosts: ['example.com']);          // exactly example.com
 *   url\external($input, hosts: ['.wikipedia.org']);       // wikipedia.org + subdomains
 * 
 * @throws \InvalidArgumentException
 */
function external(string $value, ?array $hosts = null): sigi\Url
{
    $p = parse_url($value);
    if ($p === false) {
        throw new \InvalidArgumentException("not an absolute http(s) URL: '{$value}'");
    }
    $scheme = strtolower($p['scheme'] ?? '');
    $host = strtolower($p['host'] ?? '');
    if ($host === '' || !in_array($scheme, ['http', 'https'], true)) {
        throw new \InvalidArgumentException("not an absolute http(s) URL: '{$value}'");
    }
    if (isset($p['user']) || isset($p['pass'])) {
        throw new \InvalidArgumentException("URL carries userinfo: '{$value}'");
    }
    if ($hosts !== null && !host_allowed($host, $hosts)) {
        throw new \InvalidArgumentException("host not allowed: '{$host}'");
    }
    return new sigi\Url($value);
}

/** @internal */
function host_allowed(string $host, array $hosts): bool
{
    foreach ($hosts as $h) {
        $h = strtolower((string) $h);
        if (str_starts_with($h, '.')) {
            if ($host === substr($h, 1) || str_ends_with($host, $h)) {
                return true;
            }
        } elseif ($host === $h) {
            return true;
        }
    }
    return false;
}
