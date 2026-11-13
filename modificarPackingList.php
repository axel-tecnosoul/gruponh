<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

// Compatibilidad con enlaces antiguos: alta y modificación comparten el mismo formulario.
$idPackingListRevision = isset($_GET['id_packing_list_revision']) ? (int)$_GET['id_packing_list_revision'] : 0;
$prod = isset($_GET['prod']) ? (int)$_GET['prod'] : 0;
if ($idPackingListRevision > 0) {
  $params = ['id_packing_list_revision' => $idPackingListRevision];
  if ($prod > 0) {
    $params['prod'] = $prod;
  }
  header('Location: nuevaPackingList.php?' . http_build_query($params));
} else {
  header('Location: listarPackingList.php' . ($prod > 0 ? '?prod=' . $prod : ''));
}
exit;
