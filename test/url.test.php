<?php

declare(strict_types=1);

/**
 * url\ builders and the on_rejected_url hook. data() constructs an inert
 * data: URI; external() is a boundary validator for user-submitted links;
 * the hook observes what a URL slot flattens. Run: php url.test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use function sigi\test\{record, throws, summary};
use sigi\{el, at, url};

// --- data(): safe by construction ---

$png = "\x89PNG\r\n\x1a\n";
$uri = url\data('image/png', $png);
record('data() builds a base64 data uri', $uri->value === 'data:image/png;base64,' . base64_encode($png), $uri->value);

$html = (string) el\IMG(at\SRC(url\data('image/png', $png)), at\ALT('qr'));
record('data() passes the URL slot unflattened', str_contains($html, 'src="data:image/png;base64,'), $html);

throws('data() rejects a malformed mime', fn() => url\data('image/png;charset=evil', 'x'));
throws('data() rejects a mime with no subtype', fn() => url\data('image', 'x'));
throws('data() refuses text/html', fn() => url\data('text/html', '<script>x</script>'));
throws('data() refuses text/html case-insensitively', fn() => url\data('Text/HTML', 'x'));

// --- external(): boundary validation ---

record('external() accepts https', url\external('https://example.com/a?b=c')->value === 'https://example.com/a?b=c');
record('external() accepts http', url\external('http://example.com/')->value === 'http://example.com/');
throws('external() rejects javascript:', fn() => url\external('javascript:alert(1)'));
throws('external() rejects a relative path', fn() => url\external('/local/path'));
throws('external() rejects a scheme-only string', fn() => url\external('https://'));
throws('external() rejects userinfo phishing', fn() => url\external('https://trusted.com@evil.com/'));

record('allowlist: exact host matches', url\external('https://example.com/x', hosts: ['example.com'])->value !== '');
record('allowlist: host match is case-insensitive', url\external('https://EXAMPLE.com/x', hosts: ['example.com'])->value !== '');
throws('allowlist: unlisted host rejected', fn() => url\external('https://evil.com/', hosts: ['example.com']));
throws('allowlist: bare entry excludes subdomains', fn() => url\external('https://sub.example.com/', hosts: ['example.com']));
record('allowlist: dot entry matches subdomains', url\external('https://sub.example.com/', hosts: ['.example.com'])->value !== '');
record('allowlist: dot entry matches the apex', url\external('https://example.com/', hosts: ['.example.com'])->value !== '');
throws('allowlist: dot entry rejects lookalike hosts', fn() => url\external('https://evilexample.com/', hosts: ['.example.com']));

// --- on_rejected_url: the URL slot neutralizes and reports ---

$seen = null;
$prev = sigi\Env::install(new sigi\Env(
    on_rejected_url: function (string $u) use (&$seen) { $seen = $u; },
));

$flat = (string) el\A(at\HREF('javascript:alert(1)'), 'x');
record('rejected url flattens to #', str_contains($flat, 'href="#"'), $flat);
record('hook observes the rejected url', $seen === 'javascript:alert(1)', var_export($seen, true));

$seen = null;
$ok = (string) el\A(at\HREF('https://example.com/'), 'x');
record('accepted url does not fire the hook', $seen === null && str_contains($ok, 'https://example.com/'), $ok);

record('a vouched Url skips rejection entirely', (function () use (&$seen) {
    $seen = null;
    $h = (string) el\IMG(at\SRC(url\data('image/gif', 'GIF89a')), at\ALT(''));
    return $seen === null && str_contains($h, 'data:image/gif;base64,');
})());

sigi\Env::install($prev);

exit(summary());
