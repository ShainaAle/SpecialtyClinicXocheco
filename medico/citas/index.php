<?php
require_once '../../src/auth.php';
requireRol(['medico']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Mis citas';
$pageSubtitle = 'Consulta tu agenda de hoy y las citas que ya vienen en camino.';
$activeModule = 'citas';
$portalLabel = 'Médico';
$portalRole = 'Médico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'medico/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'medico/citas/'],
    ['key' => 'consultas', 'label' => 'Consultas', 'href' => 'medico/consultas/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'medico/recetas/'],
];

function medicoCitaRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
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
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

$doctor = medicoCitaRows(
    $conn,
    "SELECT m.id_medico, CONCAT(u.nombre, ' ', u.apellidos) AS medico
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
)[0] ?? null;

$dateFilter = trim($_GET['fecha'] ?? '');
if ($dateFilter === '') {
    $dateFilter = date('Y-m-d');
}
$appointments = $doctor ? medicoCitaRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            s.nombre AS servicio,
            e.nombre AS espacio,
            COALESCE(co.id_consulta, 0) AS tiene_consulta
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     WHERE c.id_medico = ? AND DATE(c.fecha_hora_inicio) = ?
     ORDER BY c.fecha_hora_inicio ASC",
    'is',
    [(int)$doctor['id_medico'], $dateFilter]
) : [];

$futureAppointments = $doctor ? medicoCitaRows(
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
     LIMIT 6",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$stats = [
    ['label' => 'Hoy', 'value' => count($appointments), 'note' => 'Citas filtradas', 'accent' => 'blue'],
    ['label' => 'Próximas', 'value' => count($futureAppointments), 'note' => 'Pendientes', 'accent' => 'sand'],
    ['label' => 'Con consulta', 'value' => count(array_filter($appointments, static fn ($item) => (int)$item['tiene_consulta'] > 0)), 'note' => 'Ya registradas', 'accent' => 'blue'],
    ['label' => 'Estado', 'value' => 1, 'note' => 'Agenda médica', 'accent' => 'sand'],
];

include '../../src/portal/header.php';
?>

<?php if (!$doctor): ?>
    <div class="alert alert-warning">No se encontró tu perfil de médico.</div>
<?php else: ?>
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
            <h2 class="section-title">Filtrar por fecha</h2>
            <p class="section-subtitle">Revisa tu agenda por día.</p>
        </div>
        <div class="panel-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                </div>
                <div class="col-md-8">
                    <button class="btn btn-brand" type="submit">Ver citas</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel-card mb-4">
        <div class="panel-head">
            <h2 class="section-title">Agenda del día</h2>
            <p class="section-subtitle">Pacientes, servicios y espacio asignado.</p>
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
                            <th>Consulta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $item): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                <td><span class="chip <?php echo $item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                <td>
                                    <?php if ((int)$item['tiene_consulta'] > 0): ?>
                                        <a class="btn btn-sm btn-soft" href="../consultas/?edit=<?php echo (int)$item['id_cita']; ?>">Ver</a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-brand" href="../consultas/?cita=<?php echo (int)$item['id_cita']; ?>">Crear</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$appointments): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No hay citas para esta fecha.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-head">
            <h2 class="section-title">Próximas citas</h2>
            <p class="section-subtitle">Lo que viene después de hoy.</p>
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
                        <?php foreach ($futureAppointments as $item): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$futureAppointments): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No hay citas próximas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../src/admin/footer.php'; ?>
