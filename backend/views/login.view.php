<?php
/**
* @var string|null $error
*/ ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Вхід · ІССКОН Луцьк</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/ico/favicon-16x16.png">
    <meta name="theme-color" content="#ffffff">
    <style>
        body { background: #f5f6f8; }
        .login-card { max-width: 380px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
<div class="login-card container px-3">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                <i class="bi bi-broadcast-pin text-primary fs-2"></i>
                <h1 class="h5 mt-2 mb-0">ІССКОН Луцьк</h1>
                <div class="text-muted small">Вхід для адміністратора</div>
            </div>
            <?php if (!empty($error)):?>
                <div class="alert alert-danger py-2" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?=htmlspecialchars($error)?>
                </div>
            <?php endif;?>
            <form method="post" action="/login" autocomplete="off">
                <div class="mb-3">
                    <label for="login" class="form-label">Логін</label>
                    <input id="login" name="login" type="text" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Пароль</label>
                    <input id="password" name="password" type="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Увійти
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="/" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left"></i> Назад до розкладу
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>