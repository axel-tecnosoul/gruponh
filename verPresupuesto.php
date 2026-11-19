<?php
require("config.php");

if (empty($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
  header("Location: listarPresupuestos.php");
  exit;
}

header("Location: modificarPresupuesto.php?id=" . $id . "&modo=ver");
exit;
