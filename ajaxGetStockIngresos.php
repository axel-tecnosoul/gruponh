<?php
require("config.php");
require 'database.php';

if (empty($_SESSION['user'])) { die("Acceso denegado"); }

$DEBUG = isset($_GET['debug']) && $_GET['debug'] == 1;

$id_material        = isset($_POST['id_material'])        ? (int)$_POST['id_material']        : 0;
$id_detalle_computo = isset($_POST['id_detalle_computo']) ? (int)$_POST['id_detalle_computo'] : 0;

if ($id_material > 0) {
    $pdo = Database::connect();

    $sql = "SELECT 
                id.id, 
                id.nro_colada_interna,
                id.id_colada,
                i.nro_remito, 
                i.nro,
                i.id_tipo_ingreso,
                id.saldo           AS disponible, 
                i.fecha_hora 
            FROM ingresos_detalle id 
            INNER JOIN ingresos i ON i.id = id.id_ingreso
            WHERE id.id_material = ? 
            AND id.saldo > 0 
            ORDER BY i.fecha_hora ASC";
        
    $q = $pdo->prepare($sql);
    $q->execute([$id_material]);
    $registros = $q->fetchAll(PDO::FETCH_ASSOC);

    Database::disconnect();

    if ($DEBUG) {
        echo '<div class="alert alert-info">';
        echo '<strong>DEBUG:</strong> Material ID: ' . $id_material;
        echo ', Computo Detalle ID: ' . $id_detalle_computo;
        echo ', Registros encontrados: ' . count($registros);
        echo '</div>';
        echo '<pre>' . print_r($registros, true) . '</pre>';
    }

    if (count($registros) > 0) { ?>

        <table class="table table-bordered table-sm table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Tipo</th>
                    <th>Documento</th>
                    <th>Fecha</th>
                    <th class="text-center">Colada</th>
                    <th class="text-center">Col. Interna</th>
                    <th class="text-right">Disponible</th>
                    <th class="text-center">Reservar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $fila):
                    $esDevolucion = ($fila['id_tipo_ingreso'] == 2); ?>
                <tr>
                    <!-- Tipo -->
                    <td>
                        <?php if ($esDevolucion): ?>
                            <span class="badge badge-warning">Devolucion</span>
                        <?php else: ?>
                            <span class="badge badge-success">Ingreso</span>
                        <?php endif; ?>
                    </td>

                    <!-- Documento -->
                    <td>
                        <?php
                        if ($esDevolucion) {
                            echo 'Devolucion #' . htmlspecialchars($fila['nro']);
                        } else {
                            echo htmlspecialchars($fila['nro_remito'] ?: 'Ingreso #' . $fila['nro']);
                        } ?>
                    </td>

                    <!-- Fecha -->
                    <td class="text-center">
                        <?= date('d/m/Y', strtotime($fila['fecha_hora'])) ?>
                    </td>

                    <td class="text-center">
                        <?php if (!empty($fila['id_colada'])): ?>
                            <span style="font-size:16px; font-weight:bold; color:#fff; background-color:#17a2b8; padding:5px 12px; border-radius:4px; display:inline-block; letter-spacing:1px;">
                                #<?= htmlspecialchars($fila['id_colada']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#aaa;">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Colada Interna (nro_colada_interna) -->
                    <td class="text-center">
                        <?php if (!empty($fila['nro_colada_interna'])): ?>
                            <span style="font-size:16px; font-weight:bold; color:#fff; background-color:#6c757d; padding:5px 12px; border-radius:4px; display:inline-block; letter-spacing:1px;">
                                <?= htmlspecialchars($fila['nro_colada_interna']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#aaa;">-</span>
                        <?php endif; ?>
                    </td>

                    <!-- Disponible -->
                    <td class="text-right">
                        <?= number_format($fila['disponible'], 2) ?>
                    </td>

                    <!-- Input reservar -->
                    <td class="text-center">
                        <input 
                            type="number" 
                            step="0.01" 
                            min="0" 
                            max="<?= $fila['disponible'] ?>"
                            class="form-control form-control-sm input-reserva" 
                            name="reservas_lote[<?= $id_detalle_computo ?>][ing_<?= $fila['id'] ?>]"
                            placeholder="0"
                            value="0"
                            style="width: 90px; margin: auto;"
                            onchange="validarMaximo(this, <?= $fila['disponible'] ?>)"
                        >
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <td colspan="6" class="text-right font-weight-bold">
                        Total a reservar:
                    </td>
                    <td class="text-center font-weight-bold" id="totalReservaLive">
                        0.00
                    </td>
                </tr>
            </tfoot>
        </table>

        <small class="text-muted">
            Incluye ingresos y devoluciones con saldo disponible.
        </small>

    <?php } else { ?>
        <div class="alert alert-warning">
            No hay stock disponible para este material.
        </div>
    <?php }
}
?>

<script>
function validarMaximo(input, max) {
    let val = parseFloat(input.value) || 0;
    if (val > max) {
        alert("No puede reservar más de lo disponible (" + max + ")");
        input.value = max;
        val = max;
    }
    if (val < 0) {
        input.value = 0;
        val = 0;
    }

    let total = 0;
    document.querySelectorAll('.input-reserva').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });
    let totalEl = document.getElementById('totalReservaLive');
    if (totalEl) totalEl.textContent = total.toFixed(2);
}
</script>