<?php
require_once __DIR__.'/informarFacturaARCA.php';

function quitarFacturaCobro($pdo, $idCobro) {
    $q = $pdo->prepare('SELECT id_factura FROM cobros WHERE id=?');
    $q->execute(array($idCobro));
    $idFactura = $q->fetchColumn();
    if (!$idFactura) throw new RuntimeException('El cobro ya no tiene una factura asociada.');
    $bloqueo = 'factura_arca_'.(int)$idFactura;
    $q = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $q->execute(array($bloqueo));
    if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('La factura se esta procesando. Espere y consulte su estado.');
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id_factura FROM cobros WHERE id=? FOR UPDATE');
        $q->execute(array($idCobro));
        if ((int)$q->fetchColumn() !== (int)$idFactura) throw new RuntimeException('La factura del cobro cambio. Recargue la pagina.');
        $q = $pdo->prepare('SELECT * FROM facturas WHERE id=? FOR UPDATE');
        $q->execute(array($idFactura));
        if (!puedeReintentarFacturaARCA($q->fetch(PDO::FETCH_ASSOC))) {
            throw new RuntimeException('No se puede eliminar: la factura fue autorizada o su envio requiere conciliacion con ARCA.');
        }
        $q = $pdo->prepare('SELECT id FROM facturas WHERE id_factura_asociada=?');
        $q->execute(array($idFactura));
        if ($q->fetchColumn()) throw new RuntimeException('La factura tiene una nota de credito asociada.');
        $q = $pdo->prepare('SELECT COUNT(*) FROM cobros WHERE id_factura=?');
        $q->execute(array($idFactura));
        if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('La factura esta vinculada a otros cobros.');
        $pdo->prepare('DELETE FROM cobro_detalle WHERE id_cobro=?')->execute(array($idCobro));
        $pdo->prepare('INSERT INTO cobro_detalle (id_cobro,descripcion,precio,cantidad) SELECT ?,descripcion,precio,cantidad FROM factura_detalle WHERE id_factura=? ORDER BY id')
            ->execute(array($idCobro,$idFactura));
        $pdo->prepare('UPDATE cobros SET id_factura=NULL WHERE id=?')->execute(array($idCobro));
        $pdo->prepare('DELETE FROM factura_detalle WHERE id_factura=?')->execute(array($idFactura));
        $pdo->prepare('DELETE FROM facturas WHERE id=?')->execute(array($idFactura));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute(array($bloqueo));
    }
}
