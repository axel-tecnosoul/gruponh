<?php
function debugQuery(PDO $pdo, string $sql, array $params): string {
  foreach ($params as $p) {
      // $pdo->quote() añade comillas y escapa el valor
      $quoted = $pdo->quote($p);
      // reemplazamos la primera aparición de '?' por el valor quoteado
      $sql = preg_replace('/\?/', $quoted, $sql, 1);
  }
  return $sql;
}