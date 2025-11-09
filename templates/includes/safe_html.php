<?php
// File: templates/includes/safe_html.php
// PHP 7+ compatible sanitizer. Works even if DOM/libxml are unavailable.

// Polyfills for PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

function sanitize_html(string $html): string {
    if ($html === '') return '';

    // Hard-strip obviously dangerous containers first
    $html = preg_replace('#</?(script|iframe|object|embed|style|meta|link)[^>]*>#i', '', $html);

    // Allow common presentation tags
    $allowed = '<b><strong><i><em><u><s><mark>'
             . '<p><br><hr><blockquote><pre><code>'
             . '<ul><ol><li>'
             . '<h1><h2><h3><h4><h5><h6>'
             . '<a><span><div><img>';

    $clean = strip_tags($html, $allowed);

    // If DOMDocument is available, do attribute-level cleaning robustly
    if (class_exists('DOMDocument')) {
        if (function_exists('libxml_use_internal_errors')) libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $flags = 0;
        if (defined('LIBXML_HTML_NOIMPLIED')) $flags |= LIBXML_HTML_NOIMPLIED;
        if (defined('LIBXML_HTML_NODEFDTD')) $flags |= LIBXML_HTML_NODEFDTD;
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $clean, $flags);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//*') as $el) {
            $remove = [];
            if ($el->hasAttributes()) {
                foreach ($el->attributes as $attr) {
                    $name = strtolower($attr->name);
                    $val  = trim($attr->value);

                    // Remove event handlers (onclick, onerror, etc.)
                    if (strpos($name, 'on') === 0) { $remove[] = $name; continue; }

                    // Constrain href/src
                    if ($name === 'href' || $name === 'src') {
                        $low = strtolower($val);
                        $ok = str_starts_with($low, 'http://')
                           || str_starts_with($low, 'https://')
                           || ($name === 'src' && str_starts_with($low, 'data:image/'));
                        if (!$ok) { $remove[] = $name; continue; }
                    }

                    // Keep only a few safe style properties
                    if ($name === 'style') {
                        $style = strtolower($val);
                        if (str_contains($style, 'expression') || str_contains($style, 'url(') || str_contains($style, 'position:fixed')) {
                            $remove[] = $name; continue;
                        }
                        $allowedProps = ['color','text-align','font-weight','font-style','text-decoration','background-color'];
                        $filtered = [];
                        foreach (explode(';', $style) as $chunk) {
                            $kv = array_map('trim', explode(':', $chunk, 2));
                            if (count($kv) === 2 && in_array($kv[0], $allowedProps, true)) {
                                $filtered[] = $kv[0] . ':' . $kv[1];
                            }
                        }
                        if (count($filtered) > 0) {
                            $el->setAttribute('style', implode(';', $filtered));
                        } else {
                            $remove[] = 'style';
                        }
                    }

                    if ($name === 'target') {
                        $el->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            }
            foreach ($remove as $n) { $el->removeAttribute($n); }
        }
        $out = $dom->saveHTML();
        if (function_exists('libxml_clear_errors')) libxml_clear_errors();
        return $out ?: '';
    }

    // Fallback (no DOM): coarse attribute scrubbing with regex
    // Remove on* attributes
    $clean = preg_replace('/\s+on\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean);
    // Remove javascript: in href/src
    $clean = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '', $clean);
    // Allow only http(s) or data:image in src
    $clean = preg_replace_callback('/\s+src\s*=\s*("|\')\s*([^"\']+)\1/i', function($m){
        $url = strtolower($m[2]);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:image/')) {
            return ' src="'.$m[2].'"';
        }
        return '';
    }, $clean);
    // Strip dangerous styles
    $clean = preg_replace('/\s+style\s*=\s*("|\')(?:[^"\']*(?:expression|url\(|position\s*:\s*fixed)[^"\']*)\1/i', '', $clean);

    return $clean;
}

function html_excerpt(string $html, int $len = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $len ? mb_substr($text, 0, $len, 'UTF-8').'…' : $text;
    }
    return strlen($text) > $len ? substr($text, 0, $len).'…' : $text;
}
