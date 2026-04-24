<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Espacios físicos';
$pageSubtitle = 'Consultorios, quirófanos, laboratorios y farmacia central.';
$activeModule = 'espacios';

$message = '';
$error = '';

$types = [];
$typeResult = $conn->query('SELECT id_tipo, tipo FROM TIPOS_ESPACIOS_FISICOS ORDER BY tipo');
if ($typeResult) {
    while ($row = $typeResult->fetch_assoc()) {
        $types[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $piso = (int)($_POST['piso'] ?? 0);
    $numero = (int)($_POST['numero'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $id_tipo = (int)($_POST['id_tipo'] ?? 0);
    $id = (int)($_POST['id_espacio'] ?? 0);
    $mode = $_POST['mode'] ?? 'create';

    if ($piso <= 0 || $numero <= 0 || $id_tipo <= 0) {
        $error = 'Completa piso, número y tipo.';
    } elseif ($mode === 'create') {
        $stmt = $conn->prepare('INSERT INTO ESPACIOS_FISICOS (piso, numero, nombre, id_tipo) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iisi', $piso, $numero, $nombre, $id_tipo);
        if ($stmt->execute()) {
            $message = 'Espacio guardado.';
            auditLog($conn, 'ESPACIOS_FISICOS', 'INSERTAR espacio');
        } else {
            $message = 'No se pudo guardar.';
        }
    } elseif ($mode === 'update' && $id > 0) {
        $stmt = $conn->prepare('UPDATE ESPACIOS_FISICOS SET piso = ?, numero = ?, nombre = ?, id_tipo = ? WHERE id_espacio = ?');
        $stmt->bind_param('iisii', $piso, $numero, $nombre, $id_tipo, $id);
        if ($stmt->execute()) {
            $message = 'Espacio actualizado.';
            auditLog($conn, 'ESPACIOS_FISICOS', 'ACTUALIZAR espacio');
        } else {
            $message = 'No se pudo actualizar.';
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM ESPACIOS_FISICOS WHERE id_espacio = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = 'Espacio eliminado.';
            auditLog($conn, 'ESPACIOS_FISICOS', 'ELIMINAR espacio');
        } else {
            $message = 'No se pudo eliminar.';
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare('SELECT id_espacio, piso, numero, nombre, id_tipo FROM ESPACIOS_FISICOS WHERE id_espacio = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editing = $result ? $result->fetch_assoc() : null;
}

$spaces = [];
$result = $conn->query("SELECT e.id_espacio, e.piso, e.numero, e.nombre, t.tipo
    FROM ESPACIOS_FISICOS e
    INNER JOIN TIPOS_ESPACIOS_FISICOS t ON e.id_tipo = t.id_tipo
    ORDER BY e.piso, e.numero");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $spaces[] = $row;
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
                <h2 class="section-title"><?php echo $editing ? 'Editar espacio' : 'Nuevo espacio'; ?></h2>
                <p class="section-subtitle">Control de consultorios y áreas físicas.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="mode" value="<?php echo $editing ? 'update' : 'create'; ?>">
                    <input type="hidden" name="id_espacio" value="<?php echo (int)($editing['id_espacio'] ?? 0); ?>">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Piso</label>
                            <input type="number" min="1" name="piso" class="form-control" value="<?php echo htmlspecialchars($editing['piso'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Número</label>
                            <input type="number" min="1" name="numero" class="form-control" value="<?php echo htmlspecialchars($editing['numero'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($editing['nombre'] ?? ''); ?>" placeholder="Consultorio A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <select name="id_tipo" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo (int)$type['id_tipo']; ?>" <?php echo ((int)($editing['id_tipo'] ?? 0) === (int)$type['id_tipo']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['tipo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                <p class="section-subtitle">Espacios disponibles en la clínica.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Piso</th>
                                <th>Número</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spaces as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id_espacio']; ?></td>
                                    <td><?php echo (int)$item['piso']; ?></td>
                                    <td><?php echo (int)$item['numero']; ?></td>
                                    <td><?php echo htmlspecialchars($item['nombre'] ?? '-'); ?></td>
                                    <td><span class="chip chip-blue"><?php echo htmlspecialchars($item['tipo']); ?></span></td>
                                    <td>
                                        <a class="btn btn-sm btn-soft me-2" href="?edit=<?php echo (int)$item['id_espacio']; ?>">Editar</a>
                                        <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int)$item['id_espacio']; ?>" onclick="return confirm('¿Eliminar este espacio?');">Borrar</a>
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
