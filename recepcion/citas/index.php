<?php
require_once '../../src/auth.php';
requireRol(['recepcion']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Citas';
$pageSubtitle = 'Programar, confirmar, reprogramar o cancelar citas.';
$activeModule = 'citas';
$portalLabel = 'Recepción';
$portalRole = 'Recepción';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '../dashboard.php'],
    ['key' => 'citas', 'label' => 'Citas', 'href' => 'index.php'],
];

include '../../src/portal/header.php';
include '../../src/citas/manager.php';
include '../../src/admin/footer.php';
