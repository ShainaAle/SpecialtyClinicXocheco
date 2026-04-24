<?php
require_once '../src/auth.php';
requireRol(['paciente']);
require_once '../src/conexion/conexion.php';

$basePath = '..';
$pageTitle = 'Mi panel';
$pageSubtitle = 'Consulta tus datos, tu historial clínico y el acceso a tus citas.';
$activeModule = 'dashboard';
$portalLabel = 'Paciente';
$portalRole = 'Paciente';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'paciente/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'paciente/citas/'],
    ['key' => 'recetas', 'label' => 'Mis recetas', 'href' => 'paciente/recetas/'],
    ['key' => 'historial', 'label' => 'Historial clínico', 'href' => 'paciente/historial-clinico.php'],
];

function patientDashboardRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$patient = patientDashboardRows(
    $conn,
    "SELECT p.id_paciente, p.fecha_nacimiento, p.tipo_sangre, p.alergias, p.contacto_emergencia, p.adeudo,
            TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
            u.nombre, u.apellidos, u.correo,
            d.calle, d.numero_exterior, d.colonia, d.codigo_postal, d.ciudad, d.estado
     FROM PACIENTES p
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     LEFT JOIN DOMICILIO d ON u.id_domicilio = d.id_domicilio
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
);
$patient = $patient[0] ?? null;

$upcoming = patientDashboardRows(
    $conn,
    "SELECT c.fecha_hora_inicio, c.estado,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            s.nombre AS servicio,
            e.nombre AS espacio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
     INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
     WHERE pu.id_usuario = ? AND c.fecha_hora_inicio >= NOW()
     ORDER BY c.fecha_hora_inicio ASC
     LIMIT 5",
    'i',
    [(int)$_SESSION['id_usuario']]
);

$history = patientDashboardRows(
    $conn,
    "SELECT c.fecha_hora_inicio, c.estado, co.motivo, co.diagnostico, co.observaciones,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            s.nombre AS servicio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
     INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     WHERE pu.id_usuario = ? AND c.fecha_hora_inicio < NOW()
     ORDER BY c.fecha_hora_inicio DESC
     LIMIT 5",
    'i',
    [(int)$_SESSION['id_usuario']]
);

$stats = [
    ['label' => 'Edad', 'value' => $patient ? (int)$patient['edad'] : 0, 'note' => 'Años cumplidos', 'accent' => 'blue'],
    ['label' => 'Próximas citas', 'value' => count($upcoming), 'note' => 'Pendientes', 'accent' => 'sand'],
    ['label' => 'Historial', 'value' => count($history), 'note' => 'Consultas pasadas', 'accent' => 'blue'],
    ['label' => 'Adeudo', 'value' => $patient && (int)$patient['adeudo'] === 1 ? 1 : 0, 'note' => $patient && (int)$patient['adeudo'] === 1 ? 'Sí' : 'No', 'accent' => 'sand'],
];

include '../src/portal/header.php';
?>

<?php if ($patient && (int)$patient['adeudo'] === 1): ?>
    <div class="alert alert-warning">Tienes un adeudo pendiente. Eso puede bloquear nuevas citas.</div>
<?php endif; ?>

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Accesos rápidos</h2>
        <p class="section-subtitle">Atajos a tus secciones principales.</p>
    </div>
    <div class="panel-body">
        <div class="mini-grid">
            <a class="mini-card text-decoration-none text-reset" href="citas/">
                <strong class="d-block mb-1">Mis citas</strong>
                <span class="text-muted-soft small">Revisa tu agenda y la disponibilidad médica.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="recetas/">
                <strong class="d-block mb-1">Mis recetas</strong>
                <span class="text-muted-soft small">Tus prescripciones médicas.</span>
            </a>
            <a class="mini-card text-decoration-none text-reset" href="historial-clinico.php">
                <strong class="d-block mb-1">Historial clínico</strong>
                <span class="text-muted-soft small">Consultas, diagnósticos y observaciones.</span>
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                <div class="stat-value"><?php echo htmlspecialchars((string)$stat['value']); ?></div>
                <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Mi perfil</h2>
                <p class="section-subtitle">Tus datos principales.</p>
            </div>
            <div class="panel-body">
                <?php if ($patient): ?>
                    <div class="mini-card mb-3">
                        <strong class="d-block mb-1"><?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellidos']); ?></strong>
                        <div class="text-muted-soft small"><?php echo htmlspecialchars($patient['correo']); ?></div>
                    </div>
                    <div class="mini-card mb-3">
                        <div class="text-muted-soft small">Dirección</div>
                        <strong class="d-block"><?php echo htmlspecialchars(trim(($patient['calle'] ?? '') . ' ' . ($patient['numero_exterior'] ?? '') . ', ' . ($patient['colonia'] ?? '') . ', ' . ($patient['ciudad'] ?? '') . ', ' . ($patient['estado'] ?? ''))); ?></strong>
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
                <h2 class="section-title">Mis citas próximas</h2>
                <p class="section-subtitle">Lo siguiente en tu agenda.</p>
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
                            <?php foreach ($upcoming as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                    <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                    <td><span class="chip <?php echo $item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$upcoming): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Todavía no tienes citas próximas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Historial reciente</h2>
        <p class="section-subtitle">Consultas y diagnósticos previos.</p>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                            <td><?php echo htmlspecialchars($item['medico']); ?></td>
                            <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                            <td><?php echo htmlspecialchars($item['motivo'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($item['diagnostico'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Aún no hay historial disponible.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../src/admin/footer.php'; ?>
