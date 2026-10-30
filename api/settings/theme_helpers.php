<?php
declare(strict_types=1);

function theme_default_colors(): array {
    return ['primary'=>'#C7A86B','secondary'=>'#19352B','background'=>'#FAF8F4','surface'=>'#FFFFFF','text'=>'#1A1A1A','muted'=>'#6B7280','footer'=>'#1A1A1A','border'=>'#ECE7DF'];
}
function theme_valid_colors(mixed $value): array {
    $defaults = theme_default_colors();
    if (!is_array($value) || array_diff(array_keys($value), array_keys($defaults)) || array_diff(array_keys($defaults), array_keys($value))) throw new InvalidArgumentException('Provide all eight theme colours.');
    $clean = [];
    foreach ($defaults as $key => $_) {
        if (!is_string($value[$key]) || !preg_match('/^#[0-9a-fA-F]{6}$/D', trim($value[$key]))) throw new InvalidArgumentException('Enter a six-digit HEX colour for ' . $key . '.');
        $clean[$key] = strtoupper(trim($value[$key]));
    }
    return $clean;
}
