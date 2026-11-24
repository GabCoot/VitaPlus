
<?php

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

$mensaje = '';
$error = '';

// REGISTRAR PACIENTE
if (isset($_POST['registrar'])) {

    try {

        $nombres = trim($_POST['nombres']);
        $apellidos = trim($_POST['apellidos']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $sexo = $_POST['sexo'];
        $telefono = trim($_POST['telefono']);
        $telefono_secundario = trim($_POST['telefono_secundario']);
        $correo = trim($_POST['correo']);
        $codigo_postal = trim($_POST['codigo_postal']);
        $direccion = trim($_POST['direccion']);

        $acepta_aviso_privacidad = isset($_POST['acepta_aviso_privacidad']) ? 1 : 0;

        // Generar folio automático
        $folio = 'VP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $stmt = $pdo->prepare("
            INSERT INTO pacientes (
                folio,
                nombres,
                apellidos,
                fecha_nacimiento,
                sexo,
                telefono,
                telefono_secundario,
                correo,
                codigo_postal,
                direccion,
                acepta_aviso_privacidad,
                activo,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $folio,
            $nombres,
            $apellidos,
            $fecha_nacimiento,
            $sexo,
            $telefono,
            $telefono_secundario ?: null,
            $correo ?: null,
            $codigo_postal,
            $direccion,
            $acepta_aviso_privacidad
        ]);

        $mensaje = "Paciente registrado correctamente. Folio: " . $folio;

    } catch (PDOException $e) {

        $error = "No se pudo registrar el paciente: " . $e->getMessage();

    }
}

// BUSCAR PACIENTES
$buscar = $_GET['buscar'] ?? '';

$stmt = $pdo->prepare("
    SELECT *
    FROM pacientes
    WHERE folio LIKE ?
       OR nombres LIKE ?
       OR apellidos LIKE ?
       OR telefono LIKE ?
    ORDER BY id DESC
");

$termino = "%$buscar%";

$stmt->execute([
    $termino,
    $termino,
    $termino,
    $termino
]);

$pacientes = $stmt->fetchAll();

// TOTAL DE PACIENTES
$total = $pdo->query("
    SELECT COUNT(*) FROM pacientes
")->fetchColumn();

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h4>Pacientes</h4>
        <p class="text-muted mb-0">Administración de pacientes registrados</p>
    </div>

    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaciente">
        <i class="bi bi-person-plus"></i> Nuevo paciente
    </button>

</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="row mb-4">

    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1">Total de pacientes</p>
                <h3><?= $total ?></h3>
            </div>
        </div>
    </div>

</div>

<div class="card shadow-sm">

    <div class="card-body">

        <form method="GET" class="row g-2 mb-4">

            <input type="hidden" name="pagina" value="pacientes">

            <div class="col-md-5">
                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar por folio, nombre o teléfono..."
                    value="<?= htmlspecialchars($buscar) ?>"
                >
            </div>

            <div class="col-auto">
                <button class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>

                <a href="index.php?pagina=pacientes" class="btn btn-secondary">
                    Limpiar
                </a>
            </div>

        </form>

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Nombre completo</th>
                        <th>Fecha nacimiento</th>
                        <th>Sexo</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($pacientes)): ?>

                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No hay pacientes registrados.
                            </td>
                        </tr>

                    <?php endif; ?>

                    <?php foreach ($pacientes as $p): ?>

                        <tr>

                            <td>
                                <strong><?= htmlspecialchars($p['folio']) ?></strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['fecha_nacimiento']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['sexo']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['telefono']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($p['correo'] ?? '') ?>
                            </td>

                            <td>
                                <?php if ($p['activo'] == 1): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- MODAL REGISTRAR PACIENTE -->

<div class="modal fade" id="modalPaciente" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">Registrar nuevo paciente</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombres *</label>
                            <input type="text" name="nombres" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellidos *</label>
                            <input type="text" name="apellidos" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de nacimiento *</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sexo *</label>
                            <select name="sexo" class="form-select" required>
                                <option value="">Seleccionar</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono *</label>
                            <input type="tel" name="telefono" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono secundario</label>
                            <input type="tel" name="telefono_secundario" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="correo" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Código postal *</label>
                            <input type="text" name="codigo_postal" class="form-control" maxlength="10" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Dirección *</label>
                            <textarea name="direccion" class="form-control" rows="2" required></textarea>
                        </div>

                        <div class="col-12 mb-3">

                            <div class="form-check">

                                <input
                                    type="checkbox"
                                    name="acepta_aviso_privacidad"
                                    value="1"
                                    class="form-check-input"
                                    id="avisoPrivacidad"
                                    required
                                >

                                <label class="form-check-label" for="avisoPrivacidad">
                                    El paciente acepta el aviso de privacidad.
                                </label>

                            </div>

                        </div>

                    </div>

                    <small class="text-muted">* Campos obligatorios</small>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" name="registrar" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar paciente
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
