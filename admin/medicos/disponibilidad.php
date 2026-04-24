<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Disponibilidad médica';
$pageSubtitle = 'Revisar agenda, ver ocupación y ajustar el turno de cada médico.';
$activeModule = 'medicos_disponibilidad';

function availabilityRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as &$value) {
            $bind[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$message = '';
$error = '';

$date = $_GET['fecha'] ?? date('Y-m-d');
$doctorFilter = (int)($_GET['medico'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = (int)($_POST['id_medico'] ?? 0);
    $turno = trim($_POST['turno'] ?? '');
    $allowedTurns = ['matutino', 'vespertino', 'nocturno'];

    if ($doctorId <= 0 || !in_array($turno, $allowedTurns, true)) {
        $error = 'Selecciona un médico y un turno válido.';
    } else {
        $stmt = $conn->prepare('UPDATE MEDICOS SET turno = ? WHERE id_medico = ?');
        $stmt->bind_param('si', $turno, $doctorId);
        if ($stmt->execute()) {
            $message = 'Turno actualizado.';
            auditLog($conn, 'MEDICOS', 'ACTUALIZAR turno médico #' . $doctorId);
        } else {
            $error = 'No se pudo actualizar.';
        }
    }
}

$doctors = availabilityRows(
    $conn,
    "SELECT m.id_medico, u.nombre, u.apellidos, e.nombre AS especialidad, m.turno
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
     ORDER BY u.apellidos, u.nombre"
);

$selectedDoctor = $doctorFilter > 0 ? array_values(array_filter($doctors, static fn ($item) => (int)$item['id_medico'] === $doctorFilter)) : [];
$selectedDoctor = $selectedDoctor[0] ?? ($doctors[0] ?? null);

$doctorId = (int)($selectedDoctor['id_medico'] ?? 0);

$agenda = [];
if ($doctorId > 0) {
    $agenda = availabilityRows(
        $conn,
        "SELECT c.fecha_hora_inicio, c.estado,
                CONCAT(up.nombre, ' ', up.apellidos) AS paciente,
                s.nombre AS servicio,
                e.nombre AS espacio
         FROM CITAS c
         INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
         INNER JOIN USUARIOS up ON p.id_usuario = up.id_usuario
         INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
         INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
         WHERE c.id_medico = ? AND DATE(c.fecha_hora_inicio) = ?
         ORDER BY c.fecha_hora_inicio ASC",
        'is',
        [$doctorId, $date]
    );
}

$doctorStats = [
    ['label' => 'Médicos', 'value' => count($doctors), 'note' => 'Profesionales cargados', 'accent' => 'blue'],
    ['label' => 'Citas del día', 'value' => count($agenda), 'note' => 'Agenda filtrada', 'accent' => 'sand'],
    ['label' => 'Fecha', 'value' => date('d/m/Y', strtotime($date)), 'note' => 'Filtro actual', 'accent' => 'blue'],
];

$turnosMap = [
    'matutino' => '06:00 - 14:00',
    'vespertino' => '14:00 - 22:00',
    'nocturno' => '22:00 - 06:00',
];

$busyCount = count($agenda);
$statusText = $busyCount > 0 ? 'Con citas' : 'Libre';
$statusClass = $busyCount > 0 ? 'chip-amber' : 'chip-green';

include '../../src/admin/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($doctorStats as $stat): ?>
        <div class="col-sm-6 col-xl-4">
            <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                <div class="stat-value"><?php echo htmlspecialchars((string)$stat['value']); ?></div>
                <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Seleccionar médico</h2>
                <p class="section-subtitle">Cambiar el doctor para revisar su disponibilidad.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="mb-3">
                    <div class="mb-3">
                        <label class="form-label">Médico</label>
                        <select name="medico" class="form-select">
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo (int)$doctor['id_medico']; ?>" <?php echo $doctorId === (int)$doctor['id_medico'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellidos'] . ' · ' . $doctor['especialidad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
                    </div>
                    <button class="btn btn-brand w-100" type="submit">Ver disponibilidad</button>
                </form>

                <?php if ($selectedDoctor): ?>
                    <div class="mini-card mb-3">
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($selectedDoctor['nombre'] . ' ' . $selectedDoctor['apellidos']); ?></strong>
                        <div class="text-muted-soft small mb-1"><?php echo htmlspecialchars($selectedDoctor['especialidad']); ?></div>
                        <span class="chip chip-sand"><?php echo htmlspecialchars($selectedDoctor['turno']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="mini-card">
                    <strong class="d-block mb-2">Actualizar turno</strong>
                    <form method="post">
                        <input type="hidden" name="id_medico" value="<?php echo $doctorId; ?>">
                        <div class="mb-3">
                            <select name="turno" class="form-select">
                                <option value="matutino" <?php echo (($selectedDoctor['turno'] ?? '') === 'matutino') ? 'selected' : ''; ?>>matutino</option>
                                <option value="vespertino" <?php echo (($selectedDoctor['turno'] ?? '') === 'vespertino') ? 'selected' : ''; ?>>vespertino</option>
                                <option value="nocturno" <?php echo (($selectedDoctor['turno'] ?? '') === 'nocturno') ? 'selected' : ''; ?>>nocturno</option>
                            </select>
                        </div>
                        <button class="btn btn-soft w-100" type="submit">Guardar turno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card mb-4">
            <div class="panel-head">
                <h2 class="section-title">Agenda del día</h2>
                <p class="section-subtitle">Citas del médico seleccionado para la fecha elegida.</p>
            </div>
            <div class="panel-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="chip <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
                    <span class="chip chip-blue"><?php echo htmlspecialchars($turnosMap[$selectedDoctor['turno'] ?? ''] ?? 'Sin turno'); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Paciente</th>
                                <th>Servicio</th>
                                <th>Espacio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agenda)): ?>
                                <?php foreach ($agenda as $item): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                        <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                        <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay citas para este doctor en esta fecha.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado de médicos</h2>
                <p class="section-subtitle">Referencia rápida de turnos y especialidades.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Médico</th>
                                <th>Especialidad</th>
                                <th>Turno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['especialidad']); ?></td>
                                    <td><span class="chip chip-sand"><?php echo htmlspecialchars($doctor['turno']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
