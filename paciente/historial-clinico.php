<?php
require_once '../src/auth.php';
requireRol(['paciente']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Historial clínico';
$pageSubtitle = 'Consulta tus visitas anteriores, diagnósticos y observaciones médicas.';
$activeModule = 'historial';
$portalLabel = 'Paciente';
$portalRole = 'Paciente';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'paciente/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'paciente/citas/'],
    ['key' => 'recetas', 'label' => 'Mis recetas', 'href' => 'paciente/recetas/'],
    ['key' => 'historial', 'label' => 'Historial clínico', 'href' => 'paciente/historial-clinico.php'],
];

function clinicalRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$patient = clinicalRows(
    $conn,
    "SELECT p.id_paciente, p.fecha_nacimiento, p.tipo_sangre, p.alergias, p.contacto_emergencia, p.adeudo,
            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
            u.nombre, u.apellidos, u.correo
     FROM PACIENTES p
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
);
$patient = $patient[0] ?? null;

$records = clinicalRows(
    $conn,
    "SELECT c.fecha_hora_inicio, c.estado,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            s.nombre AS servicio,
            co.motivo, co.diagnostico, co.observaciones,
            COALESCE(r.recetas, 0) AS recetas
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
     INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     LEFT JOIN (
         SELECT rc.id_consulta, COUNT(dr.id_detalle) AS recetas
         FROM RECETAS rc
         LEFT JOIN DETALLE_RECETA dr ON rc.id_receta = dr.id_receta
         GROUP BY rc.id_consulta
     ) r ON r.id_consulta = co.id_consulta
     WHERE pu.id_usuario = ? AND (co.id_consulta IS NOT NULL OR c.estado = 'Completada')
     ORDER BY c.fecha_hora_inicio DESC",
    'i',
    [(int)$_SESSION['id_usuario']]
);

$stats = [
    ['label' => 'Consultas', 'value' => count($records), 'note' => 'Atenciones registradas', 'accent' => 'blue'],
    ['label' => 'Diagnósticos', 'value' => count(array_filter($records, static fn ($item) => trim((string)($item['diagnostico'] ?? '')) !== '')), 'note' => 'Con diagnóstico', 'accent' => 'sand'],
    ['label' => 'Recetas', 'value' => count(array_filter($records, static fn ($item) => (int)$item['recetas'] > 0)), 'note' => 'Con medicación', 'accent' => 'blue'],
    ['label' => 'Adeudo', 'value' => $patient && (int)$patient['adeudo'] === 1 ? 1 : 0, 'note' => $patient && (int)$patient['adeudo'] === 1 ? 'Sí' : 'No', 'accent' => 'sand'],
];

include '../src/portal/header.php';
?>

<?php if ($patient && (int)$patient['adeudo'] === 1): ?>
    <div class="alert alert-warning">Tu historial sigue visible, pero el adeudo puede bloquear nuevas citas.</div>
<?php endif; ?>

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
    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Tu perfil</h2>
                <p class="section-subtitle">Datos que acompañan tu historial.</p>
            </div>
            <div class="panel-body">
                <?php if ($patient): ?>
                    <div class="mini-card mb-3">
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellidos']); ?></strong>
                        <div class="text-muted-soft small"><?php echo htmlspecialchars($patient['correo']); ?></div>
                    </div>
                    <div class="mini-card mb-3">
                        <div class="text-muted-soft small">Edad</div>
                        <strong class="d-block"><?php echo (int)$patient['edad']; ?> años</strong>
                    </div>
                    <div class="mini-card">
                        <div class="text-muted-soft small">Contacto de emergencia</div>
                        <strong class="d-block"><?php echo htmlspecialchars($patient['contacto_emergencia'] ?: '-'); ?></strong>
                    </div>
                <?php else: ?>
                    <div class="text-muted-soft">No se encontró tu perfil de paciente.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Historial clínico</h2>
                <p class="section-subtitle">Consultas, diagnósticos y observaciones previas.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Médico</th>
                                <th>Servicio</th>
                                <th>Motivo</th>
                                <th>Diagnóstico</th>
                                <th>Recetas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                    <td><?php echo htmlspecialchars($item['motivo'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($item['diagnostico'] ?: '-'); ?></td>
                                    <td><?php echo (int)$item['recetas']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$records): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Aún no tienes consultas registradas.</td></tr>
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
        <h2 class="section-title">Observaciones</h2>
        <p class="section-subtitle">Lo que el médico escribió en cada consulta.</p>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <?php foreach ($records as $item): ?>
                <div class="col-lg-6">
                    <div class="mini-card h-100">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <strong><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($item['fecha_hora_inicio']))); ?></strong>
                            <span class="chip chip-blue"><?php echo htmlspecialchars($item['estado']); ?></span>
                        </div>
                        <div class="text-muted-soft small mb-1"><?php echo htmlspecialchars($item['medico']); ?> · <?php echo htmlspecialchars($item['servicio']); ?></div>
                        <div class="small mb-2"><strong>Observaciones:</strong> <?php echo htmlspecialchars($item['observaciones'] ?: '-'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$records): ?>
                <div class="col-12"><div class="text-muted-soft">Sin observaciones registradas por ahora.</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../src/admin/footer.php'; ?>
