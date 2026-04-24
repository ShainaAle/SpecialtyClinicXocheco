<?php
require_once '../src/auth.php';
requireRol(['medico']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Mi cuenta';
$pageSubtitle = 'Revisa tu agenda, tus consultas y el avance de tus recetas.';
$activeModule = 'dashboard';
$portalLabel = 'Médico';
$portalRole = 'Médico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'medico/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'medico/citas/'],
    ['key' => 'consultas', 'label' => 'Consultas', 'href' => 'medico/consultas/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'medico/recetas/'],
];

function medicoRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$doctor = medicoRows(
    $conn,
    "SELECT m.id_medico, m.turno, m.cedula_profesional, m.universidad_origen,
            u.nombre, u.apellidos, u.correo, e.nombre AS especialidad
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
);
$doctor = $doctor[0] ?? null;

$todayAppointments = $doctor ? medicoRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            s.nombre AS servicio,
            e.nombre AS espacio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
     WHERE c.id_medico = ? AND DATE(c.fecha_hora_inicio) = CURDATE()
     ORDER BY c.fecha_hora_inicio ASC",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$upcomingAppointments = $doctor ? medicoRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            s.nombre AS servicio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     WHERE c.id_medico = ? AND c.fecha_hora_inicio >= NOW()
     ORDER BY c.fecha_hora_inicio ASC
     LIMIT 5",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$consultationsCount = $doctor ? (int)(medicoRows(
    $conn,
    "SELECT COUNT(*) AS total
     FROM CONSULTAS co
     INNER JOIN CITAS c ON co.id_cita = c.id_cita
     WHERE c.id_medico = ?",
    'i',
    [(int)$doctor['id_medico']]
)[0]['total'] ?? 0) : 0;

$recipesCount = $doctor ? (int)(medicoRows(
    $conn,
    "SELECT COUNT(*) AS total
     FROM RECETAS r
     INNER JOIN CONSULTAS co ON r.id_consulta = co.id_consulta
     INNER JOIN CITAS c ON co.id_cita = c.id_cita
     WHERE c.id_medico = ?",
    'i',
    [(int)$doctor['id_medico']]
)[0]['total'] ?? 0) : 0;

$stats = [
    ['label' => 'Citas hoy', 'value' => count($todayAppointments), 'note' => 'Agenda del día', 'accent' => 'blue'],
    ['label' => 'Próximas', 'value' => count($upcomingAppointments), 'note' => 'Pendientes', 'accent' => 'sand'],
    ['label' => 'Consultas', 'value' => $consultationsCount, 'note' => 'Atenciones registradas', 'accent' => 'blue'],
    ['label' => 'Recetas', 'value' => $recipesCount, 'note' => 'Emitidas', 'accent' => 'sand'],
];

include '../src/portal/header.php';
?>

<?php if ($doctor): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $stat): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                    <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                    <div class="stat-value"><?php echo (int)$stat['value']; ?></div>
                    <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel-card mb-4">
        <div class="panel-head">
            <h2 class="section-title">Accesos rápidos</h2>
            <p class="section-subtitle">Lo que usas más en tu trabajo diario.</p>
        </div>
        <div class="panel-body">
            <div class="mini-grid">
                <a class="mini-card text-decoration-none text-reset" href="citas/">
                    <strong class="d-block mb-1">Mis citas</strong>
                    <span class="text-muted-soft small">Agenda y pacientes del día.</span>
                </a>
                <a class="mini-card text-decoration-none text-reset" href="consultas/">
                    <strong class="d-block mb-1">Consultas</strong>
                    <span class="text-muted-soft small">Cerrar atención y registrar diagnóstico.</span>
                </a>
                <a class="mini-card text-decoration-none text-reset" href="recetas/">
                    <strong class="d-block mb-1">Recetas</strong>
                    <span class="text-muted-soft small">Emitir prescripciones.</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title">Mi perfil</h2>
                    <p class="section-subtitle">Datos profesionales del médico.</p>
                </div>
                <div class="panel-body">
                    <div class="mini-card mb-3">
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellidos']); ?></strong>
                        <div class="text-muted-soft small"><?php echo htmlspecialchars($doctor['correo']); ?></div>
                    </div>
                    <div class="mini-card mb-3">
                        <div class="text-muted-soft small">Especialidad</div>
                        <strong class="d-block"><?php echo htmlspecialchars($doctor['especialidad']); ?></strong>
                    </div>
                    <div class="mini-card">
                        <div class="text-muted-soft small">Turno</div>
                        <strong class="d-block"><?php echo htmlspecialchars($doctor['turno']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title">Citas de hoy</h2>
                    <p class="section-subtitle">Agenda que tienes para la fecha actual.</p>
                </div>
                <div class="panel-body">
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
                                <?php foreach ($todayAppointments as $item): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                        <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                        <td><span class="chip <?php echo $item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$todayAppointments): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No tienes citas hoy.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-head">
            <h2 class="section-title">Próximas citas</h2>
            <p class="section-subtitle">Lo siguiente que tienes en agenda.</p>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Servicio</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $item): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$upcomingAppointments): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No tienes citas próximas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">No se encontró tu perfil de médico.</div>
<?php endif; ?>

<?php include '../src/admin/footer.php'; ?>
