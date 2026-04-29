<?php
session_start();

if (!isset($_SESSION['rol'])) {
    header("Location: signin.php");
    exit;
}

switch ($_SESSION['rol']) {

    case 'admin':
        header("Location: admin/dashboard.php");
        break;

    case 'medico':
        header("Location: medico/dashboard.php");
        break;

    case 'recepcion':
        header("Location: recepcion/dashboard.php");
        break;

    case 'farmaceutico':
        header("Location: farmacia/dashboard.php");
        break;

    case 'paciente':
        header("Location: paciente/dashboard.php");
        break;
}
exit;
