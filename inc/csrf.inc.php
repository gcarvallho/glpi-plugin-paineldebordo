<?php

/**
 * CSRF helpers for plugin forms (GLPI csrf_compliant).
 * One token per request — reused by all forms + AJAX on the page.
 */
function plugin_paineldebordo_csrf_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $token = '';
    if (class_exists('Session') && method_exists('Session', 'getNewCSRFToken')) {
        $token = (string) Session::getNewCSRFToken();
    }
    return $token;
}

function plugin_paineldebordo_csrf_field(): string
{
    $token = plugin_paineldebordo_csrf_token();
    if ($token === '') {
        return '';
    }
    return '<input type="hidden" name="_glpi_csrf_token" value="' . htmlspecialchars($token) . '">';
}
