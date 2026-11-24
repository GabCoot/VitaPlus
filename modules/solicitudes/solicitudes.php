
<?php

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

function e($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

// Buscar motivo de consulta
function buscarMotivo($datos) {

    if (!is_array($datos)) {
        return '';
    }

    $claves = [
        'motivo_consulta',
        'motivo',
        'razon_consulta',
        'razon',
        'consulta',
        'descripcion',
        'mensaje'
    ];

    foreach ($datos as $clave => $valor) {

        if (
            in_array(strtolower((string)$clave), $claves) &&
            is_scalar($valor) &&
            trim((string)$valor) !== ''
        ) {
            return trim((string)$valor);
        }

        if (is_array($valor)) {
            $resultado = buscarMotivo($valor);

            if ($resultado !== '') {
                return $resultado;
            }
        }
    }

    return '';
}

$mensaje = '';
$error = '';


// ========================================
// ACTUALIZAR O CONFIRMAR SOLICITUD
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $proceso_id = (int)($_POST['proceso_id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    try {

        if ($proceso_id <= 0) {
            throw new Exception('Solicitud no válida.');
        }

        if (!in_array($accion, ['actualizar', 'confirmar'], true)) {
            throw new Exception('Acción no válida.');
        }

        $pdo->beginTransaction();

        // Buscar solicitud pendiente
        $stmt = $pdo->prepare("
            SELECT persona_id
            FROM procesos_pendientes
            WHERE id = ?
            AND estado = 'pendiente'
            FOR UPDATE
        ");

        $stmt->execute([$proceso_id]);
        $proceso = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proceso) {
            throw new Exception('La solicitud ya no está pendiente.');
        }

        $persona_id = (int)$proceso['persona_id'];

        // Buscar persona
        $stmt = $pdo->prepare("
            SELECT id, folio
            FROM personas
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$persona_id]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$persona) {
            throw new Exception('No se encontró la persona.');
        }

        // Recibir información
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono_secundario = trim($_POST['telefono_secundario'] ?? '');
        $codigo_postal = trim($_POST['codigo_postal'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $privacidad = (int)($_POST['acepta_aviso_privacidad'] ?? 0);

        // Validaciones básicas
        if (
            $nombre === '' ||
            $apellidos === '' ||
            $telefono === '' ||
            $ciudad === ''
        ) {
            throw new Exception(
                'Completa nombre, apellidos, teléfono y ciudad.'
            );
        }

        // Validaciones para confirmar
        if ($accion === 'confirmar') {

            if (
                $fecha_nacimiento === '' ||
                $sexo === '' ||
                $codigo_postal === '' ||
                $direccion === ''
            ) {
                throw new Exception(
                    'Completa fecha de nacimiento, sexo, código postal y dirección.'
                );
            }

            $fechaValida = DateTime::createFromFormat(
                'Y-m-d',
                $fecha_nacimiento
            );

            if (
                !$fechaValida ||
                $fechaValida->format('Y-m-d') !== $fecha_nacimiento
            ) {
                throw new Exception('La fecha de nacimiento no es válida.');
            }

            if (!preg_match('/^[0-9]{5}$/', $codigo_postal)) {
                throw new Exception(
                    'El código postal debe tener 5 números.'
                );
            }
        }

        // Generar folio si no existe
        $folio = trim($persona['folio'] ?? '');

        if ($folio === '') {

            do {

                $folio = 'VP-' . date('Ymd') . '-' .
                    strtoupper(bin2hex(random_bytes(3)));

                $verificar = $pdo->prepare("
                    SELECT id
                    FROM personas
                    WHERE folio = ?
                    LIMIT 1
                ");

                $verificar->execute([$folio]);

            } while ($verificar->fetch());
        }

        // Actualizar persona
        $stmt = $pdo->prepare("
            UPDATE personas SET
                nombre = ?,
                apellidos = ?,
                fecha_nacimiento = ?,
                sexo = ?,
                telefono = ?,
                correo = ?,
                telefono_secundario = ?,
                codigo_postal = ?,
                ciudad = ?,
                direccion = ?,
                acepta_aviso_privacidad = ?,
                folio = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $nombre,
            $apellidos,
            $fecha_nacimiento !== '' ? $fecha_nacimiento : null,
            $sexo !== '' ? $sexo : null,
            $telefono,
            $correo,
            $telefono_secundario,
            $codigo_postal,
            $ciudad,
            $direccion,
            $privacidad,
            $folio,
            $persona_id
        ]);

        // Confirmar solicitud solamente si se eligió confirmar
        if ($accion === 'confirmar') {

            $stmt = $pdo->prepare("
                UPDATE procesos_pendientes
                SET estado = 'confirmada',
                    paso_actual = 'confirmada'
                WHERE id = ?
                AND estado = 'pendiente'
            ");

            $stmt->execute([$proceso_id]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception('No se pudo confirmar la solicitud.');
            }

            $mensaje = 'Solicitud confirmada correctamente. Folio: ' . $folio;

        } else {

            $mensaje = 'Datos actualizados correctamente. La solicitud continúa pendiente. Folio: ' . $folio;
        }

        $pdo->commit();

    } catch (Throwable $ex) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $ex->getMessage();
    }
}


// ========================================
// CONSULTAR SOLICITUDES
// ========================================

$sql = "
    SELECT
        pp.id AS proceso_id,
        pp.persona_id,
        pp.estado,
        pp.fecha_creacion,
        pp.datos_guardados,
        p.folio,
        p.nombre,
        p.apellidos,
        p.telefono,
        p.correo,
        p.ciudad,
        p.fecha_registro,
        p.fecha_nacimiento,
        p.sexo,
        p.telefono_secundario,
        p.codigo_postal,
        p.direccion,
        p.acepta_aviso_privacidad

    FROM procesos_pendientes pp

    INNER JOIN personas p
        ON p.id = pp.persona_id

    WHERE pp.estado = ?

    ORDER BY pp.fecha_creacion DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute(['pendiente']);
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute(['confirmada']);
$confirmadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>

/* ========================================
   MODALES DE SOLICITUDES Y SCROLL
======================================== */

.modal[id^="modalSolicitud"] .modal-dialog {
    width: 95%;
    max-width: 1100px;
    height: calc(100vh - 30px);
    max-height: calc(100vh - 30px);
    margin: 15px auto;
}

.modal[id^="modalSolicitud"] .modal-content {
    height: 100%;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: none;
    border-radius: 12px;
}

/* IMPORTANTE: el formulario también debe ser flexible */
.modal[id^="modalSolicitud"] .modal-content > form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    margin: 0;
}

/* Encabezado fijo */
.modal[id^="modalSolicitud"] .modal-header {
    flex: 0 0 auto;
    background: #fff;
    z-index: 2;
}

/* Área desplazable */
.modal[id^="modalSolicitud"] .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding: 24px;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

/* Barra de desplazamiento */
.modal[id^="modalSolicitud"] .modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal[id^="modalSolicitud"] .modal-body::-webkit-scrollbar-thumb {
    background: #b8c2cc;
    border-radius: 10px;
}

/* Botones fijos */
.modal[id^="modalSolicitud"] .modal-footer {
    flex: 0 0 auto;
    background: #fff;
    border-top: 1px solid #dee2e6;
    z-index: 2;
}

@media (max-width: 768px) {

    .modal[id^="modalSolicitud"] .modal-dialog {
        width: 100%;
        height: 100vh;
        max-height: 100vh;
        margin: 0;
    }

    .modal[id^="modalSolicitud"] .modal-content {
        border-radius: 0;
    }

    .modal[id^="modalSolicitud"] .modal-body {
        padding: 15px;
    }

    .modal[id^="modalSolicitud"] .modal-footer {
        flex-wrap: wrap;
    }

    .modal[id^="modalSolicitud"] .modal-footer button {
        flex: 1 1 100%;
    }
}

</style>


<div class="container-fluid">

    <div class="mb-4">
        <h3>Solicitudes</h3>
        <p class="text-muted">
            Administración de registros recibidos desde la página web.
        </p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>


    <!-- SOLICITUDES PENDIENTES -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <span class="badge bg-warning text-dark">
                    <?= count($pendientes) ?>
                </span>
                Solicitudes pendientes
            </h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Folio</th>
                            <th>Persona</th>
                            <th>Teléfono</th>
                            <th>Ciudad</th>
                            <th>Fecha de registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($pendientes): ?>

                        <?php foreach ($pendientes as $s): ?>

                            <tr>

                                <td><?= e($s['persona_id']) ?></td>

                                <td>
                                    <?= e($s['folio'] ?: 'Sin folio') ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($s['nombre'] . ' ' . $s['apellidos']) ?>
                                    </strong>
                                </td>

                                <td><?= e($s['telefono']) ?></td>

                                <td><?= e($s['ciudad']) ?></td>

                                <td>
                                    <?= e($s['fecha_registro'] ?: $s['fecha_creacion']) ?>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalSolicitud<?= (int)$s['proceso_id'] ?>">

                                        <i class="bi bi-pencil-square"></i>
                                        Revisar

                                    </button>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No hay solicitudes pendientes.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- SOLICITUDES CONFIRMADAS -->

    <div class="card shadow-sm">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0">
                <span class="badge bg-success">
                    <?= count($confirmadas) ?>
                </span>
                Solicitudes confirmadas
            </h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Folio</th>
                            <th>Persona</th>
                            <th>Teléfono</th>
                            <th>Ciudad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($confirmadas): ?>

                        <?php foreach ($confirmadas as $s): ?>

                            <tr>

                                <td><?= e($s['persona_id']) ?></td>

                                <td><?= e($s['folio']) ?></td>

                                <td>
                                    <?= e($s['nombre'] . ' ' . $s['apellidos']) ?>
                                </td>

                                <td><?= e($s['telefono']) ?></td>

                                <td><?= e($s['ciudad']) ?></td>

                                <td>
                                    <span class="badge bg-success">
                                        Confirmada
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Todavía no hay solicitudes confirmadas.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- =====================================
     MODALES DE SOLICITUDES PENDIENTES
===================================== -->

<?php foreach ($pendientes as $s): ?>

    <?php

    $datosRecibidos = json_decode(
        $s['datos_guardados'] ?? '',
        true
    );

    $motivo = buscarMotivo($datosRecibidos);

    ?>

    <div class="modal fade"
         id="modalSolicitud<?= (int)$s['proceso_id'] ?>"
         tabindex="-1"
         aria-hidden="true">

        <div class="modal-dialog modal-xl">

            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">

                        <div>
                            <h5 class="modal-title">
                                Completar información
                            </h5>

                            <small class="text-muted">
                                ID de persona: <?= e($s['persona_id']) ?>
                            </small>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal">
                        </button>

                    </div>


                    <div class="modal-body">

                        <!-- MOTIVO DE CONSULTA -->

                        <div class="alert alert-info mb-4">

                            <h6 class="fw-bold">
                                <i class="bi bi-chat-left-text"></i>
                                Motivo de consulta
                            </h6>

                            <?php if ($motivo !== ''): ?>

                                <p class="mb-0">
                                    <?= nl2br(e($motivo)) ?>
                                </p>

                            <?php else: ?>

                                <p class="mb-0">
                                    No se encontró un motivo de consulta específico.
                                </p>

                            <?php endif; ?>

                            <?php if (!empty($s['datos_guardados'])): ?>

                                <details class="mt-3">

                                    <summary>
                                        Ver información recibida desde la página web
                                    </summary>

                                    <pre class="bg-white p-3 mt-2 rounded small"
                                         style="white-space:pre-wrap;"><?= e(
                                             is_array($datosRecibidos)
                                             ? json_encode($datosRecibidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                             : $s['datos_guardados']
                                         ) ?></pre>

                                </details>

                            <?php endif; ?>

                        </div>


                        <!-- DATOS PERSONALES -->

                        <h6 class="fw-bold mb-3">
                            Datos personales
                        </h6>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>

                                <input type="text"
                                       name="nombre"
                                       class="form-control"
                                       value="<?= e($s['nombre']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Apellidos *</label>

                                <input type="text"
                                       name="apellidos"
                                       class="form-control"
                                       value="<?= e($s['apellidos']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de nacimiento *</label>

                                <input type="date"
                                       name="fecha_nacimiento"
                                       class="form-control"
                                       value="<?= e($s['fecha_nacimiento']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sexo *</label>

                                <select name="sexo"
                                        class="form-select"
                                        required>

                                    <option value="">Seleccionar</option>

                                    <option value="Masculino"
                                        <?= $s['sexo'] === 'Masculino' ? 'selected' : '' ?>>
                                        Masculino
                                    </option>

                                    <option value="Femenino"
                                        <?= $s['sexo'] === 'Femenino' ? 'selected' : '' ?>>
                                        Femenino
                                    </option>

                                    <option value="Otro"
                                        <?= $s['sexo'] === 'Otro' ? 'selected' : '' ?>>
                                        Otro
                                    </option>

                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono *</label>

                                <input type="text"
                                       name="telefono"
                                       class="form-control"
                                       value="<?= e($s['telefono']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono secundario</label>

                                <input type="text"
                                       name="telefono_secundario"
                                       class="form-control"
                                       value="<?= e($s['telefono_secundario']) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Correo electrónico</label>

                                <input type="email"
                                       name="correo"
                                       class="form-control"
                                       value="<?= e($s['correo']) ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código postal *</label>

                                <input type="text"
                                       name="codigo_postal"
                                       class="form-control"
                                       maxlength="5"
                                       pattern="[0-9]{5}"
                                       value="<?= e($s['codigo_postal']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ciudad *</label>

                                <input type="text"
                                       name="ciudad"
                                       class="form-control"
                                       value="<?= e($s['ciudad']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dirección *</label>

                                <input type="text"
                                       name="direccion"
                                       class="form-control"
                                       value="<?= e($s['direccion']) ?>"
                                       required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Folio</label>

                                <input type="text"
                                       class="form-control"
                                       value="<?= e($s['folio'] ?: 'Se generará automáticamente al guardar') ?>"
                                       readonly>
                            </div>

                        </div>


                        <hr>

                        <input type="hidden"
                               name="acepta_aviso_privacidad"
                               value="0">

                        <div class="form-check mb-3">

                            <input class="form-check-input"
                                   type="checkbox"
                                   name="acepta_aviso_privacidad"
                                   value="1"
                                   id="privacidad<?= (int)$s['proceso_id'] ?>"
                                   <?= !empty($s['acepta_aviso_privacidad']) ? 'checked' : '' ?>>

                            <label class="form-check-label"
                                   for="privacidad<?= (int)$s['proceso_id'] ?>">
                                La persona acepta el aviso de privacidad.
                            </label>

                        </div>

                        <small class="text-muted">
                            Los campos marcados con * son obligatorios para confirmar.
                        </small>

                    </div>


                    <div class="modal-footer">

                        <input type="hidden"
                               name="proceso_id"
                               value="<?= e($s['proceso_id']) ?>">

                        <button type="button"
                                class="btn btn-secondary"
                                data-bs-dismiss="modal">
                            Cerrar
                        </button>

                        <button type="submit"
                                name="accion"
                                value="actualizar"
                                class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            Actualizar datos
                        </button>

                        <button type="submit"
                                name="accion"
                                value="confirmar"
                                class="btn btn-success"
                                onclick="return confirm('¿Confirmar esta solicitud?');">
                            <i class="bi bi-check-circle"></i>
                            Confirmar solicitud
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

<?php endforeach; ?>
