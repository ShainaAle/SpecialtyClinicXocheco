<?php
$portalLabel = 'Recepción';
$portalRole = 'Recepción';
$portalNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'recepcion/dashboard.php'],
    [
        'type' => 'dropdown',
        'key' => 'usuarios',
        'label' => 'Usuarios',
        'children' => [
            ['key' => 'usuarios', 'label' => 'General', 'href' => 'recepcion/usuarios/'],
            ['key' => 'pacientes', 'label' => 'Pacientes', 'href' => 'recepcion/pacientes/'],
            ['key' => 'medicos', 'label' => 'Médicos', 'href' => 'recepcion/medicos/'],
        ],
    ],
    ['key' => 'citas', 'label' => 'Citas', 'href' => 'recepcion/citas/'],
    ['key' => 'disponibilidad', 'label' => 'Disponibilidad', 'href' => 'recepcion/disponibilidad/'],
    ['key' => 'espacios', 'label' => 'Espacios', 'href' => 'recepcion/espacios/'],
    ['key' => 'reportes', 'label' => 'Reportes', 'href' => 'recepcion/reportes/'],
];
