<?php
require_once '../../src/auth.php';
requireRol(['medico']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Recetas';
$pageSubtitle = 'Emite prescripciones para tus pacientes.';
$activeModule = 'recetas';
$portalLabel = 'Médico';
$portalRole = 'Médico';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'medico/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'medico/citas/'],
    ['key' => 'consultas', 'label' => 'Consultas', 'href' => 'medico/consultas/'],
    ['key' => 'recetas', 'label' => 'Recetas', 'href' => 'medico/recetas/'],
];

function recetaMedicaRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$doctor = recetaMedicaRows(
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

$medications = recetaMedicaRows($conn, 'SELECT id_medicamento, nombre_comercial, concentracion FROM MEDICAMENTOS ORDER BY nombre_comercial');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCita = (int)($_POST['id_cita'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $idMedicamento = (int)($_POST['id_medicamento'] ?? 0);
    $dosis = trim($_POST['dosis'] ?? '');
    $frecuencia = trim($_POST['frecuencia'] ?? '');
    $duracion = trim($_POST['duracion'] ?? '');

    if (!$doctor) {
        $error = 'No se encontró tu perfil de médico.';
    } elseif ($idCita <= 0 || $idMedicamento <= 0 || $dosis === '' || $frecuencia === '' || $duracion === '') {
        $error = 'Completa cita, medicamento y la indicación.';
    } else {
        $appointment = recetaMedicaRows(
            $conn,
            "SELECT c.id_cita
             FROM CITAS c
             WHERE c.id_cita = ? AND c.id_medico = ? AND c.estado = 'Completada'",
            'ii',
            [$idCita, (int)$doctor['id_medico']]
        )[0] ?? null;

        if (!$appointment) {
            $error = 'Solo puedes recetar sobre citas completadas que te pertenezcan.';
        } else {
            $conn->begin_transaction();
            try {
                $existing = recetaMedicaRows($conn, 'SELECT co.id_consulta FROM CONSULTAS co WHERE co.id_cita = ?', 'i', [$idCita])[0] ?? null;
                if (!$existing) {
                    throw new RuntimeException('Primero guarda la consulta.');
                }

                $stmt = $conn->prepare('INSERT INTO RECETAS (id_consulta, observaciones) VALUES (?, ?)');
                $stmt->bind_param('is', $existing['id_consulta'], $observaciones);
                $stmt->execute();
                $idReceta = (int)$conn->insert_id;

                $stmt = $conn->prepare('INSERT INTO DETALLE_RECETA (id_receta, id_medicamento, dosis, frecuencia, duracion) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('iisss', $idReceta, $idMedicamento, $dosis, $frecuencia, $duracion);
                $stmt->execute();

                auditLog($conn, 'RECETAS', 'INSERTAR receta #' . $idReceta);
                $conn->commit();
                $message = 'Receta creada.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'No se pudo crear la receta.';
            }
        }
    }
}

$prescriptions = $doctor ? recetaMedicaRows(
    $conn,
    "SELECT r.id_receta, r.fecha_emision, r.observaciones AS receta_observaciones,
            CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
            c.fecha_hora_inicio,
            co.motivo,
            m.nombre_comercial,
            m.concentracion,
            dr.dosis,
            dr.frecuencia,
            dr.duracion
     FROM RECETAS r
     INNER JOIN CONSULTAS co ON r.id_consulta = co.id_consulta
     INNER JOIN CITAS c ON co.id_cita = c.id_cita
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     INNER JOIN DETALLE_RECETA dr ON dr.id_receta = r.id_receta
     INNER JOIN MEDICAMENTOS m ON dr.id_medicamento = m.id_medicamento
     WHERE c.id_medico = ?
     ORDER BY r.fecha_emision DESC",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$candidateAppointments = $doctor ? recetaMedicaRows(
    $conn,
    "SELECT c.id_cita, c.fecha_hora_inicio, CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
     WHERE c.id_medico = ? AND c.estado = 'Completada'
       AND EXISTS (SELECT 1 FROM CONSULTAS co WHERE co.id_cita = c.id_cita)
       AND NOT EXISTS (SELECT 1 FROM RECETAS r INNER JOIN CONSULTAS co ON r.id_consulta = co.id_consulta WHERE co.id_cita = c.id_cita)
     ORDER BY c.fecha_hora_inicio DESC",
    'i',
    [(int)$doctor['id_medico']]
) : [];

$stats = [
    ['label' => 'Recetas', 'value' => count($prescriptions), 'note' => 'Emitidas', 'accent' => 'blue'],
    ['label' => 'Pendientes', 'value' => count($candidateAppointments), 'note' => 'Citas listas', 'accent' => 'sand'],
    ['label' => 'Medicamentos', 'value' => count($medications), 'note' => 'Catálogo', 'accent' => 'blue'],
    ['label' => 'Modo', 'value' => 1, 'note' => 'Solo crear', 'accent' => 'sand'],
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
                    <h2 class="section-title">Nueva receta</h2>
                    <p class="section-subtitle">Solo sobre citas completadas con consulta.</p>
                </div>
                <div class="panel-body">
                    <?php if ($candidateAppointments): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Cita</label>
                                <select name="id_cita" class="form-select">
                                    <option value="">Selecciona</option>
                                    <?php foreach ($candidateAppointments as $appointment): ?>
                                        <option value="<?php echo (int)$appointment['id_cita']; ?>">
                                            <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($appointment['fecha_hora_inicio'])) . ' · ' . $appointment['paciente']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Medicamento</label>
                                <select name="id_medicamento" class="form-select">
                                    <option value="">Selecciona</option>
                                    <?php foreach ($medications as $medication): ?>
                                        <option value="<?php echo (int)$medication['id_medicamento']; ?>">
                                            <?php echo htmlspecialchars($medication['nombre_comercial'] . ' · ' . $medication['concentracion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dosis</label>
                                <input type="text" name="dosis" class="form-control" placeholder="1 tableta">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Frecuencia</label>
                                <input type="text" name="frecuencia" class="form-control" placeholder="Cada 8 horas">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duración</label>
                                <input type="text" name="duracion" class="form-control" placeholder="7 días">
                            </div>
                            <button class="btn btn-brand" type="submit">Crear receta</button>
                        </form>
                    <?php else: ?>
                        <div class="text-muted-soft">No hay citas completadas listas para recetar.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="panel-card h-100">
                <div class="panel-head">
                    <h2 class="section-title">Recetas emitidas</h2>
                    <p class="section-subtitle">Historial de prescripciones creadas por ti.</p>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table-clean">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Medicamento</th>
                                    <th>Dosis</th>
                                    <th>Frecuencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_emision'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                        <td><?php echo htmlspecialchars($item['nombre_comercial']); ?></td>
                                        <td><?php echo htmlspecialchars($item['dosis']); ?></td>
                                        <td><?php echo htmlspecialchars($item['frecuencia']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$prescriptions): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Todavía no has emitido recetas.</td></tr>
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
