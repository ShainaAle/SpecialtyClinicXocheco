<?php
require_once '../../src/auth.php';
requireRol(['admin']);
require_once '../../src/conexion/conexion.php';

$basePath = '../..';
$pageTitle = 'Citas';
$pageSubtitle = 'Programar, confirmar, reprogramar o cancelar citas.';
$activeModule = 'citas';
$portalNav = [];

include '../../src/admin/header.php';
include '../../src/citas/manager.php';
include '../../src/admin/footer.php';
