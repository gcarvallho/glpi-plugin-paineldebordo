<?php

/**
 * Global hub branding (colors, logos, texts) — users_id = 0.
 */

function plugin_paineldebordo_branding_defaults(): array
{
    $logo = function_exists('plugin_paineldebordo_asset_url')
        ? plugin_paineldebordo_asset_url('img/logo-inovare.svg')
        : 'img/logo-inovare.svg';
    $mark = function_exists('plugin_paineldebordo_asset_url')
        ? plugin_paineldebordo_asset_url('img/logo-inovare-mark.svg')
        : 'img/logo-inovare-mark.svg';

    return [
        'primary'            => '#09141F',
        'accent'             => '#E73E11',
        'favicon'            => '#e73e11',
        'bg'                 => '#f4f6f8',
        'surface'            => '#ffffff',
        'text'               => '#09141F',
        'primary_dark'       => '#ffffff',
        'bg_dark'            => '#071017',
        'surface_dark'       => '#0f1a24',
        'text_dark'          => '#ffffff',
        'logo_url'           => '',
        'logo_collapsed_url' => '',
        'eyebrow'            => 'Inovare - Hub',
        'product_name'       => 'Painel de Bordo',
        // Resolved defaults (not stored):
        '_logo'              => $logo,
        '_logo_collapsed'    => $mark,
    ];
}

/**
 * @return array<string,string>
 */
function plugin_paineldebordo_branding_get(): array
{
    global $DB;
    $defaults = plugin_paineldebordo_branding_defaults();
    $raw = '';
    if (isset($DB) && $DB->TableExists('glpi_plugin_paineldebordo_config')) {
        $res = $DB->doQuery(
            "SELECT value FROM glpi_plugin_paineldebordo_config
             WHERE name = 'branding' AND users_id = 0 LIMIT 1"
        );
        if ($res && $DB->numrows($res) > 0) {
            $raw = (string) $DB->result($res, 0, 'value');
        }
    }
    $data = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    $out = $defaults;
    foreach (['primary', 'accent', 'favicon', 'bg', 'surface', 'text', 'primary_dark', 'bg_dark', 'surface_dark', 'text_dark', 'logo_url', 'logo_collapsed_url', 'eyebrow', 'product_name'] as $k) {
        if (isset($data[$k]) && is_string($data[$k])) {
            $out[$k] = $data[$k];
        }
    }
    $out = plugin_paineldebordo_branding_sanitize($out);
    $out['_logo'] = $out['logo_url'] !== '' ? $out['logo_url'] : $defaults['_logo'];
    $out['_logo_collapsed'] = $out['logo_collapsed_url'] !== ''
        ? $out['logo_collapsed_url']
        : $defaults['_logo_collapsed'];
    return $out;
}

/**
 * @param array<string,mixed> $in
 * @return array<string,string>
 */
function plugin_paineldebordo_branding_sanitize(array $in): array
{
    $defaults = plugin_paineldebordo_branding_defaults();
    $hex = static function ($v, string $fallback): string {
        $v = trim((string) $v);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
            return strtolower($v);
        }
        return strtolower($fallback);
    };
    $url = static function ($v): string {
        $v = trim((string) $v);
        if ($v === '') {
            return '';
        }
        if (preg_match('#^https://[^\s\"\'<>]+$#i', $v)) {
            return $v;
        }
        if (preg_match('#^/?img/[A-Za-z0-9._\-/]+$#', $v)) {
            return $v;
        }
        return '';
    };
    $str = static function ($v, int $max, string $fallback): string {
        $v = trim(mb_substr((string) $v, 0, $max));
        return $v !== '' ? $v : $fallback;
    };

    return [
        'primary'            => $hex($in['primary'] ?? '', $defaults['primary']),
        'accent'             => $hex($in['accent'] ?? '', $defaults['accent']),
        'favicon'            => $hex($in['favicon'] ?? '', $defaults['favicon']),
        'bg'                 => $hex($in['bg'] ?? '', $defaults['bg']),
        'surface'            => $hex($in['surface'] ?? '', $defaults['surface']),
        'text'               => $hex($in['text'] ?? '', $defaults['text']),
        'primary_dark'       => $hex($in['primary_dark'] ?? '', $defaults['primary_dark']),
        'bg_dark'            => $hex($in['bg_dark'] ?? '', $defaults['bg_dark']),
        'surface_dark'       => $hex($in['surface_dark'] ?? '', $defaults['surface_dark']),
        'text_dark'          => $hex($in['text_dark'] ?? '', $defaults['text_dark']),
        'logo_url'           => $url($in['logo_url'] ?? ''),
        'logo_collapsed_url' => $url($in['logo_collapsed_url'] ?? ''),
        'eyebrow'            => $str($in['eyebrow'] ?? '', 80, $defaults['eyebrow']),
        'product_name'       => $str($in['product_name'] ?? '', 80, $defaults['product_name']),
    ];
}

/**
 * Dashboard mark SVG used as favicon (structure fixed; color from branding).
 */
function plugin_paineldebordo_favicon_svg(?string $color = null): string
{
    $brand = plugin_paineldebordo_branding_get();
    $c = $color ?? ($brand['favicon'] ?? $brand['accent'] ?? '#e73e11');
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) {
        $c = '#e73e11';
    }
    $c = strtolower($c);
    return '<svg data-icon-name="dashboard" data-style="line" icon_origin_id="20402" viewBox="0 0 24 24"'
        . ' xmlns="http://www.w3.org/2000/svg" id="dashboard" class="icon line" width="48" height="48">'
        . '<path style="fill: ' . $c . '; stroke: ' . $c . '; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1;"'
        . ' d="M9,12H4a1,1,0,0,1-1-1V4A1,1,0,0,1,4,3H9a1,1,0,0,1,1,1v7A1,1,0,0,1,9,12ZM21,7V4a1,1,0,0,0-1-1H15a1,1,0,0,0-1,1V7a1,1,0,0,0,1,1h5A1,1,0,0,0,21,7ZM10,20V17a1,1,0,0,0-1-1H4a1,1,0,0,0-1,1v3a1,1,0,0,0,1,1H9A1,1,0,0,0,10,20Zm11,0V13a1,1,0,0,0-1-1H15a1,1,0,0,0-1,1v7a1,1,0,0,0,1,1h5A1,1,0,0,0,21,20Z"'
        . ' id="primary"></path></svg>';
}

/** Absolute/plugin URL for dynamic favicon (cache-bust by color). */
function plugin_paineldebordo_favicon_href(?array $brand = null): string
{
    $b = $brand ?? plugin_paineldebordo_branding_get();
    $favColor = strtolower((string) ($b['favicon'] ?? $b['accent'] ?? '#e73e11'));
    if (!preg_match('/^#[0-9a-f]{6}$/', $favColor)) {
        $favColor = '#e73e11';
    }
    $base = function_exists('plugin_paineldebordo_asset_url')
        ? plugin_paineldebordo_asset_url('favicon.php')
        : 'favicon.php';
    return $base . '?c=' . rawurlencode(ltrim($favColor, '#'));
}

/** @param array<string,mixed> $data */
function plugin_paineldebordo_branding_set(array $data): bool
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_config')) {
        return false;
    }
    if (function_exists('plugin_paineldebordo_canConfigure') && !plugin_paineldebordo_canConfigure()) {
        return false;
    }
    $clean = plugin_paineldebordo_branding_sanitize($data);
    $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $exists = $DB->doQuery(
        "SELECT id FROM glpi_plugin_paineldebordo_config WHERE name = 'branding' AND users_id = 0 LIMIT 1"
    );
    if ($exists && $DB->numrows($exists) > 0) {
        $DB->doQuery(
            "UPDATE glpi_plugin_paineldebordo_config SET value = '" . $DB->escape($json) . "'
             WHERE name = 'branding' AND users_id = 0"
        );
    } else {
        $DB->doQuery(
            "INSERT INTO glpi_plugin_paineldebordo_config (name, value, users_id)
             VALUES ('branding', '" . $DB->escape($json) . "', 0)"
        );
    }
    return true;
}

function plugin_paineldebordo_branding_reset(): bool
{
    global $DB;
    if (!isset($DB) || !$DB->TableExists('glpi_plugin_paineldebordo_config')) {
        return false;
    }
    if (function_exists('plugin_paineldebordo_canConfigure') && !plugin_paineldebordo_canConfigure()) {
        return false;
    }
    $DB->doQuery("DELETE FROM glpi_plugin_paineldebordo_config WHERE name = 'branding' AND users_id = 0");
    return true;
}

/**
 * Filesystem directory where uploaded brand logos are stored (plugin public dir).
 */
function plugin_paineldebordo_branding_upload_dir(): string
{
    return rtrim(Plugin::getPhpDir('paineldebordo'), '/') . '/public/img/brand';
}

/** Max accepted upload size, in bytes. */
function plugin_paineldebordo_branding_max_upload_bytes(): int
{
    return 1024 * 1024; // 1 MB
}

/**
 * Handle an uploaded logo file from $_FILES[$field] (multipart form).
 *
 * @param string      $field  $_FILES key (e.g. 'brand_logo_file')
 * @param string      $prefix short slot id used in the stored filename ('logo' | 'logo_collapsed')
 * @param string|null $error  filled with a user-facing message on failure
 * @return string|null relative path ("img/brand/xxx.ext") to store as logo_url, or null
 *                      (null + no $error means "no file was chosen" — caller keeps prior value)
 */
function plugin_paineldebordo_branding_handle_upload(string $field, string $prefix, ?string &$error = null): ?string
{
    $error = null;
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $error = __('Upload failed', 'paineldebordo');
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > plugin_paineldebordo_branding_max_upload_bytes()) {
        $error = __('File too large (max 1 MB)', 'paineldebordo');
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $error = __('Upload failed', 'paineldebordo');
        return null;
    }

    $origName = (string) ($file['name'] ?? '');
    $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExt = ['svg', 'png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        $error = __('Unsupported file type', 'paineldebordo');
        return null;
    }

    if ($ext === 'svg') {
        // Reject SVGs carrying scripts/handlers (stored, then referenced as an <img>,
        // but stay defensive since some setups may inline/serve it directly).
        $content = (string) file_get_contents($tmp);
        if (preg_match('/<\s*script/i', $content) || preg_match('/\bon[a-z]+\s*=/i', $content)) {
            $error = __('Unsupported file type', 'paineldebordo');
            return null;
        }
    } elseif (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $okMime = ['image/png', 'image/jpeg', 'image/webp'];
        if ($mime !== '' && !in_array($mime, $okMime, true)) {
            $error = __('Unsupported file type', 'paineldebordo');
            return null;
        }
    }

    $dir = plugin_paineldebordo_branding_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        $error = __('Save failed', 'paineldebordo');
        return null;
    }

    $safePrefix = preg_replace('/[^a-z_]/', '', $prefix) ?: 'logo';
    $safeName = $safePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $safeName;
    if (!move_uploaded_file($tmp, $dest)) {
        $error = __('Save failed', 'paineldebordo');
        return null;
    }

    return 'img/brand/' . $safeName;
}

/** CSS overrides for :root (logos always absolute when possible). */
function plugin_paineldebordo_branding_css(?array $brand = null): string
{
    $b = $brand ?? plugin_paineldebordo_branding_get();
    $cssUrl = static function (string $u): string {
        $u = str_replace(['(', ')', '"', "'"], '', $u);
        return 'url(' . $u . ')';
    };
    $accent = $b['accent'];
    $primary = $b['primary'];
    // Never set light surfaces on bare :root — html is always :root, so that
    // would override [data-theme="dark"] tokens (broken TV/shell dark mode).
    $lines = [
        ':root:not([data-theme="dark"]), [data-theme="light"] {',
        '  --ho-primary: ' . $primary . ';',
        '  --ho-primary-hover: ' . $accent . ';',
        '  --ho-accent: ' . $accent . ';',
        '  --ho-info: ' . $accent . ';',
        '  --ho-danger: ' . $accent . ';',
        '  --ho-chart-2: ' . $primary . ';',
        '  --ho-bg: ' . $b['bg'] . ';',
        '  --ho-sider: ' . $primary . ';',
        '  --ho-surface: ' . $b['surface'] . ';',
        '  --ho-text: ' . $b['text'] . ';',
        '  --ho-logo: ' . $cssUrl($b['_logo']) . ';',
        '  --ho-logo-collapsed: ' . $cssUrl($b['_logo_collapsed']) . ';',
        '}',
        '[data-theme="dark"] {',
        '  --ho-primary: ' . $b['primary_dark'] . ';',
        '  --ho-primary-hover: ' . $accent . ';',
        '  --ho-accent: ' . $accent . ';',
        '  --ho-info: ' . $accent . ';',
        '  --ho-sider: ' . $primary . ';',
        '  --ho-bg: ' . $b['bg_dark'] . ';',
        '  --ho-surface: ' . $b['surface_dark'] . ';',
        '  --ho-text: ' . $b['text_dark'] . ';',
        '  --ho-logo: ' . $cssUrl($b['_logo']) . ';',
        '  --ho-logo-collapsed: ' . $cssUrl($b['_logo_collapsed']) . ';',
        '}',
    ];
    return implode("\n", $lines);
}

function plugin_paineldebordo_branding_emit_style(): void
{
    echo '<style id="ho-branding">' . plugin_paineldebordo_branding_css() . '</style>';
}

/**
 * Highcharts / dashboard series colors — brand first, then harmonic variants.
 *
 * @return list<string>
 */
function plugin_paineldebordo_chart_colors(): array
{
    $b = function_exists('plugin_paineldebordo_branding_get')
        ? plugin_paineldebordo_branding_get()
        : ['primary' => '#09141F', 'accent' => '#E73E11'];
    $primary = (string) ($b['primary'] ?? '#09141F');
    $accent = (string) ($b['accent'] ?? '#E73E11');
    return [
        $primary,
        $accent,
        '#1a7f4b',
        '#c9a227',
        '#2f6fed',
        '#8b5cf6',
        '#0d9488',
        '#ea580c',
        '#64748b',
        '#db2777',
        '#0891b2',
        '#b45309',
    ];
}

/**
 * Render product title with last word accented.
 */
function plugin_paineldebordo_branding_product_html(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        $name = 'Painel de Bordo';
    }
    $parts = preg_split('/\s+/', $name) ?: [$name];
    if (count($parts) === 1) {
        return '<span>' . htmlspecialchars($parts[0]) . '</span>';
    }
    $last = array_pop($parts);
    return htmlspecialchars(implode(' ', $parts)) . ' <span>' . htmlspecialchars((string) $last) . '</span>';
}
