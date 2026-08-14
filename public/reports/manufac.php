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

<manufac>
	<nome id='0'> ---- </nome>

	<?php

	function xmlEscape($string)
	{
		return str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $string);
	}

	$itemtype = $_REQUEST['sel_item'];

	switch ($itemtype) {
		case "1":
			$type = 'glpi_computers';
			break;
		case "2":
			$type = 'glpi_monitors';
			break;
		case "3":
			$type = 'glpi_softwares';
			break;
		case "4":
			$type = 'glpi_networkequipments';
			break;
		case "5":
			$type = 'glpi_peripherals';
			break;
		case "6":
			$type = 'glpi_printers';
			break;
		case "7":
			$type = 'glpi_phones';
			break;
	}

	$sql = "SELECT id, name 
			  FROM glpi_manufacturers
			  WHERE id IN (SELECT DISTINCT `manufacturers_id` FROM " . $type . " )
			  ORDER BY name ";

	$res = $DB->doQuery($sql);

	while ($row = $DB->fetchAssoc($res)) {
		echo "<nome id='" . $row['id'] . "'>" . xmlEscape($row['name']) . "</nome>";
	}

	?>
</manufac>