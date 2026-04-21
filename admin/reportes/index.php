<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Reportes';
$pageSubtitle = 'Consultas rápidas para médicos, pacientes, inventario e ingresos.';
$activeModule = 'reportes';

$doctors = [];
$result = $conn->query('SELECT * FROM vw_reporte_medicos_especialidad LIMIT 5');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

$patients = [];
$result = $conn->query('SELECT * FROM vw_reporte_pacientes_edades LIMIT 5');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}

$inventory = [];
$result = $conn->query('SELECT * FROM vw_reporte_inventario LIMIT 5');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory[] = $row;
    }
}

$income = [];
$result = $conn->query('SELECT * FROM vw_reporte_ingresos LIMIT 5');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $income[] = $row;
    }
}

include '../../src/admin/header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Médicos por especialidad</h2>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Especialidad</th>
                                <th>Médico</th>
                                <th>Turno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['especialidad']); ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre_medico'] . ' ' . $item['apellidos_medico']); ?></td>
                                    <td><?php echo htmlspecialchars($item['turno']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Pacientes por edad</h2>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Edad</th>
                                <th>Adeudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nombre'] . ' ' . $item['apellidos']); ?></td>
                                    <td><?php echo (int)$item['edad']; ?></td>
                                    <td><span class="chip <?php echo $item['adeudo'] ? 'chip-red' : 'chip-green'; ?>"><?php echo $item['adeudo'] ? 'Sí' : 'No'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Inventario</h2>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Existencia</th>
                                <th>Alerta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nombre_comercial']); ?></td>
                                    <td><?php echo (int)$item['cantidad_disponible']; ?></td>
                                    <td><span class="chip chip-amber"><?php echo htmlspecialchars($item['alerta_caducidad']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card h-100">
            <div class="panel-head">
                <h2 class="section-title">Ingresos</h2>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Servicio</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($income as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['paciente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($item['servicio_cobrado']); ?></td>
                                    <td>$<?php echo number_format((float)$item['monto_total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
