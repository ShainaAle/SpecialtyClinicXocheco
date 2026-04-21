<?php
require_once '../src/auth.php';
requireRol(['admin']);
require_once '../src/conexion/conexion.php';

$adminName = $_SESSION['nombre_completo'] ?? 'Administrador';
$adminRole = $_SESSION['nombre_rol'] ?? 'Administrador';
$today = date('d/m/Y');

function countRows(mysqli $conn, string $table): int
{
    $sql = "SELECT COUNT(*) AS total FROM {$table}";
    $result = $conn->query($sql);
    if ($result && ($row = $result->fetch_assoc())) {
        return (int)$row['total'];
    }
    return 0;
}

function safeQuery(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$stats = [
    [
        'label' => 'Usuarios activos',
        'value' => countRows($conn, 'USUARIOS'),
        'note' => 'Cuentas registradas en el sistema',
        'accent' => 'blue',
    ],
    [
        'label' => 'Pacientes',
        'value' => countRows($conn, 'PACIENTES'),
        'note' => 'Perfiles clínicos creados',
        'accent' => 'sand',
    ],
    [
        'label' => 'Médicos',
        'value' => countRows($conn, 'MEDICOS'),
        'note' => 'Profesionales dados de alta',
        'accent' => 'blue',
    ],
    [
        'label' => 'Citas',
        'value' => countRows($conn, 'CITAS'),
        'note' => 'Agendas registradas',
        'accent' => 'sand',
    ],
    [
        'label' => 'Consultas',
        'value' => countRows($conn, 'CONSULTAS'),
        'note' => 'Atenciones completadas',
        'accent' => 'blue',
    ],
    [
        'label' => 'Movimientos de bitácora',
        'value' => countRows($conn, 'BITACORA'),
        'note' => 'Actividad registrada',
        'accent' => 'sand',
    ],
];

$nextAppointments = safeQuery(
    $conn,
    "SELECT c.fecha_hora_inicio, c.estado, 
            CONCAT(u.nombre, ' ', u.apellidos) AS paciente,
            CONCAT(med.nombre, ' ', med.apellidos) AS medico,
            s.nombre AS servicio,
            e.nombre AS espacio
     FROM CITAS c
     INNER JOIN PACIENTES p ON c.id_paciente = p.id_paciente
     INNER JOIN USUARIOS u ON p.id_usuario = u.id_usuario
     INNER JOIN MEDICOS md ON c.id_medico = md.id_medico
     INNER JOIN USUARIOS med ON md.id_usuario = med.id_usuario
     INNER JOIN SERVICIOS s ON c.id_servicio = s.id_servicio
     INNER JOIN ESPACIOS_FISICOS e ON c.id_espacio = e.id_espacio
     ORDER BY c.fecha_hora_inicio ASC
     LIMIT 5"
);

$inventoryAlerts = safeQuery(
    $conn,
    "SELECT m.nombre_comercial, i.cantidad_disponible, i.fecha_caducidad, es.estado
     FROM INVENTARIO i
     INNER JOIN MEDICAMENTOS m ON i.id_medicamento = m.id_medicamento
     INNER JOIN ESTADOS_MEDICAMENTOS es ON i.id_estado_medicamento = es.id_estado_medicamento
     ORDER BY i.fecha_caducidad ASC
     LIMIT 4"
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin · Clínica Xocheco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../src/Images/Icon.png">
    <style>
        :root {
            --azul-oscuro: #0a1f44;
            --azul-medio: #1a3a6e;
            --azul-acento: #2563eb;
            --azul-claro: #dbeafe;
            --cafe: #7e3900;
            --cafe-hover: #ca610a;
            --blanco: #ffffff;
            --gris-suave: #f1f5f9;
            --texto-oscuro: #0f172a;
            --borde: rgba(15, 23, 42, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #ffffff 42%, #f7fafc 100%);
            color: var(--texto-oscuro);
        }

        .navbar {
            background: var(--azul-oscuro) !important;
            padding: 0.85rem 2rem;
            box-shadow: 0 2px 20px rgba(10, 31, 68, 0.25);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: var(--blanco) !important;
            letter-spacing: 0.5px;
        }

        .navbar-brand span {
            color: #60a5fa;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s;
            padding: 0.5rem 1rem !important;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #60a5fa !important;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.3);
        }

        .navbar-toggler-icon {
            filter: invert(1);
        }

        .btn-nav-login {
            background: var(--azul-acento);
            color: var(--blanco) !important;
            border-radius: 8px;
            padding: 0.45rem 1.2rem !important;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-nav-login:hover {
            background: #1d4ed8;
        }

        .btn-nav-logout {
            background: transparent;
            color: #fca5a5 !important;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 0.4rem 1rem !important;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-nav-logout:hover {
            background: rgba(252, 165, 165, 0.1);
        }

        .user-greeting {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-greeting::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
        }

        .dashboard-shell {
            padding: 2rem 0 3rem;
        }

        .hero-panel {
            background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 60%, #1e4d8c 100%);
            color: var(--blanco);
            border-radius: 28px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(10, 31, 68, 0.18);
        }

        .hero-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-panel::after {
            content: '';
            position: absolute;
            width: 420px;
            height: 420px;
            right: -140px;
            top: -180px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.32), transparent 68%);
            border-radius: 50%;
        }

        .hero-copy,
        .hero-side {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(37, 99, 235, 0.25);
            border: 1px solid rgba(96, 165, 250, 0.35);
            color: #bfdbfe;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .hero-badge::before {
            content: '+';
            color: #60a5fa;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.1;
            margin-bottom: 0.9rem;
        }

        .hero-title span {
            color: #60a5fa;
        }

        .hero-text {
            max-width: 640px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }

        .btn-soft,
        .btn-strong {
            text-decoration: none;
            border-radius: 12px;
            padding: 0.78rem 1.15rem;
            font-weight: 600;
            font-size: 0.92rem;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .btn-strong {
            background: var(--cafe);
            color: var(--blanco);
            border: 2px solid var(--cafe);
        }

        .btn-strong:hover {
            background: var(--cafe-hover);
            border-color: var(--cafe-hover);
            color: var(--blanco);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(126, 57, 0, 0.28);
        }

        .btn-soft {
            background: rgba(255, 255, 255, 0.08);
            color: var(--blanco);
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .btn-soft:hover {
            background: rgba(255, 255, 255, 0.14);
            color: var(--blanco);
            transform: translateY(-2px);
        }

        .hero-metric {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 18px;
            padding: 1rem 1.1rem;
            backdrop-filter: blur(6px);
        }

        .hero-metric small {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            margin-bottom: 0.35rem;
        }

        .hero-metric strong {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            color: #ffffff;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
            color: var(--azul-oscuro);
        }

        .section-subtitle {
            color: #64748b;
            font-size: 0.92rem;
            margin-bottom: 1.1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid var(--borde);
            border-radius: 20px;
            padding: 1.1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            height: 100%;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.82rem;
            margin-bottom: 0.2rem;
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-note {
            color: #94a3b8;
            font-size: 0.84rem;
        }

        .accent-blue {
            border-top: 4px solid #2563eb;
        }

        .accent-sand {
            border-top: 4px solid var(--cafe);
        }

        .panel-card {
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--borde);
            border-radius: 22px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .panel-card-header {
            padding: 1.2rem 1.25rem 0.2rem;
        }

        .panel-card-body {
            padding: 0 1.25rem 1.2rem;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .quick-item {
            display: flex;
            gap: 0.9rem;
            align-items: flex-start;
            padding: 1rem;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            min-height: 100%;
        }

        .quick-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.07);
            border-color: rgba(37, 99, 235, 0.18);
            color: inherit;
        }

        .quick-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            flex: 0 0 auto;
        }

        .icon-blue {
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
        }

        .icon-sand {
            background: rgba(126, 57, 0, 0.12);
            color: var(--cafe);
        }

        .quick-item h6 {
            font-size: 0.98rem;
            margin-bottom: 0.25rem;
            color: var(--azul-oscuro);
        }

        .quick-item p {
            margin: 0;
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .clean-table {
            width: 100%;
            border-collapse: collapse;
        }

        .clean-table th,
        .clean-table td {
            padding: 0.85rem 0.95rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            white-space: nowrap;
        }

        .clean-table th {
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #f8fafc;
        }

        .clean-table td {
            font-size: 0.9rem;
            color: #0f172a;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .status-programada,
        .status-confirmada {
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
        }

        .status-completada {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .status-cancelada {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }

        .inventory-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .chip-ok {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .chip-warn {
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
        }

        .chip-danger {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }

        @media (max-width: 991.98px) {
            .quick-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .dashboard-shell {
                padding: 1rem 0 2rem;
            }

            .hero-panel {
                padding: 1.25rem;
                border-radius: 22px;
            }

            .hero-actions {
                flex-direction: column;
            }

            .btn-soft,
            .btn-strong {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $basePath; ?>/index.php">Clínica <span>Xocheco</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'usuarios' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>../admin/usuarios/">Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'especialidades' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>../admin/especialidades/">Especialidades</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'espacios' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>../admin/espacios/">Espacios</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'reportes' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>../admin/reportes/">Reportes</a></li>
                    <li class="nav-item"><span class="nav-link user-greeting"><?php echo htmlspecialchars($adminName); ?></span></li>
                    <li class="nav-item"><a class="nav-link btn-nav-logout" href="<?php echo $basePath; ?>/logout.php">Salir</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="dashboard-shell">
        <div class="container">
            <section class="hero-panel mb-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8 hero-copy">
                        <div class="hero-badge">Portal administrativo</div>
                        <h1 class="hero-title">Bienvenido, <span><?php echo htmlspecialchars($adminName); ?></span></h1>
                        <p class="hero-text">
                            Aquí tienes el tablero principal para controlar usuarios, especialistas, espacios, reportes y la base operativa de la clínica.
                        </p>
                        <div class="hero-actions">
                            <a class="btn-soft" href="#actividad">Revisar actividad</a>
                        </div>
                    </div>
                    <div class="col-lg-4 hero-side">
                        <div class="hero-metric mb-3">
                            <small>Última hora de actividad</small>
                            <strong><?php echo date('d/m/Y | H:i:s'); ?></strong>
                        </div>
                        <div class="hero-metric mb-3">
                            <small>Rol actual</small>
                            <strong><?php echo htmlspecialchars($adminRole); ?></strong>
                        </div>
                        <div class="hero-metric">
                            <small>Estado</small>
                            <strong>Sistema listo para administrar</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                    <div>
                        <h2 class="section-title">Resumen general</h2>
                        <p class="section-subtitle">Vista rápida del movimiento principal del sistema.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($stats as $stat): ?>
                        <div class="col-sm-6 col-xl-4">
                            <div class="stat-card <?php echo $stat['accent'] === 'blue' ? 'accent-blue' : 'accent-sand'; ?>">
                                <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                                <div class="stat-value"><?php echo (int)$stat['value']; ?></div>
                                <div class="stat-note"><?php echo htmlspecialchars($stat['note']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="actividad" class="mb-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h2 class="section-title mb-1">Próximas citas</h2>
                                <p class="section-subtitle mb-0">Agenda reciente para dar seguimiento rápido.</p>
                            </div>
                            <div class="panel-card-body">
                                <div class="table-wrap">
                                    <table class="clean-table">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Paciente</th>
                                                <th>Médico</th>
                                                <th>Servicio</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($nextAppointments)): ?>
                                                <?php foreach ($nextAppointments as $item): ?>
                                                    <?php
                                                    $statusClass = 'status-' . strtolower($item['estado']);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora_inicio'])); ?></td>
                                                        <td><?php echo htmlspecialchars($item['paciente']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['medico']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['servicio']); ?></td>
                                                        <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($item['estado']); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">Todavía no hay citas registradas.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h2 class="section-title mb-1">Inventario</h2>
                                <p class="section-subtitle mb-0">Productos que conviene revisar primero.</p>
                            </div>
                            <div class="panel-card-body">
                                <div class="d-grid gap-3">
                                    <?php if (!empty($inventoryAlerts)): ?>
                                        <?php foreach ($inventoryAlerts as $item): ?>
                                            <?php
                                            $estado = $item['estado'];
                                            $cantidad = (int)$item['cantidad_disponible'];
                                            $chipClass = 'chip-ok';
                                            if (stripos($estado, 'caducar') !== false) {
                                                $chipClass = 'chip-warn';
                                            }
                                            if ($estado === 'Caducado' || $cantidad <= 0) {
                                                $chipClass = 'chip-danger';
                                            }
                                            ?>
                                            <div class="p-3 rounded-4 border bg-white">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <strong class="d-block"><?php echo htmlspecialchars($item['nombre_comercial']); ?></strong>
                                                        <small class="text-muted">Caduca: <?php echo date('d/m/Y', strtotime($item['fecha_caducidad'])); ?></small>
                                                    </div>
                                                    <span class="inventory-chip <?php echo $chipClass; ?>"><?php echo htmlspecialchars($estado); ?></span>
                                                </div>
                                                <div class="mt-2 text-muted small">Existencia: <?php echo $cantidad; ?> unidades</div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">No hay datos de inventario todavía.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
