<?php
require("config.php");
require 'database.php';

if (empty($_SESSION['user'])) { die("Acceso denegado"); }

$DEBUG = isset($_GET['debug']) && $_GET['debug'] == 1;

$id_material = isset($_POST['id_material']) ? (int)$_POST['id_material'] : 0;
$id_detalle_computo = isset($_POST['id_detalle_computo']) ? (int)$_POST['id_detalle_computo'] : 0;

if ($id_material > 0) {
    $pdo = Database::connect();

    $sql = "SELECT 
            id.id, 
            id.nro_colada_interna, 
            i.nro_remito, 
            i.nro,
            i.id_tipo_ingreso,
            id.saldo AS disponible, 
            i.fecha_hora 
        FROM ingresos_detalle id 
        INNER JOIN ingresos i ON i.id = id.id_ingreso 
        WHERE id.id_material = ? AND id.saldo > 0 
        ORDER BY i.fecha_hora ASC";
        
    $q = $pdo->prepare($sql);
    $q->execute([$id_material]);
    $registros = $q->fetchAll(PDO::FETCH_ASSOC);

    Database::disconnect();

    if ($DEBUG) {
        echo '<div class="alert alert-info"><strong>DEBUG:</strong> Material ID: '.$id_material.', Computo Detalle ID: '.$id_detalle_computo.', Registros encontrados: '.count($registros).'</div>';
        echo '<pre>'.print_r($registros, true).'</pre>';
    }

    if (count($registros) > 0) {
        echo '<table class="table table-bordered table-sm">';
        echo '<thead><tr><th>Tipo</th><th>Documento</th><th>Fecha</th><th>Disponible</th><th>Reservar</th></tr></thead>';
        echo '<tbody>';
        foreach ($registros as $fila) {
            $esDevolucion = ($fila['id_tipo_ingreso'] == 2);
            
            if ($esDevolucion) {
                $tipoLabel = '<span class="badge badge-warning">Devolucion</span>';
                $documento = 'Devolucion #' . $fila['nro'];
            } else {
                $tipoLabel = '<span class="badge badge-success">Ingreso</span>';
                $documento = ($fila['nro_remito'] ?: 'Ingreso #' . $fila['nro']);
            }
            
            $colada = $fila['nro_colada_interna'] ? ' (' . $fila['nro_colada_interna'] . ')' : '';
            
            echo '<tr>';
            echo '<td>' . $tipoLabel . '</td>';
            echo '<td>' . $documento . $colada . '</td>';
            echo '<td>' . date("d/m/Y", strtotime($fila['fecha_hora'])) . '</td>';
            echo '<td class="text-right">' . number_format($fila['disponible'], 2) . '</td>';
            echo '<td>';
            echo '<input type="number" step="0.01" min="0" max="'.$fila['disponible'].'" 
                class="form-control form-control-sm input-reserva" 
                name="reservas_lote['.$id_detalle_computo.'][ing_'.$fila['id'].']" 
                placeholder="0" onchange="validarMaximo(this, '.$fila['disponible'].')">';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<small class="text-muted">Incluye ingresos y devoluciones con saldo disponible.</small>';
    } else {
        echo '<div class="alert alert-warning">No hay stock disponible para este material.</div>';
    }
}
?>
<script>
function validarMaximo(input, max) {
    if (parseFloat(input.value) > max) {
        alert("No puede reservar mas de lo disponible (" + max + ")");
        input.value = max;
    }
    if (parseFloat(input.value) < 0) input.value = 0;
}
</script>