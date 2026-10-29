<?php
// Página en desuso: la lógica vive en nuevaPackingListSecciones.php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodParam = $prod ? '&prod=' . $prod : '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  header("Location: nuevaPackingListSecciones.php?id_packing_list_revision=".$id.$prodParam);
} else {
  header("Location: listarPackingList.php");
}
exit;
