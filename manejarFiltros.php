<?php
function gestionarFiltros($page_name) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $session_key = 'filtros_' . $page_name;
    $page_url = basename($_SERVER['PHP_SELF']);

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