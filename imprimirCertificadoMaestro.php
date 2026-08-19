<?php
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
  header("Location: listarOrdenesCompraClientes.php");
}

if (!empty($_POST)) {
} else {
  $pdo = Database::connect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = "SELECT cm.id,occ.numero AS numero_occ,date_format(cm.fecha_emision,'%d/%m/%y') AS fecha_emision,date_format(cm.fecha_inicio,'%d/%m/%y') AS fecha_inicio,date_format(cm.fecha_fin,'%d/%m/%y') AS fecha_fin,m.moneda,cm.cotizacion_dolar,cm.monto_total,cm.monto_acumulado_avances,cm.monto_acumulado_anticipos,cm.monto_acumulado_desacopios,cm.monto_acumulado_descuentos,cm.monto_acumulado_ajustes,cm.observaciones FROM certificados_maestros cm INNER JOIN occ ON cm.id_occ=occ.id INNER JOIN monedas m ON cm.id_moneda=m.id WHERE cm.id = ? ";
  $q = $pdo->prepare($sql);
  $q->execute([$id]);
  $data = $q->fetch(PDO::FETCH_ASSOC);
  
  Database::disconnect();
}?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include('head_forms.php');?>
    <style>
      .tabla-detalle-cm {
        border-collapse: separate;
        border-spacing: 0;
      }
      @media print {
        @page {
          size: landscape;
          margin: 10mm;
        }

        .page-wrapper,
        .page-body,
        .container-fluid,
        .card,
        .card-body,
        .row,
        .col-sm-12 {
          width: 100% !important;
          max-width: 100% !important;
          margin-left: 0 !important;
          margin-right: 0 !important;
          padding-left: 0 !important;
          padding-right: 0 !important;
        }

        .tabla-detalle-cm {
          width: 100% !important;
          max-width: 100% !important;
          table-layout: auto;
          font-size: 8pt;
          overflow-wrap: anywhere;
        }

        .tabla-detalle-cm col {
          width: auto;
          max-width: 10%;
        }

        .tabla-detalle-cm th,
        .tabla-detalle-cm td {
          max-width: 10%;
          padding: 2px 3px;
          white-space: normal;
        }

        .tabla-detalle-cm th,
        .tabla-detalle-cm td {
          overflow-wrap: anywhere;
          word-break: normal;
        }

        .encabezado-cm .row {
          display: -webkit-box;
          display: -ms-flexbox;
          display: flex;
        }
        .encabezado-cm .col-md-6 {
          -webkit-box-flex: 0 !important;
          -ms-flex: 0 0 50% !important;
          flex: 0 0 50% !important;
          max-width: 50% !important;
        }
        .encabezado-cm .col-md-12 {
          -webkit-box-flex: 0 !important;
          -ms-flex: 0 0 100% !important;
          flex: 0 0 100% !important;
          max-width: 100% !important;
        }
        .tabla-detalle-cm {
          border-collapse: separate !important;
          border-spacing: 0;
        }
      }
    </style>
  </head>
  <body>
    <!-- Loader ends-->
    <!-- page-wrapper Start-->
    <div class="page-wrapper">
    
        <!-- Page Sidebar Start-->
        <!-- Right sidebar Ends-->
        <div class="page-body"><?php
          $ubicacion="Certificado Maestro";?>
          <!-- Container-fluid starts-->
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-12">
                <div class="card">
                  <div class="card-header">
                    <h5><?=$ubicacion?></h5>
                  </div>
                  <form class="form theme-form" role="form" method="post" action="#">
                    <div class="card-body">
                      <div class="row">
                        <div class="col">
                          <div class="encabezado-cm">
                          <div class="row">
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Orden de Compra Cliente:</label>
                              <span><?=$data['numero_occ']?></span>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Número:</label>
                              <span><?=$data['id'];?></span>
                            </div>
                          </div>
                          <div class="row mt-3">
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Emisión:</label>
                              <span><?=$data['fecha_emision'];?></span>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Inicio:</label>
                              <span><?=$data['fecha_inicio'];?></span>
                            </div>
                          </div>
                          <div class="row mt-3">
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Fecha Fin:</label>
                              <span><?=$data['fecha_fin'];?></span>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Moneda:</label>
                              <span><?=$data['moneda']?></span>
                            </div>
                          </div>
                          <div class="row mt-3">
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Cotización Dólar:</label>
                              <span>$<?=$data['cotizacion_dolar'];?></span>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Monto Total:</label>
                              <span>$<?=number_format($data['monto_total'],2);?></span>
                            </div>
                          </div>
                          <div class="row mt-3">
                            <div class="col-md-12 d-flex align-items-center">
                              <label class="col-form-label mb-0 mr-1">Observaciones:</label>
                              <span><?=$data['observaciones'];?></span>
                            </div>
                          </div>
                          </div>
                          <div class="row">
                            <div class="col-sm-12">
                              <h5>Detalle del Certificado Maestro</h5>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-sm-12">
<table class="table table-sm table-bordered tabla-detalle-cm">
                                <colgroup>
                                  <col class="cm-col-id">
                                  <col class="cm-col-proyecto">
                                  <col class="cm-col-sitio">
                                  <col class="cm-col-subsite">
                                  <col class="cm-col-descripcion">
                                  <col class="cm-col-cantidad">
                                  <col class="cm-col-unidad">
                                  <col class="cm-col-precio">
                                  <col class="cm-col-subtotal">
                                  <col class="cm-col-lote">
                                </colgroup>
                                <thead>
                                    <tr>
                                      <th>ID</th>
                                      <th>Proyecto</th>
                                      <th>Sitio</th>
                                      <th>Subsitio</th>
                                      <th>Descripcion</th>
                                      <th>Cantidad</th>
                                      <th>Unidad de Medida</th>
                                      <th>Precio Unitario</th>
                                      <th>Subtotal</th>
                                      <th>Lote</th>
                                    </tr>
                                  </thead>
                                  <tbody><?php
                                  $pdo = Database::connect();
                                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  
                                  $sql = "SELECT cmd.id,s.nombre AS sitio,COALESCE(s2.nombre, '') AS subsitio,cmd.id_proyecto,p.nombre AS proyecto,cmd.id_tipo_item_certificado,tic.tipo,cmd.descripcion,cmd.cantidad,cmd.id_unidad_medida,um.unidad_medida,cmd.precio_unitario,cmd.subtotal,cmd.aperturado,cmd.lote FROM certificados_maestros_detalles cmd INNER JOIN proyectos p ON cmd.id_proyecto=p.id INNER JOIN tipos_item_certificado tic ON cmd.id_tipo_item_certificado=tic.id INNER JOIN unidades_medida um ON cmd.id_unidad_medida=um.id inner join sitios s on s.id = p.id_sitio left join sitios s2 on s2.id = s.id_sitio_superior WHERE id_certificado_maestro = ".$id;
                                foreach ($pdo->query($sql) as $row) {
                                  echo "<tr>";
                                  echo "<td>".$row["id"]."</td>";
                                  echo "<td>".$row["proyecto"]."</td>";
                                  echo "<td>".$row["sitio"]."</td>";
                                  echo "<td>".$row["subsitio"]."</td>";
                                  echo "<td>".$row["descripcion"]."</td>";
                                  echo "<td>".$row["cantidad"]."</td>";
                                  echo "<td>".$row["unidad_medida"]."</td>";
                                  echo "<td>$".number_format($row["precio_unitario"],2)."</td>";
                                  echo "<td>$".number_format($row["subtotal"],2)."</td>";
                                  echo "<td>".$row["lote"]."</td>";
                                  echo "</tr>";
                                }?></tbody>
                              </table>
                            </div>
                            <!-- Zero Configuration  Ends-->
                            <!-- Feature Unable /Disable Order Starts-->
                          </div>
                        </div>
                      </div>
                    </div>
                  </form>
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
    <script src="assets/js/typeahead/handlebars.js"></script>
    <script src="assets/js/typeahead/typeahead.bundle.js"></script>
    <script src="assets/js/typeahead/typeahead.custom.js"></script>
    <script src="assets/js/chat-menu.js"></script>
    <script src="assets/js/tooltip-init.js"></script>
    <script src="assets/js/typeahead-search/handlebars.js"></script>
    <script src="assets/js/typeahead-search/typeahead-custom.js"></script>
    <!-- Plugins JS Ends-->
    <!-- Theme js-->
    <script src="assets/js/script.js"></script>
  </body>
</html>
<script>window.addEventListener('load', function () { window.print(); });</script>