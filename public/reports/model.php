<?php
header('Content-Type: text/xml; charset="utf-8"');
print '<?xml version="1.0" encoding="utf-8"?>';

//define('GLPI_ROOT', '../../../..');
// GLPI 11 boots core before its LegacyFileLoadController require()s this
// file; only bootstrap the classic way when it isn't already loaded.
if (!defined('GLPI_ROOT')) {
    include("../../../../inc/includes.php");
}


global $DB, $sql, $res;

error_reporting(E_ERROR | E_PARSE);
?>

<model>
	<nome id='0'> ---- </nome>

	<?php

	function xmlEscape($string)
	{
		return str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $string);
	}

	$manufac = $_REQUEST['sel_fab'];

	$itemtype = $_REQUEST['sel_item'];

	switch ($itemtype) {
		case "1":
			$type = 'computer';
			break;
		case "2":
			$type = 'monitor';
			break;
		case "3":
			$type = 'software';
			break;
		case "4":
			$type = 'networkequipment';
			break;
		case "5":
			$type = 'peripheral';
			break;
		case "6":
			$type = 'printer';
			break;
		case "7":
			$type = 'phone';
			break;
	}

	if ($type != 'software') {

		$sql = "SELECT id, name
				FROM glpi_" . $type . "models
				WHERE id IN (SELECT DISTINCT gc." . $type . "models_id
								FROM glpi_" . $type . "s gc
								WHERE gc.manufacturers_id = " . $manufac . " )
				ORDER BY name";

		$res = $DB->doQuery($sql);

		while ($row = $DB->fetchAssoc($res)) {
			echo "<nome id='" . $row['id'] . "'>" . xmlEscape($row['name']) . "</nome>";
		}
	} else {

		$sql = "SELECT id, name
			FROM `glpi_softwares`
			WHERE `manufacturers_id` = " . $manufac . " 
			AND `is_deleted` = 0
			ORDER BY `glpi_softwares`.`name` ASC ";

		$res = $DB->doQuery($sql);

		while ($row = $DB->fetchAssoc($res)) {
			echo "<nome id='" . $row['id'] . "'>" . xmlEscape($row['name']) . "</nome>";
		}
	}

	?>
</model>