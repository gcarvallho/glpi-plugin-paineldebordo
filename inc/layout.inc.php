<?php

/**
 * Shared layout — Tabler-like chrome + Inovare tokens. Zero iframe.
 */

include_once __DIR__ . '/access.inc.php';
include_once __DIR__ . '/filters.inc.php';
include_once __DIR__ . '/icons.inc.php';
include_once __DIR__ . '/branding.inc.php';

/**
 * HTML lang from GLPI session / CFG.
 */
function plugin_paineldebordo_html_lang(): string
{
    global $CFG_GLPI;
    $lang = $_SESSION['glpilanguage'] ?? ($CFG_GLPI['language'] ?? 'en_GB');
    $lang = str_replace('_', '-', (string) $lang);
    return $lang !== '' ? $lang : 'en';
}

/**
 * Resolve the web base URL for plugin public assets (css/js/img).
 * Prefers SCRIPT_NAME (actual request) then Plugin::getWebDir(full=true)
 * so marketplace links include root_doc (e.g. /glpi/marketplace/…).
 *
 * @return string e.g. /glpi/plugins/paineldebordo/public  OR  /glpi/marketplace/paineldebordo
 */
function plugin_paineldebordo_asset_base(): string
{
    global $CFG_GLPI;
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    $normalize = static function (string $path): string {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }
        return $path;
    };

    // 1) From current PHP URL — most accurate for this request (includes /public when present)
    if (preg_match('#^(.*?/(?:marketplace|plugins)/paineldebordo(?:/public)?)/[^/]+\.php$#', $script, $m)
        || preg_match('#^(.*?/(?:marketplace|plugins)/paineldebordo(?:/public)?)(?:/|$)#', $script, $m)
    ) {
        $cached = $normalize($m[1]);
        return $cached;
    }

    // 2) Plugin::getWebDir FULL — includes root_doc (false omits it → /marketplace/... breaks)
    if (class_exists('Plugin') && method_exists('Plugin', 'getWebDir')) {
        $wd = Plugin::getWebDir('paineldebordo', true);
        if (is_string($wd) && $wd !== '' && $wd !== '0') {
            $cached = $normalize($wd);
            return $cached;
        }
    }

    // 3) Classic disk layout
    $cached = $normalize($root . '/plugins/paineldebordo/public');
    return $cached;
}

/**
 * True when URL host is localhost / loopback (bad for QR when GLPI url_base is mis-set).
 */
function plugin_paineldebordo_url_is_loopback(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }
    $host = strtolower($host);
    return $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || str_ends_with($host, '.localhost');
}

/**
 * Public base URL from the current HTTP request (scheme + host [+ port]).
 */
function plugin_paineldebordo_request_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }
    // X-Forwarded-Host may be a list
    if (str_contains($host, ',')) {
        $host = trim(explode(',', $host, 2)[0]);
    }
    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * Absolute URL (scheme + host) for a public plugin resource — TV QR / copy link.
 * Prefers CFG url_base, but ignores loopback url_base when the request host is public.
 */
function plugin_paineldebordo_absolute_url(string $rel): string
{
    global $CFG_GLPI;
    $path = plugin_paineldebordo_asset_url($rel);
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $cfgBase = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
    $reqBase = rtrim(plugin_paineldebordo_request_base_url(), '/');
    $base = $cfgBase;
    if ($base === '' || (plugin_paineldebordo_url_is_loopback($base) && $reqBase !== '' && !plugin_paineldebordo_url_is_loopback($reqBase))) {
        $base = $reqBase;
    }
    if ($base !== '') {
        return $base . (str_starts_with($path, '/') ? $path : '/' . $path);
    }
    return $path;
}

/**
 * Debug snapshot for TV pair / asset URL troubleshooting (?tv_pair_debug=1).
 *
 * @return array<string,mixed>
 */
function plugin_paineldebordo_url_debug(): array
{
    global $CFG_GLPI;
    $wd_full = '';
    $wd_short = '';
    if (class_exists('Plugin') && method_exists('Plugin', 'getWebDir')) {
        $wd_full = (string) Plugin::getWebDir('paineldebordo', true);
        $wd_short = (string) Plugin::getWebDir('paineldebordo', false);
    }
    return [
        'root_doc'       => (string) ($CFG_GLPI['root_doc'] ?? ''),
        'url_base'       => (string) ($CFG_GLPI['url_base'] ?? ''),
        'request_base'   => plugin_paineldebordo_request_base_url(),
        'script_name'    => (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        'getWebDir_full' => $wd_full,
        'getWebDir_short'=> $wd_short,
        'asset_base'     => plugin_paineldebordo_asset_base(),
        'asset_bases'    => function_exists('plugin_paineldebordo_asset_bases')
            ? plugin_paineldebordo_asset_bases()
            : [],
        'tv_pair_path'   => plugin_paineldebordo_asset_url('tv_pair.php'),
        'tv_pair_abs'    => plugin_paineldebordo_absolute_url('tv_pair.php'),
        'pair_ajax'      => plugin_paineldebordo_asset_url('tv_pair_api.php'),
    ];
}

/**
 * Absolute URL to a public asset. Always absolute (never bare relative) to avoid
 * unstyled pages when the browser path does not match the plugin folder.
 */
function plugin_paineldebordo_asset_url(string $rel): string
{
    return rtrim(plugin_paineldebordo_asset_base(), '/') . '/' . ltrim($rel, '/');
}

/**
 * Candidate bases (with and without /public) for dual <link>/<script> tags.
 *
 * @return string[]
 */
function plugin_paineldebordo_asset_bases(): array
{
    global $CFG_GLPI;
    $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    $primary = plugin_paineldebordo_asset_base();
    $bases = [$primary];

    if (str_ends_with($primary, '/public')) {
        $bases[] = substr($primary, 0, -strlen('/public'));
    } else {
        $bases[] = $primary . '/public';
    }

    $bases[] = $root . '/plugins/paineldebordo/public';
    $bases[] = $root . '/plugins/paineldebordo';

    if (class_exists('Plugin') && method_exists('Plugin', 'getWebDir')) {
        $wd = rtrim((string) Plugin::getWebDir('paineldebordo', true), '/');
        if ($wd !== '') {
            if ($wd[0] !== '/') {
                $wd = '/' . ltrim($wd, '/');
            }
            $bases[] = $wd;
            if (!str_ends_with($wd, '/public')) {
                $bases[] = $wd . '/public';
            }
        }
    }

    $out = [];
    foreach ($bases as $b) {
        $b = rtrim((string) $b, '/');
        if ($b !== '' && !in_array($b, $out, true)) {
            $out[] = $b;
        }
    }
    return $out;
}

/**
 * Initials for avatar fallback (no photo).
 */
function plugin_paineldebordo_user_initials(string $login, string $firstname = '', string $realname = ''): string
{
    if (class_exists('User') && method_exists('User', 'getInitialsForUserName')) {
        return (string) User::getInitialsForUserName($login, $firstname, $realname);
    }
    $a = function_exists('mb_substr') ? mb_substr($firstname, 0, 1) : substr($firstname, 0, 1);
    $b = function_exists('mb_substr') ? mb_substr($realname, 0, 1) : substr($realname, 0, 1);
    $out = $a . $b;
    if ($out === '') {
        $out = function_exists('mb_substr') ? mb_substr($login, 0, 2) : substr($login, 0, 2);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($out) : strtoupper($out);
}

/**
 * Resolve avatar data (photo URL + initials badge colors + display name) for
 * ANY user id. Reuses GLPI's User picture/initials helpers. Cached per request
 * by uid+fallback so a logs table with many rows per user stays cheap.
 *
 * @param array{login?:string,firstname?:string,realname?:string} $fallback
 * @return array{picture_url:string,initials:string,initials_bg:string,initials_fg:string,name:string,login:string}
 */
function plugin_paineldebordo_user_avatar(int $uid, array $fallback = []): array
{
    static $cache = [];
    $key = $uid . '|' . md5((string) json_encode($fallback));
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $login = (string) ($fallback['login'] ?? '');
    $firstname = (string) ($fallback['firstname'] ?? '');
    $realname = (string) ($fallback['realname'] ?? '');
    $picture_url = '';
    $bg = '#09141F';
    $initials = plugin_paineldebordo_user_initials($login, $firstname, $realname);

    if ($uid > 0 && class_exists('User')) {
        $user = new User();
        if ($user->getFromDB($uid)) {
            $firstname = (string) ($user->fields['firstname'] ?? $firstname);
            $realname = (string) ($user->fields['realname'] ?? $realname);
            $login = (string) ($user->fields['name'] ?? $login);
            if (method_exists($user, 'getUserInitials')) {
                $initials = (string) $user->getUserInitials(false);
            } else {
                $initials = plugin_paineldebordo_user_initials($login, $firstname, $realname);
            }
            if (method_exists($user, 'getUserInitialsBgColor')) {
                $bg = (string) $user->getUserInitialsBgColor(false);
            }
            if (method_exists($user, 'getThumbnailPicturePath')) {
                $thumb = $user->getThumbnailPicturePath(false);
                if (!empty($thumb)) {
                    $picture_url = (string) $thumb;
                }
            }
            if ($picture_url === '' && !empty($user->fields['picture']) && method_exists('User', 'getThumbnailURLForPicture')) {
                $thumb = (string) User::getThumbnailURLForPicture((string) $user->fields['picture']);
                if ($thumb !== '') {
                    $picture_url = $thumb;
                } elseif (method_exists('User', 'getURLForPicture')) {
                    $picture_url = (string) User::getURLForPicture((string) $user->fields['picture']);
                }
            }
        }
    }

    $fg = '#ffffff';
    if (class_exists('Toolbox') && method_exists('Toolbox', 'getFgColor')) {
        $fg = (string) Toolbox::getFgColor($bg, 60);
    }

    $display = trim($firstname . ' ' . $realname);
    if ($display === '') {
        $display = $login;
    }

    return $cache[$key] = [
        'picture_url'  => $picture_url,
        'initials'     => $initials !== '' ? $initials : '?',
        'initials_bg'  => $bg,
        'initials_fg'  => $fg,
        'name'         => $display,
        'login'        => $login,
    ];
}

/**
 * Render a `.ho-avatar` span (photo or initials badge) for any user id.
 * Reused by the top chrome and the logs/audit table.
 */
function plugin_paineldebordo_avatar_html(int $uid, string $name = ''): string
{
    $av = plugin_paineldebordo_user_avatar($uid, $name !== '' ? ['login' => $name] : []);
    $label = $name !== '' ? $name : $av['name'];
    if ($av['picture_url'] !== '') {
        return '<span class="ho-avatar"><img src="' . htmlspecialchars($av['picture_url'])
            . '" alt="' . htmlspecialchars($label) . '" width="28" height="28" decoding="async"></span>';
    }
    return '<span class="ho-avatar" style="background-color:' . htmlspecialchars($av['initials_bg'])
        . ';color:' . htmlspecialchars($av['initials_fg']) . '"><span class="ho-avatar__initials">'
        . htmlspecialchars($av['initials']) . '</span></span>';
}

/**
 * Avatar + GLPI home for top chrome (session user); thin wrapper over the
 * generic resolver above.
 *
 * @return array{picture_url:string,initials:string,initials_bg:string,initials_fg:string,glpi_home:string}
 */
function plugin_paineldebordo_user_chrome(): array
{
    global $CFG_GLPI;

    $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
    $uid = (int) ($_SESSION['glpiID'] ?? 0);
    $av = plugin_paineldebordo_user_avatar($uid, [
        'login'     => (string) ($_SESSION['glpiname'] ?? ''),
        'firstname' => (string) ($_SESSION['glpifirstname'] ?? ''),
        'realname'  => (string) ($_SESSION['glpirealname'] ?? ''),
    ]);

    return [
        'picture_url'  => $av['picture_url'],
        'initials'     => $av['initials'],
        'initials_bg'  => $av['initials_bg'],
        'initials_fg'  => $av['initials_fg'],
        'glpi_home'    => $root . '/front/central.php',
    ];
}

/**
 * @return array{title:string,active:string,theme:string,period:string,group:int,group_options:array,user_name:string,root_doc:string,plugin_web:string,nav_layout:string}
 */
function plugin_paineldebordo_layout_context(array $opts = []): array
{
    global $CFG_GLPI;

    $filters = plugin_paineldebordo_getFilters();
    $active = $opts['active'] ?? ($_GET['page'] ?? 'home');
    $title = $opts['title'] ?? __('Painel de Bordo', 'paineldebordo');
    $root = $CFG_GLPI['root_doc'] ?? '';
    $plugin_web = plugin_paineldebordo_asset_base();
    $user_chrome = plugin_paineldebordo_user_chrome();

    if (!function_exists('plugin_paineldebordo_entity_options')) {
        include_once __DIR__ . '/services/tickets.php';
    }
    $entities_only = plugin_paineldebordo_entity_options();
    $entity_locked = count($entities_only) === 1;
    if ($entity_locked) {
        $only_ent = (int) array_key_first($entities_only);
        $entity_options = $entities_only;
        $entity_focus = $only_ent;
        if ((int) ($filters['entity_focus'] ?? -1) !== $only_ent) {
            plugin_paineldebordo_setConfigValue('filter_entity_focus', (string) $only_ent);
        }
    } else {
        $entity_options = [-1 => '-- ' . __('All entities', 'paineldebordo') . ' --'] + $entities_only;
        $entity_focus = (int) ($filters['entity_focus'] ?? -1);
    }

    return [
        'title'           => $title,
        'active'          => $active,
        'theme'           => $filters['theme'],
        'period'          => $filters['period'],
        'group'           => $filters['group'],
        'group_options'   => plugin_paineldebordo_getGroupOptions(),
        'entity_focus'    => $entity_focus,
        'entity_options'  => $entity_options,
        'entity_locked'   => $entity_locked,
        'user_name'       => $_SESSION['glpiname'] ?? '',
        'user_picture'    => $user_chrome['picture_url'],
        'user_initials'   => $user_chrome['initials'],
        'user_initials_bg'=> $user_chrome['initials_bg'],
        'user_initials_fg'=> $user_chrome['initials_fg'],
        'glpi_home'       => $user_chrome['glpi_home'],
        'root_doc'        => $root,
        'plugin_web'      => $plugin_web,
        'nav_layout'      => $filters['nav_layout'],
        'nav_collapsed'   => !empty($filters['nav_collapsed']),
    ];
}

/**
 * Flat nav id => label (compat / tests). Prefer plugin_paineldebordo_nav_tree().
 *
 * @return array<string,string>
 */
function plugin_paineldebordo_nav_items(): array
{
    $flat = [];
    foreach (plugin_paineldebordo_nav_tree() as $block) {
        foreach ($block['items'] as $id => $label) {
            $flat[$id] = $label;
        }
    }
    return $flat;
}

/**
 * Grouped navigation tree for side/top chrome.
 *
 * @return list<array{id:?string,label:?string,items:array<string,string>}>
 */
function plugin_paineldebordo_nav_tree(): array
{
    $tree = [
        [
            'id'    => null,
            'label' => null,
            'items' => [
                'home' => __('Overview', 'paineldebordo'),
            ],
        ],
        [
            'id'    => 'tv_mode',
            'label' => __('TV mode', 'paineldebordo'),
            'items' => [
                'tv_pair' => __('Pair TV', 'paineldebordo'),
                'tv'      => __('Open logged-in TV', 'paineldebordo'),
            ],
        ],
    ];

    if (plugin_paineldebordo_canModule('tickets', READ)) {
        $tree[] = [
            'id'    => 'tickets',
            'label' => __('Tickets', 'paineldebordo'),
            'items' => [
                'tickets'       => __('Open tickets', 'paineldebordo'),
                'for_me'        => __('For me', 'paineldebordo'),
                'opened_by_me'  => __('Opened by me', 'paineldebordo'),
                'observer'      => __('Observer', 'paineldebordo'),
                'by_group'      => __('By group', 'paineldebordo'),
                'by_entity'     => __('By entity', 'paineldebordo'),
            ],
        ];
    }

    if (plugin_paineldebordo_canModule('analysis', READ)) {
        $tree[] = [
            'id'    => 'analysis',
            'label' => __('Analysis', 'paineldebordo'),
            'items' => [
                'charts'  => __('Charts', 'paineldebordo'),
                'reports' => __('Reports', 'paineldebordo'),
                'metrics' => __('BI', 'paineldebordo'),
            ],
        ];
    }

    if (plugin_paineldebordo_canModule('resources', READ)) {
        $tree[] = [
            'id'    => 'resources',
            'label' => __('Resources', 'paineldebordo'),
            'items' => [
                'map'    => __('Map', 'paineldebordo'),
                'assets' => __('Assets', 'paineldebordo'),
            ],
        ];
    }

    if (plugin_paineldebordo_canConfigure()) {
        $tree[] = [
            'id'    => 'admin',
            'label' => __('Admin', 'paineldebordo'),
            'items' => [
                'config' => __('Configuration', 'paineldebordo'),
                'logs'   => __('Logs & audit', 'paineldebordo'),
            ],
        ];
    }
    return $tree;
}

/**
 * Nav group id that owns the current page (for expand/highlight).
 */
function plugin_paineldebordo_nav_active_group(string $page): ?string
{
    $page = match ($page) {
        'chart'  => 'charts',
        'report' => 'reports',
        default  => $page,
    };
    foreach (plugin_paineldebordo_nav_tree() as $block) {
        if ($block['id'] === null) {
            continue;
        }
        if (isset($block['items'][$page])) {
            return $block['id'];
        }
    }
    return null;
}

function plugin_paineldebordo_page_start(array $opts = []): void
{
    $ctx = plugin_paineldebordo_layout_context($opts);
    $theme = $ctx['theme'];
    $page = $ctx['active'];
    $period = $ctx['period'];
    $sel_grp = $ctx['group'];
    $sel_ent = (int) ($ctx['entity_focus'] ?? -1);
    $nav_layout = $ctx['nav_layout'];
    $nav_collapsed = !empty($ctx['nav_collapsed']) && $nav_layout !== 'top';
    $brand = plugin_paineldebordo_branding_get();
    $lang = plugin_paineldebordo_html_lang();

    $nav_href = static function (string $id) use ($theme): string {
        if ($id === 'tv') {
            return 'tv.php?theme=' . rawurlencode($theme);
        }
        if ($id === 'tv_pair') {
            return 'tv_pair.php?theme=' . rawurlencode($theme);
        }
        return 'shell.php?' . http_build_query(['page' => $id, 'theme' => $theme]);
    };

    $qs_theme = static function (string $t) use ($page, $period, $sel_grp, $sel_ent, $nav_layout): string {
        return 'shell.php?' . http_build_query([
            'page'       => $page,
            'theme'      => $t,
            'period'     => $period,
            'sel_grp'    => $sel_grp,
            'sel_ent'    => $sel_ent,
            'nav_layout' => $nav_layout,
        ]);
    };

    $qs_nav = static function (string $n) use ($page, $theme, $period, $sel_grp, $sel_ent): string {
        return 'shell.php?' . http_build_query([
            'page'       => $page,
            'theme'      => $theme,
            'period'     => $period,
            'sel_grp'    => $sel_grp,
            'sel_ent'    => $sel_ent,
            'nav_layout' => $n,
        ]);
    };

    $period_url = static function (string $p) use ($page, $theme, $sel_grp, $sel_ent, $nav_layout): string {
        return 'shell.php?' . http_build_query([
            'page'       => $page,
            'theme'      => $theme,
            'period'     => $p,
            'sel_grp'    => $sel_grp,
            'sel_ent'    => $sel_ent,
            'nav_layout' => $nav_layout,
        ]);
    };

    $app_cls = 'ho-app';
    if ($nav_layout === 'top') {
        $app_cls .= ' ho-app--top';
    } elseif ($nav_collapsed) {
        $app_cls .= ' ho-app--rail';
    }
    $assets = rtrim($ctx['plugin_web'], '/');
    $GLOBALS['HO_PLUGIN_WEB'] = $assets;
    $GLOBALS['HO_LAYOUT'] = $ctx;

    echo '<!DOCTYPE html>';
    echo '<html lang="' . htmlspecialchars($lang) . '" data-theme="' . htmlspecialchars($theme) . '">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($brand['product_name']) . ' — ' . htmlspecialchars($ctx['title']) . '</title>';
    if ($ctx['root_doc']) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($ctx['root_doc']) . '/css/palettes/auror.css" onerror="this.remove()">';
    }
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">';

    // 1) Inline tokens from disk — never leave the UI as unstyled text if URL 404s
    // Prefer is_file + file_get_contents: is_readable() can false-negative on Windows paths with accents.
    $css_file = dirname(__DIR__) . '/public/css/dashboard-tokens.css';
    if (is_file($css_file)) {
        $css = @file_get_contents($css_file);
        if (is_string($css) && $css !== '') {
            echo '<style id="ho-tokens-inline">' . $css . '</style>';
        }
    }

    // 2) Also link absolute candidates (cache / overrides)
    foreach (plugin_paineldebordo_asset_bases() as $base) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base) . '/css/dashboard-tokens.css">';
    }
    // Branding MUST win over linked token stylesheet
    plugin_paineldebordo_branding_emit_style();
    $favHref = function_exists('plugin_paineldebordo_favicon_href')
        ? plugin_paineldebordo_favicon_href($brand)
        : plugin_paineldebordo_asset_url('img/favicon.svg');
    echo '<link rel="icon" href="' . htmlspecialchars($favHref) . '" type="image/svg+xml">';
    // Do not also link the static orange img/favicon.svg — browsers may keep that instead of the dynamic color.
    echo '</head><body class="ho-body" data-plugin-web="' . htmlspecialchars($assets) . '">';
    echo '<div class="' . $app_cls . '" id="ho_app">';

    $brand_html = static function () use ($brand) {
        echo '<div class="ho-brand">';
        echo '<span class="ho-brand__logo" role="img" aria-label="' . htmlspecialchars($brand['eyebrow']) . '"></span>';
        echo '<div class="ho-brand__text">';
        echo '<p class="ho-brand__eyebrow">' . htmlspecialchars($brand['eyebrow']) . '</p>';
        echo '<h1 class="ho-brand__name">' . plugin_paineldebordo_branding_product_html($brand['product_name']) . '</h1>';
        echo '</div></div>';
    };

    $nav_html = static function () use ($page, $nav_href, $nav_layout, $nav_collapsed) {
        $activeGroup = plugin_paineldebordo_nav_active_group($page);
        $isTop = ($nav_layout === 'top');
        // The tooltip only earns its place when the label is actually
        // hidden (collapsed rail, icon-only) — everywhere else the label
        // sits right there in the nav, so a tooltip repeating it is noise.
        $tip = static function (string $label) use ($nav_collapsed): string {
            return $nav_collapsed ? ' ho-tip" data-tip="' . htmlspecialchars($label) : '"';
        };
        echo '<nav class="ho-nav">';
        foreach (plugin_paineldebordo_nav_tree() as $block) {
            $gid = $block['id'];
            $items = $block['items'];
            if ($gid === null) {
                foreach ($items as $id => $label) {
                    $cls = $page === $id ? 'is-active' : '';
                    echo '<a class="' . $cls . $tip($label) . '" href="' . htmlspecialchars($nav_href($id)) . '">';
                    echo plugin_paineldebordo_icon($id, 'ho-nav__icon');
                    echo '<span class="ho-nav__label">' . htmlspecialchars($label) . '</span>';
                    echo '</a>';
                }
                continue;
            }

            // Top menu: never start expanded (only on user click). Side: open active group.
            $isActiveGroup = ($activeGroup === $gid);
            $open = !$isTop && $isActiveGroup;
            $groupCls = 'ho-nav-group'
                . ($open ? ' is-open' : '')
                . ($isActiveGroup ? ' is-active-group' : '');
            echo '<div class="' . $groupCls . '" data-group="' . htmlspecialchars($gid) . '">';
            echo '<button type="button" class="ho-nav-group__title' . $tip((string) $block['label']) . ' aria-expanded="' . ($open ? 'true' : 'false') . '">';
            echo plugin_paineldebordo_icon((string) $gid, 'ho-nav__icon');
            echo '<span class="ho-nav__label">' . htmlspecialchars((string) $block['label']) . '</span>';
            echo '<span class="ho-nav-group__caret" aria-hidden="true"></span>';
            echo '</button>';
            echo '<div class="ho-nav-group__items">';
            foreach ($items as $id => $label) {
                // Sub-items always keep their visible label — even inside the
                // collapsed rail's flyout (.ho-nav-group__items .ho-nav__label
                // stays display:inline there) — so no tooltip here, ever.
                $cls = $page === $id ? 'is-active' : '';
                echo '<a class="' . $cls . '" href="' . htmlspecialchars($nav_href($id)) . '">';
                echo plugin_paineldebordo_icon($id, 'ho-nav__icon');
                echo '<span class="ho-nav__label">' . htmlspecialchars($label) . '</span>';
                echo '</a>';
            }
            echo '</div></div>';
        }
        echo '</nav>';
        // Toggle groups: unified click model (side accordion / rail flyout / top dropdown).
        // One open group at a time on rail+top. No CSS :hover flyouts (avoids double menus).
        echo '<script>(function(){';
        echo 'var top=!!document.querySelector(".ho-app--top");';
        echo 'function isRail(){return !!document.querySelector(".ho-app--rail");}';
        echo 'function closeGroups(except){document.querySelectorAll(".ho-nav-group.is-open").forEach(function(x){';
        echo 'if(except&&x===except)return;x.classList.remove("is-open");var b=x.querySelector(".ho-nav-group__title");if(b)b.setAttribute("aria-expanded","false");});}';
        echo 'document.querySelectorAll(".ho-nav-group__title").forEach(function(btn){btn.addEventListener("click",function(ev){';
        echo 'ev.stopPropagation();var g=btn.closest(".ho-nav-group");if(!g)return;';
        echo 'var open=!g.classList.contains("is-open");';
        echo 'if(top||isRail()){closeGroups(g);}';
        echo 'g.classList.toggle("is-open",open);btn.setAttribute("aria-expanded",open?"true":"false");';
        echo '});});';
        echo 'document.addEventListener("click",function(){if(top||isRail()){closeGroups(null);}});';
        echo 'if(isRail()){closeGroups(null);}';
        echo 'document.addEventListener("pdb-nav-rail-change",function(ev){';
        echo 'if(ev&&ev.detail&&ev.detail.rail){closeGroups(null);}';
        echo '});';
        echo '})();</script>';
    };

    $rail_toggle = static function () use ($nav_collapsed) {
        $label_collapse = __('Collapse menu', 'paineldebordo');
        $label_expand = __('Expand menu', 'paineldebordo');
        $label = $nav_collapsed ? $label_expand : $label_collapse;
        $icon = $nav_collapsed ? 'nav_expand' : 'nav_collapse';
        echo '<button type="button" class="ho-sider__toggle ho-tip" id="ho_nav_rail_btn"';
        echo ' aria-pressed="' . ($nav_collapsed ? 'true' : 'false') . '"';
        echo ' data-label-collapse="' . htmlspecialchars($label_collapse) . '"';
        echo ' data-label-expand="' . htmlspecialchars($label_expand) . '"';
        echo ' data-tip="' . htmlspecialchars($label) . '">';
        echo '<span class="ho-sider__toggle-icon" id="ho_nav_rail_icon">' . plugin_paineldebordo_icon($icon) . '</span>';
        echo '<span class="ho-sider__toggle-label" id="ho_nav_rail_label">' . htmlspecialchars($label) . '</span>';
        echo '</button>';
        echo '<script>(function(){';
        echo 'var app=document.getElementById("ho_app");var btn=document.getElementById("ho_nav_rail_btn");';
        echo 'if(!app||!btn||app.classList.contains("ho-app--top"))return;';
        echo 'var KEY="pdb_nav_collapsed";';
        echo 'try{var ls=localStorage.getItem(KEY);if(ls==="1"){app.classList.add("ho-app--rail");}else if(ls==="0"){app.classList.remove("ho-app--rail");}}catch(e){}';
        echo 'function syncUi(){var on=app.classList.contains("ho-app--rail");btn.setAttribute("aria-pressed",on?"true":"false");';
        echo 'var lab=on?btn.getAttribute("data-label-expand"):btn.getAttribute("data-label-collapse");';
        echo 'btn.setAttribute("data-tip",lab);var L=document.getElementById("ho_nav_rail_label");if(L)L.textContent=lab;';
        echo 'var ic=document.getElementById("ho_nav_rail_icon");if(ic)ic.innerHTML=on?' .
            json_encode(plugin_paineldebordo_icon('nav_expand')) . ':' .
            json_encode(plugin_paineldebordo_icon('nav_collapse')) . ';}';
        echo 'syncUi();';
        echo 'if(app.classList.contains("ho-app--rail")){';
        echo 'try{document.dispatchEvent(new CustomEvent("pdb-nav-rail-change",{detail:{rail:true}}));}catch(e){}';
        echo '}';
        echo 'btn.addEventListener("click",function(){var on=!app.classList.contains("ho-app--rail");';
        echo 'app.classList.toggle("ho-app--rail",on);try{localStorage.setItem(KEY,on?"1":"0");}catch(e){}';
        echo 'syncUi();';
        echo 'try{document.dispatchEvent(new CustomEvent("pdb-nav-rail-change",{detail:{rail:on}}));}catch(e){}';
        echo 'var u=new URL(location.href);u.searchParams.set("nav_collapsed",on?"1":"0");';
        echo 'fetch(u.pathname+"?"+u.searchParams.toString(),{credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}}).catch(function(){});';
        echo '});})();</script>';
    };

    $pic = (string) ($ctx['user_picture'] ?? '');
    $ini = (string) ($ctx['user_initials'] ?? '?');
    $ini_bg = (string) ($ctx['user_initials_bg'] ?? '#09141F');
    $ini_fg = (string) ($ctx['user_initials_fg'] ?? '#ffffff');
    $glpi_home = (string) ($ctx['glpi_home'] ?? (($ctx['root_doc'] ?? '') . '/front/central.php'));
    $user_menu_lbl = __('User menu', 'paineldebordo');
    $back_glpi_lbl = __('Back to GLPI', 'paineldebordo');
    $nav_is_top = ($nav_layout === 'top');
    $theme_is_dark = ($theme === 'dark');
    $nav_toggle_url = $qs_nav($nav_is_top ? 'side' : 'top');
    $theme_toggle_url = $qs_theme($theme_is_dark ? 'light' : 'dark');

    $user_menu_html = static function () use (
        $ctx, $pic, $ini, $ini_bg, $ini_fg, $glpi_home, $user_menu_lbl, $back_glpi_lbl,
        $nav_is_top, $theme_is_dark, $nav_toggle_url, $theme_toggle_url
    ): void {
        echo '<div class="ho-user-menu" id="ho_user_menu">';
        echo '<button type="button" class="ho-user-menu__trigger ho-tip" id="ho_user_menu_btn" aria-expanded="false" aria-haspopup="true" aria-controls="ho_user_menu_panel" data-tip="' . htmlspecialchars($user_menu_lbl . ': ' . (string) $ctx['user_name']) . '" aria-label="' . htmlspecialchars($user_menu_lbl . ': ' . (string) $ctx['user_name']) . '">';
        echo '<span class="ho-avatar"' . ($pic === '' ? ' style="background-color:' . htmlspecialchars($ini_bg) . ';color:' . htmlspecialchars($ini_fg) . '"' : '') . '>';
        if ($pic !== '') {
            echo '<img src="' . htmlspecialchars($pic) . '" alt="" width="28" height="28" decoding="async">';
        } else {
            echo '<span class="ho-avatar__initials">' . htmlspecialchars($ini) . '</span>';
        }
        echo '</span>';
        echo '<span class="ho-user-menu__chevron" aria-hidden="true">' . plugin_paineldebordo_icon('chevron_down') . '</span>';
        echo '</button>';
        echo '<div class="ho-user-menu__panel" id="ho_user_menu_panel" hidden role="menu">';
        echo '<div class="ho-user-menu__section">';
        echo '<div class="ho-user-menu__section-label">' . htmlspecialchars(__('User', 'paineldebordo')) . '</div>';
        echo '<div class="ho-user-menu__name" role="presentation">';
        echo plugin_paineldebordo_icon('user');
        echo '<span>' . htmlspecialchars((string) $ctx['user_name']) . '</span>';
        echo '</div>';
        echo '<a class="ho-user-menu__item" role="menuitem" href="' . htmlspecialchars($glpi_home) . '">';
        echo plugin_paineldebordo_icon('glpi_home');
        echo '<span>' . htmlspecialchars($back_glpi_lbl) . '</span>';
        echo '</a>';
        echo '</div>';
        echo '<div class="ho-user-menu__section">';
        echo '<div class="ho-user-menu__section-label">' . htmlspecialchars(__('Preferences', 'paineldebordo')) . '</div>';
        echo '<a class="ho-user-menu__item ho-user-menu__pref' . ($nav_is_top ? ' is-on' : '') . '" role="menuitem" href="' . htmlspecialchars($nav_toggle_url) . '" aria-checked="' . ($nav_is_top ? 'true' : 'false') . '">';
        echo '<span class="ho-user-menu__pref-main">';
        echo plugin_paineldebordo_icon($nav_is_top ? 'layout_top' : 'layout_side');
        echo '<span>' . htmlspecialchars(__('Top menu', 'paineldebordo')) . '</span>';
        echo '</span>';
        echo '<span class="ho-switch' . ($nav_is_top ? ' is-on' : '') . '" aria-hidden="true"><span class="ho-switch__thumb"></span></span>';
        echo '</a>';
        echo '<a class="ho-user-menu__item ho-user-menu__pref' . ($theme_is_dark ? ' is-on' : '') . '" role="menuitem" href="' . htmlspecialchars($theme_toggle_url) . '" aria-checked="' . ($theme_is_dark ? 'true' : 'false') . '">';
        echo '<span class="ho-user-menu__pref-main">';
        echo plugin_paineldebordo_icon($theme_is_dark ? 'moon' : 'sun');
        echo '<span>' . htmlspecialchars(__('Dark mode', 'paineldebordo')) . '</span>';
        echo '</span>';
        echo '<span class="ho-switch' . ($theme_is_dark ? ' is-on' : '') . '" aria-hidden="true"><span class="ho-switch__thumb"></span></span>';
        echo '</a>';
        echo '</div>';
        echo '</div></div>';
    };

    if ($nav_layout === 'top') {
        echo '<header class="ho-topnav"><div class="ho-topnav__inner">';
        $brand_html();
        $nav_html();
        $user_menu_html();
        echo '</div></header>';
        echo '<div class="ho-main">';
    } else {
        echo '<aside class="ho-sider">';
        $brand_html();
        $nav_html();
        $rail_toggle();
        echo '</aside>';
        echo '<div class="ho-main">';
    }

    echo '<header class="ho-top">';
    echo '<h2 class="ho-top__title">' . htmlspecialchars($ctx['title']) . '</h2>';
    echo '<div class="ho-top__actions">';
    if (!$nav_is_top) {
        $user_menu_html();
    }
    echo '</div></header>';
    echo '<script>(function(){';
    echo 'var wrap=document.getElementById("ho_user_menu");';
    echo 'var btn=document.getElementById("ho_user_menu_btn");';
    echo 'var panel=document.getElementById("ho_user_menu_panel");';
    echo 'if(!wrap||!btn||!panel)return;';
    echo 'function setOpen(on){panel.hidden=!on;btn.setAttribute("aria-expanded",on?"true":"false");wrap.classList.toggle("is-open",on);}';
    echo 'btn.addEventListener("click",function(e){e.stopPropagation();setOpen(panel.hidden);});';
    echo 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))setOpen(false);});';
    echo 'document.addEventListener("keydown",function(e){if(e.key==="Escape")setOpen(false);});';
    echo '})();</script>';

    $show_filters = ($opts['show_filters'] ?? true) !== false
        && !in_array($page, ['config', 'setup', 'logs'], true);
    if ($show_filters) {
        echo '<form class="ho-filters" method="get" action="shell.php">';
        echo '<input type="hidden" name="page" value="' . htmlspecialchars($page) . '">';
        echo '<input type="hidden" name="theme" value="' . htmlspecialchars($theme) . '">';
        echo '<input type="hidden" name="nav_layout" value="' . htmlspecialchars($nav_layout) . '">';
        echo '<div class="ho-filters__period" role="group" aria-label="' . htmlspecialchars(__('Period', 'paineldebordo')) . '">';
        foreach (['today', '7d', 'month', 'ytd', 'all'] as $p) {
            $cls = 'ho-chip' . ($period === $p ? ' is-active' : '');
            echo '<a class="' . $cls . '" href="' . htmlspecialchars($period_url($p)) . '">' .
                htmlspecialchars(plugin_paineldebordo_period_label($p)) . '</a>';
        }
        echo '</div>';
        echo '<div class="ho-filters__group">';
        echo '<span class="ho-filters__group-label">' . htmlspecialchars(__('Group', 'paineldebordo')) . '</span>';
        echo '<select class="ho-select" name="sel_grp" onchange="this.form.submit()" aria-label="' .
            htmlspecialchars(__('Group', 'paineldebordo')) . '">';
        foreach ($ctx['group_options'] as $id => $label) {
            $sel = ((int) $id === (int) $sel_grp) ? ' selected' : '';
            echo '<option value="' . (int) $id . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="ho-filters__group">';
        echo '<span class="ho-filters__group-label">' . htmlspecialchars(__('Entity')) . '</span>';
        $ent_locked = !empty($ctx['entity_locked']);
        if ($ent_locked) {
            echo '<input type="hidden" name="sel_ent" value="' . (int) $sel_ent . '">';
            echo '<select class="ho-select" disabled aria-label="' .
                htmlspecialchars(__('Entity')) . '">';
        } else {
            echo '<select class="ho-select" name="sel_ent" onchange="this.form.submit()" aria-label="' .
                htmlspecialchars(__('Entity')) . '">';
        }
        foreach (($ctx['entity_options'] ?? []) as $id => $label) {
            $sel = ((int) $id === (int) $sel_ent) ? ' selected' : '';
            echo '<option value="' . (int) $id . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select></div></form>';
    }

    echo '<main class="ho-content page-body">';

    $GLOBALS['HO_LAYOUT'] = $ctx;
}

function plugin_paineldebordo_page_end(): void
{
    echo '</main></div></div>';
    echo '<script>' . plugin_paineldebordo_tip_bubble_js() . plugin_paineldebordo_menu_toggle_js() . '</script>';
    echo '</body></html>';
}

/**
 * JS for the shared .ho-menu dropdown: click a .ho-menu__trigger to toggle
 * its sibling .ho-menu__panel, closing any other open menu first; click
 * outside (or an item inside, or Escape) closes it. Shared by every shell
 * page (via plugin_paineldebordo_page_end()) and by public/tv.php, which
 * does not go through the shell chrome.
 */
function plugin_paineldebordo_menu_toggle_js(): string
{
    return <<<'JS'
(function(){
  function closeAll(except){
    document.querySelectorAll('.ho-menu__panel:not([hidden])').forEach(function(p){
      if (p === except) return;
      p.hidden = true;
      var m = p.closest('.ho-menu');
      var t = m && m.querySelector('.ho-menu__trigger');
      if (t) t.setAttribute('aria-expanded', 'false');
    });
  }
  document.addEventListener('click', function(ev){
    var trigger = ev.target.closest('.ho-menu__trigger');
    if (trigger) {
      var menu = trigger.closest('.ho-menu');
      var panel = menu && menu.querySelector('.ho-menu__panel');
      if (panel) {
        var willOpen = panel.hidden;
        closeAll(willOpen ? panel : null);
        panel.hidden = !willOpen;
        trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      }
      return;
    }
    if (ev.target.closest('.ho-menu__item')) { closeAll(null); return; }
    if (!ev.target.closest('.ho-menu__panel')) closeAll(null);
  });
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') closeAll(null);
  });
})();
JS;
}

/**
 * JS for the shared .ho-tip tooltip: a single body-level bubble, positioned
 * via getBoundingClientRect() on hover/focus of any [data-tip].ho-tip
 * element. Fixed positioning means it is never clipped by a scrollable
 * ancestor (unlike a per-element pseudo-element bubble would be) and always
 * renders above everything, including modals. Shared by every shell page
 * (via plugin_paineldebordo_page_end()) and by public/tv.php, which does not
 * go through the shell chrome.
 */
function plugin_paineldebordo_tip_bubble_js(): string
{
    return <<<'JS'
(function(){
  var bubble = null;
  function ensureBubble(){
    if (bubble) return bubble;
    bubble = document.createElement('div');
    bubble.className = 'ho-tip-bubble';
    bubble.setAttribute('role', 'tooltip');
    document.body.appendChild(bubble);
    return bubble;
  }
  function show(el){
    var text = el.getAttribute('data-tip');
    if (!text) return;
    var b = ensureBubble();
    b.textContent = text;
    b.classList.add('is-visible');
    var r = el.getBoundingClientRect();
    var bw = b.offsetWidth, bh = b.offsetHeight;
    var margin = 4;
    var placement = 'top';
    var top = r.top - bh - 7;
    if (top < margin) { top = r.bottom + 7; placement = 'bottom'; }
    var left = r.left + (r.width / 2) - (bw / 2);
    if (left < margin) left = margin;
    if (left + bw > window.innerWidth - margin) left = window.innerWidth - margin - bw;
    b.style.top = top + 'px';
    b.style.left = left + 'px';
    b.setAttribute('data-placement', placement);
    b.style.setProperty('--ho-tip-arrow-left', ((r.left + r.width / 2) - left) + 'px');
  }
  function hide(){
    if (bubble) bubble.classList.remove('is-visible');
  }
  document.addEventListener('mouseover', function(ev){
    var el = ev.target.closest && ev.target.closest('.ho-tip[data-tip]');
    if (el) show(el);
  });
  document.addEventListener('mouseout', function(ev){
    var el = ev.target.closest && ev.target.closest('.ho-tip[data-tip]');
    if (el) hide();
  });
  document.addEventListener('focusin', function(ev){
    var el = ev.target.closest && ev.target.closest('.ho-tip[data-tip]');
    if (el) show(el);
  });
  document.addEventListener('focusout', function(ev){
    var el = ev.target.closest && ev.target.closest('.ho-tip[data-tip]');
    if (el) hide();
  });
  document.addEventListener('scroll', hide, true);
})();
JS;
}
