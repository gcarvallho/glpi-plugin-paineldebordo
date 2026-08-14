<?php

error_reporting(E_ERROR | E_PARSE);

class PluginPaineldebordoConfig extends CommonDBTM
{


   static protected $notable = true;

   /**
    * @see CommonGLPI::getMenuName()
    **/
   static function getMenuName()
   {
      return __('Painel de Bordo', 'paineldebordo');
   }

   /**
    *  @see CommonGLPI::getMenuContent()
    **/
   static function getMenuContent()
   {
      $menu = [];
      $menu['title'] = __('Painel de Bordo', 'paineldebordo');
      $menu['page']  = '/plugins/paineldebordo/public/index.php';
      return $menu;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
   {
      switch (get_class($item)) {
         case 'Entity':
            // Entity map shortcut — Recursos UPDATE (not Admin)
            if (function_exists('plugin_paineldebordo_canModule') && !plugin_paineldebordo_canModule('resources', UPDATE)) {
               return '';
            }
            return [1 => __('Mapa do painel', 'paineldebordo')];
         default:
            return '';
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
   {
      switch (get_class($item)) {
         case 'Entity':
            $config = new self();
            $config->showFormDisplay();
            break;
      }
      return true;
   }

   /**
    * Entity map coordinates — shortcut; full editor is Configuration → Map.
    */
   function showFormDisplay()
   {
      global $CFG_GLPI, $DB;

      include_once(Plugin::getPhpDir('paineldebordo') . '/inc/access.inc.php');

      if (!plugin_paineldebordo_canModule('resources', UPDATE)) {
         return false;
      }

      $entity_id = (int) ($_GET['id'] ?? 0);
      $LNG = '';
      $LAT = '';
      if ($entity_id > 0 && $DB->TableExists('glpi_plugin_paineldebordo_map')) {
         $result_coo = $DB->doQuery(
            'SELECT lat, lng FROM glpi_plugin_paineldebordo_map WHERE entities_id = ' . $entity_id . ' LIMIT 1'
         );
         if ($result_coo && ($ent_info = $DB->fetchAssoc($result_coo))) {
            $LNG = (string) $ent_info['lng'];
            $LAT = (string) $ent_info['lat'];
         }
      }

      $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');
      $hub = $root . '/plugins/paineldebordo/public/shell.php?page=config';

      echo "<div class='center' id='tabsbody'>";
      echo "<p style='margin:0.75rem 0;'>";
      echo "<a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars($hub) . "'>";
      echo htmlspecialchars(__('Open Configuration → Map', 'paineldebordo'));
      echo "</a></p>";

      echo "<form name='form' action='" . htmlspecialchars($root . '/plugins/paineldebordo/public/map/insert_coord.php') . "' method='post'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo "<table class='tab_cadre_fixe' style='width:95%;'>";
      echo "<tr><th colspan='4'>" . htmlspecialchars(__('Map coordinates', 'paineldebordo')) . "</th></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td>" . __('Latitude') . "</td>";
      echo "<td><input type='text' class='form-control' id='lat' name='lat' value='" . htmlspecialchars($LAT) . "'></td>";
      echo "</tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td width='110px'>" . __('Longitude') . "</td>";
      echo "<td><input type='text' class='form-control' id='lng' name='lng' value='" . htmlspecialchars($LNG) . "'></td>";
      echo "</tr>";
      echo "<tr class='tab_bg_2'><td>&nbsp;</td></tr>";
      echo "<input type='hidden' id='id' name='id' value='" . $entity_id . "'>";
      echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
      echo "<input type='submit' name='update' class='btn btn-primary' value=\"" . htmlspecialchars(_sx('button', 'Save')) . "\">";
      echo "</td></tr>";
      echo "</table>";
      Html::closeForm();
      echo "</div>";
   }
}
