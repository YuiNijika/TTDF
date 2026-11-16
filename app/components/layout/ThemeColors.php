<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Color;
use TTDF\Support\Escaper;
use TTDF\Theme\Settings;

$values = Settings::values();

// Settings::values() already normalized by schema type; running the values
// through Color again keeps a hand-edited settings row out of the style block.
$accent = Color::normalize($values['Color_Accent'] ?? '');
$accentDark = Color::normalize($values['Color_Accent_Dark'] ?? '');
$background = Color::normalize($values['Color_Background'] ?? '');
$backgroundDark = Color::normalize($values['Color_Background_Dark'] ?? '');

$light = [];
$dark = [];

if ($background !== '') {
    $light['--bg'] = $background;
}

if ($accent !== '') {
    $light['--accent'] = $accent;
    $light['--accent-strong'] = Color::mix($accent, Color::isDark($accent) ? '#000000' : '#ffffff', 0.18);
    $light['--accent-contrast'] = Color::contrast($accent);
    $light['--accent-soft'] = Color::alpha($accent, 0.12);
}

if ($backgroundDark !== '') {
    $dark['--bg'] = $backgroundDark;
}

if ($accentDark !== '') {
    $dark['--accent'] = $accentDark;
    $dark['--accent-strong'] = Color::mix($accentDark, Color::isDark($accentDark) ? '#000000' : '#ffffff', 0.18);
    $dark['--accent-contrast'] = Color::contrast($accentDark);
    $dark['--accent-soft'] = Color::alpha($accentDark, 0.12);
}

$declaration = static function (array $values): string {
    $parts = [];
    foreach ($values as $name => $value) {
        if (is_string($value) && $value !== '') {
            $parts[] = $name . ':' . $value;
        }
    }

    return implode(';', $parts);
};

$lightCss = $declaration($light);
$darkCss = $declaration($dark);

if ($lightCss === '' && $darkCss === '') {
    return;
}
?>
<style>
<?php if ($lightCss !== ''): ?>
:root{<?php echo Escaper::html($lightCss); ?>}
<?php endif; ?>
<?php if ($darkCss !== ''): ?>
:root[data-theme="dark"]{<?php echo Escaper::html($darkCss); ?>}
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {<?php echo Escaper::html($darkCss); ?>}
}
<?php endif; ?>
</style>
