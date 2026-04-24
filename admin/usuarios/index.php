<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/audit.php';

$basePath = '../..';
$pageTitle = 'Usuarios';
$pageSubtitle = 'Buscar, ordenar y administrar cuentas, roles y direcciones.';
$activeModule = 'usuarios';

function bindParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => &$value) {
        $bind[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function tableQuery(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        bindParams($stmt, $types, $params);
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

$message = '';
$error = '';

$rolesCatalog = tableQuery(
    $conn,
    'SELECT id_tipo_usuario, nombre_rol FROM TIPO_USUARIO ORDER BY id_tipo_usuario'
);

$roleNames = array_map(static fn ($row) => $row['nombre_rol'], $rolesCatalog);
$selectedRole = trim($_GET['role'] ?? 'all');
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $userId = (int)($_POST['id_usuario'] ?? 0);

        if ($userId <= 0) {
            $error = 'Usuario inválido.';
        } elseif ($userId === (int)($_SESSION['id_usuario'] ?? 0)) {
            $error = 'No puedes eliminar tu propia sesión.';
        } else {
            $stmt = $conn->prepare('SELECT id_domicilio FROM USUARIOS WHERE id_usuario = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userRow = $result ? $result->fetch_assoc() : null;

            if (!$userRow) {
                $error = 'Usuario no encontrado.';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare('DELETE FROM USUARIOS WHERE id_usuario = ?');
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();

                    $domicilioId = (int)($userRow['id_domicilio'] ?? 0);
                    if ($domicilioId > 0) {
                        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM USUARIOS WHERE id_domicilio = ?');
                        $stmt->bind_param('i', $domicilioId);
                        $stmt->execute();
                        $countResult = $stmt->get_result();
                        $countRow = $countResult ? $countResult->fetch_assoc() : ['total' => 0];

                        if ((int)$countRow['total'] === 0) {
                            $stmt = $conn->prepare('DELETE FROM DOMICILIO WHERE id_domicilio = ?');
                            $stmt->bind_param('i', $domicilioId);
                            $stmt->execute();
                        }
                    }

                    if (!auditLog($conn, 'USUARIOS', 'ELIMINAR usuario #' . $userId)) {
                        throw new RuntimeException('No se pudo registrar la bitácora.');
                    }

                    $conn->commit();
                    $message = 'Usuario eliminado.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = 'No se pudo eliminar.';
                }
            }
        }
    } else {
        $userId = (int)($_POST['id_usuario'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $idTipoUsuario = (int)($_POST['id_tipo_usuario'] ?? 0);
        $calle = trim($_POST['calle'] ?? '');
        $numeroExterior = trim($_POST['numero_exterior'] ?? '');
        $colonia = trim($_POST['colonia'] ?? '');
        $codigoPostal = trim($_POST['codigo_postal'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        if ($nombre === '' || $apellidos === '' || $correo === '' || $idTipoUsuario <= 0 || $calle === '' || $numeroExterior === '' || $colonia === '' || $codigoPostal === '' || $ciudad === '' || $estado === '') {
            $error = 'Completa los campos obligatorios.';
        } elseif ($userId <= 0 && $password === '') {
            $error = 'La contraseña es obligatoria para crear un usuario.';
        } else {
            $conn->begin_transaction();
            try {
                if ($userId > 0) {
                    $stmt = $conn->prepare('SELECT id_domicilio FROM USUARIOS WHERE id_usuario = ?');
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $currentUser = $result ? $result->fetch_assoc() : null;

                    if (!$currentUser) {
                        throw new RuntimeException('Usuario no encontrado.');
                    }

                    $domicilioId = (int)($currentUser['id_domicilio'] ?? 0);
                    if ($domicilioId > 0) {
                        $stmt = $conn->prepare('UPDATE DOMICILIO SET calle = ?, numero_exterior = ?, colonia = ?, codigo_postal = ?, ciudad = ?, estado = ? WHERE id_domicilio = ?');
                        $stmt->bind_param('ssssssi', $calle, $numeroExterior, $colonia, $codigoPostal, $ciudad, $estado, $domicilioId);
                        $stmt->execute();
                    } else {
                        $stmt = $conn->prepare('INSERT INTO DOMICILIO (calle, numero_exterior, colonia, codigo_postal, ciudad, estado) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param('ssssss', $calle, $numeroExterior, $colonia, $codigoPostal, $ciudad, $estado);
                        $stmt->execute();
                        $domicilioId = (int)$conn->insert_id;
                    }

                    if ($password !== '') {
                        $stmt = $conn->prepare('UPDATE USUARIOS SET id_domicilio = ?, id_tipo_usuario = ?, nombre = ?, apellidos = ?, correo = ?, password_hash = ? WHERE id_usuario = ?');
                        $stmt->bind_param('iissssi', $domicilioId, $idTipoUsuario, $nombre, $apellidos, $correo, $password, $userId);
                    } else {
                        $stmt = $conn->prepare('UPDATE USUARIOS SET id_domicilio = ?, id_tipo_usuario = ?, nombre = ?, apellidos = ?, correo = ? WHERE id_usuario = ?');
                        $stmt->bind_param('iisssi', $domicilioId, $idTipoUsuario, $nombre, $apellidos, $correo, $userId);
                    }
                    $stmt->execute();
                    if (!auditLog($conn, 'USUARIOS', 'ACTUALIZAR usuario #' . $userId)) {
                        throw new RuntimeException('No se pudo registrar la bitácora.');
                    }
                } else {
                    $stmt = $conn->prepare('INSERT INTO DOMICILIO (calle, numero_exterior, colonia, codigo_postal, ciudad, estado) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssssss', $calle, $numeroExterior, $colonia, $codigoPostal, $ciudad, $estado);
                    $stmt->execute();
                    $domicilioId = (int)$conn->insert_id;

                    $stmt = $conn->prepare('INSERT INTO USUARIOS (id_domicilio, id_tipo_usuario, nombre, apellidos, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('iissss', $domicilioId, $idTipoUsuario, $nombre, $apellidos, $correo, $password);
                    $stmt->execute();
                    if (!auditLog($conn, 'USUARIOS', 'INSERTAR usuario #' . (int)$conn->insert_id)) {
                        throw new RuntimeException('No se pudo registrar la bitácora.');
                    }
                }

                $conn->commit();
                $message = $userId > 0 ? 'Usuario actualizado.' : 'Usuario creado.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'No se pudo guardar.';
            }
        }
    }
}

$editingUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRows = tableQuery(
        $conn,
        "SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.password_hash, u.id_tipo_usuario,
                d.calle, d.numero_exterior, d.colonia, d.codigo_postal, d.ciudad, d.estado
         FROM USUARIOS u
         LEFT JOIN DOMICILIO d ON u.id_domicilio = d.id_domicilio
         WHERE u.id_usuario = ?",
        'i',
        [$editId]
    );
    $editingUser = $editRows[0] ?? null;
}

$roleCounts = tableQuery(
    $conn,
    "SELECT t.nombre_rol, COUNT(u.id_usuario) AS total
     FROM TIPO_USUARIO t
     LEFT JOIN USUARIOS u ON t.id_tipo_usuario = u.id_tipo_usuario
     GROUP BY t.id_tipo_usuario, t.nombre_rol
     ORDER BY t.id_tipo_usuario"
);

$sortMap = [
    'id' => 'u.id_usuario',
    'name' => 'u.nombre',
    'email' => 'u.correo',
    'role' => 't.nombre_rol',
];
$sortField = $sortMap[$sort] ?? 'u.id_usuario';

$where = [];
$params = [];
$types = '';

if ($selectedRole !== 'all' && in_array($selectedRole, $roleNames, true)) {
    $where[] = 't.nombre_rol = ?';
    $types .= 's';
    $params[] = $selectedRole;
}

if ($search !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.correo LIKE ? OR t.nombre_rol LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.password_hash, t.nombre_rol,
               d.id_domicilio, d.calle, d.numero_exterior, d.colonia, d.codigo_postal, d.ciudad, d.estado
        FROM USUARIOS u
        INNER JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario
        LEFT JOIN DOMICILIO d ON u.id_domicilio = d.id_domicilio";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " ORDER BY {$sortField} {$dir}, u.id_usuario ASC";
$users = tableQuery($conn, $sql, $types, $params);

function sortLink(string $label, string $key, string $currentSort, string $currentDir, array $baseParams): string
{
    $isActive = $currentSort === $key;
    $nextDir = $isActive && $currentDir === 'ASC' ? 'desc' : 'asc';
    $baseParams['sort'] = $key;
    $baseParams['dir'] = $nextDir;
    $arrow = $isActive ? ($currentDir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a class="btn btn-sm ' . ($isActive ? 'btn-brand' : 'btn-soft') . '" href="?' . htmlspecialchars(http_build_query($baseParams)) . '">' . htmlspecialchars($label . $arrow) . '</a>';
}

$baseFilterParams = [
    'q' => $search,
    'role' => $selectedRole,
    'sort' => $sort,
    'dir' => strtolower($dir),
];

include '../../src/admin/header.php';
?>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($roleCounts as $role): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-blue">
                <div class="stat-label"><?php echo htmlspecialchars($role['nombre_rol']); ?></div>
                <div class="stat-value"><?php echo (int)$role['total']; ?></div>
                <div class="stat-note">Cuentas registradas</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title"><?php echo $editingUser ? 'Editar usuario' : 'Nuevo usuario'; ?></h2>
                <p class="section-subtitle">Datos de acceso y domicilio.</p>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="id_usuario" value="<?php echo (int)($editingUser['id_usuario'] ?? 0); ?>">
                    <input type="hidden" name="action" value="save">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($editingUser['nombre'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($editingUser['apellidos'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" value="<?php echo htmlspecialchars($editingUser['correo'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña <?php echo $editingUser ? '(opcional)' : ''; ?></label>
                        <input type="text" name="password" class="form-control" value="" placeholder="<?php echo $editingUser ? 'Dejar en blanco para conservar' : 'Contraseña'; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select name="id_tipo_usuario" class="form-select">
                            <option value="">Selecciona</option>
                            <?php foreach ($rolesCatalog as $role): ?>
                                <option value="<?php echo (int)$role['id_tipo_usuario']; ?>" <?php echo ((int)($editingUser['id_tipo_usuario'] ?? 0) === (int)$role['id_tipo_usuario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <hr class="my-4">
                    <div class="mb-3">
                        <label class="form-label">Calle</label>
                        <input type="text" name="calle" class="form-control" value="<?php echo htmlspecialchars($editingUser['calle'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número exterior</label>
                        <input type="text" name="numero_exterior" class="form-control" value="<?php echo htmlspecialchars($editingUser['numero_exterior'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Colonia</label>
                        <input type="text" name="colonia" class="form-control" value="<?php echo htmlspecialchars($editingUser['colonia'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Código postal</label>
                        <input type="text" name="codigo_postal" class="form-control" value="<?php echo htmlspecialchars($editingUser['codigo_postal'] ?? ''); ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control" value="<?php echo htmlspecialchars($editingUser['ciudad'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estado</label>
                            <input type="text" name="estado" class="form-control" value="<?php echo htmlspecialchars($editingUser['estado'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-brand" type="submit"><?php echo $editingUser ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($editingUser): ?>
                            <a class="btn btn-soft" href="index.php">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="panel-card mb-4">
            <div class="panel-head">
                <h2 class="section-title">Buscar y ordenar</h2>
                <p class="section-subtitle">Filtra por rol, busca por texto o cambia el orden de la tabla.</p>
            </div>
            <div class="panel-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" placeholder="Nombre, correo o rol" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select">
                            <option value="all" <?php echo $selectedRole === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <?php foreach ($rolesCatalog as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['nombre_rol']); ?>" <?php echo $selectedRole === $role['nombre_rol'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Orden</label>
                        <select name="sort" class="form-select">
                            <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>ID</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nombre</option>
                            <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Correo</option>
                            <option value="role" <?php echo $sort === 'role' ? 'selected' : ''; ?>>Rol</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dirección</label>
                        <select name="dir" class="form-select">
                            <option value="asc" <?php echo strtolower($dir) === 'asc' ? 'selected' : ''; ?>>Asc</option>
                            <option value="desc" <?php echo strtolower($dir) === 'desc' ? 'selected' : ''; ?>>Desc</option>
                        </select>
                    </div>
                    <div class="col-md-12 d-flex gap-2">
                        <button class="btn btn-brand" type="submit">Aplicar</button>
                        <a class="btn btn-soft" href="index.php">Limpiar</a>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php echo sortLink('ID', 'id', $sort, $dir, $baseFilterParams); ?>
                    <?php echo sortLink('Nombre', 'name', $sort, $dir, $baseFilterParams); ?>
                    <?php echo sortLink('Correo', 'email', $sort, $dir, $baseFilterParams); ?>
                    <?php echo sortLink('Rol', 'role', $sort, $dir, $baseFilterParams); ?>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-head">
                <h2 class="section-title">Listado</h2>
                <p class="section-subtitle">Usuarios activos con su dirección completa.</p>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Dirección</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo (int)$user['id_usuario']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']); ?></td>
                                    <td><?php echo htmlspecialchars($user['correo']); ?></td>
                                    <td><span class="chip chip-sand"><?php echo htmlspecialchars($user['nombre_rol']); ?></span></td>
                                    <td>
                                        <?php if (!empty($user['id_domicilio'])): ?>
                                            <?php
                                            echo htmlspecialchars(
                                                trim(
                                                    $user['calle'] . ' ' . $user['numero_exterior'] . ', ' .
                                                    $user['colonia'] . ', ' . $user['ciudad'] . ', ' . $user['estado']
                                                )
                                            );
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin dirección</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-soft" href="?<?php echo htmlspecialchars(http_build_query(array_merge($baseFilterParams, ['edit' => $user['id_usuario']] ))); ?>">Editar</a>
                                            <form method="post" onsubmit="return confirm('¿Eliminar este usuario?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_usuario" value="<?php echo (int)$user['id_usuario']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                                            </form>
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

<?php include '../../src/admin/footer.php'; ?>
