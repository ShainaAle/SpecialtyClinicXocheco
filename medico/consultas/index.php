<?php
require_once '../../src/auth.php';
requireRol(['medico']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Consultas';
$pageSubtitle = 'Registra motivo, diagnóstico y observaciones de tus pacientes.';
$activeModule = 'consultas';
$portalLabel = 'Médico';
$portalRole = 'Médico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'medico/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'medico/citas/'],
    ['key' => 'consultas', 'label' => 'Consultas', 'href' => 'medico/consultas/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'medico/recetas/'],
];

function consultaRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$doctor = consultaRows(
    $conn,
    "SELECT m.id_medico, CONCAT(u.nombre, ' ', u.apellidos) AS medico
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?",
    'i',
    [(int)$_SESSION['id_usuario']]
)[0] ?? null;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCita = (int)($_POST['id_cita'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (!$doctor) {
        $error = 'No se encontró tu perfil de médico.';
    } elseif ($idCita <= 0 || $motivo === '' || $diagnostico === '') {
        $error = 'Selecciona una cita y completa motivo y diagnóstico.';
    } else {
        $appointment = consultaRows(
            $conn,
            "SELECT c.id_cita, c.estado, DATE(c.fecha_hora_inicio) AS fecha_cita,
                    COALESCE(co.id_consulta, 0) AS id_consulta
             FROM CITAS c
             LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
             WHERE c.id_cita = ? AND c.id_medico = ?",
            'ii',
            [$idCita, (int)$doctor['id_medico']]
        )[0] ?? null;

        if (!$appointment) {
            $error = 'La cita no existe o no te pertenece.';
        } elseif (empty($appointment['id_consulta']) && $appointment['fecha_cita'] !== date('Y-m-d')) {
            $error = 'La consulta solo se puede registrar el mismo día de la cita.';
        } else {
            $conn->begin_transaction();
            try {
                $existing = consultaRows($conn, 'SELECT id_consulta FROM CONSULTAS WHERE id_cita = ?', 'i', [$idCita])[0] ?? null;
                if ($existing) {
                    $stmt = $conn->prepare('UPDATE CONSULTAS SET motivo = ?, diagnostico = ?, observaciones = ? WHERE id_cita = ?');
                    $stmt->bind_param('sssi', $motivo, $diagnostico, $observaciones, $idCita);
                    $stmt->execute();
                    $message = 'Consulta actualizada.';
                    auditLog($conn, 'CONSULTAS', 'ACTUALIZAR consulta de cita #' . $idCita);
                } else {
                    $stmt = $conn->prepare('INSERT INTO CONSULTAS (id_cita, motivo, diagnostico, observaciones) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('isss', $idCita, $motivo, $diagnostico, $observaciones);
                    $stmt->execute();
                    $message = 'Consulta creada.';
                    auditLog($conn, 'CONSULTAS', 'INSERTAR consulta de cita #' . $idCita);
                }

                $stmt = $conn->prepare("UPDATE CITAS SET estado = 'Completada' WHERE id_cita = ?");
                $stmt->bind_param('i', $idCita);
                $stmt->execute();

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'No se pudo guardar la consulta.';
            }
        }
    }
}

$editCita = (int)($_GET['cita'] ?? $_GET['edit'] ?? 0);
$editing = $editCita > 0 ? consultaRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            s.nombre AS servicio,
            co.motivo, co.diagnostico, co.observaciones
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     WHERE c.id_cita = ? AND c.id_medico = ?",
    'ii',
    [$editCita, (int)($doctor['id_medico'] ?? 0)]
)[0] ?? null : null;

$appointments = $doctor ? consultaRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            s.nombre AS servicio,
            COALESCE(co.id_consulta, 0) AS id_consulta,
            co.motivo, co.diagnostico, co.observaciones
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     WHERE c.id_medico = ? AND c.estado <> 'Cancelada'
     ORDER BY c.fecha_hora_inicio DESC",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$stats = [
    ['label' => 'Consultas', 'value' => count(array_filter($appointments, static fn ($item) => (int)$item['id_consulta'] > 0)), 'note' => 'Registradas', 'accent' => 'blue'],
    ['label' => 'Pendientes', 'value' => count(array_filter($appointments, static fn ($item) => (int)$item['id_consulta'] === 0)), 'note' => 'Sin cerrar', 'accent' => 'sand'],
    ['label' => 'Total citas', 'value' => count($appointments), 'note' => 'Disponibles', 'accent' => 'blue'],
    ['label' => 'Estado', 'value' => 1, 'note' => 'Consulta médica', 'accent' => 'sand'],
];

include '../../src/portal/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title"><?php echo $editing ? 'Editar consulta' : 'Nueva consulta'; ?></h2>
                    <p class="section-subtitle">Elige una cita y registra el cierre clínico.</p>
                </div>
                <div class="panel-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Cita</label>
                            <select name="id_cita" class="form-select">
                                <option value="">Selecciona</option>
                                <?php foreach ($appointments as $appointment): ?>
                                    <option value="<?php echo (int)$appointment['id_cita']; ?>" <?php echo $editCita === (int)$appointment['id_cita'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($appointment['fecha_hora_inicio'])) . ' · ' . $appointment['paciente']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="motivo" class="form-control" rows="3"><?php echo htmlspecialchars($editing['motivo'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Diagnóstico</label>
                            <textarea name="diagnostico" class="form-control" rows="3"><?php echo htmlspecialchars($editing['diagnostico'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"><?php echo htmlspecialchars($editing['observaciones'] ?? ''); ?></textarea>
                        </div>
                        <button class="btn btn-brand" type="submit"><?php echo $editing ? 'Actualizar' : 'Guardar'; ?></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title">Citas del médico</h2>
                    <p class="section-subtitle">Las que ya puedes cerrar como consulta.</p>
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
                                    <th>Consulta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                        <td><span class="chip <?php echo $item['estado'] === 'Completada' ? 'chip-green' : 'chip-sand'; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                        <td>
                                            <?php if ((int)$item['id_consulta'] > 0): ?>
                                                <a class="btn btn-sm btn-soft" href="?edit=<?php echo (int)$item['id_cita']; ?>">Editar</a>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-brand" href="?cita=<?php echo (int)$item['id_cita']; ?>">Crear</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$appointments): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No hay citas registradas.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../src/admin/footer.php'; ?>
