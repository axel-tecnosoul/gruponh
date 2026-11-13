<?php
require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    die("Redirecting to index.php");
}
require 'database.php';

$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

$id_packing_list_seccion = null;
if (!empty($_GET['id_packing_list_seccion'])) {
  $id_packing_list_seccion = (int)$_REQUEST['id_packing_list_seccion'];
}

if (null==$id_packing_list_seccion) {
  header("Location: listarPackingList.php$prodQuery");
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT pl.id AS id_packing_list, plr.id_proyecto, plr.id AS id_packing_list_revision, plr.id_estado_packing_list, plr.nro_revision, pl.ultimo_nro_revision FROM packing_lists_secciones pls INNER JOIN packing_lists_revisiones plr ON plr.id = pls.id_packing_list_revision INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list WHERE pls.id = ?";
$q = $pdo->prepare($sql);
$q->execute([$id_packing_list_seccion]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (empty($data)) {
  Database::disconnect();
  header("Location: listarPackingList.php".$prodQuery);
  exit;
}
$idProyecto=(int)$data['id_proyecto'];
$idPackingList=(int)$data['id_packing_list'];
$idPackingListRevision=(int)$data['id_packing_list_revision'];
$puedeEnviarAprobacion=(int)$data['id_estado_packing_list'] === 1 && (int)$data['nro_revision'] === (int)$data['ultimo_nro_revision'];

$sqlConceptosCatalogo = "SELECT cd.id AS id_computo_detalle, cd.id_material, m.codigo, m.concepto,
                                cd.cantidad AS cantidad_original,
                                c.nro AS nro_computo, c.nro_revision AS revision_computo,
                                COALESCE((
                                  SELECT SUM(plc_otro.cantidad)
                                  FROM packing_lists_componentes plc_otro
                                  INNER JOIN packing_lists_secciones pls_otro ON pls_otro.id = plc_otro.id_packing_list_seccion
                                  INNER JOIN packing_lists_revisiones plr_otro ON plr_otro.id = pls_otro.id_packing_list_revision
                                  INNER JOIN packing_lists pl_otro ON pl_otro.id = plr_otro.id_packing_list
                                  INNER JOIN estados_packing_list epl_otro ON epl_otro.id = plr_otro.id_estado_packing_list
                                  WHERE plc_otro.id_computo_detalle = cd.id
                                    AND pl_otro.id <> ? AND pl_otro.anulado = 0
                                    AND plr_otro.anulado = 0
                                    AND plr_otro.nro_revision = pl_otro.ultimo_nro_revision
                                    AND LOWER(TRIM(epl_otro.estado)) NOT IN ('cancelado', 'cancelada', 'superado')
                                ), 0) AS cantidad_otros_pl
                         FROM computos_detalle cd
                         INNER JOIN materiales m ON m.id = cd.id_material
                         INNER JOIN computos c ON c.id = cd.id_computo
                         INNER JOIN estados_computos ec ON ec.id = c.id_estado
                         INNER JOIN tareas t ON t.id = c.id_tarea
                         WHERE cd.cancelado = 0 AND t.id_proyecto = ?
                           AND LOWER(TRIM(ec.estado)) IN ('aprobado', 'aprobada', 'gestionando', 'gestionada', 'terminado', 'terminada')
                         ORDER BY c.nro, c.nro_revision, m.concepto";
$qConceptosCatalogo = $pdo->prepare($sqlConceptosCatalogo);
$qConceptosCatalogo->execute([$idPackingList, $idProyecto]);
$conceptosCatalogo = $qConceptosCatalogo->fetchAll(PDO::FETCH_ASSOC);

$sqlConjuntosCatalogo = "SELECT lcc.id AS id_conjunto_lista_corte, lcc.nombre AS conjunto,
                                lcc.cantidad AS cantidad_original, lc.id AS id_lista_corte,
                                lc.numero AS numero_lista_corte, lc.nro_revision AS revision_lista_corte,
                                lc.nombre AS nombre_lista_corte, elc.estado AS estado_lista_corte,
                                COALESCE((
                                  SELECT SUM(plc_otro.cantidad)
                                  FROM packing_lists_componentes plc_otro
                                  INNER JOIN packing_lists_secciones pls_otro ON pls_otro.id = plc_otro.id_packing_list_seccion
                                  INNER JOIN packing_lists_revisiones plr_otro ON plr_otro.id = pls_otro.id_packing_list_revision
                                  INNER JOIN packing_lists pl_otro ON pl_otro.id = plr_otro.id_packing_list
                                  INNER JOIN estados_packing_list epl_otro ON epl_otro.id = plr_otro.id_estado_packing_list
                                  WHERE plc_otro.id_conjunto_lista_corte = lcc.id
                                    AND pl_otro.id <> ? AND pl_otro.anulado = 0
                                    AND plr_otro.anulado = 0
                                    AND plr_otro.nro_revision = pl_otro.ultimo_nro_revision
                                    AND LOWER(TRIM(epl_otro.estado)) NOT IN ('cancelado', 'cancelada', 'superado')
                                ), 0) AS cantidad_otros_pl
                         FROM listas_corte_conjuntos lcc
                         INNER JOIN listas_corte lc ON lc.id = lcc.id_lista_corte
                         INNER JOIN estados_lista_corte elc ON elc.id = lc.id_estado_lista_corte
                         WHERE lc.anulado = 0 AND lc.id_proyecto = ?
                           AND LOWER(TRIM(elc.estado)) IN ('aprobada', 'aprobado', 'gestionando', 'gestionada', 'terminada', 'terminado')
                         ORDER BY lc.numero, lc.nro_revision, lcc.nombre";
$qConjuntosCatalogo = $pdo->prepare($sqlConjuntosCatalogo);
$qConjuntosCatalogo->execute([$idPackingList, $idProyecto]);
$conjuntosCatalogo = $qConjuntosCatalogo->fetchAll(PDO::FETCH_ASSOC);
$hayCandidatosConSaldo = false;
foreach (array_merge($conceptosCatalogo, $conjuntosCatalogo) as $candidato) {
  if ((float)$candidato['cantidad_original'] - (float)$candidato['cantidad_otros_pl'] > 0) {
    $hayCandidatosConSaldo = true;
    break;
  }
}

$sql = "SELECT COUNT(*) AS secciones, SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM packing_lists_componentes plc WHERE plc.id_packing_list_seccion = pls.id) THEN 1 ELSE 0 END) AS secciones_sin_componentes FROM packing_lists_secciones pls WHERE pls.id_packing_list_revision = ?";
$q = $pdo->prepare($sql);
$q->execute([$idPackingListRevision]);
$estadoSecciones = $q->fetch(PDO::FETCH_ASSOC);
$puedeEnviarAprobacion = $puedeEnviarAprobacion
  && !empty($estadoSecciones)
  && (int)$estadoSecciones['secciones'] > 0
  && (int)$estadoSecciones['secciones_sin_componentes'] === 0;

if (!empty($_POST)) {
  // insert data
  $modoDebug=0;

  if ($modoDebug==1) {
    $pdo->beginTransaction();
    var_dump($_POST);
    var_dump($_FILES);
  }

  $sql = "SELECT plr.id_packing_list FROM packing_lists_secciones pls INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id WHERE pls.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id_packing_list_seccion]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  $id_packing_list=$data['id_packing_list'];

  if (!empty($_POST['btn3'])) {
	  
	if (empty($_POST['id_concepto'])) {
		$_POST['id_concepto'] = null;
	}
	if (empty($_POST['id_conjunto_lista_corte'])) {
		$_POST['id_conjunto_lista_corte'] = null;
    }
    //editar componente
    $id_packing_list_componente=$_POST['btn3'];
    $idComputoDetalle = !empty($_POST['id_computo_detalle']) && !empty($_POST['id_concepto'])
      ? (int)$_POST['id_computo_detalle']
      : null;
    
    $sql = "UPDATE packing_lists_componentes SET id_conjunto_lista_corte=?, id_concepto=?, cantidad=?, observaciones=?, id_computo_detalle=? WHERE id = ?";
    $q = $pdo->prepare($sql);
    $q->execute([$_POST['id_conjunto_lista_corte'],$_POST['id_concepto'],$_POST['cantidad'],$_POST['observaciones'],$idComputoDetalle,$id_packing_list_componente]);

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    if ($idComputoDetalle === null && !empty($_POST['id_concepto'])) {
      $sql="SELECT cd.id AS idComputoDetalle FROM packing_lists_componentes plc INNER JOIN packing_lists_secciones pls ON plc.id_packing_list_seccion=pls.id INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id INNER JOIN proyectos p ON plr.id_proyecto=p.id INNER JOIN tareas t ON t.id_proyecto=p.id INNER JOIN computos c ON c.id_tarea=t.id INNER JOIN computos_detalle cd ON cd.id_computo=c.id WHERE plc.id = ? AND cd.cancelado = 0 AND cd.id_material = ?";
      $q = $pdo->prepare($sql);
      $q->execute([$id_packing_list_componente,$_POST['id_concepto']]);
      $data = $q->fetch(PDO::FETCH_ASSOC);
      $idComputoDetalle = $data['idComputoDetalle'] ?? null;
    }

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }

    if($idComputoDetalle>0){
      $sql = "UPDATE packing_lists_componentes SET id_computo_detalle = ? WHERE id = ?";
      $q = $pdo->prepare($sql);
      $q->execute(array($idComputoDetalle,$id_packing_list_componente));

      if ($modoDebug==1) {
        $q->debugDumpParams();
        echo "<br><br>Afe: ".$q->rowCount();
        echo "<br><br>";
      }
    }
      
    $sql = "INSERT INTO logs(fecha_hora, id_usuario, detalle_accion,modulo,link) VALUES (now(),?,?,'Packing List','')";
    $q = $pdo->prepare($sql);
    $q->execute(array($_SESSION['user']['id'], 'Modificación de Componente ID #'.$id_packing_list_componente.' de Packing List'));

    if ($modoDebug==1) {
      $q->debugDumpParams();
      echo "<br><br>Afe: ".$q->rowCount();
      echo "<br><br>";
    }
      
    if ($modoDebug==1) {
      $pdo->rollBack();
      die();
    } else {
      Database::disconnect();
      header("Location: nuevaPackingListComponentes.php?id_packing_list_seccion=".$id_packing_list_seccion.$prodParam);
      
    }

  }else{
    // Insertar en lote los componentes seleccionados en el catálogo.
    $errorCatalogo = '';
    $candidatosPorClave = [];
    foreach ($conceptosCatalogo as $candidato) {
      $candidatosPorClave['concepto:' . (int)$candidato['id_computo_detalle']] = [
        'tipo' => 'concepto',
        'id' => (int)$candidato['id_computo_detalle'],
        'id_concepto' => (int)$candidato['id_material'],
        'id_conjunto' => null,
        'cantidad_original' => (float)$candidato['cantidad_original'],
        'cantidad_otros_pl' => (float)$candidato['cantidad_otros_pl'],
      ];
    }
    foreach ($conjuntosCatalogo as $candidato) {
      $candidatosPorClave['conjunto:' . (int)$candidato['id_conjunto_lista_corte']] = [
        'tipo' => 'conjunto',
        'id' => (int)$candidato['id_conjunto_lista_corte'],
        'id_concepto' => null,
        'id_conjunto' => (int)$candidato['id_conjunto_lista_corte'],
        'cantidad_original' => (float)$candidato['cantidad_original'],
        'cantidad_otros_pl' => (float)$candidato['cantidad_otros_pl'],
      ];
    }

    $fuentesSeleccionadas = array_values(array_unique(array_filter(array_map(
      static fn($fuente) => is_scalar($fuente) ? trim((string)$fuente) : '',
      (array)($_POST['fuentes_catalogo'] ?? [])
    ))));
    if (!$fuentesSeleccionadas) {
      $errorCatalogo = 'Seleccione uno o varios conceptos o conjuntos de la tabla.';
    }

    $componentesValidados = [];
    $conjuntosEnLote = [];
    foreach ($fuentesSeleccionadas as $fuente) {
      $candidatoSeleccionado = $candidatosPorClave[$fuente] ?? null;
      if (!$candidatoSeleccionado) {
        $errorCatalogo = 'Una de las opciones seleccionadas ya no está disponible para este proyecto.';
        break;
      }

      $cantidadRaw = str_replace(',', '.', trim((string)($_POST['cantidades_catalogo'][$fuente] ?? '')));
      $cantidad = is_numeric($cantidadRaw) ? (float)$cantidadRaw : 0;
      $saldo = max(0, $candidatoSeleccionado['cantidad_original'] - $candidatoSeleccionado['cantidad_otros_pl']);
      if ($cantidad < 1 || floor($cantidad) !== $cantidad || $cantidad > $saldo) {
        $errorCatalogo = 'Revise las cantidades seleccionadas: deben ser enteras, mayores a cero y no superar el saldo de cada fila.';
        break;
      }

      if ($candidatoSeleccionado['tipo'] === 'conjunto') {
        if (isset($conjuntosEnLote[$candidatoSeleccionado['id']])) {
          $errorCatalogo = 'No se puede seleccionar dos veces el mismo conjunto de LC.';
          break;
        }
        $qDuplicado = $pdo->prepare('SELECT COUNT(*) FROM packing_lists_componentes WHERE id_conjunto_lista_corte = ? AND id_packing_list_seccion = ?');
        $qDuplicado->execute([$candidatoSeleccionado['id_conjunto'], $id_packing_list_seccion]);
        if ((int)$qDuplicado->fetchColumn() > 0) {
          $errorCatalogo = 'El conjunto de LC seleccionado ya está agregado en esta sección.';
          break;
        }
        $conjuntosEnLote[$candidatoSeleccionado['id']] = true;
      }

      $candidatoSeleccionado['cantidad'] = $cantidad;
      $componentesValidados[] = $candidatoSeleccionado;
    }

    if ($errorCatalogo === '' && $componentesValidados) {
      try {
        $pdo->beginTransaction();
        $qRevision = $pdo->prepare('SELECT id_packing_list_revision FROM packing_lists_secciones WHERE id = ?');
        $qRevision->execute([$id_packing_list_seccion]);
        $revisionSeccion = $qRevision->fetchColumn();
        if (!$revisionSeccion) {
          throw new RuntimeException('No se encontró la revisión del Packing List.');
        }
        $linkRevision = 'verPackingList.php?id=' . $revisionSeccion . $prodParam;
        $qInsertar = $pdo->prepare("INSERT INTO packing_lists_componentes (id_packing_list_seccion, id_conjunto_lista_corte, id_concepto, cantidad, id_computo_detalle, observaciones, id_estado_componente_packing_list) VALUES (?,?,?,?,?,?,1)");
        $qLog = $pdo->prepare("INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (now(), ?, ?, 'Packing List', ?)");

        foreach ($componentesValidados as $componente) {
          $qInsertar->execute([
            $id_packing_list_seccion,
            $componente['id_conjunto'],
            $componente['id_concepto'],
            $componente['cantidad'],
            $componente['tipo'] === 'concepto' ? $componente['id'] : null,
            $_POST['observaciones'] ?? '',
          ]);
          $idComponenteNuevo = (int)$pdo->lastInsertId();
          $qLog->execute([$_SESSION['user']['id'], 'Nuevo Componente ID #' . $idComponenteNuevo . ' de Packing List', $linkRevision]);
        }

        $pdo->commit();
        Database::disconnect();
        if (!empty($_POST['btn2'])) {
          header("Location: nuevaPackingListSecciones.php?id_packing_list_revision=" . $revisionSeccion . $prodParam);
        } else {
          header("Location: nuevaPackingListComponentes.php?id_packing_list_seccion=" . $id_packing_list_seccion . $prodParam);
        }
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('No se pudieron agregar componentes al Packing List: ' . $e->getMessage());
        $errorCatalogo = 'No se pudieron guardar los componentes seleccionados. Verifique la información e intente nuevamente.';
      }
    }

    
  }
  
}

//$id_packing_list_seccion=$_GET['id_packing_list_seccion'];
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = "SELECT pls.id AS id_packing_list_seccion,pls.id_packing_list_revision,plr.id AS id_packing_list,pls.observaciones,plr.id_proyecto FROM packing_lists_secciones pls INNER JOIN packing_lists_revisiones plr ON pls.id_packing_list_revision=plr.id WHERE pls.id = ? ";
$q = $pdo->prepare($sql);
$q->execute([$id_packing_list_seccion]);
$data = $q->fetch(PDO::FETCH_ASSOC);
$nombreSeccion=$data['observaciones'];
$idProyecto=$data['id_proyecto'];
Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #tablaCatalogoPackingList th:first-child,
      #tablaCatalogoPackingList td:first-child,
      #tablaCatalogoPackingList th:nth-child(4),
      #tablaCatalogoPackingList td:nth-child(4),
      #tablaCatalogoPackingList th:nth-child(5),
      #tablaCatalogoPackingList td:nth-child(5),
      #tablaCatalogoPackingList th:nth-child(6),
      #tablaCatalogoPackingList td:nth-child(6),
      #tablaCatalogoPackingList th:nth-child(7),
      #tablaCatalogoPackingList td:nth-child(7) {
        white-space: normal;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <?php include('header.php');?>
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <?php include('menu.php');?>
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Nuevo Componente";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Componentes para la Sección: <?= htmlspecialchars($nombreSeccion, ENT_QUOTES, 'UTF-8') ?></h5>
                  </div>
                                                <form id="formPackingListComponentes" class="form theme-form" role="form" method="post" action="nuevaPackingListComponentes.php?id_packing_list_seccion=<?=$id_packing_list_seccion?><?= $prodParam ?>">
                    <div class="card-body">
                      <?php
                        $pdo = Database::connect();
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sql = "SELECT plc.id, plc.id_computo_detalle, m.id AS id_concepto, m.codigo, m.concepto,
                                       lcc.id AS id_conjunto, lcc.nombre AS conjunto, plc.cantidad, plc.observaciones
                                FROM packing_lists_componentes plc
                                LEFT JOIN materiales m ON plc.id_concepto = m.id
                                LEFT JOIN listas_corte_conjuntos lcc ON plc.id_conjunto_lista_corte = lcc.id
                                WHERE plc.id_packing_list_seccion = ?";
                        $qComponentes = $pdo->prepare($sql);
                        $qComponentes->execute([$id_packing_list_seccion]);
                        $componentesSeccion = $qComponentes->fetchAll(PDO::FETCH_ASSOC);
                        Database::disconnect();
                      ?>
                      <?php
                        $conceptosEdicion = [];
                        foreach ($conceptosCatalogo as $candidato) {
                          $conceptosEdicion[(int)$candidato['id_material']] = [
                            'id' => (int)$candidato['id_material'],
                            'codigo' => $candidato['codigo'],
                            'concepto' => $candidato['concepto'],
                          ];
                        }
                        $conjuntosEdicion = [];
                        foreach ($conjuntosCatalogo as $candidato) {
                          $conjuntosEdicion[(int)$candidato['id_conjunto_lista_corte']] = [
                            'id' => (int)$candidato['id_conjunto_lista_corte'],
                            'nombre' => $candidato['conjunto'],
                          ];
                        }
                        foreach ($componentesSeccion as $componenteExistente) {
                          if (!empty($componenteExistente['id_concepto'])) {
                            $conceptosEdicion[(int)$componenteExistente['id_concepto']] = [
                              'id' => (int)$componenteExistente['id_concepto'],
                              'codigo' => $componenteExistente['codigo'] ?? '',
                              'concepto' => $componenteExistente['concepto'] ?? '',
                            ];
                          }
                          if (!empty($componenteExistente['id_conjunto'])) {
                            $conjuntosEdicion[(int)$componenteExistente['id_conjunto']] = [
                              'id' => (int)$componenteExistente['id_conjunto'],
                              'nombre' => $componenteExistente['conjunto'] ?? '',
                            ];
                          }
                        }
                        $primerComputoDetallePorMaterial = [];
                        foreach ($conceptosCatalogo as $candidato) {
                          $idMaterial = (int)$candidato['id_material'];
                          if (!isset($primerComputoDetallePorMaterial[$idMaterial])) {
                            $primerComputoDetallePorMaterial[$idMaterial] = (int)$candidato['id_computo_detalle'];
                          }
                        }
                        $componentesCatalogoPorFuente = [];
                        foreach ($componentesSeccion as $componenteExistente) {
                          if (!empty($componenteExistente['id_conjunto'])) {
                            $claveFuenteExistente = 'conjunto:' . (int)$componenteExistente['id_conjunto'];
                          } elseif (!empty($componenteExistente['id_computo_detalle'])) {
                            $claveFuenteExistente = 'concepto:' . (int)$componenteExistente['id_computo_detalle'];
                          } elseif (!empty($componenteExistente['id_concepto'])) {
                            $idMaterialExistente = (int)$componenteExistente['id_concepto'];
                            $idDetalleExistente = $primerComputoDetallePorMaterial[$idMaterialExistente] ?? 0;
                            $claveFuenteExistente = $idDetalleExistente ? 'concepto:' . $idDetalleExistente : '';
                          } else {
                            $claveFuenteExistente = '';
                          }
                          if ($claveFuenteExistente !== '') {
                            $componentesCatalogoPorFuente[$claveFuenteExistente][] = $componenteExistente;
                          }
                        }
                        $fuentesSeleccionadasPost = array_values(array_map('strval', (array)($_POST['fuentes_catalogo'] ?? [])));
                      ?>
                      <?php if (!empty($errorCatalogo)): ?>
                        <div class="alert alert-warning"><?= htmlspecialchars($errorCatalogo, ENT_QUOTES, 'UTF-8') ?></div>
                      <?php endif; ?>

                      <div class="row" id="catalogoPackingListContainer">
                        <div class="col-12">
                          <h6 class="font-weight-bold">Disponibles para agregar a esta sección</h6>
                          <p class="text-muted">Marque uno o varios conceptos o conjuntos y complete las cantidades. El saldo descuenta lo asignado a otros Packing List vigentes.</p>
                          <div class="table-responsive">
                            <table class="display table table-bordered" id="tablaCatalogoPackingList" style="width:100%">
                              <thead>
                                <tr>
                                  <th>Seleccionar</th>
                                  <th>Tipo</th>
                                  <th>Información de origen</th>
                                  <th>Concepto / conjunto</th>
                                  <th>Cantidad original</th>
                                  <th>En otros PL</th>
                                  <th>Saldo</th>
                                  <th>En esta sección</th>
                                  <th>Cantidad a agregar</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($conceptosCatalogo as $candidato):
                                  $claveCandidato = 'concepto:' . (int)$candidato['id_computo_detalle'];
                                  $cantidadOriginal = (float)$candidato['cantidad_original'];
                                  $cantidadOtrosPL = (float)$candidato['cantidad_otros_pl'];
                                  $saldoCandidato = $cantidadOriginal - $cantidadOtrosPL;
                                  $cantidadIngresada = $_POST['cantidades_catalogo'][$claveCandidato] ?? (string)max(0, $saldoCandidato);
                                  $haySaldo = $saldoCandidato > 0;
                                  $maximoCantidad = max(0, $saldoCandidato);
                                  $fuenteSeleccionada = in_array($claveCandidato, $fuentesSeleccionadasPost, true);
                                  $componentesActuales = $componentesCatalogoPorFuente[$claveCandidato] ?? [];
                                  ?>
                                  <tr>
                                    <td class="text-center">
                                      <input type="checkbox" name="fuentes_catalogo[]" value="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" <?=$fuenteSeleccionada ? 'checked' : ''?> <?=$haySaldo ? '' : 'disabled'?> data-clave="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" data-disponible="<?=$haySaldo ? '1' : '0'?>" aria-label="Seleccionar concepto">
                                    </td>
                                    <td>Concepto</td>
                                    <td>Cómputo #<?= htmlspecialchars((string)$candidato['nro_computo'], ENT_QUOTES, 'UTF-8') ?> / Rev. <?= (int)$candidato['revision_computo'] ?></td>
                                    <td><?= htmlspecialchars($candidato['concepto'] . ' (' . $candidato['codigo'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$cantidadOriginal, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$cantidadOtrosPL, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$saldoCandidato, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                      <?php if ($componentesActuales): ?>
                                        <?php foreach ($componentesActuales as $componenteActual): ?>
                                          <?php
                                            $idComputoDetalleActual = !empty($componenteActual['id_computo_detalle'])
                                              ? (int)$componenteActual['id_computo_detalle']
                                              : ((strpos($claveCandidato, 'concepto:') === 0) ? (int)substr($claveCandidato, strlen('concepto:')) : 0);
                                          ?>
                                          <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span><?= htmlspecialchars((string)$componenteActual['cantidad'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-nowrap">
                                              <?php if (!empty(tienePermiso(329))): ?>
                                                <a href="#" class="js-editar-componente" data-id="<?= (int)$componenteActual['id'] ?>" data-computo-detalle="<?= $idComputoDetalleActual ?>" data-concepto="<?= (int)$componenteActual['id_concepto'] ?>" data-conjunto="<?= (int)$componenteActual['id_conjunto'] ?>" data-cantidad="<?= htmlspecialchars((string)$componenteActual['cantidad'], ENT_QUOTES, 'UTF-8') ?>" data-observaciones="<?= htmlspecialchars((string)($componenteActual['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" title="Modificar componente">
                                                  <img src="img/icon_modificar.png" width="20" height="21" alt="Modificar">
                                                </a>
                                              <?php endif; ?>
                                              <?php if (!empty(tienePermiso(330))): ?>
                                                <a href="#" class="js-eliminar-componente" data-id="<?= (int)$componenteActual['id'] ?>" title="Eliminar componente">
                                                  <img src="img/icon_baja.png" width="20" height="21" alt="Eliminar">
                                                </a>
                                              <?php endif; ?>
                                            </span>
                                          </div>
                                        <?php endforeach; ?>
                                      <?php else: ?>
                                        <span class="text-muted">—</span>
                                      <?php endif; ?>
                                    </td>
                                    <td><input type="number" name="cantidades_catalogo[<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>]" min="1" step="1" max="<?= htmlspecialchars((string)$maximoCantidad, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$cantidadIngresada, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm cantidad-catalogo" data-clave="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" data-disponible="<?=$haySaldo ? '1' : '0'?>" <?=($haySaldo && $fuenteSeleccionada) ? '' : 'disabled'?>></td>
                                  </tr>
                                <?php endforeach; ?>
                                <?php foreach ($conjuntosCatalogo as $candidato):
                                  $claveCandidato = 'conjunto:' . (int)$candidato['id_conjunto_lista_corte'];
                                  $cantidadOriginal = (float)$candidato['cantidad_original'];
                                  $cantidadOtrosPL = (float)$candidato['cantidad_otros_pl'];
                                  $saldoCandidato = $cantidadOriginal - $cantidadOtrosPL;
                                  $cantidadIngresada = $_POST['cantidades_catalogo'][$claveCandidato] ?? (string)max(0, $saldoCandidato);
                                  $haySaldo = $saldoCandidato > 0;
                                  $maximoCantidad = max(0, $saldoCandidato);
                                  $fuenteSeleccionada = in_array($claveCandidato, $fuentesSeleccionadasPost, true);
                                  $componentesActuales = $componentesCatalogoPorFuente[$claveCandidato] ?? [];
                                  ?>
                                  <tr>
                                    <td class="text-center">
                                      <input type="checkbox" name="fuentes_catalogo[]" value="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" <?=$fuenteSeleccionada ? 'checked' : ''?> <?=$haySaldo ? '' : 'disabled'?> data-clave="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" data-disponible="<?=$haySaldo ? '1' : '0'?>" aria-label="Seleccionar conjunto de LC">
                                    </td>
                                    <td>Conjunto de LC</td>
                                    <td>LC #<?= htmlspecialchars((string)$candidato['numero_lista_corte'], ENT_QUOTES, 'UTF-8') ?> / Rev. <?= (int)$candidato['revision_lista_corte'] ?><br>Lista: <?= htmlspecialchars((string)$candidato['nombre_lista_corte'], ENT_QUOTES, 'UTF-8') ?><br>Estado: <?= htmlspecialchars((string)$candidato['estado_lista_corte'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($candidato['conjunto'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$cantidadOriginal, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$cantidadOtrosPL, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-right"><?= htmlspecialchars((string)$saldoCandidato, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                      <?php if ($componentesActuales): ?>
                                        <?php foreach ($componentesActuales as $componenteActual): ?>
                                          <?php
                                            $idComputoDetalleActual = !empty($componenteActual['id_computo_detalle'])
                                              ? (int)$componenteActual['id_computo_detalle']
                                              : ((strpos($claveCandidato, 'concepto:') === 0) ? (int)substr($claveCandidato, strlen('concepto:')) : 0);
                                          ?>
                                          <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span><?= htmlspecialchars((string)$componenteActual['cantidad'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-nowrap">
                                              <?php if (!empty(tienePermiso(329))): ?>
                                                <a href="#" class="js-editar-componente" data-id="<?= (int)$componenteActual['id'] ?>" data-computo-detalle="<?= $idComputoDetalleActual ?>" data-concepto="<?= (int)$componenteActual['id_concepto'] ?>" data-conjunto="<?= (int)$componenteActual['id_conjunto'] ?>" data-cantidad="<?= htmlspecialchars((string)$componenteActual['cantidad'], ENT_QUOTES, 'UTF-8') ?>" data-observaciones="<?= htmlspecialchars((string)($componenteActual['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" title="Modificar componente">
                                                  <img src="img/icon_modificar.png" width="20" height="21" alt="Modificar">
                                                </a>
                                              <?php endif; ?>
                                              <?php if (!empty(tienePermiso(330))): ?>
                                                <a href="#" class="js-eliminar-componente" data-id="<?= (int)$componenteActual['id'] ?>" title="Eliminar componente">
                                                  <img src="img/icon_baja.png" width="20" height="21" alt="Eliminar">
                                                </a>
                                              <?php endif; ?>
                                            </span>
                                          </div>
                                        <?php endforeach; ?>
                                      <?php else: ?>
                                        <span class="text-muted">—</span>
                                      <?php endif; ?>
                                    </td>
                                    <td><input type="number" name="cantidades_catalogo[<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>]" min="1" step="1" max="<?= htmlspecialchars((string)$maximoCantidad, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$cantidadIngresada, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm cantidad-catalogo" data-clave="<?= htmlspecialchars($claveCandidato, ENT_QUOTES, 'UTF-8') ?>" data-disponible="<?=$haySaldo ? '1' : '0'?>" <?=($haySaldo && $fuenteSeleccionada) ? '' : 'disabled'?>></td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>

                      <div id="editarComponenteFields" class="row d-none mt-3">
                        <div class="col-sm-6 form-group">
                          <label for="id_concepto">Concepto</label>
                          <select name="id_concepto" id="id_concepto" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option>
                            <?php foreach ($conceptosEdicion as $candidato): ?>
                              <option value="<?= (int)$candidato['id'] ?>"><?= htmlspecialchars($candidato['concepto'] . ($candidato['codigo'] !== '' ? ' (' . $candidato['codigo'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-sm-6 form-group">
                          <label for="id_conjunto_lista_corte">Conjunto de LC</label>
                          <select name="id_conjunto_lista_corte" id="id_conjunto_lista_corte" class="js-example-basic-single col-sm-12">
                            <option value="">Seleccione...</option>
                            <?php foreach ($conjuntosEdicion as $candidato): ?>
                              <option value="<?= (int)$candidato['id'] ?>"><?= htmlspecialchars($candidato['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-sm-6 form-group">
                          <label for="cantidad">Cantidad</label>
                          <input name="cantidad" id="cantidad" type="number" min="1" step="1" class="form-control">
                        </div>
                        <input type="hidden" name="id_computo_detalle" id="id_computo_detalle" value="">
                      </div>
                      <div class="row mt-3">
                        <div class="form-group col-sm-12">
                          <label for="observaciones">Observaciones</label>
                          <textarea name="observaciones" id="observaciones" class="form-control"><?= htmlspecialchars((string)($_POST['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <input type="hidden" name="prod" value="<?= $_REQUEST['prod'] ?? '' ?>">
                      </div>
                    </div>
                    <div class="card-footer">
                      <div class="col-12">
                        <button type="submit" value="1" name="btn1" class="btn btn-success addComponente" <?=$hayCandidatosConSaldo ? '' : 'disabled'?>>Agregar seleccionados y continuar</button>
                        <button type="submit" value="2" name="btn2" class="btn btn-primary addComponente" <?=$hayCandidatosConSaldo ? '' : 'disabled'?>>Agregar seleccionados y volver a Secciones</button>
                        <button type="submit" value="3" name="btn3" id="editComponente" class="btn btn-primary d-none">Modificar</button>
                        <button type="button" id="cancelEditComponente" class="btn btn-danger d-none">Cancelar Modificar</button>
                        <a href='nuevaPackingListSecciones.php?id_packing_list_revision=<?=$data["id_packing_list"]?><?= $prodParam ?>' class="btn btn-light">Volver</a>
                        <?php if ($puedeEnviarAprobacion) { ?>
                        <button type="button" id="btnEnviarAprobacionPK" class="btn btn-primary" data-id="<?=$idPackingListRevision?>">Enviar a aprobación</button>
                        <?php } ?>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- Modal para eliminas componentes -->
        <div class="modal fade" id="eliminarComponente" tabindex="-1" role="dialog" aria-labelledby="exampleModalComponenteLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exampleModalComponenteLabel">Confirmación</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
              </div>
              <div class="modal-body">¿Está seguro que desea eliminar el componente?</div>
              <div class="modal-footer">
                <a href="#" class="btn btn-primary">Eliminar</a>
                <button class="btn btn-light" type="button" data-dismiss="modal" aria-label="Close">Volver</button>
              </div>
            </div>
          </div>
        </div>
        <!-- footer start-->
        <?php include("footer.php"); ?>
      </div>
    </div>
    <!-- latest jquery-->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <!-- feather icon js-->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <!-- Sidebar jquery-->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <!-- Plugins JS start-->
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.buttons.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/jszip.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.colVis.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/pdfmake.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/vfs_fonts.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.autoFill.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.select.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.html5.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/buttons.print.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.responsive.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/responsive.bootstrap4.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.keyTable.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.colReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.fixedHeader.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.rowReorder.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.scroller.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/custom.js"></script>
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/select2/select2.full.min.js"></script>
    <script src="assets/js/select2/select2-custom.js"></script>
    <script>
      const prod = <?= $prod ?? 0 ?>;
      const prodQuery = prod ? '?prod=' + prod : '';
      const prodParam = prod ? '&prod=' + prod : '';
      $(document).ready(function () {
        $('#tablaCatalogoPackingList').DataTable({
          stateSave: false,
          responsive: false,
          autoWidth: false,
          order: [],
          columnDefs: [
            { targets: 0, width: '5%', className: 'text-center' },
            { targets: 3, width: '20%' },
            { targets: 4, width: '8%' },
            { targets: 5, width: '7%' },
            { targets: 6, width: '5%' },
            { targets: 7, width: '18%' },
            { targets: 8, width: '12%' }
          ],
          language: {
            emptyTable: 'No hay conceptos ni conjuntos disponibles',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ opciones',
            infoEmpty: 'Mostrando 0 opciones',
            infoFiltered: '(filtrado de _MAX_ opciones)',
            lengthMenu: 'Mostrar _MENU_ opciones',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron opciones',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
          }
        });

        function habilitarCatalogo(habilitado) {
          $('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"], #tablaCatalogoPackingList .cantidad-catalogo').each(function () {
            var disponible = $(this).data('disponible') === 1;
            $(this).prop('disabled', !habilitado || !disponible);
          });
          if (habilitado) actualizarCantidadesCatalogo();
        }

        function actualizarCantidadesCatalogo() {
          var seleccionadas = new Set($('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"]:checked').map(function () {
            return $(this).data('clave');
          }).get());
          $('#tablaCatalogoPackingList .cantidad-catalogo').each(function () {
            var disponible = $(this).data('disponible') === 1;
            var esSeleccionada = seleccionadas.has($(this).data('clave'));
            $(this).prop('disabled', !disponible || !esSeleccionada);
          });
        }

        $('#tablaCatalogoPackingList').on('change', 'input[name="fuentes_catalogo[]"]', actualizarCantidadesCatalogo);
        $('#tablaCatalogoPackingList tbody').on('click', 'tr', function (event) {
          if ($(event.target).closest('input, a, button').length) return;
          var $checkbox = $(this).find('input[name="fuentes_catalogo[]"]');
          if (!$checkbox.prop('disabled')) $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        });
        actualizarCantidadesCatalogo();

        $('#formPackingListComponentes').on('submit', function (event) {
          if ($('#editComponente').is(':visible')) return;
          if ($('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"]:checked').length === 0) {
            event.preventDefault();
            alert('Marque al menos un concepto o conjunto de LC para agregar.');
            $('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"]:enabled').first().trigger('focus');
          }
        });

        $('#id_concepto').on('change', function () {
          if ($(this).val()) {
            $('#id_conjunto_lista_corte').val('').trigger('change.select2');
            $('#id_computo_detalle').val('');
          }
        });
        $('#id_conjunto_lista_corte').on('change', function () {
          if ($(this).val()) {
            $('#id_concepto').val('').trigger('change.select2');
            $('#id_computo_detalle').val('');
          }
        });

        $(document).on('click', '.js-editar-componente', function (event) {
          event.preventDefault();
          var $componente = $(this);
          var idConcepto = Number($componente.data('concepto')) || '';
          var idConjunto = Number($componente.data('conjunto')) || '';

          $('#id_concepto').val(idConcepto).trigger('change');
          $('#id_conjunto_lista_corte').val(idConcepto ? '' : idConjunto).trigger('change');
          $('#id_computo_detalle').val(idConcepto ? ($componente.data('computo-detalle') || '') : '');
          $('#cantidad').val($componente.data('cantidad')).prop('required', true).focus();
          $('#observaciones').val($componente.data('observaciones') || '');
          $('#editComponente').val($componente.data('id'));

          $('#catalogoPackingListContainer').addClass('d-none');
          $('#editarComponenteFields').removeClass('d-none');
          habilitarCatalogo(false);
          $('.addComponente').addClass('d-none');
          $('#editComponente, #cancelEditComponente').removeClass('d-none');
        });

        $(document).on('click', '.js-eliminar-componente', function (event) {
          event.preventDefault();
          var idComponente = Number($(this).data('id'));
          if (!idComponente) return;
          var modal = $('#eliminarComponente');
          modal.modal('show');
          modal.find('.modal-footer a').attr('href', 'eliminarComponentePackingList.php?id=' + idComponente + prodParam);
        });
      });

      $("#cancelEditComponente").on("click",function(){
        $("select[name='id_concepto']").val("").trigger('change');
        $("select[name='id_conjunto_lista_corte']").val("").trigger('change');
        $('#id_computo_detalle').val('');
        $("input[name='cantidad']").val("").prop('required', false);
        $("textarea[name='observaciones']").val("");

        $('#catalogoPackingListContainer').removeClass('d-none');
        $('#editarComponenteFields').addClass('d-none');
        $('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"], #tablaCatalogoPackingList .cantidad-catalogo').each(function () {
          var disponible = $(this).data('disponible') === 1;
          $(this).prop('disabled', !disponible);
        });
        var fuentesSeleccionadas = new Set($('#tablaCatalogoPackingList input[name="fuentes_catalogo[]"]:checked').map(function () {
          return $(this).data('clave');
        }).get());
        $('#tablaCatalogoPackingList .cantidad-catalogo').each(function () {
          var disponible = $(this).data('disponible') === 1;
          $(this).prop('disabled', !disponible || !fuentesSeleccionadas.has($(this).data('clave')));
        });
        $('#tablaCatalogoPackingList').DataTable().columns.adjust().draw(false);
        $(".addComponente").removeClass("d-none");
        $("#editComponente").addClass("d-none").val("");
        $("#cancelEditComponente").addClass("d-none");
      })

      $("#btnEnviarAprobacionPK").on("click", function(){
        const idRevision = $(this).data("id");
        if (!idRevision || !confirm("¿Enviar este Packing List y la tarea asociada a aprobación?")) {
          return;
        }
        $.ajax({
          type: "POST",
          url: "modificarEstadoPackingList.php",
          data: {idPosicion: idRevision, idEstado: 2}
        }).done(function(){
          window.location.href = "listarPackingList.php" + prodQuery;
        }).fail(function(xhr){
          alert(xhr.responseText || "No se pudo enviar el Packing List a aprobación.");
        });
      });

    </script>
  </body>
</html>
