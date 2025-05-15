<?php
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}

require 'database.php';

$id = null;
if (!empty($_GET['id_lista_corte'])) {
  $id = $_REQUEST['id_lista_corte'];
}

if (null==$id) {
  header("Location: listarListasCorte.php");
}

// insert data
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modoDebug=0;

if ($modoDebug==1) {
  $pdo->beginTransaction();
  var_dump($_POST);
  var_dump($_GET);
  var_dump($_FILES);
}

$id_lista_corte_clonar=$_GET['id_lista_corte'];
$ultimo_nro_revision_clonar=$_GET['revision'];

$sql = "INSERT INTO listas_corte (ultimo_nro_revision) VALUES (0)";
$q = $pdo->prepare($sql);
$q->execute();
$id_lista_corte = $pdo->lastInsertId();

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "SELECT lcr.id, lcr.id_lista_corte, lcr.id_proyecto, lcr.fecha, lcr.id_usuario, lcr.id_estado_lista_corte, lcr.anulado, lcr.nombre, lcr.numero, lcr.adjunto, lcr.id_cuenta_realizo, lcr.id_cuenta_reviso, lcr.id_cuenta_valido FROM listas_corte_revisiones lcr WHERE lcr.id_lista_corte = ? AND lcr.nro_revision = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte_clonar,$ultimo_nro_revision_clonar]);
$data = $q->fetch(PDO::FETCH_ASSOC);

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "INSERT INTO listas_corte_revisiones (id_lista_corte, id_proyecto, fecha, id_usuario, id_estado_lista_corte, nro_revision, anulado, nombre, numero, descripcion) VALUES (?,?,?,?,1,0,0,?,?,?)";
$q = $pdo->prepare($sql);
$q->execute([$id_lista_corte,$data['id_proyecto'],$data['fecha'],$data['id_usuario'],$data['nombre'],$data['numero'],"Emisión original"]);
$id_lista_corte_revision = $pdo->lastInsertId();

if ($modoDebug==1) {
  $q->debugDumpParams();
  echo "<br><br>Afe: ".$q->rowCount();
  echo "<br><br>";
}

$sql = "SELECT lcc.id,lcc.id_lista_corte,lcc.nombre,lcc.cantidad,lcc.peso,lcc.id_estado_lista_corte_conjuntos FROM listas_corte_conjuntos lcc WHERE lcc.id_lista_corte = ".$data['id'];
foreach ($pdo->query($sql) as $row) {
  $sql = "INSERT INTO listas_corte_conjuntos (id_lista_corte, nombre, cantidad, peso, id_estado_lista_corte_conjuntos) VALUES (?,?,?,?,1)";
  $q = $pdo->prepare($sql);
  $q->execute([$id_lista_corte_revision,$row['nombre'],$row['cantidad'],$row['peso']]);
  $id_lista_corte_conjunto = $pdo->lastInsertId();

  if ($modoDebug==1) {
    $q->debugDumpParams();
    echo "<br><br>Afe: ".$q->rowCount();
    echo "<br><br>";
  }

  $sql = "SELECT lcp.id,lcp.id_lista_corte_conjunto,lcp.id_material,lcp.posicion,lcp.cantidad,lcp.largo,lcp.ancho,lcp.marca,lcp.peso,lcp.finalizado,lcp.id_colada,lcp.diametro,lcp.calidad FROM lista_corte_posiciones lcp WHERE lcp.id_lista_corte_conjunto = ".$row["id"];
  foreach ($pdo->query($sql) as $row2) {
    $sql = "INSERT INTO lista_corte_posiciones (id_lista_corte_conjunto,id_material,posicion,cantidad,largo,ancho,marca,peso,finalizado,id_colada,diametro,calidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $q = $pdo->prepare($sql);
    $q->execute([$id_lista_corte_conjunto,$row2["id_material"],$row2["posicion"],$row2["cantidad"],$row2["largo"],$row2["ancho"],$row2["marca"],$row2["peso"],$row2["finalizado"],$row2["id_colada"],$row2["diametro"],$row2["calidad"]]);
    $id_lista_corte_posicion = $pdo->lastInsertId();

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    $sql = "SELECT lcp.id_lista_corte_posicion,lcp.id_tipo_proceso,lcp.observaciones,lcp.id_estado_lista_corte_proceso FROM lista_corte_procesos lcp WHERE lcp.id_lista_corte_posicion = ".$row2["id"];
    foreach ($pdo->query($sql) as $row3) {
      $sql = "INSERT INTO lista_corte_procesos (id_lista_corte_posicion, id_tipo_proceso, observaciones, id_estado_lista_corte_proceso) VALUES (?,?,?,1)";
      $q = $pdo->prepare($sql);
      $q->execute([$id_lista_corte_posicion,$row3['id_tipo_proceso'],$row3['observaciones']]);

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }
  }
}

if ($modoDebug==1) {
  $pdo->rollBack();
  die();
} else {
  Database::disconnect();
  header("Location: modificarListaCorte.php?id_lista_corte=".$id_lista_corte."&revision=0");
}?>
