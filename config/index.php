
<?php
require_once 'config/database.php';

$pdo = db();

$hoy = date('Y-m-d');

// Estadísticas
$pacientes = $pdo->query("
    SELECT COUNT(*) FROM pacientes WHERE activo = 1
")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM citas WHERE fecha = ?
");
$stmt->execute([$hoy]);
$citasHoy = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM citas
    WHERE fecha = ? AND estado = 'Pendiente'
");
$stmt->execute([$hoy]);
$pendientes = $stmt->fetchColumn();

$solicitudes = $pdo->query("
    SELECT COUNT(*) FROM solicitudes_web
    WHERE estado = 'Pendiente'
")->fetchColumn();

// Citas de hoy
$stmt = $pdo->prepare("
    SELECT
        c.folio,
        c.hora,
        c.estado,
        p.nombres,
        p.apellidos,
        e.nombre AS estudio
    FROM citas c
    INNER JOIN pacientes p ON p.id = c.paciente_id
    INNER JOIN estudios e ON e.id = c.estudio_id
    WHERE c.fecha = ?
    ORDER BY c.hora ASC
    LIMIT 10
");
$stmt->execute([$hoy]);
$citas = $stmt->fetchAll();

// Solicitudes web
$stmt = $pdo->query("
    SELECT
        sw.folio,
        sw.created_at,
        p.nombres,
        p.apellidos,
        e.nombre AS estudio
    FROM solicitudes_web sw
    INNER JOIN pacientes p ON p.id = sw.paciente_id
    INNER JOIN estudios e ON e.id = sw.estudio_id
    WHERE sw.estado = 'Pendiente'
    ORDER BY sw.created_at DESC
    LIMIT 5
");
$solicitudesRecientes = $stmt->fetchAll();

function estadoBadge($estado) {
    $clases = [
        'Pendiente' => 'warning',
        'Confirmada' => 'success',
        'Atendida' => 'primary',
        'Cancelada' => 'danger',
        'No asistió' => 'secondary',
        'En recepción' => 'info'
    ];

    return $clases[$estado] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Centro Médico</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<div class="d-flex">

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content w-100">

        <nav class="navbar bg-white border-bottom px-4 py-3">
            <div>
                <button class="btn btn-outline-primary d-md-none"
                        id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>

                <span class="fw-bold ms-2">Panel principal</span>
            </div>

            <div class="text-muted small">
                <i class="bi bi-calendar3"></i>
                <?= date('d/m/Y') ?>
            </div>
        </nav>

        <div class="container-fluid p-4">

            <div class="mb-4">
                <h4>Bienvenido al sistema</h4>
                <p class="text-muted">
                    Resumen general de la actividad de la clínica.
                </p>
            </div>

            <!-- TARJETAS -->

            <div class="row g-3 mb-4">

                <div class="col-md-6 col-xl-3">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p>Pacientes registrados</p>
                                    <h3><?= $pacientes ?></h3>
                                </div>
                                <i class="bi bi-people card-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p>Citas de hoy</p>
                                    <h3><?= $citasHoy ?></h3>
                                </div>
                                <i class="bi bi-calendar-check card-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p>Citas pendientes</p>
                                    <h3><?= $pendientes ?></h3>
                                </div>
                                <i class="bi bi-clock card-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p>Solicitudes web</p>
                                    <h3><?= $solicitudes ?></h3>
                                </div>
                                <i class="bi bi-globe card-icon text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ACCESOS RAPIDOS -->

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Accesos rápidos</h5>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="modules/pacientes/" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i>
                            Registrar paciente
                        </a>

                        <a href="modules/citas/" class="btn btn-success">
                            <i class="bi bi-calendar-plus"></i>
                            Nueva cita
                        </a>

                        <a href="modules/solicitudes/" class="btn btn-outline-primary">
                            <i class="bi bi-globe"></i>
                            Ver solicitudes
                        </a>

                        <a href="modules/callcenter/" class="btn btn-outline-secondary">
                            <i class="bi bi-headset"></i>
                            Call Center
                        </a>
                    </div>
                </div>
            </div>

            <!-- CITAS -->

            <div class="card mb-4">
                <div class="card-body">

                    <h5 class="mb-3">Citas de hoy</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Paciente</th>
                                    <th>Estudio</th>
                                    <th>Hora</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (empty($citas)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No hay citas programadas para hoy.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($citas as $cita): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cita['folio']) ?></td>

                                    <td>
                                        <?= htmlspecialchars($cita['nombres'] . ' ' . $cita['apellidos']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($cita['estudio']) ?></td>

                                    <td>
                                        <?= date('h:i A', strtotime($cita['hora'])) ?>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-<?= estadoBadge($cita['estado']) ?>">
                                            <?= htmlspecialchars($cita['estado']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <!-- SOLICITUDES -->

            <div class="card">
                <div class="card-body">

                    <h5 class="mb-3">Solicitudes web pendientes</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Paciente</th>
                                    <th>Estudio solicitado</th>
                                    <th>Fecha de solicitud</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (empty($solicitudesRecientes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No hay solicitudes pendientes.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($solicitudesRecientes as $solicitud): ?>
                                <tr>
                                    <td><?= htmlspecialchars($solicitud['folio']) ?></td>

                                    <td>
                                        <?= htmlspecialchars($solicitud['nombres'] . ' ' . $solicitud['apellidos']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($solicitud['estudio']) ?></td>

                                    <td>
                                        <?= date('d/m/Y', strtotime($solicitud['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

            <footer class="text-center text-muted mt-4 small">
                Sistema de gestión médica © <?= date('Y') ?>
            </footer>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>

</body>
</html>
