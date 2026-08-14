<?php

/**
 * Process Configuration hub POST (before HTML). Returns flash message.
 *
 * @return array{msg:string,ok:bool}
 */
function plugin_paineldebordo_config_hub_process_post(): array
{
    global $DB;

    $msg = '';
    $msg_ok = true;

    // Audit helper — logs sensitive config actions (no-op if audit not loaded).
    $audit = static function (string $action, string $detail = ''): void {
        if (function_exists('plugin_paineldebordo_audit_log')) {
            plugin_paineldebordo_audit_log($action, $detail, 'config');
        }
    };

    if (isset($_POST['entity']) || isset($_POST['scope_save'])) {
        $ents = $_POST['entity'] ?? [];
        if (is_array($ents)) {
            $ents = implode(',', array_map('intval', $ents));
        } else {
            $ents = preg_replace('/[^0-9,]/', '', (string) $ents);
        }
        $prev_ents = (string) plugin_paineldebordo_getConfigValue('entity', '');
        plugin_paineldebordo_setConfigValue('entity', $ents);
        $msg = __('Saved', 'paineldebordo');
        $audit('config_change', 'scope: entities ' . ($prev_ents === '' ? '(all)' : $prev_ents)
            . ' -> ' . ($ents === '' ? '(all)' : $ents));
    }
    if (isset($_POST['tv_pair_code'])) {
        $code = trim((string) $_POST['tv_pair_code']);
        try {
            $ok = plugin_paineldebordo_tv_pair_approve($code);
            $msg = $ok
                ? __('TV linked successfully', 'paineldebordo')
                : __('Invalid or expired code', 'paineldebordo');
            $msg_ok = $ok;
            if ($ok) {
                $audit('tv_pair_approve', $code);
            }
        } catch (Throwable $e) {
            $msg = __('TV link failed', 'paineldebordo') . ': ' . $e->getMessage();
            $msg_ok = false;
        }
    }
    if (isset($_POST['tv_revoke_id'])) {
        plugin_paineldebordo_tv_pair_revoke((int) $_POST['tv_revoke_id']);
        $msg = __('Device revoked', 'paineldebordo');
        $audit('tv_device_revoke', 'revoked #' . (int) $_POST['tv_revoke_id']);
    }
    if (isset($_POST['tv_delete_id'])) {
        $ok = plugin_paineldebordo_tv_pair_delete((int) $_POST['tv_delete_id']);
        $msg = $ok
            ? __('Device deleted', 'paineldebordo')
            : __('You do not have permission for Painel de Bordo.', 'paineldebordo');
        $msg_ok = $ok;
        if ($ok) {
            $audit('tv_device_revoke', 'deleted #' . (int) $_POST['tv_delete_id']);
        }
    }
    if (isset($_POST['tv_nickname_id'])) {
        $ok = plugin_paineldebordo_tv_pair_set_nickname(
            (int) $_POST['tv_nickname_id'],
            (string) ($_POST['tv_nickname'] ?? '')
        );
        $msg = $ok ? __('Nickname saved', 'paineldebordo') : __('Save failed', 'paineldebordo');
        $msg_ok = $ok;
    }
    if (isset($_POST['tv_ttl_save'])) {
        $prev_ttl = plugin_paineldebordo_tv_pair_ttl_seconds();
        $new_ttl = (int) ($_POST['tv_pair_ttl'] ?? 300);
        plugin_paineldebordo_tv_pair_set_ttl($new_ttl);
        $msg = __('Saved', 'paineldebordo');
        $audit('config_change', 'tv_ttl: ' . $prev_ttl . 's -> ' . plugin_paineldebordo_tv_pair_ttl_seconds() . 's');
    }
    if (isset($_POST['branding_reset'])) {
        plugin_paineldebordo_branding_reset();
        $msg = __('Branding restored', 'paineldebordo');
        $audit('config_change', 'branding_reset');
    } elseif (isset($_POST['branding_save'])) {
        $current = plugin_paineldebordo_branding_get();
        $logo_url = (string) ($_POST['brand_logo_url'] ?? '');
        $logo_collapsed_url = (string) ($_POST['brand_logo_collapsed_url'] ?? '');
        $upload_err = null;
        $upload_err2 = null;

        if ((string) ($_POST['brand_logo_source'] ?? 'url') === 'file') {
            $uploaded = plugin_paineldebordo_branding_handle_upload('brand_logo_file', 'logo', $upload_err);
            if ($uploaded !== null) {
                $logo_url = $uploaded;
            } elseif ($upload_err === null) {
                // "File" mode selected but nothing new chosen -> keep the previously saved logo
                $logo_url = (string) ($current['logo_url'] ?? '');
            }
        }
        if ((string) ($_POST['brand_logo_collapsed_source'] ?? 'url') === 'file') {
            $uploaded2 = plugin_paineldebordo_branding_handle_upload('brand_logo_collapsed_file', 'logo_collapsed', $upload_err2);
            if ($uploaded2 !== null) {
                $logo_collapsed_url = $uploaded2;
            } elseif ($upload_err2 === null) {
                $logo_collapsed_url = (string) ($current['logo_collapsed_url'] ?? '');
            }
        }

        $branding_new = [
            'primary'            => (string) ($_POST['brand_primary'] ?? ''),
            'accent'             => (string) ($_POST['brand_accent'] ?? ''),
            'favicon'            => (string) ($_POST['brand_favicon'] ?? ''),
            'bg'                 => (string) ($_POST['brand_bg'] ?? ''),
            'surface'            => (string) ($_POST['brand_surface'] ?? ''),
            'text'               => (string) ($_POST['brand_text'] ?? ''),
            'primary_dark'       => (string) ($_POST['brand_primary_dark'] ?? ''),
            'bg_dark'            => (string) ($_POST['brand_bg_dark'] ?? ''),
            'surface_dark'       => (string) ($_POST['brand_surface_dark'] ?? ''),
            'text_dark'          => (string) ($_POST['brand_text_dark'] ?? ''),
            'logo_url'           => $logo_url,
            'logo_collapsed_url' => $logo_collapsed_url,
            'eyebrow'            => (string) ($_POST['brand_eyebrow'] ?? ''),
            'product_name'       => (string) ($_POST['brand_product_name'] ?? ''),
        ];
        $ok = plugin_paineldebordo_branding_set($branding_new);
        $upload_err = $upload_err ?? $upload_err2;
        $msg = $upload_err ?? ($ok ? __('Saved', 'paineldebordo') : __('Save failed', 'paineldebordo'));
        $msg_ok = $ok && $upload_err === null;
        if ($msg_ok) {
            // Name only the fields that actually changed — "branding" alone
            // proves someone touched it, not what they touched.
            $after = plugin_paineldebordo_branding_get();
            $touched = [];
            foreach ($branding_new as $k => $v) {
                $before_v = (string) ($current[$k] ?? '');
                $after_v = (string) ($after[$k] ?? '');
                if ($before_v !== $after_v) {
                    $touched[] = $k . ': ' . ($before_v === '' ? '(empty)' : $before_v)
                        . ' -> ' . ($after_v === '' ? '(empty)' : $after_v);
                }
            }
            $audit(
                'config_change',
                $touched ? 'branding — ' . implode('; ', $touched) : 'branding (no change)'
            );
        }
    }
    if (isset($_POST['purge_orphan_keys'])) {
        $uid = (int) ($_SESSION['glpiID'] ?? 0);
        $orphans = ['layout', 'metric', 'filter_status'];
        foreach ($orphans as $k) {
            $DB->doQuery(
                "DELETE FROM glpi_plugin_paineldebordo_config
                 WHERE name = '" . $DB->escape($k) . "' AND users_id = '$uid'"
            );
        }
        $msg = __('Orphan config keys purged', 'paineldebordo');
        $audit('config_change', 'purge_orphans');
    }

    return ['msg' => $msg, 'ok' => $msg_ok];
}
