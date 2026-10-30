<?php
declare(strict_types=1);
require_once __DIR__ . '/../../settings/theme_helpers.php';

function tulip_email_theme_luminance(string $hex): float
{
    $channels = [];
    foreach ([1, 3, 5] as $offset) {
        $value = hexdec(substr($hex, $offset, 2)) / 255;
        $channels[] = $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function tulip_email_theme_contrast(string $first, string $second): float
{
    $a = tulip_email_theme_luminance($first); $b = tulip_email_theme_luminance($second);
    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

function tulip_email_theme_ink(string $preferred, array $backgrounds): string
{
    $score = static fn(string $color): float => min(array_map(static fn($background): float => tulip_email_theme_contrast($color, $background), $backgrounds));
    if ($score($preferred) >= 4.5) return $preferred;
    return $score('#FFFFFF') >= $score('#1A1A1A') ? '#FFFFFF' : '#1A1A1A';
}

function tulip_email_theme_panel(string $accent, string $surface): string
{
    $result = '#';
    foreach ([1, 3, 5] as $offset) $result .= str_pad(dechex((int) round(hexdec(substr($accent, $offset, 2)) * 0.06 + hexdec(substr($surface, $offset, 2)) * 0.94)), 2, '0', STR_PAD_LEFT);
    return strtoupper($result);
}

/** Apply validated palette values only to HTML style attributes, never to guest text or URLs.
 * Inline colours and bgcolor attributes work without CSS variables in email clients.
 */
function tulip_email_apply_theme(string $html, array $colors): string
{
    $colors = theme_valid_colors($colors);
    $panel = tulip_email_theme_panel($colors['secondary'], $colors['surface']);
    $backgrounds = [$colors['surface'], $panel];
    $ink = tulip_email_theme_ink($colors['text'], $backgrounds);
    $muted = tulip_email_theme_ink($colors['muted'], $backgrounds);
    $heading = tulip_email_theme_ink($colors['secondary'], $backgrounds);
    $link = tulip_email_theme_ink($colors['primary'], $backgrounds);
    $onButton = tulip_email_theme_ink('#FFFFFF', [$colors['primary']]);
    $onFooter = $ink; // Footer follows the main email body text and surface.
    $footerDepth = 0;
    $paint = static function (string $value, string $property, string $tag, bool $footer) use ($colors, $panel, $ink, $muted, $heading, $link, $onButton, $onFooter): string {
        $key = strtolower(trim($value));
        if ($footer && $property === 'color') return $onFooter;
        if (str_starts_with($property, 'background') || $property === 'bgcolor') {
            return match ($key) {
                '#19352b' => $colors['primary'],
                '#f3f4f2' => $colors['background'],
                '#ffffff', '#fff', 'white' => $colors['surface'],
                '#f6f3ec', '#faf9f6' => $panel,
                default => $value,
            };
        }
        if ($property === 'color') {
            if ($tag === 'a' && in_array($key, ['#19352b', '#647067', '#56635b'], true)) return $link;
            return match ($key) {
                '#19352b' => $heading,
                '#865d1c' => $link,
                '#27392e', '#1a1a1a' => $ink,
                '#647067', '#56635b' => $muted,
                '#ffffff', '#fff', 'white' => $onButton,
                default => $value,
            };
        }
        // Border declarations may include width and line style before their colour.
        if (str_starts_with($property, 'border')) return preg_replace_callback('/#[0-9a-f]{6}\b/i', static function ($match) use ($colors): string {
            return match (strtolower($match[0])) { '#c7a86b'=>$colors['primary'], '#e8e3da', '#ece7df'=>$colors['border'], default=>$match[0] };
        }, $value) ?? $value;
        return $value;
    };
    return preg_replace_callback('~</?[a-z][^>]*>~i', static function ($match) use (&$footerDepth, $paint, $colors, $onFooter): string {
        $tagHtml = $match[0];
        preg_match('~^<(\/?)\s*([a-z][a-z0-9]*)~i', $tagHtml, $parts);
        $closing = ($parts[1] ?? '') === '/'; $tag = strtolower($parts[2] ?? '');
        $startsFooter = !$closing && $tag === 'td' && str_contains($tagHtml, 'data-email-theme-footer="true"');
        if (!$closing && $tag === 'td' && ($startsFooter || $footerDepth > 0)) $footerDepth++;
        $footer = $footerDepth > 0;
        if (!$closing) {
            $tagHtml = preg_replace_callback('/\bstyle="([^"]*)"/i', static function ($styleMatch) use ($paint, $tag, $footer, $startsFooter, $colors, $onFooter): string {
                $declarations = []; $semanticBackground = (bool) preg_match('/(?:^|;)\s*background(?:-color)?\s*:\s*#fff5df\s*(?:;|$)/i', $styleMatch[1]); $hasColor = false;
                foreach (explode(';', $styleMatch[1]) as $declaration) {
                    $pair = explode(':', $declaration, 2);
                    if (count($pair) !== 2) continue;
                    $property = strtolower(trim($pair[0])); $value = trim($pair[1]);
                    if ($property === 'color') $hasColor = true;
                    if (str_starts_with($property, 'background') && strtolower($value) === '#fff5df') $semanticBackground = true;
                    $painted = !$footer && $semanticBackground && $property === 'color' && strtolower($value) === '#865d1c'
                        ? $value : $paint($value, $property, $tag, $footer);
                    $declarations[] = $property . ':' . $painted;
                }
                if ($semanticBackground && !$hasColor) $declarations[] = 'color:#865d1c';
                if ($startsFooter) { $declarations[] = 'background:' . $colors['surface']; $declarations[] = 'color:' . $onFooter; }
                return 'style="' . implode(';', $declarations) . ';"';
            }, $tagHtml) ?? $tagHtml;
            $tagHtml = preg_replace_callback('/\bbgcolor="([^"]*)"/i', static fn($attribute): string => 'bgcolor="' . ($startsFooter ? $colors['surface'] : $paint($attribute[1], 'bgcolor', $tag, $footer)) . '"', $tagHtml) ?? $tagHtml;
        }
        if ($closing && $tag === 'td' && $footerDepth > 0) $footerDepth--;
        return $tagHtml;
    }, $html) ?? $html;
}
