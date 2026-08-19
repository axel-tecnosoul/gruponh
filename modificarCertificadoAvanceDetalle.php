<?php
// Compatibilidad: mantener ruta historica de modificacion apuntando al formulario unificado.
if (!isset($_GET['id_certificado_avance']) || (int) $_GET['id_certificado_avance'] <= 0) {
  header("Location: listarCertificadosAvances.php");
  exit;
}
require __DIR__ . '/nuevoCertificadoAvanceDetalle.php';
