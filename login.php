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
    if ($email === 'admin@rentify.com' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'System Administrator';
        $_SESSION['user_role'] = 'admin';
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Authentication Failed: Invalid access credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Suite - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/premium.css">
    <style>
        body {
            background-color: #050505;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(168, 85, 247, 0.05) 0%, transparent 50%);
        }
        
        .login-glass {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        .login-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .login-input:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full animate-fadeIn">
        <!-- Logo Branding -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-primary-600 to-indigo-700 shadow-glow mb-6 animate-slideIn">
                <i class="fas fa-cube text-white text-3xl"></i>
            </div>
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter mb-2">Rentify <span class="text-primary-400">OS</span></h1>
            <p class="text-muted text-xs font-bold uppercase tracking-[0.3em]">Access Management Protocol</p>
        </div>
        
        <!-- Auth Card -->
        <div class="login-glass rounded-[2rem] p-10 relative overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-purple-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <?php if ($error): ?>
                    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 flex items-center text-xs font-bold animate-shake">
                        <i class="fas fa-shield-exclamation mr-3"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-muted uppercase tracking-widest ml-1">Universal Identity</label>
                            <div class="relative group">
                                <i class="fas fa-at absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xs group-focus-within:text-primary-400 transition-colors"></i>
                                <input name="email" type="email" required 
                                       class="w-full h-14 pl-11 pr-4 login-input rounded-2xl outline-none text-sm font-medium" 
                                       placeholder="identity@rentify.io">
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-muted uppercase tracking-widest ml-1">Access Cipher</label>
                            <div class="relative group">
                                <i class="fas fa-lock-hashtag absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xs group-focus-within:text-primary-400 transition-colors"></i>
                                <input name="password" type="password" required 
                                       class="w-full h-14 pl-11 pr-4 login-input rounded-2xl outline-none text-sm font-medium" 
                                       placeholder="••••••••••••">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between pb-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" class="hidden peer">
                            <div class="w-4 h-4 rounded border border-white/10 bg-white/5 peer-checked:bg-primary-500 peer-checked:border-primary-500 transition-all flex items-center justify-center">
                                <i class="fas fa-check text-[8px] text-white opacity-0 peer-checked:opacity-100"></i>
                            </div>
                            <span class="text-[10px] font-bold text-muted uppercase tracking-widest group-hover:text-white transition-colors">Preserve Session</span>
                        </label>
                        <a href="#" class="text-[10px] font-bold text-primary-400 uppercase tracking-widest hover:text-white transition-colors">Reset Cipher</a>
                    </div>
                    
                    <button type="submit" 
                            class="premium-btn premium-btn-primary w-full h-14 justify-center text-sm group">
                        Sign In <i class="fas fa-arrow-right-long ml-3 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Footing -->
        <div class="mt-8 text-center space-y-4 animate-slideIn" style="animation-delay: 0.2s">
            <div class="flex items-center justify-center gap-2 text-[9px] font-bold text-muted uppercase tracking-widest">
                <i class="fas fa-key text-primary-500/50"></i>
                Simulation Override: 
                <span class="text-white">admin@rentify.com</span>
                <span class="mx-1">•</span>
                <span class="text-white">admin123</span>
            </div>
            <p class="text-[9px] text-muted/30 font-medium italic">© 2026 Rentify Neural Systems. Restricted Access Module.</p>
        </div>
    </div>
</body>
</html>
