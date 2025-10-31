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
  header("Location: listarPedidos.php");
}
    
if (!empty($_POST)) {

} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT pe.id, pe.id_computo, c.id_tarea, c.id_cuenta_solicitante, pe.fecha, pe.lugar_entrega, pe.id_cuenta_recibe, pro.nombre, cu.nombre cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,t.observaciones FROM pedidos pe LEFT join computos c on c.id = pe.id_computo LEFT join tareas t on t.id = c.id_tarea LEFT join proyectos pro on pro.id = t.id_proyecto LEFT join cuentas cu on cu.id = c.id_cuenta_solicitante LEFT join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  if (!empty($data['id_computo'])) {
    $sql2 = "SELECT pro.nombre, cu.nombre cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,t.observaciones FROM pedidos pe inner join computos c on c.id = pe.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pro on pro.id = t.id_proyecto inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
    $q2 = $pdo->prepare($sql2);
    $q2->execute([$id]);
    $data2 = $q2->fetch(PDO::FETCH_ASSOC);
    $nombreProyecto = $data2['nombre'];
    $idSitio = $data2['id_sitio'];
    $nroSitio = $data2['nro_sitio'];
    $nroSubsitio = $data2['nro_subsitio'];
    $nroProyecto = $data2['nro_proyecto'];
    $cuentaSolicitante = $data2['cuenta_solicitante'];
    $observaciones = $data2['observaciones'];
  } else {
    $sql2 = "SELECT pro.nombre, pro.solicitante cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,pro.observaciones FROM pedidos pe inner join proyectos pro on pro.id = pe.id_proyecto inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
    $q2 = $pdo->prepare($sql2);
    $q2->execute([$id]);
    $data2 = $q2->fetch(PDO::FETCH_ASSOC);
    $nombreProyecto = $data2['nombre'];
    $idSitio = $data2['id_sitio'];
    $nroSitio = $data2['nro_sitio'];
    $nroSubsitio = $data2['nro_subsitio'];
    $nroProyecto = $data2['nro_proyecto'];
    $cuentaSolicitante = $data2['cuenta_solicitante'];
    $observaciones = $data2['observaciones'];
  }

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
        border: 2px solid black; /* 2px width, solid style, black color */
        padding: 10px; /* Optional: add some padding inside the div */
			  margin: 2px;
      }
		  .bordered-div-thin {
        border: 1px solid black; /* 2px width, solid style, black color */
        padding: 10px; /* Optional: add some padding inside the div */
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
          $ubicacion="Ver Pedido";
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
                            <h3><b>NOTA DE PEDIDO</b></h3>
                          </div>
                          <div class="col-sm-3 bordered-div">
                            <h6><b>Nº <?=$data['id']; ?></b></h6>
                          </div>
                        </div><?php
                        $logo = "logo_np.png";

                        $sql666 = "SELECT id_empresa from sitios where id = ".$idSitio;
                        $q666 = $pdo->prepare($sql666);
                        $q666->execute();
                        $data666 = $q666->fetch(PDO::FETCH_ASSOC);
                        if ($data666['id_empresa'] == 1) {
                          $logo = "logo_nc.png";
                        }?>
                        <div class="form-group row">
                          <div class="col-sm-11 bordered-div">
                            <img src="img/<?=$logo;?>" width="500px">
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11 bordered-div">
                            <b>Fecha:</b> <?=$data['fecha'];?><br>
                            <b>Proyecto:</b> <?=$data['nombre'];?><br>
                            <b>Nro:</b> <?=$data['nro_sitio']."_".$data['nro_subsitio']."_".$data['nro_proyecto'];?><br>
                            <b>Solicitó:</b> <?=$data['cuenta_solicitante'];?><br>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11">
                            <table class="display" border="1" style="width: 100%;">
                              <thead>
                                <tr>
                                  <th align="center">Código</th>
                                  <th align="center">Unidad</th>
                                  <th align="center">Cantidad</th>
                                  <th align="center">Descripción</th>
                                </tr>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $sql = " SELECT m.codigo, u.unidad_medida, d.cantidad, m.concepto FROM pedidos_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_pedido = ".$_GET['id'];
                                foreach ($pdo->query($sql) as $row) {?>
                                  <tr>
                                    <td><?=$row["codigo"]?></td>
                                    <td><?=$row["unidad_medida"]?></td>
                                    <td><?=$row["cantidad"]?></td>
                                    <td><?=$row["concepto"]?></td>
                                  </tr><?php
                                }
                                Database::disconnect();?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11 bordered-div-thin"><?php
                            $sql666 = "SELECT valor from parametros where id = 9 ";
                            $q666 = $pdo->prepare($sql666);
                            $q666->execute();
                            $data666 = $q666->fetch(PDO::FETCH_ASSOC);
                            echo $data666['valor'];?>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-12">
                            <b>Comentarios:</b> <?=$data['observaciones'];?><br><br>
                            <b>Lugar de entrega:</b> <?=$data['lugar_entrega'];?><br>
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