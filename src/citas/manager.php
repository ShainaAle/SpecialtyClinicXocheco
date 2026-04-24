<?php
require_once __DIR__ . '/../audit.php';

function citasQuery(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

$message = $message ?? '';
$error = $error ?? '';

$q = trim($_GET['q'] ?? '');
$estadoFilter = trim($_GET['estado'] ?? '');
$doctorFilter = (int)($_GET['medico'] ?? 0);
$patientFilter = (int)($_GET['paciente'] ?? 0);
$dateFilter = trim($_GET['fecha'] ?? '');
$sort = $_GET['sort'] ?? 'date';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$patients = citasQuery(
    $conn,
    "SELECT p.id_paciente, CONCAT(u.nombre, ' ', u.apellidos) AS paciente, u.correo, p.adeudo
     FROM PACIENTES p
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     ORDER BY u.apellidos, u.nombre"
);

$doctors = citasQuery(
    $conn,
    "SELECT m.id_medico, CONCAT(u.nombre, ' ', u.apellidos) AS medico, e.nombre AS especialidad, m.turno
     FROM MEDICOS m
     INNER JOIN USUARIOS u ON m.id_usuario = u.id_usuario
     INNER JOIN ESPECIALIDADES e ON m.id_especialidad = e.id_especialidad
     ORDER BY u.apellidos, u.nombre"
);

$rooms = citasQuery(
    $conn,
    "SELECT id_espacio, CONCAT(COALESCE(nombre, 'Espacio'), ' - Piso ', piso, ' / #', numero) AS espacio
     FROM ESPACIOS_FISICOS
     ORDER BY piso, numero"
);

$services = citasQuery($conn, 'SELECT id_servicio, nombre, precio FROM SERVICIOS ORDER BY nombre');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $idCita = (int)($_POST['id_cita'] ?? 0);
        $idPaciente = (int)($_POST['id_paciente'] ?? 0);
        $idMedico = (int)($_POST['id_medico'] ?? 0);
        $idEspacio = (int)($_POST['id_espacio'] ?? 0);
        $idServicio = (int)($_POST['id_servicio'] ?? 0);
        $fechaHora = trim($_POST['fecha_hora_inicio'] ?? '');
        $estado = trim($_POST['estado'] ?? 'Programada');

        if ($idPaciente <= 0 || $idMedico <= 0 || $idEspacio <= 0 || $idServicio <= 0 || $fechaHora === '') {
            $error = 'Completa todos los campos.';
        } else {
            $fechaSql = date('Y-m-d H:i:s', strtotime($fechaHora));

            if ($idCita <= 0) {
                $stmt = $conn->prepare('CALL sp_agendar_cita_segura(?, ?, ?, ?, ?)');
                $stmt->bind_param('iiiis', $idPaciente, $idMedico, $idEspacio, $idServicio, $fechaSql);
                if ($stmt->execute()) {
                    $message = 'Cita programada.';
                    auditLog($conn, 'CITAS', 'PROGRAMAR cita');
                } else {
                    $error = 'No se pudo programar. Revisa adeudo, horarios u ocupación.';
                }
            } else {
                $row = citasQuery($conn, 'SELECT estado FROM CITAS WHERE id_cita = ?', 'i', [$idCita]);
                if (!$row) {
                    $error = 'Cita no encontrada.';
                } else {
                    $conflictDoctor = citasQuery(
                        $conn,
                        'SELECT COUNT(*) AS total FROM CITAS WHERE id_medico = ? AND fecha_hora_inicio = ? AND estado != "Cancelada" AND id_cita != ?',
                        'isi',
                        [$idMedico, $fechaSql, $idCita]
                    );
                    $conflictRoom = citasQuery(
                        $conn,
                        'SELECT COUNT(*) AS total FROM CITAS WHERE id_espacio = ? AND fecha_hora_inicio = ? AND estado != "Cancelada" AND id_cita != ?',
                        'isi',
                        [$idEspacio, $fechaSql, $idCita]
                    );
                    $debt = citasQuery($conn, 'SELECT adeudo FROM PACIENTES WHERE id_paciente = ?', 'i', [$idPaciente]);

                    if ((int)($debt[0]['adeudo'] ?? 0) === 1) {
                        $error = 'El paciente tiene adeudo.';
                    } elseif ((int)($conflictDoctor[0]['total'] ?? 0) > 0) {
                        $error = 'El médico ya tiene otra cita.';
                    } elseif ((int)($conflictRoom[0]['total'] ?? 0) > 0) {
                        $error = 'El espacio está ocupado.';
                    } else {
                        $stmt = $conn->prepare('UPDATE CITAS SET id_paciente = ?, id_medico = ?, id_espacio = ?, id_servicio = ?, fecha_hora_inicio = ?, estado = ? WHERE id_cita = ?');
                        $stmt->bind_param('iiiissi', $idPaciente, $idMedico, $idEspacio, $idServicio, $fechaSql, $estado, $idCita);
                        if ($stmt->execute()) {
                            $message = 'Cita actualizada.';
                            auditLog($conn, 'CITAS', 'ACTUALIZAR cita #' . $idCita);
                        } else {
                            $error = 'No se pudo actualizar.';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'status') {
        $idCita = (int)($_POST['id_cita'] ?? 0);
        $estado = trim($_POST['estado'] ?? '');
        if ($idCita > 0 && in_array($estado, ['Confirmada', 'Cancelada', 'Completada'], true)) {
            $stmt = $conn->prepare('UPDATE CITAS SET estado = ? WHERE id_cita = ?');
            $stmt->bind_param('si', $estado, $idCita);
            if ($stmt->execute()) {
                $message = 'Estado actualizado.';
                auditLog($conn, 'CITAS', 'CAMBIAR ESTADO cita #' . $idCita . ' a ' . $estado);
            } else {
                $message = 'No se pudo cambiar el estado.';
            }
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rows = citasQuery(
        $conn,
        "SELECT id_cita, id_paciente, id_medico, id_espacio, id_servicio, fecha_hora_inicio, estado
         FROM CITAS WHERE id_cita = ?",
        'i',
        [$editId]
    );
    $editing = $rows[0] ?? null;
}

$sortMap = [
    'date' => 'c.fecha_hora_inicio',
    'patient' => 'paciente',
    'doctor' => 'medico',
    'service' => 'servicio',
    'status' => 'c.estado',
];
$sortField = $sortMap[$sort] ?? 'c.fecha_hora_inicio';

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(pu.nombre LIKE ? OR pu.apellidos LIKE ? OR mu.nombre LIKE ? OR mu.apellidos LIKE ? OR s.nombre LIKE ? OR e.nombre LIKE ?)';
    $like = '%' . $q . '%';
    $types .= str_repeat('s', 6);
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
    }
}

if ($estadoFilter !== '') {
    $where[] = 'c.estado = ?';
    $types .= 's';
    $params[] = $estadoFilter;
}

if ($doctorFilter > 0) {
    $where[] = 'c.id_medico = ?';
    $types .= 'i';
    $params[] = $doctorFilter;
}

if ($patientFilter > 0) {
    $where[] = 'c.id_paciente = ?';
    $types .= 'i';
    $params[] = $patientFilter;
}

if ($dateFilter !== '') {
    $where[] = 'DATE(c.fecha_hora_inicio) = ?';
    $types .= 's';
    $params[] = $dateFilter;
}

$sql = "SELECT c.id_cita, c.fecha_hora_inicio, c.estado,
               CONCAT(pu.nombre, ' ', pu.apellidos) AS paciente,
               CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
               s.nombre AS servicio,
               e.nombre AS espacio
        FROM CITAS c
        INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
        INNER JOIN USUARIOS pu ON p.id_usuario = pu.id_usuario
        INNER JOIN MEDICOS m ON c.id_medico = m.id_medico
        INNER JOIN USUARIOS mu ON m.id_usuario = mu.id_usuario
        INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
        INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, c.id_cita DESC";
$appointments = citasQuery($conn, $sql, $types, $params);

$stats = [
    ['label' => 'Citas', 'value' => count($appointments), 'note' => 'Visibles en pantalla', 'accent' => 'blue'],
    ['label' => 'Programadas', 'value' => count(array_filter($appointments, static fn ($x) => $x['estado'] === 'Programada')), 'note' => 'Pendientes', 'accent' => 'sand'],
    ['label' => 'Confirmadas', 'value' => count(array_filter($appointments, static fn ($x) => $x['estado'] === 'Confirmada')), 'note' => 'Listas', 'accent' => 'blue'],
    ['label' => 'Canceladas', 'value' => count(array_filter($appointments, static fn ($x) => $x['estado'] === 'Cancelada')), 'note' => 'Anuladas', 'accent' => 'sand'],
];

function citasSortButton(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
{
    $isActive = $currentSort === $key;
    $nextDir = $isActive && $currentDir === 'ASC' ? 'desc' : 'asc';
    $baseParams['sort'] = $key;
    $baseParams['dir'] = $nextDir;
    $arrow = $isActive ? ($currentDir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a class="btn btn-sm ' . ($isActive ? 'btn-brand' : 'btn-soft') . '" href="?' . htmlspecialchars(http_build_query($baseParams)) . '">' . htmlspecialchars($label . $arrow) . '</a>';
}

$baseFilterParams = [
    'q' => $q,
    'estado' => $estadoFilter,
    'medico' => $doctorFilter,
    'paciente' => $patientFilter,
    'fecha' => $dateFilter,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

$selectedDateTime = $editing ? date('Y-m-d\TH:i', strtotime($editing['fecha_hora_inicio'])) : date('Y-m-d\TH:i');
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar cita' : 'Nueva cita'; ?></h2>
                <p class="section-subtitle">Programar, reprogramar o cambiar estado.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id_cita" value="<?php echo (int)($editing['id_cita'] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Paciente</label>
                        <select name="id_paciente" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo (int)$patient['id_paciente']; ?>" <?php echo ((int)($editing['id_paciente'] ?? 0) === (int)$patient['id_paciente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['paciente'] . ($patient['adeudo'] ? ' · adeudo' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Médico</label>
                        <select name="id_medico" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo (int)$doctor['id_medico']; ?>" <?php echo ((int)($editing['id_medico'] ?? 0) === (int)$doctor['id_medico']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['medico'] . ' · ' . $doctor['especialidad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Espacio</label>
                        <select name="id_espacio" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo (int)$room['id_espacio']; ?>" <?php echo ((int)($editing['id_espacio'] ?? 0) === (int)$room['id_espacio']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['espacio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Servicio</label>
                        <select name="id_servicio" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo (int)$service['id_servicio']; ?>" <?php echo ((int)($editing['id_servicio'] ?? 0) === (int)$service['id_servicio']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['nombre'] . ' · $' . number_format((float)$service['precio'], 2)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha y hora</label>
                        <input type="datetime-local" name="fecha_hora_inicio" class="form-control" value="<?php echo htmlspecialchars($selectedDateTime); ?>">
                    </div>
                    <?php if ($editing): ?>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <?php foreach (['Programada', 'Confirmada', 'Cancelada', 'Completada'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo (($editing['estado'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-brand" type="submit"><?php echo $editing ? 'Actualizar' : 'Programar'; ?></button>
                        <?php if ($editing): ?><a class="btn btn-soft" href="index.php">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card mb-4">
            <div class="panel-head">
                <h2 class="section-title">Buscar y ordenar</h2>
                <p class="section-subtitle">Filtra por texto, estado, médico, paciente o fecha.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($q); ?>" placeholder="Paciente, médico, servicio">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach (['Programada', 'Confirmada', 'Cancelada', 'Completada'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $estadoFilter === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Médico</label>
                        <select name="medico" class="form-select">
                            <option value="0">Todos</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo (int)$doctor['id_medico']; ?>" <?php echo $doctorFilter === (int)$doctor['id_medico'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['medico']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Paciente</label>
                        <select name="paciente" class="form-select">
                            <option value="0">Todos</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo (int)$patient['id_paciente']; ?>" <?php echo $patientFilter === (int)$patient['id_paciente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['paciente']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Dir</label>
                        <select name="dir" class="form-select">
                            <option value="desc" <?php echo strtolower($dir) === 'desc' ? 'selected' : ''; ?>>↓</option>
                            <option value="asc" <?php echo strtolower($dir) === 'asc' ? 'selected' : ''; ?>>↑</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex gap-2">
                        <button class="btn btn-brand" type="submit">Aplicar</button>
                        <a class="btn btn-soft" href="index.php">Limpiar</a>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php echo citasSortButton('Fecha', 'date', $sort, $dir, $baseFilterParams); ?>
                    <?php echo citasSortButton('Paciente', 'patient', $sort, $dir, $baseFilterParams); ?>
                    <?php echo citasSortButton('Médico', 'doctor', $sort, $dir, $baseFilterParams); ?>
                    <?php echo citasSortButton('Servicio', 'service', $sort, $dir, $baseFilterParams); ?>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Citas registradas en el sistema.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Paciente</th>
                                <th>Médico</th>
                                <th>Servicio</th>
                                <th>Espacio</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                    <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                    <td><?php echo htmlspecialchars($item['espacio']); ?></td>
                                    <td><span class="chip <?php echo $item['estado'] === 'Cancelada' ? 'chip-red' : ($item['estado'] === 'Confirmada' ? 'chip-green' : 'chip-sand'); ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-soft" href="?<?php echo htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['edit' => $item['id_cita']]))); ?>">Editar</a>
                                            <?php foreach (['Confirmada', 'Cancelada', 'Completada'] as $st): ?>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="status">
                                                    <input type="hidden" name="id_cita" value="<?php echo (int)$item['id_cita']; ?>">
                                                    <input type="hidden" name="estado" value="<?php echo $st; ?>">
                                                    <button class="btn btn-sm <?php echo $st === 'Cancelada' ? 'btn-outline-danger' : 'btn-soft'; ?>" type="submit"><?php echo $st; ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
