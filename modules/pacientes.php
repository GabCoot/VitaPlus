
<?php

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

$buscar = trim($_GET['buscar'] ?? '');

// BUSCAR PERSONAS
$sql = "SELECT
            id,
            folio,
            nombre,
            apellidos,
            usuario,
            fecha_nacimiento,
            sexo,
            correo,
            telefono,
            telefono_secundario,
            codigo_postal,
            ciudad,
            direccion,
            acepta_aviso_privacidad,
            perfil_completo,
            fecha_registro
        FROM personas";

if ($buscar !== '') {
    $sql .= " WHERE folio LIKE ?
              OR nombre LIKE ?
              OR apellidos LIKE ?
              OR usuario LIKE ?
              OR correo LIKE ?
              OR telefono LIKE ?";
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);

if ($buscar !== '') {
    $termino = "%$buscar%";
    $stmt->execute([
        $termino,
        $termino,
        $termino,
        $termino,
        $termino,
        $termino
    ]);
} else {
    $stmt->execute();
}

$personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $pdo->query("SELECT COUNT(*) FROM personas")->fetchColumn();

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4>Pacientes</h4>
        <p class="text-muted mb-0">
            Administración y consulta de personas registradas
        </p>
    </div>

    <span class="badge bg-primary fs-6">
        <?= $total ?> pacientes
    </span>
</div>

<!-- BUSCADOR -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <input type="hidden" name="pagina" value="pacientes">

            <div class="col-md-8">
                <input
                    type="text"
                    name="buscar"
                    class="form-control"
                    placeholder="Buscar por folio, nombre, apellidos, teléfono o correo..."
                    value="<?= htmlspecialchars($buscar) ?>"
                >
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>

                <a href="index.php?pagina=pacientes" class="btn btn-secondary">
                    Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABLA DE PACIENTES -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <strong>Personas registradas</strong>
    </div>

    <div class="card-body">
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
                        <th>Ciudad</th>
                        <th>Perfil</th>
                        <th>Registro</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($personas)): ?>

                    <?php foreach ($personas as $p): ?>

                        <tr>
                            <td>
                                <strong class="text-primary">
                                    <?= htmlspecialchars($p['folio'] ?? '') ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    trim(($p['nombre'] ?? '') . ' ' . ($p['apellidos'] ?? ''))
                                ) ?>
                            </td>

                            <td><?= htmlspecialchars($p['fecha_nacimiento'] ?? '') ?></td>

                            <td><?= htmlspecialchars($p['sexo'] ?? '') ?></td>

                            <td><?= htmlspecialchars($p['telefono'] ?? '') ?></td>

                            <td><?= htmlspecialchars($p['correo'] ?? '') ?></td>

                            <td><?= htmlspecialchars($p['ciudad'] ?? '') ?></td>

                            <td>
                                <?php if ($p['perfil_completo']): ?>
                                    <span class="badge bg-success">Completo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Incompleto</span>
                                <?php endif; ?>
                            </td>

                            <td><?= htmlspecialchars($p['fecha_registro'] ?? '') ?></td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No se encontraron pacientes.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>
