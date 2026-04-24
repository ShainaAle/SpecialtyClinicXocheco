<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Especialidades';
$pageSubtitle = 'Alta, edición y baja del catálogo de especialidades.';
$activeModule = 'especialidades';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $id = (int)($_POST['id_especialidad'] ?? 0);
    $mode = $_POST['mode'] ?? 'create';

    if ($nombre === '') {
        $error = 'Escribe un nombre.';
    } elseif ($mode === 'create') {
        $stmt = $conn->prepare('INSERT INTO ESPECIALIDADES (nombre) VALUES (?)');
        $stmt->bind_param('s', $nombre);
        if ($stmt->execute()) {
            $message = 'Especialidad guardada.';
            auditLog($conn, 'ESPECIALIDADES', 'INSERTAR especialidad');
        } else {
            $error = 'No se pudo guardar.';
        }
    } elseif ($mode === 'update' && $id > 0) {
        $stmt = $conn->prepare('UPDATE ESPECIALIDADES SET nombre = ? WHERE id_especialidad = ?');
        $stmt->bind_param('si', $nombre, $id);
        if ($stmt->execute()) {
            $message = 'Especialidad actualizada.';
            auditLog($conn, 'ESPECIALIDADES', 'ACTUALIZAR especialidad');
        } else {
            $error = 'No se pudo actualizar.';
        }
    }
}

if (isset($_GET['delete'])) {
    $error = 'La eliminación está deshabilitada por integridad referencial.';
}

$editing = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT id_especialidad, nombre FROM ESPECIALIDADES WHERE id_especialidad = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editing = $result ? $result->fetch_assoc() : null;
}

$specialties = [];
$result = $conn->query('SELECT id_especialidad, nombre FROM ESPECIALIDADES ORDER BY nombre');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $specialties[] = $row;
    }
}

include '../../src/admin/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editing ? 'Editar especialidad' : 'Nueva especialidad'; ?></h2>
                <p class="section-subtitle">Mantén ordenado el catálogo médico.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="mode" value="<?php echo $editing ? 'update' : 'create'; ?>">
                    <input type="hidden" name="id_especialidad" value="<?php echo (int)($editing['id_especialidad'] ?? 0); ?>">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($editing['nombre'] ?? ''); ?>" placeholder="Cardiología">
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-brand" type="submit"><?php echo $editing ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($editing): ?><a class="btn btn-soft" href="index.php">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Especialidades registradas en el sistema.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($specialties as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id_especialidad']; ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-soft me-2" href="?edit=<?php echo (int)$item['id_especialidad']; ?>">Editar</a>
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

<?php include '../../src/admin/footer.php'; ?>
