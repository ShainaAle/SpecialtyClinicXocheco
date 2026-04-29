<?php
require_once '../../src/auth.php';
requireRol(['recepcion']);
require_once '../../src/conexion/conexion.php';
require_once '../../src/recepcion/context.php';

$basePath = '../..';
$pageTitle = 'Citas';
$pageSubtitle = 'Programar, confirmar, reprogramar o cancelar citas.';
$activeModule = 'citas';

include '../../src/portal/header.php';
include '../../src/citas/manager.php';
include '../../src/admin/footer.php';
