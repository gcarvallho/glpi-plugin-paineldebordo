<?php
/**
 * Shared tickets list: filters + sortable table.
 *
 * Expects: $data, $ticket_base, $status_map, $list_title, $list_page
 * Optional: $list_extra (array of hidden GET fields), $filters
 */
$opts = $data['opts'] ?? plugin_paineldebordo_tickets_list_query_opts();
$filters = $filters ?? (function_exists('plugin_paineldebordo_getFilters') ? plugin_paineldebordo_getFilters() : []);
$list_extra = $list_extra ?? [];
$status_map = $status_map ?? (class_exists('Ticket') ? Ticket::getAllStatusArray() : []);

$base_q = array_merge(
    [
        'page'   => $list_page,
        'theme'  => $filters['theme'] ?? 'light',
        'period' => $filters['period'] ?? 'month',
        'q'      => $opts['q'],
        'status' => $opts['status'] !== null ? (string) $opts['status'] : '',
        'priority' => $opts['priority'] !== null ? (string) $opts['priority'] : '',
        'late'   => $opts['late'] ?? '',
        'view'   => $opts['view'] ?? '',
    ],
    $list_extra
);

$sort_href = static function (string $col) use ($base_q, $opts): string {
    $next = $base_q;
    $next['sort'] = $col;
    $cur = $opts['sort'] ?? null;
    $dir = 'asc';
    if ($cur === $col && ($opts['dir'] ?? 'asc') === 'asc') {
        $dir = 'desc';
    }
    $next['dir'] = $dir;
    return 'shell.php?' . http_build_query(array_filter($next, static fn($v) => $v !== '' && $v !== null));
};

$sort_mark = static function (string $col) use ($opts): string {
    if (($opts['sort'] ?? null) !== $col) {
        return '';
    }
    return ($opts['dir'] ?? 'asc') === 'desc' ? ' ↓' : ' ↑';
};

$prio_opts = [1, 2, 3, 4, 5, 6];
?>
<div class="card" style="margin-bottom:0.75rem;">
  <div class="card-body" style="padding:0.75rem 1rem;">
    <form method="get" action="shell.php" class="ho-form-row" id="tk_filter_form" style="margin-bottom:0;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;">
      <input type="hidden" name="page" value="<?php echo htmlspecialchars($list_page); ?>">
      <input type="hidden" name="theme" value="<?php echo htmlspecialchars((string) ($filters['theme'] ?? 'light')); ?>">
      <input type="hidden" name="period" value="<?php echo htmlspecialchars((string) ($filters['period'] ?? 'month')); ?>">
      <?php if (!empty($opts['sort'])) { ?>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string) $opts['sort']); ?>">
        <input type="hidden" name="dir" value="<?php echo htmlspecialchars((string) ($opts['dir'] ?? 'asc')); ?>">
      <?php } ?>
      <?php if (!empty($opts['view'])) { ?>
        <input type="hidden" name="view" value="<?php echo htmlspecialchars((string) $opts['view']); ?>">
      <?php } ?>
      <?php foreach ($list_extra as $ek => $ev) { ?>
        <input type="hidden" name="<?php echo htmlspecialchars((string) $ek); ?>" value="<?php echo htmlspecialchars((string) $ev); ?>">
      <?php } ?>
      <label class="ho-field" style="flex:1 1 12rem;min-width:10rem;">
        <span class="ho-field__label"><?php echo __('Search', 'paineldebordo'); ?></span>
        <input class="form-control" type="search" name="q" value="<?php echo htmlspecialchars($opts['q']); ?>" placeholder="# / <?php echo htmlspecialchars(__('Title')); ?>" autocomplete="off">
      </label>
      <label class="ho-field">
        <span class="ho-field__label"><?php echo __('Status'); ?></span>
        <select class="ho-select" name="status">
          <option value=""><?php echo __('All', 'paineldebordo'); ?></option>
          <?php foreach ($status_map as $sid => $slabel) { ?>
            <option value="<?php echo (int) $sid; ?>" <?php echo $opts['status'] === (int) $sid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $slabel); ?></option>
          <?php } ?>
        </select>
      </label>
      <label class="ho-field">
        <span class="ho-field__label"><?php echo __('Priority'); ?></span>
        <select class="ho-select" name="priority">
          <option value=""><?php echo __('All', 'paineldebordo'); ?></option>
          <?php foreach ($prio_opts as $p) { ?>
            <option value="<?php echo $p; ?>" <?php echo $opts['priority'] === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars(plugin_paineldebordo_priority_label($p)); ?></option>
          <?php } ?>
        </select>
      </label>
      <label class="ho-field">
        <span class="ho-field__label"><?php echo __('SLA late', 'paineldebordo'); ?></span>
        <select class="ho-select" name="late">
          <option value=""><?php echo __('All', 'paineldebordo'); ?></option>
          <option value="1" <?php echo ($opts['late'] ?? '') === '1' ? 'selected' : ''; ?>><?php echo __('Yes'); ?></option>
          <option value="0" <?php echo ($opts['late'] ?? '') === '0' ? 'selected' : ''; ?>><?php echo __('No'); ?></option>
        </select>
      </label>
      <button type="submit" class="btn btn-sm btn-primary"><?php echo plugin_paineldebordo_icon('filter'); ?><span><?php echo __('Filter', 'paineldebordo'); ?></span></button>
      <button type="button" class="btn btn-sm btn-primary" id="tk_refresh"><?php echo plugin_paineldebordo_icon('refresh'); ?><span><?php echo __('Refresh'); ?></span></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header ho-card-head" style="justify-content:space-between;">
    <span><?php echo htmlspecialchars($list_title); ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table" id="tk_table">
      <thead>
        <tr>
          <th><a href="<?php echo htmlspecialchars($sort_href('id')); ?>">#<?php echo $sort_mark('id'); ?></a></th>
          <th><a href="<?php echo htmlspecialchars($sort_href('name')); ?>"><?php echo __('Title'); ?><?php echo $sort_mark('name'); ?></a></th>
          <th><a href="<?php echo htmlspecialchars($sort_href('status')); ?>"><?php echo __('Status'); ?><?php echo $sort_mark('status'); ?></a></th>
          <th><a href="<?php echo htmlspecialchars($sort_href('priority')); ?>"><?php echo __('Priority'); ?><?php echo $sort_mark('priority'); ?></a></th>
          <th><a href="<?php echo htmlspecialchars($sort_href('date')); ?>"><?php echo __('Opened', 'paineldebordo'); ?><?php echo $sort_mark('date'); ?></a></th>
          <th><a href="<?php echo htmlspecialchars($sort_href('late')); ?>">SLA<?php echo $sort_mark('late'); ?></a></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$data['rows']) {
          $empty_open_only = ($opts['status'] !== null && !in_array((int) $opts['status'], [5, 6], true));
          $empty_msg = $empty_open_only
              ? __('No open tickets', 'paineldebordo')
              : __('No tickets', 'paineldebordo');
          ?>
          <tr><td colspan="6" class="ho-empty"><?php echo $empty_msg; ?></td></tr>
        <?php } else {
          foreach ($data['rows'] as $row) {
            $st = (int) $row['status'];
            $pr = (int) $row['priority'];
            $late_row = !empty($row['time_to_resolve']) && strtotime($row['time_to_resolve']) < time();
            $st_label = $status_map[$st] ?? plugin_paineldebordo_status_label($st);
            $href = $ticket_base . (int) $row['id'];
            ?>
            <tr>
              <td><a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener"><strong>#<?php echo (int) $row['id']; ?></strong></a></td>
              <td><a href="<?php echo htmlspecialchars($href); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($row['name']); ?></a></td>
              <td><span class="badge badge-orange"><?php echo htmlspecialchars($st_label); ?></span></td>
              <td><span class="badge <?php echo $pr >= 4 ? 'badge-danger' : 'badge-secondary'; ?>"><?php echo htmlspecialchars(plugin_paineldebordo_priority_label($pr)); ?></span></td>
              <td><?php echo htmlspecialchars(substr((string) $row['date'], 0, 16)); ?></td>
              <td><?php echo $late_row ? '<span class="badge badge-danger">SLA</span>' : '—'; ?></td>
            </tr>
          <?php }
        } ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function(){
  var btn = document.getElementById('tk_refresh');
  if (btn) btn.addEventListener('click', function(){ location.reload(); });
  setInterval(function(){ location.reload(); }, 60000);
})();
</script>
