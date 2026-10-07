<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/FacturacionARCA.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$guard = $pdo->query("SELECT id FROM acciones WHERE accion='Menu_Empresas' LIMIT 1")->fetchColumn();
if ($guard && !tienePermiso((int)$guard)) {
    header('Location: dashboard.php');
    exit;
}

$companyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$companyId || $companyId < 1) {
    header('Location: listarEmpresas.php');
    exit;
}
$companyQuery = $pdo->prepare('SELECT id, empresa, cuit, es_agente_retencion FROM empresas WHERE id = ?');
$companyQuery->execute([$companyId]);
$company = $companyQuery->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    Database::disconnect();
    header('Location: listarEmpresas.php');
    exit;
}
FacturacionARCA::asegurarCatalogoTiposComprobante($pdo);

if (empty($_SESSION['csrf_empresa_arca'])) {
    $_SESSION['csrf_empresa_arca'] = bin2hex(random_bytes(32));
}
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? null;
    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_empresa_arca'], $postedToken)) {
        $errorMessage = 'La sesión del formulario venció. Actualice la página e intente nuevamente.';
    } else {
        $postedAgent = $_POST['es_agente_retencion'] ?? '0';
        if (!is_scalar($postedAgent) || !in_array((string)$postedAgent, ['0', '1'], true)) {
            $errorMessage = 'El indicador de agente de retención no es válido.';
        }
        $agent = is_scalar($postedAgent) && (string)$postedAgent === '1' ? 1 : 0;
        $points = $_POST['puntos'] ?? [];
        $provinces = $_POST['provincias'] ?? [];
        if (!is_array($points) || !is_array($provinces)) {
            $errorMessage = 'La configuración recibida no es válida.';
        } else {
            $validTypes = array_fill_keys(
                array_map('intval', $pdo->query('SELECT codigo FROM arca_tipos_comprobante')->fetchAll(PDO::FETCH_COLUMN)),
                true
            );
            $validProvinces = array_fill_keys(
                array_map('intval', $pdo->query('SELECT id FROM provincias')->fetchAll(PDO::FETCH_COLUMN)),
                true
            );
            $cleanPoints = [];
            $seenNumbers = [];
            foreach ($points as $point) {
                if (!is_array($point)) {
                    $errorMessage = 'Uno de los puntos de venta tiene datos inválidos.';
                    break;
                }
                $rawNumber = $point['numero'] ?? '';
                $numberText = is_scalar($rawNumber) ? trim((string)$rawNumber) : '';
                if (!preg_match('/^\d{1,5}$/D', $numberText) || (int)$numberText < 1 || (int)$numberText > 99998) {
                    $errorMessage = 'Cada punto de venta debe ser un número entre 1 y 99998.';
                    break;
                }
                $number = (int)$numberText;
                if (isset($seenNumbers[$number])) {
                    $errorMessage = 'No se puede repetir un punto de venta.';
                    break;
                }
                $seenNumbers[$number] = true;
                $selectedTypes = $point['tipos'] ?? [];
                if (!is_array($selectedTypes)) {
                    $errorMessage = 'La relación de tipos de comprobante no es válida.';
                    break;
                }
                $typeCodes = [];
                foreach ($selectedTypes as $code) {
                    $code = filter_var($code, FILTER_VALIDATE_INT);
                    if ($code === false || !isset($validTypes[(int)$code])) {
                        $errorMessage = 'Se seleccionó un tipo de comprobante que no está en el catálogo ARCA.';
                        break 2;
                    }
                    $typeCodes[(int)$code] = true;
                }
                if (count($typeCodes) === 0) {
                    $errorMessage = 'Asocie al menos un tipo de comprobante a cada punto de venta.';
                    break;
                }
                $cleanPoints[] = [
                    'numero' => $number,
                    'emision_tipo' => substr(
                        is_scalar($point['emision_tipo'] ?? '') ? trim((string)($point['emision_tipo'] ?? '')) : '',
                        0,
                        50
                    ),
                    'tipos' => array_keys($typeCodes),
                ];
            }

            $cleanProvinces = [];
            if ($agent === 1 && $errorMessage === '') {
                foreach ($provinces as $provinceId => $row) {
                    $provinceId = filter_var($provinceId, FILTER_VALIDATE_INT);
                    if ($provinceId === false || !isset($validProvinces[(int)$provinceId]) || !is_array($row)) {
                        $errorMessage = 'La provincia seleccionada no es válida.';
                        break;
                    }
                    $rawUrl = $row['url'] ?? '';
                    $url = is_scalar($rawUrl) ? trim((string)$rawUrl) : '';
                    $parts = parse_url($url);
                    if (strlen($url) > 2048
                        || filter_var($url, FILTER_VALIDATE_URL) === false
                        || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
                        $errorMessage = 'Cada padrón debe tener una URL válida que comience con http:// o https://.';
                        break;
                    }
                    $cleanProvinces[(int)$provinceId] = $url;
                }
                if ($errorMessage === '' && count($cleanProvinces) === 0) {
                    $errorMessage = 'Seleccione al menos una provincia y guarde el enlace de su padrón.';
                }
            }

            if ($errorMessage === '') {
                try {
                    $pdo->beginTransaction();
                    $update = $pdo->prepare('UPDATE empresas SET es_agente_retencion = ? WHERE id = ?');
                    $update->execute([$agent, $companyId]);
                    $pdo->prepare('DELETE FROM arca_puntos_venta WHERE id_empresa = ?')->execute([$companyId]);
                    $insertPoint = $pdo->prepare(
                        'INSERT INTO arca_puntos_venta (id_empresa, numero, emision_tipo, bloqueado, fecha_baja)
                         VALUES (?, ?, ?, 0, NULL)'
                    );
                    $insertType = $pdo->prepare(
                        'INSERT INTO arca_punto_venta_tipos (id_punto_venta, codigo_tipo_comprobante) VALUES (?, ?)'
                    );
                    foreach ($cleanPoints as $point) {
                        $insertPoint->execute([$companyId, $point['numero'], $point['emision_tipo']]);
                        $pointId = (int)$pdo->lastInsertId();
                        foreach ($point['tipos'] as $typeCode) {
                            $insertType->execute([$pointId, $typeCode]);
                        }
                    }

                    $pdo->prepare('DELETE FROM empresas_padrones_retencion WHERE id_empresa = ?')->execute([$companyId]);
                    if ($agent === 1) {
                        $insertProvince = $pdo->prepare(
                            'INSERT INTO empresas_padrones_retencion (id_empresa, id_provincia, url_padron) VALUES (?, ?, ?)'
                        );
                        foreach ($cleanProvinces as $provinceId => $url) {
                            $insertProvince->execute([$companyId, $provinceId, $url]);
                        }
                    }
                    $log = $pdo->prepare(
                        "INSERT INTO logs (fecha_hora, id_usuario, detalle_accion, modulo, link)
                         VALUES (NOW(), ?, 'Configuración ARCA de empresa', 'Empresas', '')"
                    );
                    $log->execute([(int)$_SESSION['user']['id']]);
                    $pdo->commit();
                    header('Location: configurarEmpresaARCA.php?id=' . $companyId . '&guardado=1');
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errorMessage = 'No se pudo guardar la configuración: ' . $exception->getMessage();
                }
            }
        }
    }
}

if (isset($_GET['guardado'])) {
    $successMessage = 'La configuración de la empresa se guardó correctamente.';
}

$companyQuery->execute([$companyId]);
$company = $companyQuery->fetch(PDO::FETCH_ASSOC);
$types = $pdo->query('SELECT codigo, descripcion FROM arca_tipos_comprobante ORDER BY codigo')->fetchAll(PDO::FETCH_ASSOC);
$pointQuery = $pdo->prepare(
    'SELECT id, numero, emision_tipo FROM arca_puntos_venta
     WHERE id_empresa = ? AND bloqueado = 0 AND fecha_baja IS NULL ORDER BY numero'
);
$pointQuery->execute([$companyId]);
$pointRows = $pointQuery->fetchAll(PDO::FETCH_ASSOC);
$pointTypeQuery = $pdo->prepare(
    'SELECT codigo_tipo_comprobante FROM arca_punto_venta_tipos WHERE id_punto_venta = ?'
);
foreach ($pointRows as &$pointRow) {
    $pointTypeQuery->execute([(int)$pointRow['id']]);
    $pointRow['tipos'] = array_map('intval', $pointTypeQuery->fetchAll(PDO::FETCH_COLUMN));
}
unset($pointRow);

$provinceRows = $pdo->query('SELECT id, provincia FROM provincias ORDER BY provincia')->fetchAll(PDO::FETCH_ASSOC);
$provinceQuery = $pdo->prepare('SELECT id_provincia, url_padron FROM empresas_padrones_retencion WHERE id_empresa = ?');
$provinceQuery->execute([$companyId]);
$savedProvinces = [];
foreach ($provinceQuery->fetchAll(PDO::FETCH_ASSOC) as $savedProvince) {
    $savedProvinces[(int)$savedProvince['id_provincia']] = $savedProvince['url_padron'];
}
Database::disconnect();
$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include('head_forms.php'); ?>
  <style>
    .arca-point-types { min-width: 280px; min-height: 110px; }
    .arca-import-list { margin-top: 12px; }
    .arca-import-list li { margin-bottom: 6px; }
    .retention-flag {
      display: inline-flex;
      align-items: center;
      width: max-content;
      max-width: 100%;
      margin: 0;
      padding: .2rem .45rem .2rem 2rem;
      border: 1px solid #ced4da;
      border-radius: .25rem;
      white-space: nowrap;
    }
    .retention-flag .custom-control-label {
      position: static;
      padding: 0;
      line-height: 1.35;
      font-size: .875rem;
    }
    .retention-flag .custom-control-label::before,
    .retention-flag .custom-control-label::after {
      left: .45rem;
    }
    .retention-control { margin-bottom: .75rem; }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <?php include('header.php'); ?>
    <div class="page-body-wrapper">
      <?php include('menu.php'); ?>
      <div class="page-body">
        <?php $ubicacion = 'Configuración ARCA - ' . $company['empresa']; include_once('head_page.php'); ?>
        <div class="container-fluid">
          <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $escape($errorMessage) ?></div>
          <?php elseif ($successMessage !== ''): ?>
            <div class="alert alert-success" role="alert"><?= $escape($successMessage) ?></div>
          <?php endif; ?>
          <div class="card">
            <div class="card-header">
              <h5><?= $escape($company['empresa']) ?> — CUIT <?= $escape($company['cuit']) ?></h5>
            </div>
            <form method="post" action="configurarEmpresaARCA.php?id=<?= (int)$companyId ?>" id="configuracionEmpresaArca">
              <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_empresa_arca']) ?>">
              <div class="card-body">
                <h5 class="mb-3">Puntos de venta y tipos de comprobante</h5>
                <p class="text-muted">
                  Los catálogos importados se pueden guardar localmente. El SDK consulta los tipos de comprobante de forma general,
                  no informa qué tipos están habilitados para cada punto; la asociación se selecciona aquí.
                </p>
                <div class="mb-3">
                  <button type="button" class="btn btn-outline-primary" id="consultarCatalogosArca"
                    <?= FacturacionARCA::habilitada() ? '' : 'disabled' ?>>
                    Consultar puntos y tipos en ARCA
                  </button>
                  <?php if (!FacturacionARCA::habilitada()): ?>
                    <small class="form-text text-muted">La consulta está deshabilitada. Active FACTURACION_ARCA_HABILITADA=1 y configure el certificado para usarla.</small>
                  <?php endif; ?>
                  <div id="estadoConsultaArca" class="mt-2" role="status"></div>
                  <ul id="puntosVentaImportados" class="arca-import-list list-unstyled"></ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" id="agregarPuntoVentaManual">Agregar punto manual</button>
                <div class="table-responsive">
                  <table class="table table-bordered table-sm">
                    <thead>
                      <tr><th>Punto de venta</th><th>Tipo de emisión</th><th>Tipos de comprobante ARCA</th><th></th></tr>
                    </thead>
                    <tbody id="puntosVentaConfigurados">
                    <?php foreach ($pointRows as $index => $point): ?>
                      <tr class="arca-point-row">
                        <td><input class="form-control" type="number" min="1" max="99998" name="puntos[<?= $index ?>][numero]" required value="<?= (int)$point['numero'] ?>"></td>
                        <td><input class="form-control" type="text" maxlength="50" name="puntos[<?= $index ?>][emision_tipo]" value="<?= $escape($point['emision_tipo']) ?>"></td>
                        <td>
                          <select class="form-control arca-point-types" multiple required name="puntos[<?= $index ?>][tipos][]">
                            <?php foreach ($types as $type): ?>
                              <option value="<?= (int)$type['codigo'] ?>" <?= in_array((int)$type['codigo'], $point['tipos'], true) ? 'selected' : '' ?>>
                                <?= (int)$type['codigo'] ?> — <?= $escape($type['descripcion']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger quitarPuntoVenta">Quitar</button></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <hr>
                <div class="retention-control">
                  <div class="custom-control custom-checkbox retention-flag">
                    <input type="hidden" name="es_agente_retencion" value="0">
                    <input type="checkbox" class="custom-control-input" id="es_agente_retencion" name="es_agente_retencion" value="1"
                      <?= !empty($company['es_agente_retencion']) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="es_agente_retencion">La empresa es agente de retención</label>
                  </div>
                </div>
                <div id="padronesRetencion">
                  <p class="text-muted">Seleccione las provincias donde actúa como agente e indique el enlace de cada padrón.</p>
                  <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                      <thead><tr><th>Provincia</th><th>Agente de retención</th><th>URL del padrón</th></tr></thead>
                      <tbody>
                      <?php foreach ($provinceRows as $province): $provinceId = (int)$province['id']; ?>
                        <tr>
                          <td><?= $escape($province['provincia']) ?></td>
                          <td class="text-center">
                            <input type="checkbox" class="provincia-retencion-check" data-province="<?= $provinceId ?>"
                              <?= array_key_exists($provinceId, $savedProvinces) ? 'checked' : '' ?>>
                          </td>
                          <td>
                            <input class="form-control url-padron" type="url" maxlength="2048"
                              name="provincias[<?= $provinceId ?>][url]"
                              value="<?= $escape($savedProvinces[$provinceId] ?? '') ?>"
                              <?= array_key_exists($provinceId, $savedProvinces) ? 'required' : 'disabled' ?>>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div class="card-footer text-right">
                <button class="btn btn-primary" type="submit">Guardar configuración</button>
                <a class="btn btn-light" href="listarEmpresas.php">Volver al listado</a>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php include('footer.php'); ?>
    </div>
  </div>
  <script src="assets/js/jquery-3.2.1.min.js"></script>
  <script src="assets/js/bootstrap/popper.min.js"></script>
  <script src="assets/js/bootstrap/bootstrap.js"></script>
  <script src="assets/js/sidebar-menu.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    (function () {
      const body = document.getElementById('puntosVentaConfigurados');
      const typeCatalog = <?= json_encode($types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      let nextPointIndex = <?= count($pointRows) ?>;

      function appendPoint(point, selectedTypes) {
        const row = document.createElement('tr');
        row.className = 'arca-point-row';
        const numberCell = row.insertCell();
        const number = document.createElement('input');
        number.className = 'form-control';
        number.type = 'number';
        number.min = '1';
        number.max = '99998';
        number.required = true;
        number.name = 'puntos[' + nextPointIndex + '][numero]';
        number.value = point.numero || '';
        numberCell.appendChild(number);

        const modeCell = row.insertCell();
        const mode = document.createElement('input');
        mode.className = 'form-control';
        mode.type = 'text';
        mode.maxLength = 50;
        mode.name = 'puntos[' + nextPointIndex + '][emision_tipo]';
        mode.value = point.emision_tipo || '';
        modeCell.appendChild(mode);

        const typesCell = row.insertCell();
        const types = document.createElement('select');
        types.className = 'form-control arca-point-types';
        types.multiple = true;
        types.required = true;
        types.name = 'puntos[' + nextPointIndex + '][tipos][]';
        typeCatalog.forEach(function (type) {
          const option = new Option(type.codigo + ' — ' + type.descripcion, type.codigo);
          option.selected = (selectedTypes || []).map(Number).includes(Number(type.codigo));
          types.add(option);
        });
        typesCell.appendChild(types);

        const actionCell = row.insertCell();
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger quitarPuntoVenta';
        remove.textContent = 'Quitar';
        actionCell.appendChild(remove);
        body.appendChild(row);
        nextPointIndex += 1;
      }

      document.getElementById('agregarPuntoVentaManual').addEventListener('click', function () {
        appendPoint({}, []);
      });
      body.addEventListener('click', function (event) {
        if (event.target.classList.contains('quitarPuntoVenta')) {
          event.target.closest('tr').remove();
        }
      });

      document.getElementById('consultarCatalogosArca').addEventListener('click', async function () {
        const status = document.getElementById('estadoConsultaArca');
        const resultList = document.getElementById('puntosVentaImportados');
        const button = this;
        button.disabled = true;
        status.className = 'text-muted';
        status.textContent = 'Consultando ARCA...';
        resultList.replaceChildren();
        try {
          const response = await fetch('consultarCatalogosEmpresaARCA.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              id_empresa: '<?= (int)$companyId ?>',
              csrf_token: '<?= $escape($_SESSION['csrf_empresa_arca']) ?>'
            })
          });
          const data = await response.json();
          if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudieron consultar los catálogos.');
          typeCatalog.splice(0, typeCatalog.length, ...data.tipos_comprobante);
          document.querySelectorAll('.arca-point-types').forEach(function (select) {
            const selected = new Set(Array.from(select.selectedOptions).map(function (option) {
              return option.value;
            }));
            typeCatalog.forEach(function (type) {
              if (!Array.from(select.options).some(function (option) {
                return Number(option.value) === Number(type.codigo);
              })) {
                const option = new Option(type.codigo + ' — ' + type.descripcion, type.codigo);
                option.selected = selected.has(String(type.codigo));
                select.add(option);
              }
            });
          });
          const savedNumbers = new Set(Array.from(body.querySelectorAll('input[name$="[numero]"]')).map(function (input) {
            return Number(input.value);
          }));
          const activePoints = data.puntos_venta.filter(function (point) {
            return !point.bloqueado && !point.fecha_baja && !savedNumbers.has(Number(point.numero));
          });
          activePoints.forEach(function (point) {
            const item = document.createElement('li');
            item.appendChild(document.createTextNode('Punto ' + point.numero + (point.emision_tipo ? ' — ' + point.emision_tipo : ' ')));
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'btn btn-sm btn-outline-primary ml-2';
            add.textContent = 'Agregar';
            add.addEventListener('click', function () {
              appendPoint(point, []);
              savedNumbers.add(Number(point.numero));
              item.remove();
            });
            item.appendChild(add);
            resultList.appendChild(item);
          });
          status.textContent = 'Catálogo de tipos actualizado (' + data.tipos_comprobante.length + '). Seleccione los puntos de venta que desea guardar y asócieles los tipos habilitados.';
          status.className = 'text-success';
          if (activePoints.length === 0) {
            const empty = document.createElement('li');
            empty.textContent = 'No hay puntos de venta nuevos activos para agregar.';
            resultList.appendChild(empty);
          }
        } catch (error) {
          status.textContent = error.message;
          status.className = 'text-danger';
        } finally {
          button.disabled = false;
        }
      });

      const agentCheckbox = document.getElementById('es_agente_retencion');
      const provincesPanel = document.getElementById('padronesRetencion');
      function toggleProvinces() {
        provincesPanel.hidden = !agentCheckbox.checked;
        provincesPanel.querySelectorAll('.provincia-retencion-check').forEach(function (checkbox) {
          const url = provincesPanel.querySelector('input[name="provincias[' + checkbox.dataset.province + '][url]"]');
          url.disabled = !agentCheckbox.checked || !checkbox.checked;
          url.required = agentCheckbox.checked && checkbox.checked;
        });
      }
      agentCheckbox.addEventListener('change', toggleProvinces);
      provincesPanel.addEventListener('change', function (event) {
        if (event.target.classList.contains('provincia-retencion-check')) toggleProvinces();
      });
      toggleProvinces();
    }());
  </script>
</body>
</html>
