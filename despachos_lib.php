<?php

/**
 * Devuelve los componentes vigentes de los packing lists de un proyecto con
 * disponibilidad, despachos confirmados y saldo calculados.
 */
function despachoObtenerComponentesProyecto(PDO $pdo, int $idProyecto, ?int $idDespachoPendiente = null): array
{
    $condicionRevision = "(pl.anulado = 0 AND plr.anulado = 0
                           AND plc.id_estado_componente_packing_list = 1
                           AND plr.nro_revision = pl.ultimo_nro_revision)";
    $params = [$idProyecto];
    if ($idDespachoPendiente !== null) {
        $condicionRevision = "($condicionRevision OR plc.id IN (
            SELECT bd.id_detalle_bulto
            FROM bultos b
            INNER JOIN bultos_detalle bd ON bd.id_bulto = b.id
            WHERE b.id_despacho = ? AND bd.id_tipo_bulto IN (1, 2)
        ))";
        $params[] = $idDespachoPendiente;
    }

    $sql = "SELECT plc.id, plc.id_conjunto_lista_corte, plc.id_concepto,
                   plc.id_computo_detalle, plc.cantidad, plc.observaciones,
                   plr.numero AS numero_packing, plr.nro_revision,
                   pl.ultimo_nro_revision,
                   lcc.nombre AS nombre_conjunto, lcc.cantidad AS cantidad_conjunto,
                   m.concepto AS nombre_concepto
            FROM packing_lists_componentes plc
            INNER JOIN packing_lists_secciones pls ON pls.id = plc.id_packing_list_seccion
            INNER JOIN packing_lists_revisiones plr ON plr.id = pls.id_packing_list_revision
            INNER JOIN packing_lists pl ON pl.id = plr.id_packing_list
            LEFT JOIN listas_corte_conjuntos lcc ON lcc.id = plc.id_conjunto_lista_corte
            LEFT JOIN materiales m ON m.id = plc.id_concepto
            WHERE plr.id_proyecto = ?
              AND $condicionRevision
            ORDER BY plr.numero, plr.nro_revision, plc.id";
    $q = $pdo->prepare($sql);
    $q->execute($params);
    $componentes = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$componentes) {
        return [];
    }

    $idsConjuntos = [];
    $idsComputoDetalle = [];
    foreach ($componentes as $componente) {
        if (!empty($componente['id_conjunto_lista_corte'])) {
            $idsConjuntos[] = (int)$componente['id_conjunto_lista_corte'];
        }
        if (!empty($componente['id_computo_detalle'])) {
            $idsComputoDetalle[] = (int)$componente['id_computo_detalle'];
        }
    }
    $idsConjuntos = array_values(array_unique($idsConjuntos));
    $idsComputoDetalle = array_values(array_unique($idsComputoDetalle));

    $despachadoPorComponente = [];
    $despachadoPorFuente = [];
    $sqlDespachado = "SELECT plc.id, plc.id_conjunto_lista_corte,
                             plc.id_computo_detalle, bd.id_tipo_bulto,
                             SUM(bd.cantidad) AS cantidad_despachada
                      FROM bultos_detalle bd
                      INNER JOIN bultos b ON b.id = bd.id_bulto
                      INNER JOIN despachos des ON des.id = b.id_despacho
                      INNER JOIN packing_lists_componentes plc ON plc.id = bd.id_detalle_bulto
                      INNER JOIN packing_lists_secciones pls ON pls.id = plc.id_packing_list_seccion
                      INNER JOIN packing_lists_revisiones plr ON plr.id = pls.id_packing_list_revision
                      WHERE plr.id_proyecto = ?
                        AND des.id_estado_despacho = 2
                        AND bd.id_tipo_bulto IN (1, 2)
                      GROUP BY plc.id, plc.id_conjunto_lista_corte,
                               plc.id_computo_detalle, bd.id_tipo_bulto";
    $q = $pdo->prepare($sqlDespachado);
    $q->execute([$idProyecto]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idComponente = (int)$fila['id'];
        $cantidad = (float)$fila['cantidad_despachada'];
        $despachadoPorComponente[$idComponente] = ($despachadoPorComponente[$idComponente] ?? 0) + $cantidad;

        if ((int)$fila['id_tipo_bulto'] === 1 && !empty($fila['id_conjunto_lista_corte'])) {
            $claveFuente = 'lc:' . (int)$fila['id_conjunto_lista_corte'];
            $despachadoPorFuente[$claveFuente] = ($despachadoPorFuente[$claveFuente] ?? 0) + $cantidad;
        } elseif ((int)$fila['id_tipo_bulto'] === 2 && !empty($fila['id_computo_detalle'])) {
            $claveFuente = 'computo:' . (int)$fila['id_computo_detalle'];
            $despachadoPorFuente[$claveFuente] = ($despachadoPorFuente[$claveFuente] ?? 0) + $cantidad;
        }
    }

    $disponiblePorFuente = [];
    if ($idsConjuntos) {
        $marcadores = implode(',', array_fill(0, count($idsConjuntos), '?'));
        $sqlLiberadas = "SELECT lcc.id AS id_conjunto, lcc.cantidad AS cantidad_conjunto,
                                lcp.id AS id_posicion, lcp.cantidad AS piezas_por_conjunto,
                                COALESCE(SUM(otd.cant_liberadas), 0) AS piezas_liberadas
                         FROM listas_corte_conjuntos lcc
                         INNER JOIN lista_corte_posiciones lcp ON lcp.id_lista_corte_conjunto = lcc.id
                         LEFT JOIN ordenes_trabajo_detalle otd ON otd.id_posicion = lcp.id
                         WHERE lcc.id IN ($marcadores)
                         GROUP BY lcc.id, lcc.cantidad, lcp.id, lcp.cantidad";
        $q = $pdo->prepare($sqlLiberadas);
        $q->execute($idsConjuntos);
        $setsLiberadosPorFuente = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $idConjunto = (int)$fila['id_conjunto'];
            $piezasPorConjunto = (float)$fila['piezas_por_conjunto'];
            $piezasLiberadas = (float)$fila['piezas_liberadas'];
            $setsPosicion = $piezasPorConjunto > 0
                ? (int)floor($piezasLiberadas / $piezasPorConjunto)
                : 0;
            if (!isset($setsLiberadosPorFuente[$idConjunto])) {
                $setsLiberadosPorFuente[$idConjunto] = [
                    'sets' => $setsPosicion,
                    'cantidad_conjunto' => (float)$fila['cantidad_conjunto'],
                ];
            } else {
                $setsLiberadosPorFuente[$idConjunto]['sets'] = min(
                    $setsLiberadosPorFuente[$idConjunto]['sets'],
                    $setsPosicion
                );
            }
        }
        foreach ($setsLiberadosPorFuente as $idConjunto => $datos) {
            $disponiblePorFuente['lc:' . $idConjunto] = min(
                (float)$datos['sets'],
                (float)$datos['cantidad_conjunto']
            );
        }
    }

    if ($idsComputoDetalle) {
        $marcadores = implode(',', array_fill(0, count($idsComputoDetalle), '?'));
        $q = $pdo->prepare(
            "SELECT id, reservado
             FROM computos_detalle
             WHERE cancelado = 0 AND id IN ($marcadores)"
        );
        $q->execute($idsComputoDetalle);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $disponiblePorFuente['computo:' . (int)$fila['id']] = (float)floor(max(0, (float)$fila['reservado']));
        }
    }

    $usadoPorFuente = [];
    foreach ($despachadoPorFuente as $claveFuente => $cantidad) {
        $usadoPorFuente[$claveFuente] = $cantidad;
    }
    $restantePorFuente = [];
    foreach ($disponiblePorFuente as $claveFuente => $disponible) {
        $restantePorFuente[$claveFuente] = max(
            0,
            $disponible - ($usadoPorFuente[$claveFuente] ?? 0)
        );
    }

    foreach ($componentes as &$componente) {
        $idComponente = (int)$componente['id'];
        $cantidadComponente = max(0, (float)$componente['cantidad']);
        $cantidadDespachada = (float)($despachadoPorComponente[$idComponente] ?? 0);

        if (!empty($componente['id_conjunto_lista_corte'])) {
            $claveFuente = 'lc:' . (int)$componente['id_conjunto_lista_corte'];
            $componente['id_tipo_bulto'] = 1;
        } elseif (!empty($componente['id_computo_detalle'])) {
            $claveFuente = 'computo:' . (int)$componente['id_computo_detalle'];
            $componente['id_tipo_bulto'] = 2;
        } else {
            $claveFuente = '';
            $componente['id_tipo_bulto'] = 2;
        }

        $restanteFuente = (float)($restantePorFuente[$claveFuente] ?? 0);
        $porAsignar = max(0, $cantidadComponente - $cantidadDespachada);
        $asignacionDisponible = min($porAsignar, $restanteFuente);
        $componente['nombre_componente'] = !empty($componente['id_conjunto_lista_corte'])
            ? (string)$componente['nombre_conjunto']
            : (string)($componente['nombre_concepto'] ?? '');
        $componente['revision_vigente'] = (int)$componente['nro_revision'] === (int)$componente['ultimo_nro_revision'];
        $componente['cantidad_despachada_calculada'] = $cantidadDespachada;
        $componente['cantidad_disponible'] = $cantidadDespachada + $asignacionDisponible;
        $componente['saldo'] = max(0, $componente['cantidad_disponible'] - $cantidadDespachada);
        if ($claveFuente !== '') {
            $restantePorFuente[$claveFuente] = max(0, $restanteFuente - $asignacionDisponible);
        }
    }
    unset($componente);

    return $componentes;
}

/** Devuelve las cantidades y etiquetas de bulto guardadas en un despacho pendiente. */
function despachoObtenerLineasPendientes(PDO $pdo, int $idDespacho): array
{
    $q = $pdo->prepare(
        "SELECT bd.id_detalle_bulto AS id_componente,
                SUM(bd.cantidad) AS cantidad,
                GROUP_CONCAT(DISTINCT b.nombre ORDER BY b.numero SEPARATOR ', ') AS bultos
         FROM bultos b
         INNER JOIN bultos_detalle bd ON bd.id_bulto = b.id
         WHERE b.id_despacho = ? AND bd.id_tipo_bulto IN (1, 2)
         GROUP BY bd.id_detalle_bulto"
    );
    $q->execute([$idDespacho]);
    $lineas = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $lineas[(int)$fila['id_componente']] = [
            'cantidad' => (string)$fila['cantidad'],
            'bulto' => (string)($fila['bultos'] ?? ''),
        ];
    }
    return $lineas;
}

/**
 * Determina la pantalla de destino para una acción antigua sobre un bulto.
 * La gestión de pendientes se centraliza en la nueva pantalla de carga.
 */
function despachoUrlDesdeBulto(PDO $pdo, int $idBulto): string
{
    $q = $pdo->prepare(
        "SELECT b.id_despacho, des.id_estado_despacho
         FROM bultos b
         INNER JOIN despachos des ON des.id = b.id_despacho
         WHERE b.id = ?"
    );
    $q->execute([$idBulto]);
    $fila = $q->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return 'listarDespachos.php';
    }
    if ((int)$fila['id_estado_despacho'] === 1) {
        return 'nuevoBultoDespacho.php?id_despacho=' . (int)$fila['id_despacho'];
    }
    return 'verDespacho.php?id=' . (int)$fila['id_despacho'];
}
