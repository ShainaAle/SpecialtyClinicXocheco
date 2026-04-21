<!--
-- ==============================================================================
-- INDEX PHP PAGE: Clinica Xocheco
-- Purpose: Main webpage of the project
-- Engine: PHP/HTML/CSS/JS/BOOTSTRAP
-- Author: Julián Pacheco
-- Version: 1.0
-- Date: 2026-04-21
-- ==============================================================================
-->

<?php
session_start();
include("src/conexion/conexion.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Xocheco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="src/styles/styleIndex.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="src/Images/Icon.png">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--blanco);
            color: var(--texto-oscuro);
        }

        /* ── NAVBAR ── */
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

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 60%, #1e4d8c 100%);
            min-height: 85vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero::after {
            content: '';
            position: absolute;
            right: -100px;
            top: -100px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.3) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(37, 99, 235, 0.25);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #93c5fd;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(4px);
        }

        .hero-badge::before {
            content: '+';
            font-size: 1rem;
            font-weight: 700;
            color: #60a5fa;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            color: var(--blanco);
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }

        .hero h1 span {
            color: #60a5fa;
        }

        .hero p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 520px;
            margin-bottom: 2rem;
        }

        .btn-hero-primary {
            background: var(--cafe);
            color: var(--blanco);
            padding: 0.85rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.25s;
            border: 2px solid var(--cafe);
        }

        .btn-hero-primary:hover {
            background: var(--cafe-hover);
            border-color: var(--cafe-hover);
            color: var(--blanco);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(126, 57, 0, 0.35);
        }

        .btn-hero-secondary {
            background: transparent;
            color: var(--blanco);
            padding: 0.85rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
            border: 2px solid rgba(255, 255, 255, 0.35);
            transition: all 0.25s;
        }

        .btn-hero-secondary:hover {
            border-color: rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.08);
            color: var(--blanco);
        }

        /* Stats en hero */
        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            text-align: left;
        }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #60a5fa;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.55);
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Imagen hero */
        .hero-image-wrap {
            position: relative;
            z-index: 2;
        }

        .hero-img-card {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(12px);
            text-align: center;
        }

        .hero-icon-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .hero-icon-item {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 1.2rem 0.8rem;
            text-align: center;
            transition: all 0.3s;
        }

        .hero-icon-item:hover {
            background: rgba(37, 99, 235, 0.3);
            border-color: rgba(96, 165, 250, 0.4);
            transform: translateY(-3px);
        }

        .hero-icon-item .icon {
            font-size: 1.8rem;
            margin-bottom: 0.4rem;
        }

        .hero-icon-item .label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.65);
            font-weight: 500;
        }

        /* ── SERVICIOS ── */
        .servicios {
            padding: 5rem 0;
            background: var(--gris-suave);
        }

        .section-tag {
            display: inline-block;
            background: var(--azul-claro);
            color: var(--azul-acento);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 100px;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--azul-oscuro);
            margin-bottom: 0.75rem;
        }

        .section-sub {
            color: #64748b;
            font-size: 0.95rem;
            max-width: 500px;
        }

        .servicio-card {
            background: var(--blanco);
            border-radius: 16px;
            padding: 2rem 1.75rem;
            height: 100%;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .servicio-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--azul-acento), #60a5fa);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s;
        }

        .servicio-card:hover {
            box-shadow: 0 12px 40px rgba(10, 31, 68, 0.1);
            transform: translateY(-4px);
            border-color: #bfdbfe;
        }

        .servicio-card:hover::before {
            transform: scaleX(1);
        }

        .servicio-icon {
            width: 52px;
            height: 52px;
            background: var(--azul-claro);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .servicio-card h5 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--azul-oscuro);
            margin-bottom: 0.6rem;
        }

        .servicio-card p {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.65;
        }

        /* ── ABOUT ── */
        .about {
            padding: 5rem 0;
            background: var(--blanco);
        }

        .about-img-wrap {
            background: linear-gradient(135deg, var(--azul-oscuro), var(--azul-medio));
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            color: var(--blanco);
        }

        .about-img-wrap .big-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }

        .about-feature {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .about-feature-icon {
            width: 40px;
            height: 40px;
            background: var(--azul-claro);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .about-feature-text h6 {
            font-weight: 600;
            color: var(--azul-oscuro);
            margin-bottom: 2px;
            font-size: 0.9rem;
        }

        .about-feature-text p {
            color: #64748b;
            font-size: 0.82rem;
            margin: 0;
        }

        /* ── FOOTER ── */
        footer {
            background: var(--azul-oscuro);
            color: rgba(255, 255, 255, 0.7);
            padding: 3rem 0 1.5rem;
        }

        footer h5,
        footer h6 {
            color: var(--blanco);
            font-family: 'Playfair Display', serif;
        }

        footer a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s;
        }

        footer a:hover {
            color: #60a5fa;
        }

        footer hr {
            border-color: rgba(255, 255, 255, 0.1);
        }

        footer .small {
            font-size: 0.8rem;
        }

        /* CARRUSEL */

        .carousel-img {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .carousel-item {
            position: relative;
        }

        .carousel-item::before,
        .carousel-item::after {
            content: "";
            position: absolute;
            top: 0;
            width: 15%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .carousel-item::before {
            left: 0;
            background: linear-gradient(to right, white, transparent);
        }

        .carousel-item::after {
            right: 0;
            background: linear-gradient(to left, white, transparent);
        }

        .carousel-item:hover::before,
        .carousel-item:hover::after {
            opacity: 0.8;
        }

        .custom-bars button {
            width: 40px;
            height: 5px;
            border: none;
            background-color: rgba(255, 255, 255, 0.5);
        }

        .custom-bars .active {
            background-color: white;
        }

        .custom-arrows {
            position: relative;
            margin-top: -200px;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            pointer-events: none;
        }

        .carousel-wrapper {
            position: relative;
        }

        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            font-size: 22px;
            cursor: pointer;
            z-index: 10;
        }

        .arrow.left {
            left: 15px;
        }

        .arrow.right {
            right: 15px;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <a href="index.php" class="logo">
                <img src="src/Images/Icon.png" alt="Logo" style="height: 40px;">
            </a>
            <a class="navbar-brand" href="index.php">Clínica <span>Xocheco</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-1">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="servicios.php">Servicios</a></li>
                    <li class="nav-item"><a class="nav-link" href="medicos.php">Médicos</a></li>
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        <li class="nav-item"><a class="nav-link" href="citas.php">Mis Citas</a></li>
                        <?php if (in_array($_SESSION['rol'], ['admin', 'recepcion'])): ?>
                            <li class="nav-item"><a class="nav-link" href="agenda.php">Agenda</a></li>
                            <li class="nav-item"><a class="nav-link" href="pacientes.php">Pacientes</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">Administración</a></li>
                        <?php endif; ?>
                        <li class="nav-item ms-2">
                            <span class="user-greeting">
                                <?= htmlspecialchars(explode(' ', $_SESSION['nombre_completo'])[0]) ?>
                            </span>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="nav-link btn-nav-logout" href="logout.php">Salir</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-3">
                            <a class="nav-link btn-nav-login" href="signin.php">Iniciar sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 hero-content">
                    <div class="hero-badge">Clínica de Especialidades · Querétaro</div>
                    <h1>Tu salud en <span>manos expertas</span></h1>
                    <p>Atención médica especializada con tecnología de vanguardia. Agenda tus citas, consulta tu historial y mantente en contacto con tu médico desde cualquier lugar.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php if (isset($_SESSION['id_usuario'])): ?>
                            <a href="citas.php" class="btn-hero-primary">Ver mis citas</a>
                            <a href="agendar.php" class="btn-hero-secondary">Agendar cita</a>
                        <?php else: ?>
                            <a href="signin.php" class="btn-hero-primary">Acceder al sistema</a>
                            <a href="#servicios" class="btn-hero-secondary">Ver servicios</a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-num">12+</div>
                            <div class="stat-label">Especialidades</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num">24/7</div>
                            <div class="stat-label">Atención urgente</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num">100%</div>
                            <div class="stat-label">Digital</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 hero-image-wrap d-none d-lg-block">
                    <div class="hero-img-card">
                        <div class="hero-icon-grid">
                            <div class="hero-icon-item">
                                <div class="icon">🩺</div>
                                <div class="label">Consultas</div>
                            </div>
                            <div class="hero-icon-item">
                                <div class="icon">📅</div>
                                <div class="label">Agenda</div>
                            </div>
                            <div class="hero-icon-item">
                                <div class="icon">💊</div>
                                <div class="label">Farmacia</div>
                            </div>
                            <div class="hero-icon-item">
                                <div class="icon">🔬</div>
                                <div class="label">Laboratorio</div>
                            </div>
                            <div class="hero-icon-item">
                                <div class="icon">📋</div>
                                <div class="label">Expediente</div>
                            </div>
                            <div class="hero-icon-item">
                                <div class="icon">💳</div>
                                <div class="label">Pagos</div>
                            </div>
                        </div>
                        <p style="color:rgba(255,255,255,0.5); font-size:0.78rem;">Sistema integral de gestión clínica</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!--CARRUSEL-->
    <div class="carousel-wrapper">
        <div id="carouselClinica" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
            <div class="carousel-indicators custom-bars">
                <button type="button" data-bs-target="#carouselClinica" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#carouselClinica" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#carouselClinica" data-bs-slide-to="2"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="src/Images/img1.avif" class="d-block w-100 carousel-img">
                </div>
                <div class="carousel-item">
                    <img src="src/Images/img2.png" class="d-block w-100 carousel-img">
                </div>
                <div class="carousel-item">
                    <img src="src/Images/img3.jpg" class="d-block w-100 carousel-img">
                </div>
            </div>
        </div>
        <!-- Flechas -->
        <button class="arrow left" id="prevBtn">❮</button>
        <button class="arrow right" id="nextBtn">❯</button>
    </div>

    <!-- SERVICIOS -->
    <section class="servicios" id="servicios">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-6">
                    <span class="section-tag">Nuestros servicios</span>
                    <h2 class="section-title">Atención médica integral</h2>
                    <p class="section-sub">Contamos con especialistas en múltiples áreas para brindarte la mejor atención.</p>
                </div>
            </div>
            <div class="row g-4">
                <?php
                $servicios = [
                    ['🫀', 'Cardiología', 'Diagnóstico y tratamiento de enfermedades del corazón y sistema cardiovascular.'],
                    ['🧠', 'Neurología', 'Atención especializada en trastornos del sistema nervioso central y periférico.'],
                    ['🦴', 'Traumatología', 'Tratamiento de lesiones y enfermedades del sistema musculoesquelético.'],
                    ['👁️', 'Oftalmología', 'Cuidado integral de la salud visual con tecnología de última generación.'],
                    ['🫁', 'Neumología', 'Diagnóstico y manejo de enfermedades respiratorias y pulmonares.'],
                    ['🩸', 'Laboratorio', 'Análisis clínicos completos con resultados en línea y en tiempo real.'],
                ];
                foreach ($servicios as $s): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="servicio-card">
                            <div class="servicio-icon"><?= $s[0] ?></div>
                            <h5><?= $s[1] ?></h5>
                            <p><?= $s[2] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section class="about">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <div class="about-img-wrap">
                        <div class="big-icon">🏥</div>
                        <h4 style="font-family:'Playfair Display',serif; margin-bottom:0.5rem;">Clínica Xocheco</h4>
                        <p style="font-size:0.875rem; opacity:0.7;">Centro de especialidades médicas en Querétaro</p>
                    </div>
                </div>
                <div class="col-lg-6 offset-lg-1">
                    <span class="section-tag">Sobre nosotros</span>
                    <h2 class="section-title">Comprometidos con tu bienestar</h2>
                    <p style="color:#64748b; margin-bottom:2rem; font-size:0.95rem; line-height:1.7;">
                        En Clínica Xocheco combinamos experiencia médica de alto nivel con un sistema digital eficiente que te permite gestionar toda tu atención médica desde un solo lugar.
                    </p>
                    <?php
                    $features = [
                        ['💻', 'Expediente digital', 'Accede a tu historial clínico en cualquier momento y lugar.'],
                        ['🔔', 'Recordatorios automáticos', 'Recibe alertas de tus citas por correo o SMS.'],
                        ['🔒', 'Datos seguros', 'Tu información médica protegida con los más altos estándares.'],
                    ];
                    foreach ($features as $f): ?>
                        <div class="about-feature">
                            <div class="about-feature-icon"><?= $f[0] ?></div>
                            <div class="about-feature-text">
                                <h6><?= $f[1] ?></h6>
                                <p><?= $f[2] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-2">Clínica Xocheco</h5>
                    <p class="small" style="color:rgba(255,255,255,0.55); line-height:1.7;">
                        Centro de especialidades médicas comprometido con la salud y bienestar de nuestra comunidad.
                    </p>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h6 class="mb-3">Accesos</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php">Inicio</a></li>
                        <li class="mb-2"><a href="servicios.php">Servicios</a></li>
                        <li class="mb-2"><a href="medicos.php">Médicos</a></li>
                        <li class="mb-2"><a href="signin.php">Portal del paciente</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="mb-3">Contacto</h6>
                    <p class="small mb-1">📍 Av. Tecnológico 1, Querétaro</p>
                    <p class="small mb-1">📞 +52 442 123 4567</p>
                    <p class="small">✉️ contacto@xocheco.com</p>
                </div>
            </div>
            <hr>
            <p class="small text-center mb-0" style="color:rgba(255,255,255,0.4);">
                &copy; 2026 Clínica Xocheco · Todos los derechos reservados
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const carousel = document.querySelector('#carouselClinica');
        const bsCarousel = new bootstrap.Carousel(carousel);
        document.getElementById('prevBtn').onclick = () => bsCarousel.prev();
        document.getElementById('nextBtn').onclick = () => bsCarousel.next();
    </script>
</body>
</html>