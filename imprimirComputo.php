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

    if (null == $id) {
        header("Location: listarComputos.php");
    }

    if (!empty($_POST)) {
        // No se maneja POST en este archivo
    } else {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT c.`id`, c.nro_computo nro_computo, c.`nro_revision`, c.`id_tarea`, date_format(c.`fecha`,'%d/%m/%Y') fecha, c.`id_cuenta_solicitante`, c.`id_estado`, p.nombre, p.nro nro_proyecto, s.nro_sitio, s.nro_subsitio, cu.nombre cliente, cu2.nombre cuenta_solicitante, c.nro nro_solo 
                FROM `computos` c 
                INNER JOIN tareas t ON t.id = c.id_tarea 
                INNER JOIN proyectos p ON p.id = t.id_proyecto 
                INNER JOIN sitios s ON s.id = p.id_sitio 
                INNER JOIN cuentas cu ON cu.id = p.id_cliente 
                INNER JOIN cuentas cu2 ON cu2.id = c.id_cuenta_solicitante 
                WHERE c.id = ?";
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        Database::disconnect();
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include('head_forms.php'); ?>
    <link rel="stylesheet" type="text/css" href="assets/css/select2.css">
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 5px;
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="page-body-wrapper">
        <div class="page-body">
            <?php
                $ubicacion = "Ver Cómputo";
                include_once("head_page.php");
            ?>
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <div class="form-group row">
                                            <div class="col-sm-2 bordered-div"><img src="assets/images/logo.jpg"></div>
                                            <div class="col-sm-6 bordered-div text-center"><h3><b>COMPUTO DE MATERIALES</b></h3></div>
                                            <div class="col-sm-3 bordered-div"><h6><b>Nº <?php echo $data['nro_solo']; ?> - Rev <?php echo $data['nro_revision']; ?></b></h6></div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-sm-11 bordered-div">
                                                <b>Emisión:</b> <?php echo $data['fecha']; ?><br>
                                                <b>Proyecto:</b> <?php echo $data['nombre']; ?><br>
                                                <b>Nro:</b> <?php echo $data['nro_sitio'] . "_" . $data['nro_subsitio'] . "_" . $data['nro_proyecto']; ?><br>
                                                <b>Cliente:</b> <?php echo $data['cliente']; ?><br>
                                                <b>Solicitó:</b> <?php echo $data['cuenta_solicitante']; ?><br>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-sm-12">
                                                <h5><b>Historial de Revisiones</b></h5>
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>Rev</th>
                                                            <th>Fecha</th>
                                                            <th>Modificaciones</th>
                                                            <th>Realizó</th>
                                                            <th>Revisó</th>
                                                            <th>Validó</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $pdo = Database::connect();
                                                        $sql = "SELECT c.nro_revision, 
                                                                       date_format(c.fecha_hora_revision,'%d/%m/%Y') fecha, 
                                                                       c.comentarios_revision,
                                                                       date_format(c.fecha,'%d/%m/%Y') fecha_emision, 
                                                                       c1.nombre, c2.nombre, c3.nombre 
                                                                FROM computos c 
                                                                LEFT JOIN cuentas c1 ON c1.id = c.id_cuenta_realizo 
                                                                LEFT JOIN cuentas c2 ON c2.id = c.id_cuenta_reviso 
                                                                LEFT JOIN cuentas c3 ON c3.id = c.id_cuenta_valido 
                                                                WHERE c.nro_computo = {$data['nro_computo']} 
                                                                ORDER BY c.nro_revision ASC";
                                                        foreach ($pdo->query($sql) as $row) {
                                                            echo '<tr>';
                                                            echo '<td>' . $row[0] . '</td>';
                                                            echo '<td>' . (!empty($row[1]) ? $row[1] : $row[3]) . '</td>';
                                                            echo '<td>' . (!empty($row[2]) ? $row[2] : 'Emisión Original') . '</td>';
                                                            echo '<td>' . $row[4] . '</td>';
                                                            echo '<td>' . $row[5] . '</td>';
                                                            echo '<td>' . $row[6] . '</td>';
                                                            echo '</tr>';
                                                        }
                                                        Database::disconnect();
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <div class="col-sm-12">
                                                <h5><b>Detalle de Materiales</b></h5>
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>Categoría</th>
                                                            <th>Cantidad</th>
                                                            <th>Concepto</th>
                                                            <th>Largo (mm)</th>
                                                            <th>Peso (kg)</th>
                                                            <th>Observaciones</th>
                                                            <th>Req</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $pdo = Database::connect();
                                                        $sql = "SELECT c.categoria, d.cantidad, m.concepto, m.largo, m.peso_metro, d.comentarios, date_format(d.fecha_necesidad,'%d/%m/%Y') 
                                                                FROM `computos_detalle` d 
                                                                INNER JOIN materiales m ON m.id = d.id_material 
                                                                INNER JOIN categorias c ON c.id = m.id_categoria 
                                                                WHERE d.id_computo = {$_GET['id']} AND d.cancelado = 0 
                                                                ORDER BY c.categoria";
                                                        foreach ($pdo->query($sql) as $row) {
                                                            echo '<tr>';
                                                            echo '<td>' . $row[0] . '</td>';
                                                            echo '<td>' . $row[1] . '</td>';
                                                            echo '<td>' . $row[2] . '</td>';
                                                            echo '<td>' . $row[3] . '</td>';
                                                            echo '<td>' . $row[4] . '</td>';
                                                            echo '<td>' . $row[5] . '</td>';
                                                            echo '<td>' . $row[6] . '</td>';
                                                            echo '</tr>';
                                                        }
                                                        Database::disconnect();
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                    </div> <!-- col -->
                                </div> <!-- row -->
                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div> <!-- col -->
                </div> <!-- row -->
            </div> <!-- container-fluid -->
        </div> <!-- page-body -->
    </div> <!-- page-body-wrapper -->
</div> <!-- page-wrapper -->
<script>window.print();</script>
</body>
</html>
