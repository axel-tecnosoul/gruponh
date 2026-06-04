<?php
include("permisos.php");
require("config.php");
if (empty($_SESSION['user'])) {
  header("Location: index.php");
  die("Redirecting to index.php");
}
require 'database.php';

$id = null;
if (!empty($_GET['id'])) {
  $id = $_REQUEST['id'];
}

if (null==$id) {
  header("Location: listarOrdenesTrabajo.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  
  // Obtener datos de la orden de trabajo
  $sql = "SELECT ot.id, ot.fecha, ot.fecha_hora_ultima_modificacion, ot.id_lista_corte, lc.nombre nombre_lc, lc.id_proyecto, u.usuario FROM ordenes_trabajo ot INNER JOIN listas_corte lc ON ot.id_lista_corte = lc.id LEFT JOIN usuarios u ON ot.id_usuario = u.id WHERE ot.id = ?";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  if (!$data) {
    header("Location: listarOrdenesTrabajo.php");
    die();
  }
  
  // Obtener datos del proyecto y sitio
  $sql2 = "SELECT p.id, p.nombre, p.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio, s.id_empresa FROM proyectos p INNER JOIN sitios s ON s.id = p.id_sitio WHERE p.id = ?";
  $q2 = $pdo->prepare($sql2);
  $q2->execute([$data['id_proyecto']]);
  $data2 = $q2->fetch(PDO::FETCH_ASSOC);
  
  $nombreProyecto = $data2['nombre'];
  $idSitio = $data2['id_sitio'];
  $nroSitio = $data2['nro_sitio'];
  $nroSubsitio = $data2['nro_subsitio'];
  $nroProyecto = $data2['nro_proyecto'];
  $idEmpresa = $data2['id_empresa'];
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
    <link rel="stylesheet" type="text/css" href="assets/css/datatables.css">
    <style>
      .bordered-div {
        border: 2px solid black;
        padding: 10px;
        margin: 2px;
      }
      .bordered-div-thin {
        border: 1px solid black;
        padding: 10px;
        margin: 2px;
      }
      th, td {
        padding: 0.75rem;
        text-align: left;
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
      <!-- Page Header Start-->
      <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Ver Orden de Trabajo";
          include_once("head_page.php")?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-body">
                    <div class="row">
                      <div class="col">
                        <div class="form-group row">
                          <div class="col-sm-8 bordered-div">
                            <h3><b>ORDEN DE TRABAJO</b></h3>
                          </div>
                          <div class="col-sm-3 bordered-div">
                            <h6><b>Nº <?=$data['id']?></b></h6>
                          </div>
                        </div><?php
                        $logo = "logo_np.png";
                        if ($idEmpresa == 1) {
                          $logo = "logo_nc.png";
                        }?>
                        <div class="form-group row">
                          <div class="col-sm-11 bordered-div">
                            <img src="img/<?=$logo;?>" width="500px">
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-5 bordered-div">
                            <b>Proyecto:</b> <?=$nombreProyecto;?><br>
                            <b>Número:</b> <?=$nroSitio."_".$nroSubsitio."_".$nroProyecto;?><br>
                            <b>Lista de Corte:</b> <?=$data['nombre_lc'];?><br>
                          </div>
                          <div class="col-sm-6 bordered-div">
                            <b>Fecha:</b> <?=date("d-m-Y", strtotime($data['fecha']));?><br>
                            <b>Usuario:</b> <?=$data['usuario'];?><br>
                            <b>Cantidad LC:</b> -<br>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11 p-0">
                            <table class="display" border="1" style="width: 100%;">
                              <thead>
                                <tr>
                                  <th align="center">Item</th>
                                  <th align="center">Conjunto</th>
                                  <th align="center">Posición</th>
                                  <th align="center">Material</th>
                                  <th align="center">Procesos</th>
                                  <th align="center">Cant. Pedida</th>
                                  <th align="center">Liberadas</th>
                                  <th align="center">Reproceso</th>
                                  <th align="center">Rechazadas</th>
                                  <th align="center">Estado</th>
                                </tr>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $sql = "SELECT otd.id AS id_detalle_ot, lcc.nombre, lcc.cantidad AS cant_conj, lcp.posicion, lcp.cantidad AS cant_pos, otd.cantidad AS cant_pedida, m.concepto, GROUP_CONCAT(tp.tipo SEPARATOR ',') AS procesos, eotp.estado, eotp.id AS id_estado, cant_liberadas, cant_reproceso, cant_rechazadas, date_format(COALESCE(otd.fecha_hora_ultima_modificacion, otd.fecha),'%d/%m/%y') fecha, u.usuario, lcp.id AS id_posicion FROM ordenes_trabajo_detalle otd left join usuarios u on u.id = otd.id_usuario INNER JOIN lista_corte_posiciones lcp ON otd.id_posicion=lcp.id INNER JOIN listas_corte_conjuntos lcc ON lcp.id_lista_corte_conjunto=lcc.id INNER JOIN lista_corte_procesos lcpr ON lcpr.id_lista_corte_posicion=lcp.id INNER JOIN tipos_procesos tp ON lcpr.id_tipo_proceso=tp.id INNER JOIN materiales m ON lcp.id_material=m.id INNER JOIN estados_orden_trabajo_posicion eotp ON otd.id_estado_orden_trabajo_posicion=eotp.id WHERE otd.id_orden_trabajo = ".$_GET['id']." GROUP BY lcp.id ORDER BY lcp.id";
                                $b=1;
                                foreach ($pdo->query($sql) as $row) {
                                  ?>
                                  <tr>
                                    <td><?=$b?></td>
                                    <td><?=$row["nombre"]?></td>
                                    <td><?=$row["posicion"]?></td>
                                    <td><?=$row["concepto"]?></td>
                                    <td><?=$row["procesos"]?></td>
                                    <td class="text-right"><?=$row["cant_pedida"]?></td>
                                    <td class="text-right"><?=$row["cant_liberadas"]?></td>
                                    <td class="text-right"><?=$row["cant_reproceso"]?></td>
                                    <td class="text-right"><?=$row["cant_rechazadas"]?></td>
                                    <td><?=$row["estado"]?></td>
                                  </tr><?php
                                  $b++;
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid Ends-->
        </div>
        <!-- footer start-->
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
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
    <!-- Plugin used-->
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <!-- Plugins JS Ends-->
    <script src="https://cdn.datatables.net/plug-ins/1.10.15/i18n/Spanish.json"></script>
  </body>
</html>
<script>window.print();</script>
