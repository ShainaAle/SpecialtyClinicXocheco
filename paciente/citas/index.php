<?php
require_once '../../src/auth.php';
requireRol(['paciente']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Mis citas';
$pageSubtitle = 'Consulta tu agenda y la disponibilidad general de los médicos.';
$activeModule = 'citas';
$portalLabel = 'Paciente';
$portalRole = 'Paciente';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'paciente/dashboard.php'],
    ['key' => 'historial', 'label' => 'Historial clínico', 'href' => 'paciente/historial-clinico.php'],
];

function citaPacienteRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$patient = citaPacienteRows(
    $conn,
    "SELECT p.id_paciente, p.adeudo, u.nombre, u.apellidos
     FROM PACIENTES p
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
);
$patient = $patient[0] ?? null;

$appointments = citaPacienteRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            e.nombre AS espacio,
            s.nombre AS servicio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
     INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
     INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     WHERE pu.id_usuario = ?
     ORDER BY c.fecha_hora_inicio DESC",
    'i',
    [(int)$_SESSION['id_usuario']]
);

$upcomingAppointments = array_values(array_filter($appointments, static fn ($item) => strtotime($item['fecha_hora_inicio']) >= time()));
$confirmadas = count(array_filter($appointments, static fn ($item) => $item['estado'] === 'Confirmada'));

$doctorAvailability = citaPacienteRows(
    $conn,
    "SELECT m.id_medico, CONCAT(u.nombre, ' ', u.apellidos) AS medico, e.nombre AS especialidad, m.turno,
            COUNT(c.id_cita) AS citas_hoy
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
     LEFT JOIN CITAS c ON c.id_medico = m.id_medico AND DATE(c.fecha_hora_inicio) = CURDATE() AND c.estado <> 'Cancelada'
     GROUP BY m.id_medico, u.nombre, u.apellidos, e.nombre, m.turno
     ORDER BY u.apellidos, u.nombre"
);

$availableDoctors = count(array_filter($doctorAvailability, static fn ($item) => (int)$item['citas_hoy'] < 4));

$stats = [
    ['label' => 'Citas', 'value' => count($appointments), 'note' => 'Totales en tu cuenta', 'accent' => 'blue'],
    ['label' => 'Próximas', 'value' => count($upcomingAppointments), 'note' => 'A futuro', 'accent' => 'sand'],
    ['label' => 'Confirmadas', 'value' => $confirmadas, 'note' => 'Listas para atender', 'accent' => 'blue'],
    ['label' => 'Médicos disponibles', 'value' => $availableDoctors, 'note' => 'Con agenda más ligera', 'accent' => 'sand'],
];

include '../../src/portal/header.php';
?>

<?php if ($patient && (int)$patient['adeudo'] === 1): ?>
    <div class="alert alert-warning">Tienes un adeudo pendiente. Eso puede bloquear nuevas citas.</div>
<?php endif; ?>

<div class="alert alert-info mb-4">
    La disponibilidad de los médicos cambia según su turno y su agenda. Si no ves espacio, consulta recepción.
</div>

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

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Mis citas</h2>
                <p class="section-subtitle">Consulta lo programado, confirmado o ya atendido.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Médico</th>
                                <th>Servicio</th>
                                <th>Espacio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                    <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                    <td><span class="chip <?php echo $item['estado'] === 'Cancelada' ? 'chip-red' : ($item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'); ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$appointments): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Todavía no tienes citas registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Disponibilidad médica</h2>
                <p class="section-subtitle">Referencia rápida de turno y carga del día.</p>
            </div>
            <div class="panel-body d-grid gap-3">
                <?php foreach ($doctorAvailability as $doctor): ?>
                    <?php $isLight = (int)$doctor['citas_hoy'] < 4; ?>
                    <div class="mini-card">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <strong><?php echo htmlspecialchars($doctor['medico']); ?></strong>
                            <span class="chip <?php echo $isLight ? 'chip-green' : 'chip-amber'; ?>">
                                <?php echo $isLight ? 'Disponible' : 'Con agenda'; ?>
                            </span>
                        </div>
                        <div class="text-muted-soft small"><?php echo htmlspecialchars($doctor['especialidad']); ?> · <?php echo htmlspecialchars($doctor['turno']); ?></div>
                        <div class="text-muted-soft small mt-2">Citas hoy: <?php echo (int)$doctor['citas_hoy']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($upcomingAppointments): ?>
    <div class="panel-card">
        <div class="panel-head">
            <h2 class="section-title">Próximas citas</h2>
            <p class="section-subtitle">Lo más cercano en tu agenda.</p>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Médico</th>
                            <th>Servicio</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $item): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                <td><span class="chip <?php echo $item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../src/admin/footer.php'; ?>
