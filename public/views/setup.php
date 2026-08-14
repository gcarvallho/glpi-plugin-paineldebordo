<?php
/**
 * Legacy Setup — redirects to Configuration hub (2.9.0).
 */
$theme = plugin_paineldebordo_getFilters()['theme'] ?? 'light';
header('Location: shell.php?' . http_build_query(['page' => 'config', 'theme' => $theme]));
exit;
