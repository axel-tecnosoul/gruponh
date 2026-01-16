<?php
require("config.php");
require 'database.php';

// Validar sesión
if (empty($_SESSION['user'])) { die("Acceso denegado"); }

$id_material = isset($_POST['id_material']) ? (int)$_POST['id_material'] : 0;
$id_detalle_computo = isset($_POST['id_detalle_computo']) ? (int)$_POST['id_detalle_computo'] : 0;

if ($id_material > 0) {
    $pdo = Database::connect();
    
    // Buscar ingresos con saldo positivo para este material
    $sql = "SELECT id.id, id.nro_colada_interna, i.nro_remito, id.saldo, i.fecha_hora 
            FROM ingresos_detalle id 
            INNER JOIN ingresos i ON i.id = id.id_ingreso 
            WHERE id.id_material = ? AND id.saldo > 0 
            ORDER BY i.fecha_hora ASC";
            
    $q = $pdo->prepare($sql);
    $q->execute([$id_material]);
    $ingresos = $q->fetchAll(PDO::FETCH_ASSOC);
    
    Database::disconnect();
    
    if (count($ingresos) > 0) {
        echo '<table class="table table-bordered table-sm">';
        echo '<thead><tr><th>Remito/Colada</th><th>Fecha</th><th>Saldo Disp.</th><th>Reservar</th></tr></thead>';
        echo '<tbody>';
        foreach ($ingresos as $fila) {
            echo '<tr>';
            echo '<td>' . ($fila['nro_remito'] ?: 'S/R') . ' (' . ($fila['nro_colada_interna'] ?: '-') . ')</td>';
            echo '<td>' . date("d/m/Y", strtotime($fila['fecha_hora'])) . '</td>';
            echo '<td class="text-right">' . number_format($fila['saldo'], 2) . '</td>';
            echo '<td>';
            // Input array: reservas_lote[id_computo_detalle][id_ingreso_detalle]
            echo '<input type="number" step="0.01" min="0" max="'.$fila['saldo'].'" 
                  class="form-control form-control-sm input-reserva" 
                  name="reservas_lote['.$id_detalle_computo.']['.$fila['id'].']" 
                  placeholder="0" onchange="validarMaximo(this, '.$fila['saldo'].')">';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<small class="text-muted">La suma se validará al guardar.</small>';
    } else {
        echo '<div class="alert alert-warning">No hay stock disponible en ingresos para este material.</div>';
    }
}
?>
<!-- Script JS simple para validación visual inmediata en el modal -->
<script>
function validarMaximo(input, max) {
    if (parseFloat(input.value) > max) {
        alert("No puede reservar más de lo disponible en este ingreso (" + max + ")");
        input.value = max;
    }
    if (parseFloat(input.value) < 0) input.value = 0;
}
</script>