<?php
require("config.php");

if (empty($_SESSION['user'])) {
  header("Location: index.php");
  exit;
}

header("Location: modificarPresupuesto.php");
exit;
