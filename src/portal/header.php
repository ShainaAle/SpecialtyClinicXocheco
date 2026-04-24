<?php
$pageTitle = $pageTitle ?? 'Portal';
$pageSubtitle = $pageSubtitle ?? '';
$activeModule = $activeModule ?? '';
$basePath = $basePath ?? '..';
$portalNav = $portalNav ?? [];
$portalLabel = $portalLabel ?? 'Portal';
$portalRole = $portalRole ?? ($_SESSION['nombre_rol'] ?? 'Usuario');
$portalUser = $_SESSION['nombre_completo'] ?? 'Usuario';
$currentDateTime = date('d/m/Y | H:i:s');

function portal_nav_href(string $basePath, string $href): string
{
    if ($href === '' || $href[0] === '#' || str_starts_with($href, 'http')) {
        return $href;
    }
    return rtrim($basePath, '/') . '/' . ltrim($href, '/');
}
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#portalNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="portalNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($portalNav as $item): ?>
                        <?php $href = portal_nav_href($basePath, $item['href'] ?? '#'); ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeModule === ($item['key'] ?? '') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href); ?>">
                                <?php echo htmlspecialchars($item['label'] ?? 'Link'); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <span class="nav-link user-greeting"><?php echo htmlspecialchars($portalUser); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-nav-logout" href="<?php echo $basePath; ?>/logout.php">Salir</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <div class="container">
            <section class="module-hero mb-4">
                <div class="row align-items-end g-4">
                    <div class="col-lg-8 hero-copy">
                        <div class="hero-badge"><?php echo htmlspecialchars($portalLabel); ?></div>
                        <h1 class="hero-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="hero-text"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                    </div>
                    <div class="col-lg-4 hero-side">
                        <div class="hero-meta mb-3">
                            <small>Rol</small>
                            <strong><?php echo htmlspecialchars($portalRole); ?></strong>
                        </div>
                        <div class="hero-meta">
                            <small>Fecha y hora</small>
                            <strong><?php echo $currentDateTime; ?></strong>
                        </div>
                    </div>
                </div>
            </section>
