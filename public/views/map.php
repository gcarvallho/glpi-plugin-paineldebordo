<?php
/**
 * Native map view — Leaflet markers from plugin map table (no iframe).
 */
include_once(Plugin::getPhpDir('paineldebordo') . '/inc/icons.inc.php');

global $DB, $CFG_GLPI;

$plugin_web = ($GLOBALS['HO_LAYOUT']['plugin_web'] ?? '') ?: (($CFG_GLPI['root_doc'] ?? '') . '/plugins/paineldebordo/public');
$plugin_web = rtrim($plugin_web, '/');

$points = [];
if ($DB->TableExists('glpi_plugin_paineldebordo_map')) {
    $res = $DB->doQuery('SELECT entities_id, location, lat, lng FROM glpi_plugin_paineldebordo_map');
    if ($res) {
        while ($row = $DB->fetchAssoc($res)) {
            $points[] = [
                'name' => $row['location'] ?: ('Entity #' . $row['entities_id']),
                'lat'  => (float) $row['lat'],
                'lng'  => (float) $row['lng'],
            ];
        }
    }
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($plugin_web); ?>/map/css/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

<header class="ho-section-head ho-dash-intro">
  <?php echo plugin_paineldebordo_icon('map'); ?>
  <div>
    <h2 class="ho-dash-intro__title"><?php echo __('Map', 'paineldebordo'); ?></h2>
    <p class="ho-dash-intro__meta"><?php echo sprintf(__('%d locations', 'paineldebordo'), count($points)); ?></p>
  </div>
</header>

<div class="ho-map-panel">
  <div id="ho_map" class="ho-map-canvas" role="application" aria-label="<?php echo htmlspecialchars(__('Map', 'paineldebordo')); ?>"></div>
</div>
<?php if (!$points) { ?>
<p class="ho-empty"><?php echo __('No map coordinates configured. Use Setup / Entity map tab.', 'paineldebordo'); ?></p>
<?php } ?>

<script src="<?php echo htmlspecialchars($plugin_web); ?>/map/js/leaflet.js"></script>
<script>
(function () {
  function loadScript(src) {
    return new Promise(function (resolve) {
      var s = document.createElement('script');
      s.src = src;
      s.onload = function () { resolve(true); };
      s.onerror = function () { resolve(false); };
      document.head.appendChild(s);
    });
  }

  async function ensureLeaflet() {
    if (typeof L !== 'undefined') return true;
    return await loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js') && typeof L !== 'undefined';
  }

  async function boot() {
    var ok = await ensureLeaflet();
    if (!ok) {
      var empty = document.createElement('p');
      empty.className = 'ho-empty';
      empty.textContent = 'Leaflet <?php echo htmlspecialchars(__('failed to load', 'paineldebordo'), ENT_QUOTES); ?>';
      var host = document.querySelector('.ho-map-panel');
      if (host) host.appendChild(empty);
      return;
    }

    var points = <?php echo json_encode($points, JSON_UNESCAPED_UNICODE); ?>;
    var el = document.getElementById('ho_map');
    var map = L.map(el, { zoomControl: true }).setView([-15.78, -47.93], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var bounds = [];
    points.forEach(function (p) {
      if (!p.lat && !p.lng) return;
      L.marker([p.lat, p.lng]).addTo(map).bindPopup(p.name);
      bounds.push([p.lat, p.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });

    function fixSize() {
      try { map.invalidateSize(false); } catch (e) {}
    }
    setTimeout(fixSize, 0);
    setTimeout(fixSize, 120);
    setTimeout(fixSize, 400);
    window.addEventListener('resize', fixSize);
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(fixSize).observe(el);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(); });
  } else {
    boot();
  }
})();
</script>
