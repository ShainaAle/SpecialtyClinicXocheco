<?php
$pageTitle = $pageTitle ?? 'Módulo';
$pageSubtitle = $pageSubtitle ?? '';
$activeModule = $activeModule ?? '';
$basePath = $basePath ?? '..';
$adminName = $_SESSION['nombre_completo'] ?? 'Administrador';
$adminRole = $_SESSION['nombre_rol'] ?? 'Administrador';
$currentDateTime = date('d/m/Y | H:i:s');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> · Clínica Xocheco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>/src/Images/Icon.png">
    <link href="<?php echo $basePath; ?>/src/styles/admin.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a href="<?php echo $basePath; ?>/index.php" class="logo me-2">
                <img src="<?php echo $basePath; ?>/src/Images/Icon.png" alt="Logo" style="height: 40px;">
            </a>
            <a class="navbar-brand" href="<?php echo $basePath; ?>/index.php">Clínica <span>Xocheco</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/dashboard.php">Dashboard</a></li>
                    <?php $userNavActive = in_array($activeModule, ['usuarios', 'pacientes', 'medicos'], true); ?>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle <?php echo $userNavActive ? 'active' : ''; ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Usuarios
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark admin-dropdown">
                            <li><a class="dropdown-item <?php echo $activeModule === 'usuarios' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/usuarios/">General</a></li>
                            <li><a class="dropdown-item <?php echo $activeModule === 'pacientes' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/pacientes/">Pacientes</a></li>
                            <li><a class="dropdown-item <?php echo $activeModule === 'medicos' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/medicos/">Médicos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'medicos_disponibilidad' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/medicos/disponibilidad.php">Disponibilidad</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'citas' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/citas/">Citas</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'inventario' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/inventario/">Inventario</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'especialidades' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/especialidades/">Especialidades</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'espacios' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/espacios/">Espacios</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'reportes' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/reportes/">Reportes</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $activeModule === 'bitacora' ? 'active' : ''; ?>" href="<?php echo $basePath; ?>/admin/bitacora/">Bitácora</a></li>
                    <li class="nav-item"><span class="nav-link user-greeting"><?php echo htmlspecialchars($adminName); ?></span></li>
                    <li class="nav-item"><a class="nav-link btn-nav-logout" href="<?php echo $basePath; ?>/logout.php">Salir</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <div class="container">
            <section class="module-hero mb-4">
                <div class="row align-items-end g-4">
                    <div class="col-lg-8 hero-copy">
                        <div class="hero-badge">Portal administrativo</div>
                        <h1 class="hero-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="hero-text"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                    </div>
                    <div class="col-lg-4 hero-side">
                        <div class="hero-meta mb-3">
                            <small>Acceso</small>
                            <strong><?php echo htmlspecialchars($adminRole); ?></strong>
                        </div>
                        <div class="hero-meta">
                            <small>Fecha y hora</small>
                            <strong><?php echo $currentDateTime; ?></strong>
                        </div>
                    </div>
                </div>
            </section>
