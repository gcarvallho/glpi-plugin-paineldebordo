<?php

/**
 * Pure helpers for TV / wallboard mode (testable without GLPI).
 */

/**
 * Event types supported by Modo TV.
 *
 * @return array<string, array{label:string,priority:int,color:string,freq:int}>
 */
function plugin_paineldebordo_tv_event_catalog(): array
{
    // Labels via __() when GLPI i18n is loaded; plain English fallback for unit tests.
    $t = static function (string $s): string {
        return function_exists('__') ? __($s, 'paineldebordo') : $s;
    };
    return [
        'solucao_negada' => [
            'label'    => $t('Solution refused'),
            'priority' => 100,
            'color'    => '#E73E11',
            'freq'     => 880,
        ],
        'solucao_aceita' => [
            'label'    => $t('Solution accepted'),
            'priority' => 95,
            'color'    => '#2f9e44',
            'freq'     => 820,
        ],
        'reabertura' => [
            'label'    => $t('Reopened'),
            'priority' => 90,
            'color'    => '#E73E11',
            'freq'     => 740,
        ],
        'novo' => [
            'label'    => $t('New ticket'),
            'priority' => 80,
            'color'    => '#E73E11',
            'freq'     => 660,
        ],
        'sla_atrasado' => [
            'label'    => $t('SLA late'),
            'priority' => 70,
            'color'    => '#E73E11',
            'freq'     => 520,
        ],
        'atualizacao' => [
            'label'    => $t('Update'),
            'priority' => 40,
            'color'    => '#09141F',
            'freq'     => 440,
        ],
    ];
}

/**
 * Short status labels for TV columns (wallboard density).
 */
function plugin_paineldebordo_tv_status_label(int $status): string
{
    $t = static function (string $s): string {
        return function_exists('__') ? __($s, 'paineldebordo') : $s;
    };
    $map = [
        1 => $t('New'),
        2 => $t('In progress'),
        3 => $t('Planned'),
        4 => $t('Pending'),
        5 => $t('Solved'),
        6 => $t('Closed'),
    ];
    return $map[$status] ?? (string) $status;
}

/**
 * Label for ticket validation column (global_validation WAITING).
 */
function plugin_paineldebordo_tv_validation_label(): string
{
    return function_exists('__') ? __('Validation', 'paineldebordo') : 'Validation';
}

/**
 * Label for solution-approved wallboard column (requester accepted).
 */
function plugin_paineldebordo_tv_solution_label(): string
{
    return function_exists('__') ? __('Approved', 'paineldebordo') : 'Approved';
}

/**
 * @deprecated Use plugin_paineldebordo_tv_solution_label()
 */
function plugin_paineldebordo_tv_approval_label(): string
{
    return plugin_paineldebordo_tv_solution_label();
}

/**
 * Display name expression for a user alias (firstname+realname, fallback login).
 */
function plugin_paineldebordo_tv_user_display_sql(string $userAlias): string
{
    return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE({$userAlias}.firstname,''), ' ', COALESCE({$userAlias}.realname,''))), ''), {$userAlias}.name)";
}

/**
 * SQL scalar expression: observer names (users + groups, type=3), up to 3.
 *
 * MySQL/MariaDB reject outer refs like `glpi_tickets.id` inside a FROM-derived
 * table (error 1054). Keep correlation only in simple scalar subqueries.
 */
function plugin_paineldebordo_tv_observers_sql(string $ticketTable = 'glpi_tickets'): string
{
    $userDisp = plugin_paineldebordo_tv_user_display_sql('uo');
    $users = "(SELECT GROUP_CONCAT({$userDisp} ORDER BY tuo.id ASC SEPARATOR ', ')
 FROM glpi_tickets_users tuo
 INNER JOIN glpi_users uo ON uo.id = tuo.users_id
 WHERE tuo.tickets_id = {$ticketTable}.id AND tuo.type = 3)";
    $groups = "(SELECT GROUP_CONCAT(g.name ORDER BY gt.id ASC SEPARATOR ', ')
 FROM glpi_groups_tickets gt
 INNER JOIN glpi_groups g ON g.id = gt.groups_id
 WHERE gt.tickets_id = {$ticketTable}.id AND gt.type = 3)";
    return "SUBSTRING_INDEX(TRIM(BOTH ', ' FROM CONCAT_WS(', ', {$users}, {$groups})), ', ', 3)";
}

/**
 * Tickets waiting for ticket validation (global_validation WAITING=2), not solved/closed.
 */
function plugin_paineldebordo_tv_validation_waiting_sql(string $ticketTable = 'glpi_tickets'): string
{
    return "{$ticketTable}.global_validation = 2
 AND {$ticketTable}.status NOT IN (5, 6)";
}

/**
 * Tickets whose requester approved the technician solution (ITILSolution ACCEPTED=3).
 */
function plugin_paineldebordo_tv_solution_approved_sql(string $ticketTable = 'glpi_tickets'): string
{
    return "EXISTS (
   SELECT 1 FROM glpi_itilsolutions s_ap
   WHERE s_ap.itemtype = 'Ticket'
     AND s_ap.items_id = {$ticketTable}.id
     AND s_ap.status = 3
 )";
}

/**
 * @deprecated Use plugin_paineldebordo_tv_solution_approved_sql() — column now lists approved solutions.
 */
function plugin_paineldebordo_tv_solution_waiting_sql(string $ticketTable = 'glpi_tickets'): string
{
    return plugin_paineldebordo_tv_solution_approved_sql($ticketTable);
}

/**
 * @deprecated Use plugin_paineldebordo_tv_solution_approved_sql()
 */
function plugin_paineldebordo_tv_waiting_approval_sql(string $ticketTable = 'glpi_tickets'): string
{
    return plugin_paineldebordo_tv_solution_approved_sql($ticketTable);
}

/**
 * Short open-date label for TV cards (d/m H:i).
 */
function plugin_paineldebordo_tv_date_label(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    return date('d/m H:i', $ts);
}

/**
 * Relative age label for TV (Nm / Nh / Nd).
 */
function plugin_paineldebordo_tv_age_label(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return '';
    }
    $mins = (int) floor((time() - $ts) / 60);
    if ($mins < 60) {
        return $mins . 'm';
    }
    $hours = (int) floor($mins / 60);
    if ($hours < 48) {
        return $hours . 'h';
    }
    return (int) floor($hours / 24) . 'd';
}

/**
 * Map a TV queue SQL row to a card payload.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function plugin_paineldebordo_tv_ticket_card(array $row): array
{
    $ttr = $row['time_to_resolve'] ?? null;
    $is_late = $ttr !== null && $ttr !== '' && strtotime((string) $ttr) < time();
    $tech = trim((string) ($row['tech_name'] ?? ''));
    $observers = trim((string) ($row['observers'] ?? ''));
    $date = (string) ($row['date'] ?? '');
    $date_mod = (string) ($row['date_mod'] ?? '');
    $prio = (int) ($row['priority'] ?? 0);
    return [
        'id'             => (int) ($row['id'] ?? 0),
        'title'          => (string) ($row['name'] ?? ''),
        'status'         => (int) ($row['status'] ?? 0),
        'priority'       => $prio,
        'prio'           => function_exists('plugin_paineldebordo_priority_label')
            ? plugin_paineldebordo_priority_label($prio)
            : (string) $prio,
        'date'           => $date,
        'date_label'     => plugin_paineldebordo_tv_date_label($date),
        'date_mod'       => $date_mod,
        'date_mod_label' => $date_mod !== '' ? plugin_paineldebordo_tv_date_label($date_mod) : '',
        'late'           => $is_late,
        'age'            => plugin_paineldebordo_tv_age_label($date),
        'group'          => (string) ($row['group_name'] ?? ''),
        'tech'           => $tech,
        'observers'      => $observers,
        'requester'      => trim((string) ($row['requester_name'] ?? '')),
        'category'       => trim((string) ($row['category_name'] ?? '')),
        'description'    => plugin_paineldebordo_tv_description_preview((string) ($row['content'] ?? '')),
    ];
}

/**
 * HTML preview of a ticket's description — keeps a minimal safe subset of
 * structural tags (headings turned into bold lines, line/paragraph breaks,
 * emphasis) so form-builder tickets (section titles, numbered questions,
 * answers) stay visually distinguishable instead of collapsing into one
 * run-on wall of plain text. Falls back to plain text, capped as a safety
 * net, only for pathological (e.g. pasted log dump) descriptions.
 */
function plugin_paineldebordo_tv_description_preview(string $html, int $max = 5000): string
{
    // Section headings become their own bold line — otherwise a heading and
    // the line right after it (often a question) visually run together.
    $html = preg_replace('/<h[1-6][^>]*>/i', '<br><strong>', $html) ?? $html;
    $html = preg_replace('/<\/h[1-6]>/i', '</strong><br>', $html) ?? $html;
    // Keep paragraph/list breaks so a long description doesn't read as one
    // run-on wall of text.
    $html = preg_replace('/<(br\s*\/?|\/p|\/div|\/li)>/i', '<br>', $html) ?? $html;

    // Whitelist a minimal safe subset; strip_tags() leaves attributes on
    // allowed tags intact, so strip those separately (defense in depth —
    // no href/onclick/style survives even though the source is GLPI's own
    // sanitized ticket content).
    $safe = strip_tags($html, '<br><strong><b><em><i>');
    $safe = preg_replace('/<(\/?)(br|strong|b|em|i)\b[^>]*>/i', '<$1$2>', $safe) ?? $safe;
    $safe = preg_replace('/(?:<br>\s*){3,}/i', '<br><br>', $safe) ?? $safe;
    $safe = trim($safe);
    $safe = preg_replace('/^(?:<br>\s*)+|(?:<br>\s*)+$/i', '', $safe) ?? $safe;
    $safe = trim($safe);
    if ($safe === '') {
        return '';
    }

    $plain = html_entity_decode(strip_tags($safe), ENT_QUOTES, 'UTF-8');
    if (mb_strlen($plain) > $max) {
        // Pathological input: drop structure and hard-truncate as plain text
        // rather than risk cutting a tag in half.
        $plain = preg_replace('/[ \t]+/', ' ', $plain) ?? $plain;
        return mb_substr(trim($plain), 0, $max) . '…';
    }
    return $safe;
}

/**
 * SQL scalar subquery: first assigned group name for a ticket (type=2).
 */
function plugin_paineldebordo_tv_group_name_sql(string $ticketTable = 'glpi_tickets'): string
{
    return "(SELECT g.name FROM glpi_groups_tickets gt_tv
 INNER JOIN glpi_groups g ON g.id = gt_tv.groups_id
 WHERE gt_tv.tickets_id = {$ticketTable}.id AND gt_tv.type = 2
 ORDER BY gt_tv.id ASC LIMIT 1)";
}

/**
 * SQL scalar subquery: ticket category name.
 */
function plugin_paineldebordo_tv_category_name_sql(string $ticketTable = 'glpi_tickets'): string
{
    return "(SELECT c.name FROM glpi_itilcategories c
 WHERE c.id = {$ticketTable}.itilcategories_id)";
}

/**
 * SQL scalar subquery: first assigned technician name (tickets_users type=2 ASSIGN).
 */
function plugin_paineldebordo_tv_tech_name_sql(string $ticketTable = 'glpi_tickets'): string
{
    $disp = plugin_paineldebordo_tv_user_display_sql('u');
    return "(SELECT {$disp}
 FROM glpi_tickets_users tu_tv
 INNER JOIN glpi_users u ON u.id = tu_tv.users_id
 WHERE tu_tv.tickets_id = {$ticketTable}.id AND tu_tv.type = 2
 ORDER BY tu_tv.id ASC LIMIT 1)";
}

/**
 * SQL scalar subquery: ticket requester name (tickets_users type=1 REQUESTER).
 * Falls back to the recipient's display name when no requester actor row exists
 * (tickets opened on behalf of someone, or via email with no linked user).
 */
function plugin_paineldebordo_tv_requester_name_sql(string $ticketTable = 'glpi_tickets'): string
{
    $disp = plugin_paineldebordo_tv_user_display_sql('ur');
    $dispRec = plugin_paineldebordo_tv_user_display_sql('urec');
    $requester = "(SELECT {$disp}
 FROM glpi_tickets_users tu_req
 INNER JOIN glpi_users ur ON ur.id = tu_req.users_id
 WHERE tu_req.tickets_id = {$ticketTable}.id AND tu_req.type = 1
 ORDER BY tu_req.id ASC LIMIT 1)";
    $recipient = "(SELECT {$dispRec}
 FROM glpi_users urec
 WHERE urec.id = {$ticketTable}.users_id_recipient)";
    return "COALESCE(NULLIF({$requester}, ''), NULLIF({$recipient}, ''))";
}

/**
 * Event types that should raise a wallboard toast (not updates).
 *
 * @return list<string>
 */
function plugin_paineldebordo_tv_toast_types(): array
{
    return ['novo', 'solucao_aceita', 'solucao_negada', 'reabertura', 'sla_atrasado'];
}

function plugin_paineldebordo_tv_event_priority(string $type): int
{
    $catalog = plugin_paineldebordo_tv_event_catalog();
    return $catalog[$type]['priority'] ?? 0;
}

/**
 * @param array<int, array{type?:string,ts?:string}> $events
 * @return array<int, array>
 */
function plugin_paineldebordo_tv_sort_events(array $events): array
{
    usort($events, static function ($a, $b) {
        $pa = plugin_paineldebordo_tv_event_priority((string) ($a['type'] ?? ''));
        $pb = plugin_paineldebordo_tv_event_priority((string) ($b['type'] ?? ''));
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }
        return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? ''));
    });
    return array_values($events);
}

/**
 * Normalize "since" timestamp for polling. Returns Y-m-d H:i:s.
 * First load defaults to last 10 minutes (seed).
 */
function plugin_paineldebordo_tv_validate_since(?string $since): string
{
    if ($since === null || $since === '') {
        return date('Y-m-d H:i:s', time() - 600);
    }
    $since = trim($since);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $since);
    if ($dt instanceof DateTime && $dt->format('Y-m-d H:i:s') === $since) {
        $min = new DateTime('-1 hour');
        if ($dt < $min) {
            return $min->format('Y-m-d H:i:s');
        }
        return $since;
    }
    return date('Y-m-d H:i:s', time() - 600);
}

/**
 * Build SQL-safe entity IN list from int IDs.
 *
 * @param int[] $entity_ids
 */
function plugin_paineldebordo_tv_entity_in(array $entity_ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $entity_ids), static fn($id) => $id >= 0)));
    if ($ids === []) {
        return '0';
    }
    return implode(',', $ids);
}

/**
 * @param int[] $group_ids empty = no group filter fragment
 */
function plugin_paineldebordo_tv_group_in(array $group_ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $group_ids), static fn($id) => $id > 0)));
    if ($ids === []) {
        return '';
    }
    return implode(',', $ids);
}

/**
 * Classify a ticket status transition into reopen or empty.
 */
function plugin_paineldebordo_tv_classify_status_change(int $old_status, int $new_status): string
{
    // GLPI: 5=solved, 6=closed; reopen when leaving those to an open status
    $closed = [5, 6];
    if (in_array($old_status, $closed, true) && !in_array($new_status, $closed, true)) {
        return 'reabertura';
    }
    return '';
}
