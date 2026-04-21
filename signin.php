<?php
session_start();
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Clínica Xocheco</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="src/Images/Icon.png">
    <style>
        :root {
            --azul-oscuro:  #0a1f44;
            --azul-medio:   #1a3a6e;
            --azul-acento:  #2563eb;
            --azul-claro:   #dbeafe;
            --cafe:         #7e3900;
            --cafe-hover:   #ca610a;
            --blanco:       #ffffff;
            --gris-suave:   #f1f5f9;
            --error:        #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gris-suave);
            display: flex;
            flex-direction: column;
        }

        /* ── NAVBAR mínima ── */
        .top-bar {
            background: var(--azul-oscuro);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .top-bar .brand {
            font-family: 'Playfair Display', serif;
            color: var(--blanco);
            font-size: 1.2rem;
            text-decoration: none;
        }
        .top-bar .brand span { color: #60a5fa; }
        .top-bar .back-link {
            color: rgba(255,255,255,0.6);
            font-size: 0.82rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .top-bar .back-link:hover { color: #60a5fa; }

        /* ── LAYOUT ── */
        .login-wrapper {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: calc(100vh - 62px);
        }

        /* Panel izquierdo decorativo */
        .login-panel-left {
            background: linear-gradient(150deg, var(--azul-oscuro) 0%, var(--azul-medio) 50%, #1e4d8c 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 4rem 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .login-panel-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .login-panel-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(37,99,235,0.4) 0%, transparent 65%);
            border-radius: 50%;
        }

        .panel-left-content { position: relative; z-index: 2; }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #93c5fd;
            padding: 5px 14px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 2rem;
        }

        .panel-left-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--blanco);
            line-height: 1.2;
            margin-bottom: 1rem;
        }
        .panel-left-content h2 span { color: #60a5fa; }
        .panel-left-content p {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
            line-height: 1.7;
            max-width: 360px;
            margin-bottom: 2.5rem;
        }

        /* Roles disponibles */
        .roles-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .rol-item {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .rol-icon { font-size: 1.3rem; }
        .rol-name {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
        }

        /* Panel derecho (formulario) */
        .login-panel-right {
            background: var(--blanco);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        .login-form-container {
            width: 100%;
            max-width: 400px;
        }

        .form-header { margin-bottom: 2.25rem; }
        .form-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.65rem;
            color: var(--azul-oscuro);
            margin-bottom: 0.4rem;
        }
        .form-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Campo del formulario */
        .field-group { margin-bottom: 1.25rem; }
        .field-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--azul-oscuro);
            margin-bottom: 0.4rem;
            letter-spacing: 0.2px;
        }
        .field-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--azul-oscuro);
            background: var(--gris-suave);
            transition: all 0.2s;
            outline: none;
        }
        .field-input:focus {
            border-color: var(--azul-acento);
            background: var(--blanco);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .field-input::placeholder { color: #94a3b8; }

        /* Password toggle */
        .password-wrap { position: relative; }
        .password-wrap .field-input { padding-right: 2.75rem; }
        .toggle-pass {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            padding: 0;
            transition: color 0.2s;
        }
        .toggle-pass:hover { color: var(--azul-acento); }

        /* Alert de error */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-login {
            width: 100%;
            background: var(--cafe);
            color: var(--blanco);
            border: none;
            border-radius: 10px;
            padding: 0.85rem;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 0.5rem;
        }
        .btn-login:hover {
            background: var(--cafe-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(126,57,0,0.3);
        }
        .btn-login:active { transform: translateY(0); }

        .form-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .form-footer a {
            color: var(--azul-acento);
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.2s;
        }
        .form-footer a:hover { color: #1d4ed8; text-decoration: underline; }
        .form-footer p { color: #94a3b8; font-size: 0.8rem; margin-top: 0.5rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-panel-left { display: none; }
            .login-panel-right { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
    <a href="index.php" class="logo">
        <img src="src/Images/Icon.png" alt="Logo" style="height: 40px;">
    </a>
    <a class="brand" href="index.php">Clínica <span>Xocheco</span></a>
    <a class="back-link" href="index.php">← Volver al inicio</a>
</div>

<!-- LAYOUT -->
<div class="login-wrapper">

    <!-- Panel izquierdo -->
    <div class="login-panel-left">
        <div class="panel-left-content">
            <div class="panel-badge">🏥 Sistema de Gestión Clínica</div>
            <h2>Bienvenido a tu <span>portal médico</span></h2>
            <p>Accede para gestionar citas, consultar tu expediente, revisar recetas y más. Todo en un solo lugar.</p>
            <div class="roles-grid">
                <div class="rol-item"><span class="rol-icon">👨‍⚕️</span><span class="rol-name">Médico</span></div>
                <div class="rol-item"><span class="rol-icon">🧑‍💻</span><span class="rol-name">Administrador</span></div>
                <div class="rol-item"><span class="rol-icon">🧾</span><span class="rol-name">Recepción</span></div>
                <div class="rol-item"><span class="rol-icon">🧑‍🦯</span><span class="rol-name">Paciente</span></div>
            </div>
        </div>
    </div>

    <!-- Panel derecho (formulario) -->
    <div class="login-panel-right">
        <div class="login-form-container">

            <div class="form-header">
                <h3>Iniciar sesión</h3>
                <p>Ingresa tus credenciales para continuar</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert-error">
                ⚠️ Correo o contraseña incorrectos. Intenta de nuevo.
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['inactivo'])): ?>
            <div class="alert-error">
                🔒 Tu cuenta está inactiva. Contacta a recepción.
            </div>
            <?php endif; ?>

            <form action="logIn.php" method="POST" novalidate>

                <div class="field-group">
                    <label for="email">Correo electrónico</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="field-input"
                        placeholder="correo@ejemplo.com"
                        required
                        autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>

                <div class="field-group">
                    <label for="password">Contraseña</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="field-input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-pass" onclick="togglePassword()" title="Mostrar/ocultar contraseña">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <a href="recovery.php" style="font-size:0.82rem; color:#2563eb; text-decoration:none;">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button type="submit" class="btn-login">Entrar al sistema</button>
            </form>

            <div class="form-footer">
                <a href="index.php">← Volver al inicio</a>
                <p>¿Eres paciente nuevo? Regístrate en recepción.</p>
            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        const input = document.getElementById('password');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>
</body>
</html>