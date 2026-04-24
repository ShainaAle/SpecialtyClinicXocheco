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
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'paciente/citas/'],
    ['key' => 'recetas', 'label' => 'Mis recetas', 'href' => 'paciente/recetas/'],
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

$doctors = citaPacienteRows(
    $conn,
    "SELECT m.id_medico, CONCAT(u.nombre, ' ', u.apellidos) AS medico, e.nombre AS especialidad, m.turno
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
     ORDER BY u.apellidos, u.nombre"
);

$services = citaPacienteRows(
    $conn,
    'SELECT id_servicio, nombre, precio FROM SERVICIOS ORDER BY nombre'
);

function patientTurnAllowsDateTime(string $turno, string $dateTime): bool
{
    $hour = (int)date('H', strtotime($dateTime));
    return match ($turno) {
        'matutino' => $hour >= 6 && $hour < 14,
        'vespertino' => $hour >= 14 && $hour < 22,
        'nocturno' => $hour >= 22 || $hour < 6,
        default => false,
    };
}

function patientFindRoom(mysqli $conn, int $doctorId, string $dateTime): ?array
{
    $rooms = citaPacienteRows(
        $conn,
        "SELECT e.id_espacio, COALESCE(e.nombre, CONCAT('Espacio #', e.numero)) AS espacio
         FROM ESPACIOS_FISICOS e
         INNER JOIN TIPOS_ESPACIOS_FISICOS t ON e.id_tipo = t.id_tipo
         WHERE LOWER(t.tipo) = 'consultorio'
           AND NOT EXISTS (
               SELECT 1
               FROM CITAS c
               WHERE c.id_espacio = e.id_espacio
                 AND c.fecha_hora_inicio = ?
                 AND c.estado <> 'Cancelada'
           )
         ORDER BY e.piso, e.numero",
        's',
        [$dateTime]
    );

    if (!$rooms) {
        $rooms = citaPacienteRows(
            $conn,
            "SELECT e.id_espacio, COALESCE(e.nombre, CONCAT('Espacio #', e.numero)) AS espacio
             FROM ESPACIOS_FISICOS e
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM CITAS c
                 WHERE c.id_espacio = e.id_espacio
                   AND c.fecha_hora_inicio = ?
                   AND c.estado <> 'Cancelada'
             )
             ORDER BY e.piso, e.numero",
            's',
            [$dateTime]
        );
    }

    return $rooms[0] ?? null;
}

$scheduleMessage = '';
$scheduleError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule') {
    $doctorId = (int)($_POST['id_medico'] ?? 0);
    $serviceId = (int)($_POST['id_servicio'] ?? 0);
    $dateTime = trim($_POST['fecha_hora_inicio'] ?? '');

    if (!$patient) {
        $scheduleError = 'No se encontró tu perfil de paciente.';
    } elseif ($doctorId <= 0 || $serviceId <= 0 || $dateTime === '') {
        $scheduleError = 'Completa médico, servicio y fecha.';
    } else {
        $selectedDoctor = citaPacienteRows(
            $conn,
            "SELECT m.id_medico, m.turno, CONCAT(u.nombre, ' ', u.apellidos) AS medico
             FROM MEDICOS m
             INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
             WHERE m.id_medico = ?",
            'i',
            [$doctorId]
        )[0] ?? null;

        if (!$selectedDoctor) {
            $scheduleError = 'Médico no encontrado.';
        } elseif (strtotime($dateTime) < time()) {
            $scheduleError = 'La cita debe ser en una fecha futura.';
        } elseif (!patientTurnAllowsDateTime($selectedDoctor['turno'], $dateTime)) {
            $scheduleError = 'El horario no coincide con el turno del médico.';
        } else {
            $busyDoctor = citaPacienteRows(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM CITAS
                 WHERE id_medico = ?
                   AND fecha_hora_inicio = ?
                   AND estado <> 'Cancelada'",
                'is',
                [$doctorId, date('Y-m-d H:i:s', strtotime($dateTime))]
            );
            if ((int)($busyDoctor[0]['total'] ?? 0) > 0) {
                $scheduleError = 'El médico ya tiene una cita en ese horario.';
            } else {
                $room = patientFindRoom($conn, $doctorId, date('Y-m-d H:i:s', strtotime($dateTime)));
                if (!$room) {
                    $scheduleError = 'No hay consultorio disponible para esa hora.';
                } else {
                    $stmt = $conn->prepare('CALL sp_agendar_cita_segura(?, ?, ?, ?, ?)');
                    $dateSql = date('Y-m-d H:i:s', strtotime($dateTime));
                    $stmt->bind_param('iiiis', $patient['id_paciente'], $doctorId, $room['id_espacio'], $serviceId, $dateSql);
                    if ($stmt->execute()) {
                        $scheduleMessage = 'Cita programada correctamente.';
                    } else {
                        $scheduleError = 'No se pudo programar. Revisa adeudo o disponibilidad.';
                    }
                }
            }
        }
    }
}

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

<?php if ($scheduleMessage): ?><div class="alert alert-success"><?php echo htmlspecialchars($scheduleMessage); ?></div><?php endif; ?>
<?php if ($scheduleError): ?><div class="alert alert-danger"><?php echo htmlspecialchars($scheduleError); ?></div><?php endif; ?>
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

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Programar cita</h2>
        <p class="section-subtitle">Elige médico, servicio y horario. El sistema verifica turno y espacio libre.</p>
    </div>
    <div class="panel-body">
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="schedule">
            <div class="col-md-4">
                <label class="form-label">Médico</label>
                <select name="id_medico" class="form-select">
                    <option value="">Selecciona</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo (int)$doctor['id_medico']; ?>">
                            <?php echo htmlspecialchars($doctor['medico'] . ' · ' . $doctor['especialidad'] . ' · ' . $doctor['turno']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Servicio</label>
                <select name="id_servicio" class="form-select">
                    <option value="">Selecciona</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo (int)$service['id_servicio']; ?>">
                            <?php echo htmlspecialchars($service['nombre'] . ' · $' . number_format((float)$service['precio'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha y hora</label>
                <input type="datetime-local" name="fecha_hora_inicio" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-brand w-100" type="submit">Programar</button>
            </div>
        </form>
    </div>
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
