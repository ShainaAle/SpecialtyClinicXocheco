<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Usuarios';
$pageSubtitle = 'Vista general de cuentas, roles y perfiles registrados.';
$activeModule = 'usuarios';

$users = [];
$result = $conn->query("SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, t.nombre_rol
    FROM USUARIOS u
    INNER JOIN TIPO_USUARIO t ON u.id_tipo_usuario = t.id_tipo_usuario
    ORDER BY t.nombre_rol, u.apellidos");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$roles = [];
$roleResult = $conn->query("SELECT t.nombre_rol, COUNT(u.id_usuario) AS total
    FROM TIPO_USUARIO t
    LEFT JOIN USUARIOS u ON t.id_tipo_usuario = u.id_tipo_usuario
    GROUP BY t.id_tipo_usuario, t.nombre_rol
    ORDER BY t.id_tipo_usuario");
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        $roles[] = $row;
    }
}

include '../../src/admin/header.php';
?>

<div class="row g-3 mb-4">
    <?php foreach ($roles as $role): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card accent-blue">
                <div class="stat-label"><?php echo htmlspecialchars($role['nombre_rol']); ?></div>
                <div class="stat-value"><?php echo (int)$role['total']; ?></div>
                <div class="stat-note">Cuentas registradas</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="panel-card">
    <div class="panel-head">
        <h2 class="section-title">Listado</h2>
        <p class="section-subtitle">Usuarios activos en el sistema.</p>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int)$user['id_usuario']; ?></td>
                            <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']); ?></td>
                            <td><?php echo htmlspecialchars($user['correo']); ?></td>
                            <td><span class="chip chip-sand"><?php echo htmlspecialchars($user['nombre_rol']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../src/admin/footer.php'; ?>
