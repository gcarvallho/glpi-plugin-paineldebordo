<?php

/**
 * GLPI CronTask entry point for the audit-log retention purge.
 *
 * The plugin also purges inline (throttled to 1×/hour on page load) — that
 * stays as a safety net, because the inline path only runs when somebody
 * actually opens the plugin. This cron makes retention hold even on an idle
 * instance, which is what a retention policy is supposed to guarantee.
 */
// CommonGLPI (not CommonDBTM): this class is only a cron entry point and has
// no table of its own — CommonDBTM would imply one.
class PluginPaineldebordoAudit extends CommonGLPI
{
    public static $rightname = 'plugin_paineldebordo';

    /**
     * Descriptions shown in Setup > Automatic actions.
     * @return array<string,string>
     */
    public static function cronInfo(string $name): array
    {
        switch ($name) {
            case 'auditpurge':
                return ['description' => __('Purge Painel de Bordo audit logs past retention', 'paineldebordo')];
        }
        return [];
    }

    /**
     * @param CronTask|null $task
     * @return int 1 = did something, 0 = nothing to do, -1 = error
     */
    public static function cronAuditpurge($task = null): int
    {
        $dir = defined('GLPI_ROOT') ? Plugin::getPhpDir('paineldebordo') : dirname(__DIR__);
        include_once $dir . '/inc/audit.inc.php';

        if (!function_exists('plugin_paineldebordo_audit_retention_days')) {
            return 0;
        }
        $days = plugin_paineldebordo_audit_retention_days();
        if ($days <= 0) {
            // 0 = inherit GLPI: nothing for us to purge.
            return 0;
        }
        $deleted = plugin_paineldebordo_audit_purge_now($days);
        if ($task !== null && method_exists($task, 'addVolume')) {
            $task->addVolume($deleted);
        }
        return $deleted > 0 ? 1 : 0;
    }
}
