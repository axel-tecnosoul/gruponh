<?php
require 'config.php';
if (empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

require 'database.php';
require_once 'despachos_lib.php';

$idDespacho = filter_input(INPUT_GET, 'id_despacho', FILTER_VALIDATE_INT);
if (!$idDespacho || $idDespacho < 1) {
  header('Location: listarDespachos.php');
  exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$qDespacho = $pdo->prepare(
  "SELECT des.id, des.id_proyecto, des.id_estado_despacho,
          DATE_FORMAT(des.fecha, '%d/%m/%Y') AS fecha,
          des.nro_remito, des.transporte, des.chofer,
          e.estado, p.nombre AS proyecto, p.nro AS nro_proyecto,
          s.nro_sitio, s.nro_subsitio, cliente.nombre AS cliente,
          operario.nombre AS operario
   FROM despachos des
   INNER JOIN estados_despacho e ON e.id = des.id_estado_despacho
   LEFT JOIN proyectos p ON p.id = des.id_proyecto
   LEFT JOIN sitios s ON s.id = p.id_sitio
   LEFT JOIN cuentas cliente ON cliente.id = p.id_cliente
   INNER JOIN cuentas operario ON operario.id = des.id_cuenta_operario
   WHERE des.id = ?"
);
$qDespacho->execute([$idDespacho]);
$despacho = $qDespacho->fetch(PDO::FETCH_ASSOC);
if (!$despacho) {
  Database::disconnect();
  header('Location: listarDespachos.php');
  exit;
}
if ((int)$despacho['id_estado_despacho'] !== 1) {
  Database::disconnect();
  header('Location: verDespacho.php?id=' . $idDespacho);
  exit;
}
if (empty($despacho['id_proyecto'])) {
  Database::disconnect();
  header('Location: modificarDespacho.php?id=' . $idDespacho);
  exit;
}

$qOcc = $pdo->prepare(
  "SELECT o.numero
   FROM despachos_occ d_o
   INNER JOIN occ o ON o.id = d_o.id_occ
   WHERE d_o.id_despacho = ?
   ORDER BY o.numero"
);
$qOcc->execute([$idDespacho]);
$numerosOcc = $qOcc->fetchAll(PDO::FETCH_COLUMN);
if (!$numerosOcc && !empty($despacho['detalle_oc'])) {
  $numerosOcc = [(string)$despacho['detalle_oc']];
}

$componentes = despachoObtenerComponentesProyecto($pdo, (int)$despacho['id_proyecto'], $idDespacho);
$componentesPorId = [];
foreach ($componentes as $componente) {
  $componentesPorId[(int)$componente['id']] = $componente;
}
$errorFormulario = '';
$cantidadesFormulario = [];
$bultosFormulario = [];
$lineasPendientes = despachoObtenerLineasPendientes($pdo, $idDespacho);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cantidadesFormulario = is_array($_POST['cantidad'] ?? null) ? $_POST['cantidad'] : [];
  $bultosFormulario = is_array($_POST['bulto'] ?? null) ? $_POST['bulto'] : [];
  $entradas = [];

  foreach ($cantidadesFormulario as $idComponentePost => $cantidadPost) {
    $idComponente = filter_var($idComponentePost, FILTER_VALIDATE_INT);
    if (!$idComponente || !isset($componentesPorId[$idComponente])) {
      continue;
    }

    $cantidadTexto = trim((string)$cantidadPost);
    if ($cantidadTexto === '') {
      continue;
    }
    if (!ctype_digit($cantidadTexto)) {
      $errorFormulario = 'Las cantidades a despachar deben ser números enteros iguales o mayores que cero.';
      break;
    }

    $cantidad = (int)$cantidadTexto;
    if ($cantidad === 0) {
      continue;
    }

    $componente = $componentesPorId[$idComponente];
    if ($cantidad > (float)$componente['saldo']) {
      $errorFormulario = 'La cantidad de un componente supera su saldo disponible. Actualiza la pantalla y vuelve a intentarlo.';
      break;
    }

    $etiquetaBulto = trim((string)($bultosFormulario[$idComponente] ?? ''));
    if ($etiquetaBulto === '') {
      $errorFormulario = 'Indica el identificador del bulto para cada componente que vas a despachar.';
      break;
    }
    if (strlen($etiquetaBulto) > 99) {
      $errorFormulario = 'El identificador del bulto no puede superar los 99 caracteres.';
      break;
    }

    $entradas[] = [
      'id_componente' => $idComponente,
      'cantidad' => $cantidad,
      'bulto' => $etiquetaBulto,
      'tipo' => (int)$componente['id_tipo_bulto'],
    ];
  }

  if ($errorFormulario === '' && !$entradas) {
    $errorFormulario = 'No se guardó la carga: indica una cantidad mayor que cero en al menos un componente. Las etiquetas de bulto solo se guardan junto con una cantidad.';
  }

  if ($errorFormulario === '') {
    try {
      $pdo->beginTransaction();
      $qEstado = $pdo->prepare("SELECT id_proyecto, id_estado_despacho FROM despachos WHERE id = ? FOR UPDATE");
      $qEstado->execute([$idDespacho]);
      $estadoActual = $qEstado->fetch(PDO::FETCH_ASSOC);
      if (!$estadoActual || (int)$estadoActual['id_estado_despacho'] !== 1) {
        throw new RuntimeException('El despacho dejó de estar pendiente y ya no puede modificarse.');
      }
      $pdo->prepare("SELECT id FROM proyectos WHERE id = ? FOR UPDATE")->execute([$estadoActual['id_proyecto']]);

      $componentesActuales = despachoObtenerComponentesProyecto($pdo, (int)$estadoActual['id_proyecto'], $idDespacho);
      $actualesPorId = [];
      foreach ($componentesActuales as $componente) {
        $actualesPorId[(int)$componente['id']] = $componente;
      }
      foreach ($entradas as $entrada) {
        $componenteActual = $actualesPorId[$entrada['id_componente']] ?? null;
        if (!$componenteActual || $entrada['cantidad'] > (float)$componenteActual['saldo']) {
          throw new RuntimeException('El saldo cambió mientras editabas. Actualiza la pantalla y revisa las cantidades.');
        }
      }

      $pdo->prepare(
        "DELETE FROM bultos_detalle
         WHERE id_bulto IN (SELECT id FROM bultos WHERE id_despacho = ?)"
      )->execute([$idDespacho]);
      $pdo->prepare("DELETE FROM bultos WHERE id_despacho = ?")->execute([$idDespacho]);

      $gruposBulto = [];
      foreach ($entradas as $entrada) {
        $claveBulto = strtolower($entrada['bulto']);
        if (!isset($gruposBulto[$claveBulto])) {
          $gruposBulto[$claveBulto] = [
            'nombre' => $entrada['bulto'],
            'componentes' => [],
          ];
        }
        $gruposBulto[$claveBulto]['componentes'][] = $entrada;
      }

      $qBulto = $pdo->prepare(
        "INSERT INTO bultos (id_despacho, numero, nombre, color, id_estado_bulto)
         VALUES (?, ?, ?, '', 1)"
      );
      $qDetalle = $pdo->prepare(
        "INSERT INTO bultos_detalle
           (id_bulto, id_tipo_bulto, id_origen_bulto, id_detalle_bulto, cantidad)
         VALUES (?, ?, 0, ?, ?)"
      );
      $numeroBulto = 1;
      foreach ($gruposBulto as $grupo) {
        $qBulto->execute([$idDespacho, (string)$numeroBulto, $grupo['nombre']]);
        $idBulto = (int)$pdo->lastInsertId();
        foreach ($grupo['componentes'] as $entrada) {
          $qDetalle->execute([
            $idBulto,
            $entrada['tipo'],
            $entrada['id_componente'],
            $entrada['cantidad'],
          ]);
        }
        $numeroBulto++;
      }

      $qLog = $pdo->prepare(
        "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
         VALUES (NOW(), ?, ?, 'Despachos', ?)"
      );
      $qLog->execute([
        $_SESSION['user']['id'],
        'Actualización de carga pendiente del despacho #' . $idDespacho,
        'nuevoBultoDespacho.php?id_despacho=' . $idDespacho,
      ]);
      $pdo->commit();
      Database::disconnect();
      header('Location: nuevoBultoDespacho.php?id_despacho=' . $idDespacho . '&guardado=1');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $errorFormulario = $e->getMessage();
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  foreach ($lineasPendientes as $idComponente => $linea) {
    $cantidadesFormulario[$idComponente] = $linea['cantidad'];
    $bultosFormulario[$idComponente] = $linea['bulto'];
  }
}

$escape = static function ($valor): string {
  return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
Database::disconnect();
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <?php include 'head_forms.php'; ?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      #tablaComponentesDespacho th { white-space: nowrap; }
      #tablaComponentesDespacho td { vertical-align: middle; }
      .cantidad-despacho, .bulto-despacho { min-width: 105px; }
      .observacion-componente { min-width: 170px; white-space: normal; }
      .resumen-despacho .form-group { margin-bottom: .25rem; }
      .resumen-despacho .form-control-plaintext { padding-top: .1rem; padding-bottom: .1rem; }
    </style>
  </head>
  <body>
    <div class="page-wrapper">
      <?php include 'header.php'; ?>
      <div class="page-body-wrapper">
        <?php include 'menu.php'; ?>
        <div class="page-body">
          <?php $ubicacion = 'Carga del despacho'; include_once 'head_page.php'; ?>
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5>Cabecera del despacho #<?=$escape($despacho['id'])?> — <?=$escape($despacho['estado'])?></h5>
                  </div>
                  <div class="card-body">
                    <?php if (isset($_GET['guardado'])): ?>
                      <div class="alert alert-success" role="status">La carga pendiente se guardó correctamente.</div>
                    <?php endif; ?>
                    <?php if ($errorFormulario !== ''): ?>
                      <div class="alert alert-danger" role="alert"><?=$escape($errorFormulario)?></div>
                    <?php endif; ?>
                    <?php if (!$componentes): ?>
                      <div class="alert alert-info" role="status">El proyecto no tiene componentes en packing lists vigentes.</div>
                    <?php elseif (count(array_filter($componentes, static function ($componente) { return (float)$componente['saldo'] > 0; })) === 0): ?>
                      <div class="alert alert-warning" role="status">Los componentes cargados no tienen saldo disponible. Revisa si falta liberar producción de lista de corte o reservar material de cómputo.</div>
                    <?php endif; ?>
                    <div class="row resumen-despacho">
                      <div class="col-lg-6">
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Proyecto</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape(implode('-', array_filter([$despacho['nro_sitio'], $despacho['nro_subsitio'], $despacho['nro_proyecto']])) . ': ' . $despacho['proyecto'])?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Cliente</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['cliente'])?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">OCC</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape(implode(', ', $numerosOcc))?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Operario</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['operario'])?></div></div></div>
                      </div>
                      <div class="col-lg-6">
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Fecha</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['fecha'])?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">N.º remito</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['nro_remito'])?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Transporte</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['transporte'])?></div></div></div>
                        <div class="form-group row"><label class="col-sm-4 col-form-label">Chofer</label><div class="col-sm-8"><div class="form-control-plaintext"><?=$escape($despacho['chofer'])?></div></div></div>
                      </div>
                    </div>

                    <form method="post" action="nuevoBultoDespacho.php?id_despacho=<?=(int)$idDespacho?>">
                      <div class="dt-ext table-responsive">
                        <table class="table table-sm table-bordered display" id="tablaComponentesDespacho" style="width:100%">
                          <thead>
                            <tr>
                              <th>Packing / revisión</th>
                              <th>Componente</th>
                              <th>Cantidad</th>
                              <th>Observaciones</th>
                              <th>Cant. disponible</th>
                              <th>Cant. despachada</th>
                              <th>Saldo</th>
                              <th>A despachar</th>
                              <th>Bulto</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($componentes as $componente):
                              $idComponente = (int)$componente['id'];
                              $cantidadFormulario = $cantidadesFormulario[$idComponente] ?? '';
                              $bultoFormulario = $bultosFormulario[$idComponente] ?? '';
                            ?>
                              <tr>
                                <td><?= $escape($componente['numero_packing'] . ' / Rev. ' . $componente['nro_revision'] . ($componente['revision_vigente'] ? '' : ' (histórica)')) ?></td>
                                <td><?= $escape($componente['nombre_componente']) ?></td>
                                <td class="text-right"><?=number_format((float)$componente['cantidad'], 0, ',', '.')?></td>
                                <td class="observacion-componente"><?=nl2br($escape($componente['observaciones'] ?? ''))?></td>
                                <td class="text-right"><?=number_format((float)$componente['cantidad_disponible'], 0, ',', '.')?></td>
                                <td class="text-right"><?=number_format((float)$componente['cantidad_despachada_calculada'], 0, ',', '.')?></td>
                                <td class="text-right"><?=number_format((float)$componente['saldo'], 0, ',', '.')?></td>
                                <td><input name="cantidad[<?=$idComponente?>]" type="number" min="0" max="<?=(int)$componente['saldo']?>" step="1" class="form-control form-control-sm cantidad-despacho" value="<?=$escape($cantidadFormulario)?>" aria-label="Cantidad a despachar" <?=((float)$componente['saldo'] <= 0 && (float)$cantidadFormulario <= 0) ? 'disabled title="Sin saldo disponible"' : ''?>></td>
                                <td><input name="bulto[<?=$idComponente?>]" type="text" maxlength="99" class="form-control form-control-sm bulto-despacho" value="<?=$escape($bultoFormulario)?>" aria-label="Identificador del bulto"></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                      <div class="card-footer px-0 pb-0">
                        <button class="btn btn-primary" type="submit" <?=!$componentes ? 'disabled' : ''?>>Guardar pendiente</button>
                        <a href="listarDespachos.php" class="btn btn-light">Volver a despachos</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'footer.php'; ?>
      </div>
    </div>
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/bootstrap/popper.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.js"></script>
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/config.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/datatable/datatables/jquery.dataTables.min.js"></script>
    <script src="assets/js/datatable/datatable-extension/dataTables.bootstrap4.min.js"></script>
    <script>
      $(function() {
        $('#tablaComponentesDespacho').DataTable({
          stateSave: false,
          responsive: false,
          paging: false,
          info: false,
          lengthChange: false,
          order: [],
          language: {
            decimal: '', emptyTable: 'No hay componentes disponibles',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ Registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 Registros',
            infoFiltered: '(Filtrado de _MAX_ total registros)',
            lengthMenu: 'Mostrar _MENU_ Registros', loadingRecords: 'Cargando...',
            processing: 'Procesando...', search: 'Buscar:', zeroRecords: 'No hay resultados',
            paginate: {first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior'}
          }
        });

        $('.cantidad-despacho').on('input', function() {
          const maximo = parseInt($(this).attr('max'), 10) || 0;
          const valor = parseInt($(this).val(), 10) || 0;
          if (valor > maximo) {
            $(this).val(maximo);
          } else if (valor < 0) {
            $(this).val(0);
          }
        });
      });
    </script>
  </body>
</html>
