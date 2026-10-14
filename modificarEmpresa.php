<?php
// Vista unificada: la edición de la empresa (datos + ARCA) vive en configurarEmpresaARCA.php.
// Se conserva este archivo para no romper links existentes.
if (empty($_REQUEST['id'])) {
    header("Location: listarEmpresas.php");
    die();
}
header("Location: configurarEmpresaARCA.php?id=" . urlencode((string)$_REQUEST['id']));
die();
