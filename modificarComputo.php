<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("config.php");
if (empty($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
require 'database.php';

$prod = isset($_REQUEST['prod']) ? (int)$_REQUEST['prod'] : null;
$prodQuery = $prod ? '?prod=' . $prod : '';
$prodParam = $prod ? '&prod=' . $prod : '';

$id = null;
if (!empty($_GET['id'])) {
    $id = $_REQUEST['id'];
}

if (null == $id) {
    header("Location: listarComputos.php$prodQuery");
    exit();
}

if (!empty($_POST)) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $modoDebug = 0; // isset($_POST['modo_debug']) ? (int)$_POST['modo_debug'] : 0;

    $idComputo = isset($_POST['idComputo']) ? (int)$_POST['idComputo'] : 0;
    $pedidos = isset($_POST['cantidad_pedir']) ? $_POST['cantidad_pedir'] : [];
    $userId = $_SESSION['user']['id'];

    $userCuentaId = 0;
    
    $stmtCuenta = $pdo->prepare("SELECT id FROM cuentas WHERE id_usuario = ? AND activo = 1 LIMIT 1");
    $stmtCuenta->execute([$userId]);
    $cuentaUsuario = $stmtCuenta->fetchColumn();
    
    if ($cuentaUsuario) {
        $userCuentaId = (int)$cuentaUsuario;
    } else {
        if (isset($_SESSION['user']['id_cuenta']) && $_SESSION['user']['id_cuenta'] > 0) {
            $stmtVerify = $pdo->prepare("SELECT id FROM cuentas WHERE id = ? AND activo = 1");
            $stmtVerify->execute([$_SESSION['user']['id_cuenta']]);
            if ($stmtVerify->fetchColumn()) {
                $userCuentaId = (int)$_SESSION['user']['id_cuenta'];
            }
        }
        
        if ($userCuentaId <= 0) {
            $stmtFallback = $pdo->prepare("SELECT id FROM cuentas WHERE activo = 1 AND anulado = 0 ORDER BY id ASC LIMIT 1");
            $stmtFallback->execute();
            $cuentaFallback = $stmtFallback->fetchColumn();
            $userCuentaId = $cuentaFallback ? (int)$cuentaFallback : 0;
        }
    }
    
    if ($userCuentaId <= 0) {
        echo "<div style='padding:20px; background:#f2dede; border:1px solid red; color:red;'>";
        echo "<h3>Error de Configuración</h3>";
        echo "<p>No se encontró una cuenta válida para realizar la reserva. Por favor, configure una cuenta activa en el sistema.</p>";
        echo "</div>";
        exit();
    }

    try {
        $pdo->beginTransaction();
        $reservasRealizadas = false;

        if ($modoDebug) {
            echo "<div style='padding:10px; background:#d9edf7; border:1px solid #31708f; margin:10px;'>";
            echo "<h4>MODO DEBUG ACTIVADO</h4>";
            echo "<p>userCuentaId: $userCuentaId (verificado en tabla cuentas)</p>";
        }

        if (!empty($_POST['reservas_lote'])) {
            $sqlProy = "SELECT t.id_proyecto, p.id_sitio FROM computos c JOIN tareas t ON t.id = c.id_tarea JOIN proyectos p ON p.id = t.id_proyecto WHERE c.id = ?";
            $qProy = $pdo->prepare($sqlProy);
            $qProy->execute([$idComputo]);
            $rowProy = $qProy->fetch(PDO::FETCH_ASSOC);
            $idProyecto = $rowProy ? $rowProy['id_proyecto'] : null;
            $idSitio = $rowProy ? (int)$rowProy['id_sitio'] : 0;

            if ($modoDebug) {
                echo "<p>idProyecto: $idProyecto, idSitio: $idSitio</p>";
            }

            $sqlEgreso = "INSERT INTO egresos (fecha_hora, id_tipo_egreso, nro, id_cuenta_retira, id_sitio_destino, observaciones, id_proyecto) VALUES (NOW(), 2, ?, ?, ?, 'Reserva automatica', ?)";
            $qEgreso = $pdo->prepare($sqlEgreso);
            $qEgreso->execute([$idComputo, $userCuentaId, $idSitio, $idProyecto]);
            $idEgreso = $pdo->lastInsertId();

            if ($modoDebug) {
                echo "<p>Egreso creado ID: $idEgreso</p>";
            }

            $sqlInsDet = "INSERT INTO egresos_detalle (id_egreso, id_material, id_detalle_ingreso, cantidad, cantidad_reservada, id_unidad_medida) VALUES (?,?,?,?,?,?)";
            $sqlUpdIng = "UPDATE ingresos_detalle SET saldo = saldo - ? WHERE id = ?";
            $sqlUpdComp = "UPDATE computos_detalle SET reservado = reservado + ? WHERE id = ?";

            $qInsDet = $pdo->prepare($sqlInsDet);
            $qUpdIng = $pdo->prepare($sqlUpdIng);
            $qUpdComp = $pdo->prepare($sqlUpdComp);

            if ($modoDebug) {
                echo "<p><strong>Contenido de reservas_lote:</strong></p>";
                echo "<pre>" . print_r($_POST['reservas_lote'], true) . "</pre>";
            }

            foreach ($_POST['reservas_lote'] as $idCompDet => $lotes) {
                $totalReservadoItem = 0;
                
                if ($modoDebug) {
                    echo "<p>Procesando idCompDet: $idCompDet con " . count($lotes) . " lotes</p>";
                }
                
                foreach ($lotes as $loteKey => $cant) {
                    $cant = (float)$cant;
                    
                    if ($modoDebug) {
                        echo "<p>-- Lote: $loteKey = $cant</p>";
                    }
                    
                    if ($cant > 0) {
                        $idDetalle = (int)str_replace(['ing_', 'dev_'], '', $loteKey);

                        $sqlMat = "SELECT id.id_material, m.id_unidad_medida, i.id_tipo_ingreso
                                   FROM ingresos_detalle id
                                   JOIN ingresos i ON i.id = id.id_ingreso
                                   JOIN materiales m ON id.id_material = m.id 
                                   WHERE id.id = ?";
                        $stmtMat = $pdo->prepare($sqlMat);
                        $stmtMat->execute([$idDetalle]);
                        $matRow = $stmtMat->fetch(PDO::FETCH_ASSOC);

                        if ($modoDebug) {
                            echo "<p>Procesando: $loteKey, idDetalle: $idDetalle, cantidad: $cant</p>";
                            if ($matRow) {
                                echo "<p>-- Material: {$matRow['id_material']}, tipo_ingreso: {$matRow['id_tipo_ingreso']}</p>";
                            }
                        }

                        if ($matRow) {
                            $idMaterial = $matRow['id_material'];
                            $idUnidad = $matRow['id_unidad_medida'];
                            $esDevolucion = ($matRow['id_tipo_ingreso'] == 2);

                            $qInsDet->execute([$idEgreso, $idMaterial, $idDetalle, $cant, $cant, $idUnidad]);
                            
                            if ($modoDebug) {
                                echo "<p style='color:purple;'>INSERT egresos_detalle: idEgreso=$idEgreso, idMaterial=$idMaterial, idDetalle=$idDetalle, cant=$cant</p>";
                            }
                            
                            $qUpdIng->execute([$cant, $idDetalle]);
                            $rowsIng = $qUpdIng->rowCount();
                            
                            if ($modoDebug) {
                                echo "<p style='color:purple;'>UPDATE ingresos_detalle SET saldo = saldo - $cant WHERE id = $idDetalle (Filas: $rowsIng)</p>";
                            }

                            $totalReservadoItem += $cant;

                            if ($modoDebug) {
                                $tipoStr = $esDevolucion ? 'Devolucion' : 'Ingreso';
                                echo "<p style='color:green;'>$tipoStr procesado: material $idMaterial, cantidad $cant</p>";
                            }
                        }
                    }
                }

                if ($totalReservadoItem > 0) {
                    if ($modoDebug) {
                        echo "<p style='color:blue;'><strong>UPDATE computos_detalle SET reservado = reservado + $totalReservadoItem, saldo = saldo - $totalReservadoItem WHERE id = $idCompDet</strong></p>";
                    }
                    $sqlUpdCompSaldo = "UPDATE computos_detalle SET reservado = reservado + ?, saldo = saldo - ? WHERE id = ?";
                    $qUpdCompSaldo = $pdo->prepare($sqlUpdCompSaldo);
                    $qUpdCompSaldo->execute([$totalReservadoItem, $totalReservadoItem, $idCompDet]);
                    $rowsAffected = $qUpdComp->rowCount();
                    if ($modoDebug) {
                        echo "<p>Filas afectadas en computos_detalle: $rowsAffected</p>";
                    }
                    $reservasRealizadas = true;
                }
            }

            if ($reservasRealizadas) {
                $sqlLogR = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nueva reserva de stock', 'Computos', ?)";
                $stmtLogR = $pdo->prepare($sqlLogR);
                $stmtLogR->execute([$userId, "verComputo.php?id={$idComputo}$prodParam"]);
            }
        }

        $tienePedido = false;
        foreach ($pedidos as $amt) {
            if ((int)$amt > 0) {
                $tienePedido = true;
                break;
            }
        }

        if ($tienePedido) {
            if (!isset($idProyecto)) {
                $sqlProy = "SELECT t.id_proyecto FROM computos c JOIN tareas t ON t.id = c.id_tarea WHERE c.id = ?";
                $qProy = $pdo->prepare($sqlProy);
                $qProy->execute([$idComputo]);
                $idProyecto = $qProy->fetchColumn();
            }

            $sqlInsPedido = "INSERT INTO pedidos (id_computo, id_proyecto, fecha, lugar_entrega, id_cuenta_recibe, id_estado) VALUES (?, ?, NOW(), ?, ?, 1)";
            $stmtInsPedido = $pdo->prepare($sqlInsPedido);

            $lugar = !empty($_POST['lugar_entrega']) ? $_POST['lugar_entrega'] : 'Direccion Default';
            $recibe = !empty($_POST['id_cuenta_recibe']) ? $_POST['id_cuenta_recibe'] : $userCuentaId;

            $stmtInsPedido->execute([$idComputo, $idProyecto, $lugar, $recibe]);
            $idPedido = $pdo->lastInsertId();

            $sqlInsDetalle = "INSERT INTO pedidos_detalle (id_pedido, id_computo_detalle, id_material, fecha_necesidad, cantidad, id_unidad_medida, reservado, comprado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtInsDet = $pdo->prepare($sqlInsDetalle);

            $sqlFetch = "SELECT cd.id AS id_computo_detalle, m.concepto, cd.cantidad, cd.fecha_necesidad, cd.aprobado, cd.id_material, cd.reservado, cd.comprado, m.id_unidad_medida FROM computos_detalle cd inner join materiales m on m.id = cd.id_material WHERE cd.cancelado = 0 and cd.id_computo = ?";
            $datos = $pdo->prepare($sqlFetch);
            $datos->execute([$idComputo]);
            $rows = $datos->fetchAll(PDO::FETCH_ASSOC);

            $sqlUpdSaldoPedido = "UPDATE computos_detalle SET saldo = saldo - ? WHERE id = ?";
            $stmtUpdSaldoPedido = $pdo->prepare($sqlUpdSaldoPedido);

            foreach ($rows as $r) {
                $id_computo_detalle = $r['id_computo_detalle'];
                $cantP = isset($pedidos[$id_computo_detalle]) ? (int)$pedidos[$id_computo_detalle] : 0;
                if ($cantP > 0) {
                    $params = [$idPedido, $r['id_computo_detalle'], $r['id_material'], $r['fecha_necesidad'], $cantP, $r['id_unidad_medida'], $r['reservado'], $r['comprado']];
                    $stmtInsDet->execute($params);
                    
                    $stmtUpdSaldoPedido->execute([$cantP, $id_computo_detalle]);
                    
                    if ($modoDebug) {
                        echo "<p style='color:blue;'>UPDATE computos_detalle SET saldo = saldo - $cantP WHERE id = $id_computo_detalle</p>";
                    }
                }
            }

            $sqlLogP = "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link) VALUES (NOW(), ?, 'Nuevo Pedido', 'Pedidos', ?)";
            $stmtLogP = $pdo->prepare($sqlLogP);
            $stmtLogP->execute([$userId, "verPedido.php?id={$idPedido}"]);
        }

        if (function_exists('marcarComputoGestionandoOTerminado')) {
            $ok = marcarComputoGestionandoOTerminado($pdo, $idComputo, $modoDebug);
        } else {
            $sqlCheck = "SELECT 
                cd.cantidad AS solicitada, 
                cd.reservado, 
                cd.comprado, 
                COALESCE(SUM(pd.cantidad), 0) AS pedido_total
              FROM computos_detalle cd
              LEFT JOIN pedidos p ON p.id_computo = cd.id_computo AND p.anulado = 0
              LEFT JOIN pedidos_detalle pd ON pd.id_pedido = p.id AND pd.id_material = cd.id_material
              WHERE cd.id_computo = ? AND cd.cancelado = 0
              GROUP BY cd.id";

            $qCheck = $pdo->prepare($sqlCheck);
            $qCheck->execute([$idComputo]);
            $items = $qCheck->fetchAll(PDO::FETCH_ASSOC);

            $computoTerminado = true;
            foreach ($items as $row) {
                $reservado = ($row['reservado'] < 0) ? 0 : $row['reservado'];
                $pendiente = $row['solicitada'] - $reservado - $row['comprado'] - $row['pedido_total'];
                if (round($pendiente, 2) > 0) {
                    $computoTerminado = false;
                    break;
                }
            }
            $nuevoEstado = $computoTerminado ? 5 : 2;
            $pdo->prepare("UPDATE computos SET id_estado = ? WHERE id = ?")->execute([$nuevoEstado, $idComputo]);
        }

        if ($modoDebug) {
            $pdo->commit();
            echo "<p><strong>Modo Debug Finalizado (COMMIT realizado, cambios guardados).</strong></p></div>";
            echo "<p><a href='verComputo.php?id=$id$prodParam'>Volver al Computo</a></p>";
            exit();
        } else {
            $pdo->commit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<div style='padding:20px; background:#f2dede; border:1px solid red; color:red;'>";
        echo "<h3>Error Detectado</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        exit();
    } finally {
        Database::disconnect();
    }

    header("Location: verComputo.php?id=" . $id . $prodParam);
    exit();
} else {
    header("Location: listarComputos.php$prodQuery");
    exit();
}
?>
