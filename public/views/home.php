<?php
/**
 * Home overview view.
 */
if (!isset($filters) || !is_array($filters)) {
    $filters = plugin_paineldebordo_getFilters();
}
include __DIR__ . '/../home.php';
