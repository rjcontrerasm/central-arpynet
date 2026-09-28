<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta name="color-scheme" content="light dark">
    <title>Ingresar · Central ARPYNET</title>

    <link
        rel="stylesheet"
        href="{{ asset('central-assets/pages/operational-login.css') }}?v={{ filemtime(public_path('central-assets/pages/operational-login.css')) }}"
    >
</head>

<body>
<main class="login-shell">
    <section class="login-card">
        <div class="brand">Central ARPYNET</div>

        <h1>Ingrese a su cuenta</h1>

        <p class="hint">
            Acceso operativo para Mi Día y trabajo en equipo.
        </p>

        <form
            method="POST"
            action="{{ route('login.store') }}"
        >
            @csrf

            <label>
                Correo electrónico

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                >
            </label>

            @error('email')
                <div class="error">{{ $message }}</div>
            @enderror

            <label>
                Contraseña

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror

            <label class="remember">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                >

                Recordarme
            </label>

            <button type="submit">
                Entrar
            </button>
        </form>

        <div class="admin-link">
            <a href="{{ url('/admin/login') }}">
                Acceso administrativo
            </a>
        </div>
    </section>
</main>
</body>
</html>
