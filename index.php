
<?php
require_once 'config/database.php';

$pdo = db();

$pagina = $_GET['pagina'] ?? 'dashboard';

// Estadísticas
$hoy = date('Y-m-d');

$pacientesTotal = $pdo->query("
    SELECT COUNT(*) FROM pacientes
")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE fecha = ?");
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

$titulos = [
    'dashboard' => 'Panel principal',
    'pacientes' => 'Pacientes',
    'citas' => 'Citas médicas',
    'solicitudes' => 'Solicitudes pendientes',
    'recepcion' => 'Recepción',
    'callcenter' => 'Call Center',
    'estudios' => 'Estudios',
    'sucursales' => 'Sucursales',
    'reportes' => 'Reportes'
];

$modulos = [
    'pacientes' => 'modules/pacientes/pacientes.php',
    'citas' => 'modules/citas/citas.php',
    'solicitudes' => 'modules/solicitudes/solicitudes.php',
    'recepcion' => 'modules/recepcion/recepcion.php',
    'callcenter' => 'modules/callcenter/callcenter.php',
    'estudios' => 'modules/estudios/estudios.php',
    'sucursales' => 'modules/sucursales/sucursales.php',
    'reportes' => 'modules/reportes/reportes.php'
];

$archivo = $modulos[$pagina] ?? null;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($titulos[$pagina] ?? 'VitaPlus') ?> | VitaPlus</title>

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
                <button class="btn btn-outline-primary d-md-none" id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>

                <span class="fw-bold ms-2">
                    <?= htmlspecialchars($titulos[$pagina] ?? 'VitaPlus') ?>
                </span>
            </div>

            <div class="text-muted small">
                <i class="bi bi-calendar3"></i>
                <?= date('d/m/Y') ?>
            </div>

        </nav>

        <div class="container-fluid p-4">

            <?php if ($pagina === 'dashboard'): ?>

                <div class="mb-4">
                    <h4>Bienvenido al sistema</h4>
                    <p class="text-muted">
                        Resumen general de la actividad de la clínica.
                    </p>
                </div>

                <div class="row g-3 mb-4">

                    <div class="col-md-6 col-xl-3">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <p>Pacientes registrados</p>
                                <h3><?= $pacientesTotal ?></h3>
                                <i class="bi bi-people card-icon text-primary"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <p>Citas de hoy</p>
                                <h3><?= $citasHoy ?></h3>
                                <i class="bi bi-calendar-check card-icon text-success"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <p>Citas pendientes</p>
                                <h3><?= $pendientes ?></h3>
                                <i class="bi bi-clock card-icon text-warning"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <p>Solicitudes web</p>
                                <h3><?= $solicitudes ?></h3>
                                <i class="bi bi-globe card-icon text-info"></i>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="card">
                    <div class="card-body">

                        <h5 class="mb-3">Accesos rápidos</h5>

                        <a href="index.php?pagina=pacientes" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Registrar paciente
                        </a>

                        <a href="index.php?pagina=citas" class="btn btn-success">
                            <i class="bi bi-calendar-plus"></i> Nueva cita
                        </a>

                        <a href="index.php?pagina=solicitudes" class="btn btn-outline-primary">
                            <i class="bi bi-globe"></i> Solicitudes web
                        </a>

                    </div>
                </div>

            <?php elseif ($archivo && file_exists(__DIR__ . '/' . $archivo)): ?>

                <?php include __DIR__ . '/' . $archivo; ?>

            <?php else: ?>

                <div class="alert alert-warning">
                    El módulo todavía no está desarrollado.
                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>

</body>
</html>
