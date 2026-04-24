<?php
require_once '../src/auth.php';
requireRol(['recepcion']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Recepción';
$pageSubtitle = 'Control rápido de agenda, pacientes y citas del día.';
$activeModule = 'dashboard';
$portalLabel = 'Recepción';
$portalRole = 'Recepción';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'citas', 'label' => 'Citas', 'href' => 'citas/'],
];

function receptionRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types !== '') {
        $bind = [$types];
        foreach ($params as &$value) $bind[] = &$value;
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

$todayAppointments = receptionRows(
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
     WHERE DATE(c.fecha_hora_inicio) = CURDATE()
     ORDER BY c.fecha_hora_inicio ASC"
);

$stats = [
    ['label' => 'Citas hoy', 'value' => count($todayAppointments), 'note' => 'Agenda del día', 'accent' => 'blue'],
    ['label' => 'Programadas', 'value' => count(array_filter($todayAppointments, static fn ($x) => $x['estado'] === 'Programada')), 'note' => 'Pendientes', 'accent' => 'sand'],
    ['label' => 'Confirmadas', 'value' => count(array_filter($todayAppointments, static fn ($x) => $x['estado'] === 'Confirmada')), 'note' => 'Listas', 'accent' => 'blue'],
    ['label' => 'Canceladas', 'value' => count(array_filter($todayAppointments, static fn ($x) => $x['estado'] === 'Cancelada')), 'note' => 'Anuladas', 'accent' => 'sand'],
];

include '../src/portal/header.php';
?>

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
        <h2 class="section-title">Acceso rápido</h2>
        <p class="section-subtitle">Entra directo a la gestión de citas.</p>
    </div>
    <div class="panel-body">
        <div class="mini-grid">
            <a class="mini-card text-decoration-none text-reset" href="citas/">
                <strong class="d-block mb-1">Citas</strong>
                <span class="text-muted-soft small">Programar, confirmar y cancelar.</span>
            </a>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-head">
        <h2 class="section-title">Citas de hoy</h2>
        <p class="section-subtitle">Lo que está moviéndose en recepción.</p>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Servicio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayAppointments as $item): ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                            <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                            <td><?php echo htmlspecialchars($item['medico']); ?></td>
                            <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                            <td><span class="chip <?php echo $item['estado'] === 'Cancelada' ? 'chip-red' : ($item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'); ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$todayAppointments): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay citas hoy.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../src/admin/footer.php'; ?>
