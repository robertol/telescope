<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Login{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />
    {{ Laravel\Telescope\Telescope::css() }}
    <style>
        .telescope-login {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .telescope-login .card {
            width: 100%;
            max-width: 28rem;
        }
        .telescope-login .error {
            color: #ef4444;
            min-height: 1.25rem;
        }
    </style>
</head>
<body class="telescope-login">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ config('app.name') ?: 'Telescope' }}</h4>
            <small class="text-muted">{{ app()->environment() }}</small>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Sign in with a Telescope dashboard account. This is not an application user.</p>
            <form id="telescope-login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" class="form-control" required>
                </div>
                <p id="telescope-login-error" class="error mb-3"></p>
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>
            <p class="text-muted small mt-3 mb-0">
                Default account: {{ config('telescope.auth.default.email', 'telescope@local') }}
            </p>
        </div>
    </div>
    <script>
        (function () {
            var form = document.getElementById('telescope-login-form');
            var error = document.getElementById('telescope-login-error');
            var path = @json('/'.trim((string) config('telescope.path'), '/'));

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                error.textContent = '';

                fetch(path + '/telescope-api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: form.email.value,
                        password: form.password.value,
                    }),
                }).then(function (response) {
                    if (response.ok) {
                        window.location = path || '/';
                        return;
                    }

                    return response.json().then(function (payload) {
                        error.textContent = payload.message || 'These credentials do not match our records.';
                    }).catch(function () {
                        error.textContent = 'These credentials do not match our records.';
                    });
                }).catch(function () {
                    error.textContent = 'Unable to sign in.';
                });
            });
        })();
    </script>
</body>
</html>
