<?php
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === PORTAL_PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Ungültiges Passwort. Bitte überprüfen Sie Ihre Zugangsdaten.';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joschua Agency Portal | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950">
    <div class="w-full max-w-md bg-slate-900/90 border border-slate-800 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 mb-4 shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Joschua Web Agency</h1>
            <p class="text-xs uppercase tracking-wider text-indigo-400 font-semibold mt-1">Lead Intelligence & CRO Audit</p>
            <p class="text-sm text-slate-400 mt-2">Geschützter Mandanten-Zugang (Sanitär Zürich)</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="password" class="block text-xs font-medium uppercase tracking-wider text-slate-400 mb-2">Agentur-Passwort</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required autofocus
                           placeholder="••••••••••••"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3.5 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-semibold rounded-xl shadow-lg shadow-indigo-600/30 transition-all transform active:scale-[0.98]">
                Portal öffnen →
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
            <p class="text-xs text-slate-500">
                Entwickelt für Joschua R. • Powered by Lexmation AI
            </p>
        </div>
    </div>
</body>
</html>
