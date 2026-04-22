<?php
require_once '../src/auth.php';
requireRol(['admin']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Dashboard';
$pageSubtitle = 'Tablero principal para revisar la actividad de la clínica y saltar a los módulos clave.';
$activeModule = 'dashboard';

function countRows(mysqli $conn, string $table): int
{
    $result = $conn->query("SELECT COUNT(*) AS total FROM {$table}");
    if ($result && ($row = $result->fetch_assoc())) {
        return (int)$row['total'];
    }
    return 0;
}

function fetchAllRows(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$stats = [
    ['label' => 'Usuarios', 'value' => countRows($conn, 'USUARIOS'), 'note' => 'Cuentas registradas', 'accent' => 'blue'],
    ['label' => 'Pacientes', 'value' => countRows($conn, 'PACIENTES'), 'note' => 'Perfiles clínicos', 'accent' => 'sand'],
    ['label' => 'Médicos', 'value' => countRows($conn, 'MEDICOS'), 'note' => 'Profesionales activos', 'accent' => 'blue'],
    ['label' => 'Citas', 'value' => countRows($conn, 'CITAS'), 'note' => 'Agendas creadas', 'accent' => 'sand'],
    ['label' => 'Consultas', 'value' => countRows($conn, 'CONSULTAS'), 'note' => 'Atenciones cerradas', 'accent' => 'blue'],
    ['label' => 'Bitácora', 'value' => countRows($conn, 'BITACORA'), 'note' => 'Movimientos guardados', 'accent' => 'sand'],
];

$appointments = fetchAllRows(
    $conn,
    "SELECT c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            s.nombre AS servicio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
     INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     ORDER BY c.fecha_hora_inicio ASC
     LIMIT 5"
);

$inventory = fetchAllRows(
    $conn,
    "SELECT m.nombre_comercial, i.cantidad_disponible, i.fecha_caducidad, e.estado
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     INNER JOIN ESTADOS_MEDICAMENTOS e ON i.id_estado_medicamento = e.id_estado_medicamento
     ORDER BY i.fecha_caducidad ASC
     LIMIT 4"
);

include '../src/admin/header.php';
?>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
        <div class="col-sm-6 col-xl-4">
            <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                <div class="stat-value"><?php echo (int)$stat['value']; ?></div>
                <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Próximas citas</h2>
                <p class="section-subtitle">Seguimiento rápido de la agenda.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Paciente</th>
                                <th>Médico</th>
                                <th>Servicio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                    <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                    <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Inventario</h2>
                <p class="section-subtitle">Medicamentos que conviene revisar.</p>
            </div>
            <div class="panel-body d-grid gap-3">
                <?php foreach ($inventory as $item): ?>
                    <?php
                    $chip = 'chip-green';
                    if ($item['estado'] === 'Caducado') {
                        $chip = 'chip-red';
                    } elseif (stripos($item['estado'], 'caducar') !== false) {
                        $chip = 'chip-amber';
                    }
                    ?>
                    <div class="mini-card">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong class="d-block"><?php echo htmlspecialchars($item['nombre_comercial']); ?></strong>
                                <small class="text-muted-soft">Caduca: <?php echo date('d/m/Y', strtotime($item['fecha_caducidad'])); ?></small>
                            </div>
                            <span class="chip <?php echo $chip; ?>"><?php echo htmlspecialchars($item['estado']); ?></span>
                        </div>
                        <div class="mt-2 text-muted-soft small">Existencia: <?php echo (int)$item['cantidad_disponible']; ?> unidades</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!--
<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Accesos rápidos</h2>
        <p class="section-subtitle">Entradas directas a los módulos más usados.</p>
    </div>
    <div class="panel-body">
        <div class="mini-grid">
            <a class="mini-card text-decoration-none text-reset" href="<?php echo $basePath; ?>/admin/usuarios/">
                <strong class="d-block mb-1">Usuarios</strong>
                <span class="text-muted-soft small">Cuentas, roles y direcciones.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="<?php echo $basePath; ?>/admin/medicos/">
                <strong class="d-block mb-1">Médicos</strong>
                <span class="text-muted-soft small">Especialistas y turnos.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="<?php echo $basePath; ?>/admin/especialidades/">
                <strong class="d-block mb-1">Especialidades</strong>
                <span class="text-muted-soft small">Catálogo médico.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="<?php echo $basePath; ?>/admin/reportes/">
                <strong class="d-block mb-1">Reportes</strong>
                <span class="text-muted-soft small">Datos clave del sistema.</span>
            </a>
        </div>
    </div>
</div>
-->

<?php include '../src/admin/footer.php'; ?>
