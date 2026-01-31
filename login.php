<?php
include 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    
    // For demo purposes, using hardcoded admin credentials
    // In production, you would verify against database
    if ($email === 'admin@rentify.com' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['user_role'] = 'admin';
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rentify Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-8">
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-white rounded-xl flex items-center justify-center mb-4">
                    <i class="fas fa-cube text-indigo-600 text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-white">Sign in to Rentify</h2>
                <p class="mt-2 text-white/80">Access your rental management dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-100 px-4 py-3 rounded-lg">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" method="POST">
                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-white mb-2">Email address</label>
                        <input id="email" name="email" type="email" required
                               class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/50"
                               placeholder="admin@rentify.com">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">Password</label>
                        <input id="password" name="password" type="password" required
                               class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/50"
                               placeholder="••••••••">
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-white/80">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-white/80 hover:text-white">Forgot password?</a>
                </div>
                
                <button type="submit" 
                        class="w-full bg-white text-indigo-600 py-3 px-4 rounded-lg font-semibold hover:bg-gray-100 transition transform hover:scale-105">
                    Sign in
                </button>
            </form>
            
            <div class="text-center">
                <p class="text-white/60 text-sm">
                    Demo Credentials: admin@rentify.com / admin123
                </p>
            </div>
        </div>
    </div>
</body>
</html>
