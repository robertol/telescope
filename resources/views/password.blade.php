<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Change password{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>
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
            <h4 class="mb-0">Change password</h4>
            <small class="text-muted">{{ app()->environment() }}</small>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Choose a new password before using the Telescope dashboard.</p>
            <form id="telescope-password-form">
                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" required>
                </div>
                <p id="telescope-password-error" class="error mb-3"></p>
                <button type="submit" class="btn btn-primary btn-block">Save password</button>
            </form>
        </div>
    </div>
    <script>
        (function () {
            var form = document.getElementById('telescope-password-form');
            var error = document.getElementById('telescope-password-error');
            var path = @json('/'.trim((string) config('telescope.path'), '/'));

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                error.textContent = '';

                fetch(path + '/telescope-api/password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        current_password: form.current_password.value,
                        password: form.password.value,
                        password_confirmation: form.password_confirmation.value,
                    }),
                }).then(function (response) {
                    if (response.ok) {
                        window.location = path || '/';
                        return;
                    }

                    return response.json().then(function (payload) {
                        var messages = payload.errors ? Object.values(payload.errors).flat() : [];
                        error.textContent = messages[0] || payload.message || 'Unable to change the password.';
                    }).catch(function () {
                        error.textContent = 'Unable to change the password.';
                    });
                }).catch(function () {
                    error.textContent = 'Unable to change the password.';
                });
            });
        })();
    </script>
</body>
</html>
