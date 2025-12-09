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
  header("Location: listarCompras.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT c.id, c.id_pedido, c.id_cuenta_proveedor, c.fecha_emision, c.fecha_entrega, c.id_forma_pago, c.id_estado_compra, c.nro_revision, c.total, c.comentarios, pe.lugar_entrega, c.id_moneda, c.tipo_cambio_dia, pe.id_proyecto, pe.id_computo, cu.nombre, cu.direccion, cu.telefono, cu.cuit, fp.forma_pago,cu.contacto, c.iva, c.descuento FROM compras c inner join pedidos pe on pe.id = c.id_pedido inner join cuentas cu on cu.id = c.id_cuenta_proveedor inner join formas_pago fp on fp.id = c.id_forma_pago WHERE c.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  $signo = '$';
  if ($data['id_moneda'] == 1) {
    $signo = 'u$s';	
  }
		
  if (empty($data['id_computo'])) {
    $sql2 = "SELECT pro.nombre, pro.solicitante cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,pro.observaciones FROM pedidos pe inner join proyectos pro on pro.id = pe.id_proyecto inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
    $q2 = $pdo->prepare($sql2);
    $q2->execute([$data['id_pedido']]);
    $data2 = $q2->fetch(PDO::FETCH_ASSOC);
    $nombreProyecto = $data2['nombre'];
    $idSitio = $data2['id_sitio'];
    $nroSitio = $data2['nro_sitio'];
    $nroSubsitio = $data2['nro_subsitio'];
    $nroProyecto = $data2['nro_proyecto'];
    $cuentaSolicitante = $data2['cuenta_solicitante'];
    $observaciones = $data2['observaciones'];
  } else {
    $sql2 = "SELECT pro.nombre, cu.nombre cuenta_solicitante, pro.nro nro_proyecto, s.nro_sitio, s.id id_sitio, s.nro_subsitio,t.observaciones FROM pedidos pe inner join computos c on c.id = pe.id_computo inner join tareas t on t.id = c.id_tarea inner join proyectos pro on pro.id = t.id_proyecto inner join cuentas cu on cu.id = c.id_cuenta_solicitante inner join sitios s on s.id = pro.id_sitio WHERE pe.id = ? ";
    $q2 = $pdo->prepare($sql2);
    $q2->execute([$data['id_pedido']]);
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
          $ubicacion="Ver Orden de Compra";
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
                            <h3><b>ORDEN DE SUMINISTRO</b></h3>
                          </div>
                          <div class="col-sm-3 bordered-div">
                            <h6><b>Nº <?=$data['id']." / ".$data['nro_revision'] ?></b></h6>
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
                          <div class="col-sm-5 bordered-div">
                            <b>Proveedor:</b> <?=$data['nombre'];?><br>
                            <b>Domicilio:</b> <?=$data['direccion'];?><br>
                            <b>Teléfono:</b> <?=$data['telefono'];?><br>
                            <b>CUIT:</b> <?=$data['cuit'];?><br>
                            <b>Contacto:</b> <?=$data['contacto'];?><br>
                          </div>
                          <div class="col-sm-6 bordered-div">
                            <b>Fecha:</b> <?=date("d-m-Y", strtotime($data['fecha_emision']));?><br>
                            <b>Proyecto:</b> <?=$nombreProyecto;?><br>
                            <b>Nro:</b> <?=$nroSitio."_".$nroSubsitio."_".$nroProyecto;?><br>
                            <b>Pedido:</b> <?=$data['id_pedido'];?><br>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11 p-0">
                            <table class="display" border="1" style="width: 100%;">
                              <thead>
                                <tr>
                                  <th align="center">Item</th>
                                  <th align="center">Concepto</th>
                                  <th align="center">Cantidad</th>
                                  <th align="center">Unidad</th>
                                  <th align="center">Peso Total Kg</th>
                                  <th align="center">P/Unitario</th>
                                  <th align="center">P/Total</th>
                                </tr>
                              </thead>
                              <tbody><?php
                                $pdo = Database::connect();
                                $sql = " SELECT d.id, m.concepto, d.cantidad, u.unidad_medida,d.id_material,d.precio,d.entregado,d.precio_kg,m.peso_metro,m.largo FROM compras_detalle d inner join materiales m on m.id = d.id_material inner join unidades_medida u on u.id = d.id_unidad_medida WHERE d.id_compra = ".$_GET['id'];
                                $b=1;
                                foreach ($pdo->query($sql) as $row) {
                                  
                                  $precio = number_format($row["precio"],2);
                                  $preciokg = number_format($row["precio_kg"],2);
                                  $subtotal = number_format($row["precio"]*$row["cantidad"],2);
                                  
                                  $peso = $row["peso_metro"]*($row["largo"]/1000);?>
                                  <tr>
                                    <td><?=$b?></td>
                                    <td><?=$row["concepto"]?></td>
                                    <td><?=$row["cantidad"]?></td>
                                    <td><?=$row["unidad_medida"]?></td>
                                    <td><?=number_format($peso,2)?></td>
                                    <td>$<?=$precio?></td>
                                    <td>$<?=$subtotal?></td>
                                  </tr><?php
                                  $b++;
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
                          <div class="col-sm-11 bordered-div-thin">
                            <b>Subtotal:</b> <?=$signo.number_format($data['total'],2);?><br>
                            <b>Iva:</b> <?=$signo.number_format($data['iva'],2);?><br>
                            <b>Descuento:</b> <?=$signo.number_format($data['descuento'],2);?><br>
                            <b>Total:</b> <?=$signo.number_format($data['total']+$data['iva']-$data['descuento'],2);?><br>
                          </div>
                        </div>
                        <div class="form-group row">
                          <div class="col-sm-11 p-0">
                            <b>Fecha de entrega:</b> <?=date("d-m-Y", strtotime($data['fecha_entrega']));?><br>
                            <b>Condición de pago:</b> <?=$data['forma_pago'];?><br>
                            <b>Lugar de entrega:</b> <?=$data['lugar_entrega'];?><br>
                            <b>Comentarios:</b> <?=$data['comentarios'];?><br><br>
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