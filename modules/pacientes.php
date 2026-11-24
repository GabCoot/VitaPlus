
<?php
require_once __DIR__ . '/../../config/database.php';

$pdo = db();
date_default_timezone_set('America/Mexico_City');

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =========================================
   GENERAR FOLIO AUTOMATICO DE CITA
========================================= */
function generarFolioCita($pdo) {
    do {
        $folio = 'CIT-' . date('Ymd') . '-' . random_int(100000, 999999);

        $consulta = $pdo->prepare("
            SELECT id FROM citas WHERE folio = ? LIMIT 1
        ");
        $consulta->execute([$folio]);

    } while ($consulta->fetch());

    return $folio;
}

$mensaje = '';
$error = '';

/* =========================================
   ASIGNAR CITA
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'asignar_cita') {

    try {

        $persona_id = (int)($_POST['persona_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $tipo_atencion = trim($_POST['tipo_atencion'] ?? '');
        $motivo = trim($_POST['motivo'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        if (!$persona_id || !$fecha || !$hora || !$tipo_atencion || !$motivo) {
            throw new Exception('Completa todos los campos obligatorios.');
        }

        // Comprobar que la persona esté confirmada
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
            throw new Exception('La persona no tiene una identidad confirmada.');
        }

        $folio = generarFolioCita($pdo);

        // Crear cita confirmada
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

        header('Location: pacientes.php?mensaje=cita_asignada');
        exit;

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* =========================================
   MENSAJES
========================================= */
if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'cita_asignada') {
    $mensaje = 'La cita se asignó correctamente.';
}

/* =========================================
   BUSCADOR
========================================= */
$buscar = trim($_GET['buscar'] ?? '');

/* =========================================
   CONSULTAR TODOS LOS PACIENTES
   CON IDENTIDAD CONFIRMADA
========================================= */

$sql = "
    SELECT
        p.id,
        p.nombre,
        p.apellidos,
        p.folio,
        p.telefono,
        p.telefono_secundario,
        p.correo,
        p.fecha_nacimiento,
        p.sexo,
        p.ciudad,
        p.direccion,
        p.codigo_postal,

        (
            SELECT COUNT(*)
            FROM citas c
            WHERE c.persona_id = p.id
            AND c.estado <> 'cancelada'
        ) AS total_citas

    FROM personas p

    WHERE EXISTS (
        SELECT 1
        FROM procesos_pendientes pp
        WHERE pp.persona_id = p.id
        AND pp.estado = 'confirmada'
    )
";

$parametros = [];

if ($buscar !== '') {

    $sql .= "
        AND (
            p.nombre LIKE ?
            OR p.apellidos LIKE ?
            OR p.folio LIKE ?
            OR p.telefono LIKE ?
            OR p.correo LIKE ?
        )
    ";

    $valor = "%{$buscar}%";

    $parametros = [
        $valor,
        $valor,
        $valor,
        $valor,
        $valor
    ];
}

$sql .= " ORDER BY p.id DESC";

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);

$pacientes = $consulta->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   CONTADORES
========================================= */

$totalPacientes = (int)$pdo->query("
    SELECT COUNT(*)
    FROM personas p
    WHERE EXISTS (
        SELECT 1
        FROM procesos_pendientes pp
        WHERE pp.persona_id = p.id
        AND pp.estado = 'confirmada'
    )
")->fetchColumn();

$totalCitas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM citas
    WHERE estado <> 'cancelada'
")->fetchColumn();

$totalConfirmadas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM citas
    WHERE estado = 'confirmada'
")->fetchColumn();

?>

<style>
.pacientes-contenido {
    padding: 25px;
}

.paciente-estadistica {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    height: 100%;
}

.paciente-estadistica h3 {
    font-size: 27px;
    font-weight: 700;
    margin-bottom: 4px;
}

.paciente-estadistica p {
    color: #777;
    margin: 0;
}

.paciente-tabla th {
    font-size: 13px;
    white-space: nowrap;
}

.paciente-tabla td {
    font-size: 14px;
    vertical-align: middle;
}

.paciente-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e8f0ff;
    color: #2463c9;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    font-weight: 700;
    margin-right: 10px;
}

.paciente-modal .modal-body {
    max-height: 65vh;
    overflow-y: auto;
}
</style>

<div class="d-flex">

    <?php
    $sidebar = __DIR__ . '/../../includes/sidebar.php';

    if (file_exists($sidebar)) {
        include $sidebar;
    }
    ?>

    <main class="flex-grow-1 pacientes-contenido">

        <!-- ENCABEZADO -->

        <div class="mb-4">

            <h3 class="fw-bold mb-1">
                Pacientes
            </h3>

            <p class="text-muted mb-0">
                Consulta tus pacientes confirmados y administra sus citas.
            </p>

        </div>

        <!-- MENSAJES -->

        <?php if ($mensaje): ?>

            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo escapar($mensaje); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo escapar($error); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php endif; ?>

        <!-- ESTADISTICAS -->

        <div class="row g-3 mb-4">

            <div class="col-md-4">

                <div class="paciente-estadistica">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h3><?php echo $totalPacientes; ?></h3>
                            <p>Pacientes confirmados</p>
                        </div>

                        <i class="bi bi-people fs-2 text-primary"></i>

                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="paciente-estadistica">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h3><?php echo $totalCitas; ?></h3>
                            <p>Citas registradas</p>
                        </div>

                        <i class="bi bi-calendar-check fs-2 text-success"></i>

                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="paciente-estadistica">

                    <div class="d-flex justify-content-between">

                        <div>
                            <h3><?php echo $totalConfirmadas; ?></h3>
                            <p>Citas confirmadas</p>
                        </div>

                        <i class="bi bi-check-circle fs-2 text-info"></i>

                    </div>

                </div>

            </div>

        </div>

        <!-- BUSCADOR -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body">

                <form method="GET" class="row g-2">

                    <div class="col-md-10">

                        <input
                            type="text"
                            name="buscar"
                            class="form-control"
                            placeholder="Buscar por nombre, folio, teléfono o correo..."
                            value="<?php echo escapar($buscar); ?>">

                    </div>

                    <div class="col-md-2 d-grid">

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>
                            Buscar
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <!-- TABLA -->

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white py-3">

                <div class="d-flex justify-content-between align-items-center">

                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-person-check text-primary me-2"></i>
                        Pacientes registrados y confirmados
                    </h5>

                    <span class="badge bg-success">
                        <?php echo count($pacientes); ?> pacientes
                    </span>

                </div>

            </div>

            <div class="table-responsive">

                <table class="table table-hover paciente-tabla mb-0">

                    <thead class="table-light">

                        <tr>
                            <th>Paciente</th>
                            <th>Folio</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Citas</th>
                            <th>Identidad</th>
                            <th class="text-center">Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($pacientes)): ?>

                        <tr>
                            <td colspan="7" class="text-center py-5">

                                <i class="bi bi-person-x fs-1 text-muted"></i>

                                <h6 class="mt-3 fw-bold">
                                    No hay pacientes confirmados
                                </h6>

                                <p class="text-muted mb-0">
                                    Las personas aparecerán aquí cuando confirmes
                                    sus solicitudes desde el módulo Solicitudes.
                                </p>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($pacientes as $paciente): ?>

                            <tr>

                                <td>

                                    <div class="d-flex align-items-center">

                                        <div class="paciente-avatar">

                                            <?php
                                            echo escapar(
                                                strtoupper(substr($paciente['nombre'], 0, 1))
                                            );
                                            ?>

                                        </div>

                                        <div>

                                            <div class="fw-semibold">
                                                <?php
                                                echo escapar(
                                                    $paciente['nombre'] . ' ' . $paciente['apellidos']
                                                );
                                                ?>
                                            </div>

                                            <small class="text-muted">
                                                <?php echo escapar($paciente['ciudad']); ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>

                                <td>
                                    <span class="fw-semibold">
                                        <?php echo escapar($paciente['folio']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo escapar($paciente['telefono']); ?>
                                </td>

                                <td>
                                    <?php echo escapar($paciente['correo']); ?>
                                </td>

                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo (int)$paciente['total_citas']; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Confirmada
                                    </span>
                                </td>

                                <td class="text-center">

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#verPaciente<?php echo (int)$paciente['id']; ?>"
                                        title="Ver información">

                                        <i class="bi bi-eye"></i>

                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#citaPaciente<?php echo (int)$paciente['id']; ?>">

                                        <i class="bi bi-calendar-plus me-1"></i>
                                        Asignar cita

                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

<!-- =========================================
     MODALES DE PACIENTES
========================================= -->

<?php foreach ($pacientes as $paciente): ?>

    <!-- VER PACIENTE -->

    <div
        class="modal fade paciente-modal"
        id="verPaciente<?php echo (int)$paciente['id']; ?>"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title fw-bold">
                        Información del paciente
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <small class="text-muted">Nombre completo</small>
                            <div class="fw-semibold">
                                <?php echo escapar($paciente['nombre'] . ' ' . $paciente['apellidos']); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Folio</small>
                            <div class="fw-semibold">
                                <?php echo escapar($paciente['folio']); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Teléfono</small>
                            <div><?php echo escapar($paciente['telefono']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Teléfono secundario</small>
                            <div><?php echo escapar($paciente['telefono_secundario']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Correo</small>
                            <div><?php echo escapar($paciente['correo']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Fecha de nacimiento</small>
                            <div><?php echo escapar($paciente['fecha_nacimiento']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Sexo</small>
                            <div><?php echo escapar($paciente['sexo']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Código postal</small>
                            <div><?php echo escapar($paciente['codigo_postal']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Ciudad</small>
                            <div><?php echo escapar($paciente['ciudad']); ?></div>
                        </div>

                        <div class="col-md-12">
                            <small class="text-muted">Dirección</small>
                            <div><?php echo escapar($paciente['direccion']); ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted">Citas registradas</small>
                            <div><?php echo (int)$paciente['total_citas']; ?></div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cerrar
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- ASIGNAR CITA -->

    <div
        class="modal fade paciente-modal"
        id="citaPaciente<?php echo (int)$paciente['id']; ?>"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <form method="POST">

                    <div class="modal-header">

                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-calendar-plus text-primary me-2"></i>
                            Asignar cita
                        </h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body">

                        <input type="hidden" name="accion" value="asignar_cita">

                        <input
                            type="hidden"
                            name="persona_id"
                            value="<?php echo (int)$paciente['id']; ?>">

                        <div class="alert alert-light border">

                            <div class="fw-bold">
                                <?php echo escapar($paciente['nombre'] . ' ' . $paciente['apellidos']); ?>
                            </div>

                            <small class="text-muted">
                                Folio: <?php echo escapar($paciente['folio']); ?>
                            </small>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Fecha de la cita *
                                </label>

                                <input
                                    type="date"
                                    name="fecha"
                                    class="form-control"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Hora *
                                </label>

                                <input
                                    type="time"
                                    name="hora"
                                    class="form-control"
                                    required>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Tipo de atención *
                            </label>

                            <select name="tipo_atencion" class="form-select" required>

                                <option value="">Selecciona una opción</option>
                                <option value="Presencial">Presencial</option>
                                <option value="En línea">En línea</option>
                                <option value="Laboratorio">Laboratorio</option>
                                <option value="Estudio">Estudio</option>
                                <option value="Consulta general">Consulta general</option>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Motivo de la cita *
                            </label>

                            <textarea
                                name="motivo"
                                class="form-control"
                                rows="3"
                                placeholder="Describe el motivo de la cita..."
                                required></textarea>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Observaciones
                            </label>

                            <textarea
                                name="observaciones"
                                class="form-control"
                                rows="2"
                                placeholder="Observaciones adicionales..."></textarea>

                        </div>

                        <div class="alert alert-success mb-0">

                            <i class="bi bi-check-circle me-2"></i>
                            La cita se guardará automáticamente como confirmada.

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>

                        <button type="submit" class="btn btn-primary">

                            <i class="bi bi-calendar-check me-1"></i>
                            Guardar y confirmar cita

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

<?php endforeach; ?>
