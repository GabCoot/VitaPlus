
<?php
require_once __DIR__ . '/../../config/database.php';

$pdo = db();
date_default_timezone_set('America/Mexico_City');

function e($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function generarFolioCita($pdo) {
    do {
        $folio = 'CIT-' . date('Ymd') . '-' . random_int(100000, 999999);

        $consulta = $pdo->prepare("SELECT id FROM citas WHERE folio = ? LIMIT 1");
        $consulta->execute([$folio]);
    } while ($consulta->fetch());

    return $folio;
}

$mensaje = '';
$error = '';

/* =========================================
   PROCESAR FORMULARIOS
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    try {

        /* CREAR CITA */
        if ($accion === 'crear') {

            $persona_id = (int)($_POST['persona_id'] ?? 0);
            $fecha = $_POST['fecha'] ?? '';
            $hora = $_POST['hora'] ?? '';
            $tipo_atencion = trim($_POST['tipo_atencion'] ?? '');
            $motivo = trim($_POST['motivo'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');

            if (!$persona_id || !$fecha || !$hora || !$tipo_atencion || !$motivo) {
                throw new Exception('Completa todos los campos obligatorios.');
            }

            // Verificar que la persona tenga una solicitud confirmada
            $verificar = $pdo->prepare("
                SELECT p.id
                FROM personas p
                WHERE p.id = ?
                AND EXISTS (
                    SELECT 1
                    FROM procesos_pendientes pp
                    WHERE pp.persona_id = p.id
                    AND pp.estado = 'confirmada'
                )
                LIMIT 1
            ");
            $verificar->execute([$persona_id]);

            if (!$verificar->fetch()) {
                throw new Exception('La persona no tiene una solicitud confirmada.');
            }

            $folio = generarFolioCita($pdo);

            $insertar = $pdo->prepare("
                INSERT INTO citas (
                    folio,
                    persona_id,
                    fecha,
                    hora,
                    tipo_atencion,
                    motivo,
                    estado,
                    observaciones,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'confirmada', ?, NOW(), NOW())
            ");

            $insertar->execute([
                $folio,
                $persona_id,
                $fecha,
                $hora,
                $tipo_atencion,
                $motivo,
                $observaciones
            ]);

            header('Location: citas.php?mensaje=creada');
            exit;
        }

        /* ACTUALIZAR CITA */
        if ($accion === 'actualizar') {

            $id = (int)($_POST['id'] ?? 0);
            $fecha = $_POST['fecha'] ?? '';
            $hora = $_POST['hora'] ?? '';
            $tipo_atencion = trim($_POST['tipo_atencion'] ?? '');
            $motivo = trim($_POST['motivo'] ?? '');
            $estado = $_POST['estado'] ?? 'confirmada';
            $observaciones = trim($_POST['observaciones'] ?? '');

            $estadosPermitidos = [
                'pendiente',
                'confirmada',
                'atendida',
                'cancelada'
            ];

            if (!$id || !$fecha || !$hora || !$tipo_atencion || !$motivo) {
                throw new Exception('Completa todos los campos obligatorios.');
            }

            if (!in_array($estado, $estadosPermitidos, true)) {
                throw new Exception('El estado seleccionado no es válido.');
            }

            $actualizar = $pdo->prepare("
                UPDATE citas
                SET fecha = ?,
                    hora = ?,
                    tipo_atencion = ?,
                    motivo = ?,
                    estado = ?,
                    observaciones = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $actualizar->execute([
                $fecha,
                $hora,
                $tipo_atencion,
                $motivo,
                $estado,
                $observaciones,
                $id
            ]);

            header('Location: citas.php?mensaje=actualizada');
            exit;
        }

        /* CANCELAR CITA */
        if ($accion === 'cancelar') {

            $id = (int)($_POST['id'] ?? 0);

            $cancelar = $pdo->prepare("
                UPDATE citas
                SET estado = 'cancelada',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $cancelar->execute([$id]);

            header('Location: citas.php?mensaje=cancelada');
            exit;
        }

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

/* =========================================
   MENSAJES
========================================= */

if (isset($_GET['mensaje'])) {
    switch ($_GET['mensaje']) {
        case 'creada':
            $mensaje = 'La cita se asignó y confirmó correctamente.';
            break;
        case 'actualizada':
            $mensaje = 'La cita se actualizó correctamente.';
            break;
        case 'cancelada':
            $mensaje = 'La cita fue cancelada correctamente.';
            break;
    }
}

/* =========================================
   BUSCADOR
========================================= */

$busqueda = trim($_GET['buscar'] ?? '');

$sql = "
    SELECT
        c.*,
        p.nombre,
        p.apellidos,
        p.telefono,
        p.correo,
        p.folio AS folio_persona
    FROM citas c
    INNER JOIN personas p ON p.id = c.persona_id
";

$parametros = [];

if ($busqueda !== '') {
    $sql .= "
        WHERE
            c.folio LIKE ?
            OR p.folio LIKE ?
            OR p.nombre LIKE ?
            OR p.apellidos LIKE ?
            OR p.telefono LIKE ?
            OR p.correo LIKE ?
    ";

    $valor = "%{$busqueda}%";

    $parametros = [
        $valor,
        $valor,
        $valor,
        $valor,
        $valor,
        $valor
    ];
}

$sql .= " ORDER BY c.fecha DESC, c.hora DESC";

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);
$todasLasCitas = $consulta->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   SEPARAR CITAS POR ESTADO
========================================= */

$citasConfirmadas = [];
$citasPendientes = [];
$citasAtendidas = [];
$citasCanceladas = [];

foreach ($todasLasCitas as $cita) {

    switch ($cita['estado']) {

        case 'confirmada':
            $citasConfirmadas[] = $cita;
            break;

        case 'pendiente':
            $citasPendientes[] = $cita;
            break;

        case 'atendida':
            $citasAtendidas[] = $cita;
            break;

        case 'cancelada':
            $citasCanceladas[] = $cita;
            break;
    }
}

/* =========================================
   PERSONAS DISPONIBLES PARA CITA
========================================= */

$consultaPersonas = $pdo->query("
    SELECT DISTINCT
        p.id,
        p.nombre,
        p.apellidos,
        p.folio,
        p.telefono
    FROM personas p
    INNER JOIN procesos_pendientes pp
        ON pp.persona_id = p.id
    WHERE pp.estado = 'confirmada'
    ORDER BY p.nombre ASC
");

$personas = $consultaPersonas->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   FUNCIÓN PARA MOSTRAR TABLAS
========================================= */

function mostrarTablaCitas($citas, $titulo, $color) {

    ?>

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <span class="badge bg-<?php echo e($color); ?> me-2">&nbsp;</span>
                <?php echo e($titulo); ?>
            </h5>

            <span class="badge bg-light text-dark">
                <?php echo count($citas); ?> citas
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Persona</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo de atención</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($citas)): ?>

                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No hay citas en este apartado.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($citas as $cita): ?>

                        <tr>
                            <td>
                                <span class="fw-semibold">
                                    <?php echo e($cita['folio']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?php echo e($cita['nombre'] . ' ' . $cita['apellidos']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo e($cita['telefono']); ?>
                                </small>
                            </td>

                            <td>
                                <?php echo e(date('d/m/Y', strtotime($cita['fecha']))); ?>
                            </td>

                            <td>
                                <?php echo e(substr($cita['hora'], 0, 5)); ?>
                            </td>

                            <td>
                                <?php echo e($cita['tipo_atencion']); ?>
                            </td>

                            <td>
                                <?php
                                $colores = [
                                    'pendiente' => 'warning',
                                    'confirmada' => 'success',
                                    'atendida' => 'primary',
                                    'cancelada' => 'danger'
                                ];

                                $colorEstado = $colores[$cita['estado']] ?? 'secondary';
                                ?>

                                <span class="badge bg-<?php echo e($colorEstado); ?>">
                                    <?php echo e(ucfirst($cita['estado'])); ?>
                                </span>
                            </td>

                            <td class="text-center">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#verCita<?php echo (int)$cita['id']; ?>">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editarCita<?php echo (int)$cita['id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <?php if ($cita['estado'] !== 'cancelada' && $cita['estado'] !== 'atendida'): ?>

                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Deseas cancelar esta cita?');">

                                        <input type="hidden" name="accion" value="cancelar">
                                        <input type="hidden" name="id" value="<?php echo (int)$cita['id']; ?>">

                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle"></i>
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

    <?php
}

?>

<style>
.citas-contenido {
    padding: 25px;
}

.citas-card {
    border: 0;
    border-radius: 12px;
}

.citas-estadistica {
    border-radius: 12px;
    padding: 20px;
    background: #fff;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
}

.citas-estadistica h3 {
    font-weight: 700;
    margin-bottom: 4px;
}

.citas-estadistica p {
    color: #777;
    margin: 0;
}

.table th {
    font-size: 13px;
    white-space: nowrap;
}

.table td {
    font-size: 14px;
}

.modal-body {
    max-height: 65vh;
    overflow-y: auto;
}
</style>

<?php
$includeSidebar = __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex">

    <?php
    if (file_exists($includeSidebar)) {
        include $includeSidebar;
    }
    ?>

    <main class="flex-grow-1 citas-contenido">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">

            <div>
                <h3 class="fw-bold mb-1">Gestión de citas</h3>
                <p class="text-muted mb-0">
                    Administra y consulta las citas de tus personas registradas.
                </p>
            </div>

            <button
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalNuevaCita">

                <i class="bi bi-calendar-plus me-1"></i>
                Asignar cita

            </button>

        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo e($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS -->

        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="citas-estadistica">
                    <h3><?php echo count($citasConfirmadas); ?></h3>
                    <p>Citas confirmadas</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="citas-estadistica">
                    <h3><?php echo count($citasPendientes); ?></h3>
                    <p>Citas pendientes</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="citas-estadistica">
                    <h3><?php echo count($citasAtendidas); ?></h3>
                    <p>Citas atendidas</p>
                </div>
            </div>

            <div class="col-md-3">
                <div class="citas-estadistica">
                    <h3><?php echo count($citasCanceladas); ?></h3>
                    <p>Citas canceladas</p>
                </div>
            </div>

        </div>

        <!-- BUSCADOR -->

        <div class="card citas-card shadow-sm mb-4">

            <div class="card-body">

                <form method="GET" class="row g-2">

                    <div class="col-md-10">
                        <input
                            type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por folio, nombre, teléfono o correo..."
                            value="<?php echo e($busqueda); ?>">
                    </div>

                    <div class="col-md-2 d-grid">
                        <button class="btn btn-dark" type="submit">
                            <i class="bi bi-search me-1"></i>
                            Buscar
                        </button>
                    </div>

                </form>

            </div>
        </div>

        <!-- TABLAS DE CITAS -->

        <?php
        mostrarTablaCitas($citasConfirmadas, 'Citas confirmadas', 'success');
        mostrarTablaCitas($citasPendientes, 'Citas pendientes', 'warning');
        mostrarTablaCitas($citasAtendidas, 'Citas atendidas', 'primary');
        mostrarTablaCitas($citasCanceladas, 'Citas canceladas', 'danger');
        ?>

    </main>
</div>

<!-- =====================================
     MODAL NUEVA CITA
===================================== -->

<div class="modal fade" id="modalNuevaCita" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-plus me-2"></i>
                        Asignar nueva cita
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="accion" value="crear">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Persona *</label>

                        <select name="persona_id" class="form-select" required>
                            <option value="">Selecciona una persona</option>

                            <?php foreach ($personas as $persona): ?>

                                <option value="<?php echo (int)$persona['id']; ?>">
                                    <?php
                                    echo e(
                                        $persona['folio'] . ' - ' .
                                        $persona['nombre'] . ' ' .
                                        $persona['apellidos'] .
                                        ' - ' . $persona['telefono']
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if (empty($personas)): ?>
                            <small class="text-danger">
                                No hay personas con solicitudes confirmadas disponibles.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Fecha *</label>
                            <input
                                type="date"
                                name="fecha"
                                class="form-control"
                                min="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Hora *</label>
                            <input
                                type="time"
                                name="hora"
                                class="form-control"
                                required>
                        </div>

                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de atención *</label>

                        <select name="tipo_atencion" class="form-select" required>
                            <option value="">Selecciona el tipo</option>
                            <option value="Presencial">Presencial</option>
                            <option value="En línea">En línea</option>
                            <option value="Laboratorio">Laboratorio</option>
                            <option value="Estudio">Estudio</option>
                            <option value="Consulta general">Consulta general</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo de la cita *</label>
                        <textarea
                            name="motivo"
                            class="form-control"
                            rows="3"
                            required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Observaciones</label>
                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="2"></textarea>
                    </div>

                    <div class="alert alert-info mb-0">
                        La cita se guardará automáticamente con estado confirmado.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        <?php echo empty($personas) ? 'disabled' : ''; ?>>
                        <i class="bi bi-check-circle me-1"></i>
                        Guardar cita
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- =====================================
     MODALES DE CONSULTA Y EDICIÓN
===================================== -->

<?php foreach ($todasLasCitas as $cita): ?>

    <!-- VER CITA -->

    <div class="modal fade" id="verCita<?php echo (int)$cita['id']; ?>" tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Información de la cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <small class="text-muted">Folio de cita</small>
                            <div class="fw-bold"><?php echo e($cita['folio']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Folio de persona</small>
                            <div class="fw-bold"><?php echo e($cita['folio_persona']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Nombre</small>
                            <div class="fw-semibold">
                                <?php echo e($cita['nombre'] . ' ' . $cita['apellidos']); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Teléfono</small>
                            <div><?php echo e($cita['telefono']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Correo</small>
                            <div><?php echo e($cita['correo']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Fecha</small>
                            <div>
                                <?php echo e(date('d/m/Y', strtotime($cita['fecha']))); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Hora</small>
                            <div><?php echo e(substr($cita['hora'], 0, 5)); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Tipo de atención</small>
                            <div><?php echo e($cita['tipo_atencion']); ?></div>
                        </div>

                        <div class="col-md-12">
                            <small class="text-muted">Motivo</small>
                            <div><?php echo e($cita['motivo']); ?></div>
                        </div>

                        <div class="col-md-12">
                            <small class="text-muted">Observaciones</small>
                            <div><?php echo e($cita['observaciones'] ?: 'Sin observaciones'); ?></div>
                        </div>

                        <div class="col-md-12">
                            <small class="text-muted">Estado</small>
                            <div class="fw-bold"><?php echo e(ucfirst($cita['estado'])); ?></div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- EDITAR CITA -->

    <div class="modal fade" id="editarCita<?php echo (int)$cita['id']; ?>" tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Actualizar cita</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id" value="<?php echo (int)$cita['id']; ?>">

                        <div class="mb-3">
                            <label class="form-label">Folio</label>
                            <input
                                type="text"
                                class="form-control"
                                value="<?php echo e($cita['folio']); ?>"
                                readonly>
                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha *</label>
                                <input
                                    type="date"
                                    name="fecha"
                                    class="form-control"
                                    value="<?php echo e($cita['fecha']); ?>"
                                    required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hora *</label>
                                <input
                                    type="time"
                                    name="hora"
                                    class="form-control"
                                    value="<?php echo e(substr($cita['hora'], 0, 5)); ?>"
                                    required>
                            </div>

                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de atención *</label>
                            <input
                                type="text"
                                name="tipo_atencion"
                                class="form-control"
                                value="<?php echo e($cita['tipo_atencion']); ?>"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea
                                name="motivo"
                                class="form-control"
                                rows="3"
                                required><?php echo e($cita['motivo']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estado</label>

                            <select name="estado" class="form-select" required>

                                <option value="pendiente"
                                    <?php echo $cita['estado'] === 'pendiente' ? 'selected' : ''; ?>>
                                    Pendiente
                                </option>

                                <option value="confirmada"
                                    <?php echo $cita['estado'] === 'confirmada' ? 'selected' : ''; ?>>
                                    Confirmada
                                </option>

                                <option value="atendida"
                                    <?php echo $cita['estado'] === 'atendida' ? 'selected' : ''; ?>>
                                    Atendida
                                </option>

                                <option value="cancelada"
                                    <?php echo $cita['estado'] === 'cancelada' ? 'selected' : ''; ?>>
                                    Cancelada
                                </option>

                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea
                                name="observaciones"
                                class="form-control"
                                rows="3"><?php echo e($cita['observaciones']); ?></textarea>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>
                            Guardar cambios
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

<?php endforeach; ?>
