<?php
require_once '../../src/auth.php';
requireRol(['admin', 'recepcion']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/recepcion/context.php';

$basePath = '../..';
$pageTitle = 'Disponibilidad médica';
$pageSubtitle = 'Revisar agenda y ver el turno de cada médico.';
$activeModule = 'disponibilidad';

function availabilityRowsRecepcion(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$date = $_GET['fecha'] ?? date('Y-m-d');
$doctorFilter = (int)($_GET['medico'] ?? 0);

$doctors = availabilityRowsRecepcion(
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
    $agenda = availabilityRowsRecepcion(
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

$turnosMap = [
    'matutino' => '06:00 - 14:00',
    'vespertino' => '14:00 - 22:00',
    'nocturno' => '22:00 - 06:00',
];

include '../../src/portal/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Médicos</div>
            <div class="stat-value"><?php echo count($doctors); ?></div>
            <div class="stat-note">Profesionales cargados</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-sand">
            <div class="stat-label">Citas del día</div>
            <div class="stat-value"><?php echo count($agenda); ?></div>
            <div class="stat-note">Agenda filtrada</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card accent-blue">
            <div class="stat-label">Fecha</div>
            <div class="stat-value"><?php echo htmlspecialchars(date('d/m/Y', strtotime($date))); ?></div>
            <div class="stat-note">Filtro actual</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Seleccionar médico</h2>
                <p class="section-subtitle">Cambiar el doctor para revisar su disponibilidad.</p>
            </div>
            <div class="panel-body">
                <form method="get">
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
                    <div class="mini-card mt-3">
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($selectedDoctor['nombre'] . ' ' . $selectedDoctor['apellidos']); ?></strong>
                        <div class="text-muted-soft small mb-1"><?php echo htmlspecialchars($selectedDoctor['especialidad']); ?></div>
                        <span class="chip chip-sand"><?php echo htmlspecialchars($selectedDoctor['turno']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Agenda del día</h2>
                <p class="section-subtitle">Citas del médico seleccionado para la fecha elegida.</p>
            </div>
            <div class="panel-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
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
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
