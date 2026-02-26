<?php
/**
 * fix_nro_oc_v5.php
 * 
 * UNA SOLA PASADA en orden de ID:
 *   - rev=0  → asignar autoincremental, guardar en mapa
 *   - rev>0  → buscar en mapa por nro_oc actual
 *              Si existe → usar ese valor
 *              Si NO existe → se vuelve base, asignar autoincremental
 *
 *   fix_nro_oc_v5.php              → DRY RUN
 *   fix_nro_oc_v5.php?ejecutar=1   → Aplica cambios
 */

require("config.php");
require 'database.php';

$ejecutar = isset($_GET['ejecutar']) && $_GET['ejecutar'] == 1;

echo "<pre>";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   CORRECCIÓN DE nro_oc EN TABLA compras  (v5)               ║\n";
echo "║   Una sola pasada en orden de ID, autoincremental desde 1   ║\n";
echo "║   Modo: " . ($ejecutar ? "EJECUCIÓN REAL" : "DRY RUN (solo lectura)") . str_repeat(" ", $ejecutar ? 18 : 8) . "║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ═══════════════════════════════════════════════════════════
    // 1. OBTENER TODOS LOS REGISTROS EN ORDEN DE ID
    // ═══════════════════════════════════════════════════════════
    $stmt = $pdo->query("
        SELECT id, nro_oc, nro_revision, id_pedido, id_cuenta_proveedor
        FROM compras
        ORDER BY id ASC
    ");
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($compras)) {
        echo "⚠ No hay registros.\n</pre>";
        Database::disconnect();
        exit;
    }

    echo "Total de registros: " . count($compras) . "\n\n";

    // ═══════════════════════════════════════════════════════════
    // 2. UNA SOLA PASADA: Procesar en orden de ID
    // ═══════════════════════════════════════════════════════════
    $counter     = 1;       // autoincremental propio, empieza en 1
    $updates     = [];      // [id => nuevo_nro_oc]
    $mapOldToNew = [];      // [old_nro_oc_string => nuevo_nro_oc]
    $metodos     = [];      // [id => método]
    $warnings    = [];

    foreach ($compras as $c) {
        $id       = (int)$c['id'];
        $oldNroOc = trim($c['nro_oc']);
        $rev      = (int)$c['nro_revision'];

        if ($rev === 0) {
            // ── RAÍZ: siempre asignar autoincremental ──
            $updates[$id]           = $counter;
            $mapOldToNew[$oldNroOc] = $counter;
            $metodos[$id]           = "RAÍZ → nro_oc = $counter";
            $counter++;

        } else {
            // ── REVISIÓN: buscar si ya existe el nro_oc en el mapa ──
            if (isset($mapOldToNew[$oldNroOc])) {
                // Encontró raíz o base previa → usar su valor
                $updates[$id] = $mapOldToNew[$oldNroOc];
                $metodos[$id] = "REVISIÓN → base old='$oldNroOc' → nro_oc = {$mapOldToNew[$oldNroOc]}";
            } else {
                // No existe base → esta revisión SE VUELVE la base
                $updates[$id]           = $counter;
                $mapOldToNew[$oldNroOc] = $counter;
                $metodos[$id]           = "SIN BASE → se vuelve base → nro_oc = $counter";
                $warnings[]             = "ID $id (rev=$rev, old='$oldNroOc'): sin rev=0 previa. Se asignó como base con nro_oc=$counter";
                $counter++;
            }
        }
    }

    echo "Autoincremental final: " . ($counter - 1) . "\n\n";

    // ═══════════════════════════════════════════════════════════
    // 3. IMPRIMIR TABLA DETALLADA
    // ═══════════════════════════════════════════════════════════
    echo "── DETALLE DE CAMBIOS ─────────────────────────────────────────────────────────────────────────\n";
    echo "┌──────┬────────────────┬──────────────┬─────┬─────────────────────────────────────────────────────┐\n";
    echo "│ ID   │ nro_oc ACTUAL  │ nro_oc NUEVO │ REV │ MÉTODO                                              │\n";
    echo "├──────┼────────────────┼──────────────┼─────┼─────────────────────────────────────────────────────┤\n";

    $totalCambios = 0;
    foreach ($compras as $c) {
        $id     = (int)$c['id'];
        $actual = trim($c['nro_oc']);
        $nuevo  = $updates[$id];
        $rev    = $c['nro_revision'];
        $met    = $metodos[$id] ?? '?';
        $cambio = ($actual !== (string)$nuevo);

        if ($cambio) $totalCambios++;

        printf("│ %-4s │ %-14s │ %-12s │ %-3s │ %-51s │\n",
            $id, $actual, $nuevo, $rev, $met
        );
    }

    echo "└──────┴────────────────┴──────────────┴─────┴─────────────────────────────────────────────────────┘\n\n";

    // ═══════════════════════════════════════════════════════════
    // 4. VERIFICACIÓN: Cadenas de revisión
    // ═══════════════════════════════════════════════════════════
    echo "── CADENAS DE REVISIÓN ────────────────────────────────────────\n";

    $cadenas = [];
    foreach ($updates as $id => $nroOcNuevo) {
        $cadenas[$nroOcNuevo][] = $id;
    }
    ksort($cadenas);

    foreach ($cadenas as $nroOc => $ids) {
        $detalles = [];
        foreach ($compras as $c) {
            if (in_array((int)$c['id'], $ids)) {
                $detalles[] = "ID {$c['id']}(rev={$c['nro_revision']})";
            }
        }
        $marca = count($ids) > 1 ? ' ← cadena' : '';
        echo "  nro_oc = $nroOc: " . implode(', ', $detalles) . "$marca\n";
    }
    echo "\n";

    // ═══════════════════════════════════════════════════════════
    // 5. ADVERTENCIAS
    // ═══════════════════════════════════════════════════════════
    if (!empty($warnings)) {
        echo "⚠ ADVERTENCIAS (revisiones que se volvieron base):\n";
        foreach ($warnings as $w) {
            echo "  - $w\n";
        }
        echo "\n";
    }

    echo "Resumen: " . count($compras) . " registros | $totalCambios cambios necesarios\n\n";

    // ═══════════════════════════════════════════════════════════
    // 6. EJECUTAR O NO
    // ═══════════════════════════════════════════════════════════
    if (!$ejecutar) {
        echo "═══════════════════════════════════════════════════════════\n";
        echo "  DRY RUN finalizado. No se modificó la base de datos.\n";
        echo "  Para aplicar: fix_nro_oc_v5.php?ejecutar=1\n";
        echo "═══════════════════════════════════════════════════════════\n";
        Database::disconnect();
        echo "</pre>";
        exit;
    }

    if ($totalCambios === 0) {
        echo "✔ No hay cambios necesarios.\n";
        Database::disconnect();
        echo "</pre>";
        exit;
    }

    // ── Transacción ──
    echo "Iniciando transacción...\n";
    $pdo->beginTransaction();

    $stmtUpdate   = $pdo->prepare("UPDATE compras SET nro_oc = ? WHERE id = ?");
    $actualizados = 0;

    foreach ($updates as $id => $nuevoNroOc) {
        $actual = null;
        foreach ($compras as $c) {
            if ((int)$c['id'] === $id) {
                $actual = trim($c['nro_oc']);
                break;
            }
        }

        if ($actual !== (string)$nuevoNroOc) {
            $stmtUpdate->execute([(string)$nuevoNroOc, $id]);
            $actualizados++;
            echo "  ✔ id=$id : '$actual' → $nuevoNroOc\n";
        }
    }

    $pdo->commit();

    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "  ✔ COMMIT — $actualizados registros actualizados\n";
    echo "═══════════════════════════════════════════════════════════\n";

    Database::disconnect();

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n✘ ROLLBACK ejecutado.\n";
    }
    echo "\n✘ ERROR: " . $e->getMessage() . "\n";
    if (isset($pdo)) Database::disconnect();
}
echo "</pre>";
?>