<?php

use Glpi\Http\Firewall;
use Glpi\Http\SessionManager;

function plugin_init_paineldebordo()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['paineldebordo'] = true;
    $PLUGIN_HOOKS['config_page']['paineldebordo'] = 'public/config.php';
    $PLUGIN_HOOKS['change_profile']['paineldebordo'] = ['PluginPaineldebordoProfile', 'initProfile'];

    // Public TV pairing + token-authenticated AJAX (GLPI 11 Firewall).
    // Patterns match $plugin_resource (path inside plugin public root), e.g. /tv_pair.php
    $tv_public = [
        '#^/tv_pair\.php#',
        '#^/tv_pair_api\.php#',
        '#^/tv\.php#',
        '#^/favicon\.php#',
        '#^/ajax/tv_pair\.php#',
        '#^/ajax/tv_events\.php#',
        '#^/ajax/tv_board\.php#',
        '#^/ajax/tv_unpair\.php#',
        // Candidates that incorrectly keep /public in the URL path
        '#^/public/ajax/tv_pair\.php#',
        '#^/public/tv_pair_api\.php#',
        '#^/public/favicon\.php#',
    ];
    if (class_exists(Firewall::class)
        && method_exists(Firewall::class, 'addPluginStrategyForLegacyScripts')
        && defined(Firewall::class . '::STRATEGY_NO_CHECK')
    ) {
        try {
            foreach ($tv_public as $pattern) {
                Firewall::addPluginStrategyForLegacyScripts(
                    'paineldebordo',
                    $pattern,
                    Firewall::STRATEGY_NO_CHECK
                );
            }
        } catch (Throwable $e) {
            // Ignore older GLPI builds without this API surface
        }
    }
    // NO_CHECK skips login only — CSRF still applies unless path is stateless.
    // Display TV must create/poll codes without a CSRF token.
    if (class_exists(SessionManager::class)
        && method_exists(SessionManager::class, 'registerPluginStatelessPath')
    ) {
        try {
            foreach ($tv_public as $pattern) {
                SessionManager::registerPluginStatelessPath('paineldebordo', $pattern);
            }
        } catch (Throwable $e) {
            // Ignore
        }
    }

    Plugin::registerClass('PluginPaineldebordoProfile', [
        'addtabon' => ['Profile'],
    ]);

    // Audit retention cron (Setup > Automatic actions). Registered class only —
    // the task row itself is created during install.
    include_once __DIR__ . '/inc/audit.class.php';
    Plugin::registerClass('PluginPaineldebordoAudit');

    $isCentralInterface = isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] === 'central';

    // Session sync only (cheap). Do NOT migrate all profiles or createFirstAccess here:
    // that hung Config → Plugins on first Disable (migrate held DB/session while unactivate ran).
    // Full ACL seed/migrate runs in hook.php install/upgrade; bootstrap in public/index.php.
    include_once __DIR__ . '/inc/profile.class.php';
    include_once __DIR__ . '/inc/access.inc.php';
    if ($isCentralInterface && isset($_SESSION['glpiactiveprofile']['id'])) {
        try {
            PluginPaineldebordoProfile::initProfile();
        } catch (Throwable $e) {
            // Keep plugin loadable
        }
    }

    if ($isCentralInterface && Session::haveRight('plugin_paineldebordo', READ)) {
        $PLUGIN_HOOKS['redefine_menus']['paineldebordo'] = 'plugin_paineldebordo_redefine_menus';
    }

    if ($isCentralInterface && function_exists('plugin_paineldebordo_canModule')
        && plugin_paineldebordo_canModule('resources', UPDATE)
    ) {
        Plugin::registerClass('PluginPaineldebordoConfig', [
            'addtabon' => ['Entity'],
        ]);
    } elseif ($isCentralInterface && Session::haveRight('plugin_paineldebordo', UPDATE)
        && !isset($_SESSION['glpiactiveprofile']['plugin_paineldebordo_resources'])
    ) {
        // Legacy before migrate: keep Entity map tab for master UPDATE
        Plugin::registerClass('PluginPaineldebordoConfig', [
            'addtabon' => ['Entity'],
        ]);
    }
}


function plugin_version_paineldebordo()
{
    return [
        'name'            => __('Painel de Bordo', 'paineldebordo'),
        'version'         => '2.34.1',
        'author'          => '<a href="https://wa.me/5591985390491" target="_blank" rel="noopener">gcarvallho.dev</a> / <a href="https://inovareempreendimentos.com.br" target="_blank" rel="noopener">Inovare</a> / Stevenes Donato',
        'license'         => 'GPLv2+',
        'homepage'        => 'https://inovareempreendimentos.com.br',
        'minGlpiVersion'  => '11.0',
    ];
}


function plugin_paineldebordo_check_prerequisites()
{
    $errors = [];

    if (!defined('GLPI_VERSION') || version_compare(GLPI_VERSION, '11.0', '<')) {
        $errors[] = sprintf(
            __('Requires GLPI >= 11.0 (detected: %s).', 'paineldebordo'),
            defined('GLPI_VERSION') ? GLPI_VERSION : '?'
        );
    }

    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        $errors[] = sprintf(
            __('Requires PHP >= 8.1 (detected: %s).', 'paineldebordo'),
            PHP_VERSION
        );
    }

    if (!extension_loaded('json')) {
        $errors[] = __('PHP extension missing: json', 'paineldebordo');
    }

    if (!extension_loaded('mysqli') && !extension_loaded('pdo_mysql')) {
        $errors[] = __('PHP extension missing: mysqli or pdo_mysql', 'paineldebordo');
    }

    if ($errors) {
        echo implode('<br>', array_map('htmlspecialchars', $errors));
        return false;
    }

    return true;
}


function plugin_paineldebordo_check_config($verbose = false)
{
    global $DB;

    $required = [
        'glpi_plugin_paineldebordo_count',
        'glpi_plugin_paineldebordo_map',
        'glpi_plugin_paineldebordo_config',
        'glpi_plugin_paineldebordo_tv_devices',
    ];

    $missing = [];
    if (isset($DB) && method_exists($DB, 'TableExists')) {
        foreach ($required as $table) {
            if (!$DB->TableExists($table)) {
                $missing[] = $table;
            }
        }
    }

    if ($missing) {
        if ($verbose) {
            echo sprintf(
                __('Painel de Bordo is not fully installed. Missing table(s): %s. Reinstall or update the plugin.', 'paineldebordo'),
                htmlspecialchars(implode(', ', $missing))
            );
        }
        return false;
    }

    if ($verbose) {
        echo __('Painel de Bordo is installed and ready.', 'paineldebordo');
    }

    return true;
}
