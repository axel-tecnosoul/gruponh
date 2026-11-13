<?php
function gestionarFiltros($page_name, array $parametrosUrl = []) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $session_key = 'filtros_' . $page_name;
    $page_url = basename($_SERVER['PHP_SELF']);
    if ($parametrosUrl) {
        $page_url .= '?' . http_build_query($parametrosUrl, '', '&', PHP_QUERY_RFC3986);
    }

    if (isset($_GET['clear_filters'])) {
        unset($_SESSION[$session_key]);
        header("Location: " . $page_url);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION[$session_key] = $_POST;
        header("Location: " . $page_url);
        exit;
    }

    return $_SESSION[$session_key] ?? [];
}