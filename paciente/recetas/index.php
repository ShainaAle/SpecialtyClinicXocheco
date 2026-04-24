<?php
require_once '../../src/auth.php';
requireRol(['paciente']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/recetas.php';

$basePath = '../..';
$pageTitle = 'Mis recetas';
$pageSubtitle = 'Revisa tus prescripciones, el código y si ya fueron surtidas.';
$activeModule = 'recetas';
$portalLabel = 'Paciente';
$portalRole = 'Paciente';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Cuenta', 'href' => 'paciente/dashboard.php'],
    ['key' => 'citas', 'label' => 'Mis citas', 'href' => 'paciente/citas/'],
    ['key' => 'recetas', 'label' => 'Mis recetas', 'href' => 'paciente/recetas/'],
    ['key' => 'historial', 'label' => 'Historial clínico', 'href' => 'paciente/historial-clinico.php'],
];

function recetaRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
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

ensurePrescriptionTrackingTable($conn);

$rawRows = recetaRows(
    $conn,
    "SELECT r.id_receta, r.fecha_emision, r.observaciones AS receta_observaciones,
            c.fecha_hora_inicio, co.motivo, co.diagnostico, co.observaciones AS consulta_observaciones,
            CONCAT(mu.nombre, ' ', mu.apellidos) AS medico,
            m.nombre_comercial, m.principio_activo, m.concentracion,
            dr.dosis, dr.frecuencia, dr.duracion,
            rs.codigo_receta, rs.estado_surtido, rs.fecha_surtido
     FROM PACIENTES p
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     INNER JOIN CITAS c ON c.id_paciente = p.id_paciente
     LEFT JOIN CONSULTAS co ON co.id_cita = c.id_cita
     LEFT JOIN RECETAS r ON r.id_consulta = co.id_consulta
     LEFT JOIN MEDICOS med ON c.id_medico = med.id_medico
     LEFT JOIN USUARIOS mu ON med.id_usuario = mu.id_usuario
     LEFT JOIN DETALLE_RECETA dr ON dr.id_receta = r.id_receta
     LEFT JOIN MEDICAMENTOS m ON m.id_medicamento = dr.id_medicamento
     LEFT JOIN RECETAS_SURTIDO rs ON rs.id_receta = r.id_receta
     WHERE u.id_usuario = ? AND r.id_receta IS NOT NULL
     ORDER BY r.fecha_emision DESC, c.fecha_hora_inicio DESC",
    'i',
    [(int)$_SESSION['id_usuario']]
);

if ($rawRows) {
    ensurePrescriptionTrackingRows($conn, $rawRows);
}
$trackingMap = getPrescriptionTrackingMap($conn, array_column($rawRows, 'id_receta'));

$grouped = [];
foreach ($rawRows as $row) {
    $id = (int)$row['id_receta'];
    if (!isset($grouped[$id])) {
        $tracking = $trackingMap[$id] ?? [];
        $grouped[$id] = [
            'id_receta' => $id,
            'codigo_receta' => $tracking['codigo_receta'] ?? generatePrescriptionCode($id, $row['fecha_emision']),
            'estado_surtido' => $tracking['estado_surtido'] ?? 'Pendiente',
            'fecha_surtido' => $tracking['fecha_surtido'] ?? null,
            'fecha_emision' => $row['fecha_emision'],
            'fecha_cita' => $row['fecha_hora_inicio'],
            'medico' => $row['medico'],
            'motivo' => $row['motivo'],
            'diagnostico' => $row['diagnostico'],
            'consulta_observaciones' => $row['consulta_observaciones'],
            'receta_observaciones' => $row['receta_observaciones'],
            'medicamentos' => [],
        ];
    }
    if (!empty($row['nombre_comercial'])) {
        $grouped[$id]['medicamentos'][] = $row;
    }
}

$records = array_values($grouped);

$stats = [
    ['label' => 'Recetas', 'value' => count($records), 'note' => 'Total visibles', 'accent' => 'blue'],
    ['label' => 'Surtidas', 'value' => count(array_filter($records, static fn ($item) => $item['estado_surtido'] === 'Surtida')), 'note' => 'Entregadas', 'accent' => 'sand'],
    ['label' => 'Pendientes', 'value' => count(array_filter($records, static fn ($item) => $item['estado_surtido'] !== 'Surtida')), 'note' => 'Por recoger', 'accent' => 'blue'],
    ['label' => 'Acceso', 'value' => 1, 'note' => 'Solo lectura', 'accent' => 'sand'],
];

include '../../src/portal/header.php';
?>

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

<div class="panel-card mb-4">
    <div class="panel-head">
        <h2 class="section-title">Recetas</h2>
        <p class="section-subtitle">Consulta lo que te indicaron y si ya fue surtido.</p>
    </div>
    <div class="panel-body">
        <?php if ($records): ?>
            <div class="row g-3">
                <?php foreach ($records as $recipe): ?>
                    <div class="col-lg-6">
                        <div class="mini-card h-100">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                <strong><?php echo htmlspecialchars($recipe['codigo_receta']); ?></strong>
                                <span class="chip <?php echo $recipe['estado_surtido'] === 'Surtida' ? 'chip-green' : 'chip-sand'; ?>">
                                    <?php echo htmlspecialchars($recipe['estado_surtido']); ?>
                                </span>
                            </div>
                            <div class="small mb-2"><strong>Fecha emisión:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($recipe['fecha_emision']))); ?></div>
                            <div class="small mb-2"><strong>Médico:</strong> <?php echo htmlspecialchars($recipe['medico']); ?></div>
                            <div class="small mb-2"><strong>Motivo:</strong> <?php echo htmlspecialchars($recipe['motivo'] ?: '-'); ?></div>
                            <div class="small mb-2"><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($recipe['diagnostico'] ?: '-'); ?></div>
                            <?php if (!empty($recipe['fecha_surtido'])): ?>
                                <div class="small mb-2"><strong>Surtida el:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($recipe['fecha_surtido']))); ?></div>
                            <?php else: ?>
                                <div class="small mb-2"><strong>Surtida el:</strong> -</div>
                            <?php endif; ?>
                            <div class="mt-3">
                                <strong class="d-block mb-2">Medicamentos</strong>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($recipe['medicamentos'] as $med): ?>
                                        <li class="mb-2">
                                            <div><strong><?php echo htmlspecialchars($med['nombre_comercial']); ?></strong> · <?php echo htmlspecialchars($med['concentracion']); ?></div>
                                            <div class="text-muted-soft small"><?php echo htmlspecialchars($med['dosis'] . ' | ' . $med['frecuencia'] . ' | ' . $med['duracion']); ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted-soft">Aún no tienes recetas registradas.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
