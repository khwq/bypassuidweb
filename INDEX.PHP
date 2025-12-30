<?php


session_start();
require_once __DIR__ . "/config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);


$message = "";
$statusResult = "";
$uid = "";
$checkUid = "";

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ADD / UPDATE UID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "<div class='alert alert-error'>❌ Security token mismatch</div>";
    } elseif (isset($_POST['add_uid'])) {
        $uid = trim($_POST['uid'] ?? '');
        $hours = 60; // Default hours
        
        if (empty($uid)) {
            $message = "<div class='alert alert-error'>❌ UID cannot be empty</div>";
        } elseif (!ctype_digit($uid) || strlen($uid) > 20) {
            $message = "<div class='alert alert-error'>❌ UID must contain only digits (max 20 characters)</div>";
        } else {
            $expiry = date('Y-m-d H:i:s', strtotime("+$hours hours"));
            
            // Check if UID exists
            $check = $conn->prepare("SELECT id FROM uid_users WHERE uid = ?");
            $check->bind_param("s", $uid);
            $check->execute();
            
            if ($check->get_result()->num_rows) {
                // Update existing UID
                $stmt = $conn->prepare("UPDATE uid_users SET expiry_time = ?, status = 'active', updated_at = NOW() WHERE uid = ?");
                $stmt->bind_param("ss", $expiry, $uid);
                $action = "updated";
            } else {
                // Insert new UID
                $stmt = $conn->prepare("INSERT INTO uid_users (uid, expiry_time, status, created_at) VALUES (?, ?, 'active', NOW())");
                $stmt->bind_param("ss", $uid, $expiry);
                $action = "added";
            }
            
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ UID $action successfully for $hours hours</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ Database error: " . htmlspecialchars($conn->error) . "</div>";
            }
            $stmt->close();
            $check->close();
        }

    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_uid'])) {
    $checkUid = trim($_GET['check_uid'] ?? '');
    
    if (!empty($checkUid)) {
        if (!ctype_digit($checkUid) || strlen($checkUid) > 20) {
            $statusResult = "<div class='alert alert-error'>❌ Invalid UID format</div>";
        } else {
            $stmt = $conn->prepare("SELECT uid, expiry_time, status FROM uid_users WHERE uid = ?");
            $stmt->bind_param("s", $checkUid);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($row = $res->fetch_assoc()) {
                $expiryTime = strtotime($row['expiry_time']);
                $currentTime = time();
                
                if ($expiryTime < $currentTime) {
                    $statusResult = "<div class='alert alert-error'>❌ UID EXPIRED on " . date('Y-m-d H:i', $expiryTime) . "</div>";
                } else {
                    $diff = $expiryTime - $currentTime;
                    $days = floor($diff / (60 * 60 * 24));
                    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
                    $minutes = floor(($diff % (60 * 60)) / 60);
                    
                    $timeLeft = "";
                    if ($days > 0) $timeLeft .= "$days days ";
                    if ($hours > 0) $timeLeft .= "$hours hours ";
                    if ($minutes > 0) $timeLeft .= "$minutes minutes";
                    
                    $statusResult = "<div class='alert alert-success'>
                        ✅ <strong>UID ACTIVE</strong><br>
                        Expires: " . date('Y-m-d H:i', $expiryTime) . "<br>
                        Time left: " . ($timeLeft ?: "Less than a minute") . "
                    </div>";
                }
            } else {
                $statusResult = "<div class='alert alert-warning'>⚠️ UID NOT FOUND in database</div>";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UID Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7289da;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --dark: #0a0e17;
            --light: #f8f9fa;
            --gray: #6c757d;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.1);
            --discord-purple: #5865f2;
            --discord-blurple: #7289da;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0e17 0%, #1a1f3a 50%, #0a0e17 100%);
            color: var(--light);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Particle Background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(76, 201, 240, 0.2);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0) rotate(0deg); }
            25% { transform: translateY(-100px) translateX(100px) rotate(90deg); }
            50% { transform: translateY(-200px) translateX(0) rotate(180deg); }
            75% { transform: translateY(-100px) translateX(-100px) rotate(270deg); }
            100% { transform: translateY(0) translateX(0) rotate(360deg); }
        }

      
        .glowing-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.3;
            z-index: -1;
        }

        .orb-1 {
            width: 300px;
            height: 300px;
            background: var(--primary);
            top: 10%;
            left: 10%;
            animation: pulse 8s infinite alternate;
        }

        .orb-2 {
            width: 200px;
            height: 200px;
            background: var(--discord-purple);
            bottom: 20%;
            right: 10%;
            animation: pulse 6s infinite alternate-reverse;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.3; }
            100% { transform: scale(1.2); opacity: 0.5; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 50px;
            padding-top: 30px;
            position: relative;
        }

        .header h1 {
            font-size: 3.5rem;
            background: linear-gradient(90deg, var(--primary), var(--success), var(--discord-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            text-shadow: 0 0 30px rgba(76, 201, 240, 0.3);
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from { text-shadow: 0 0 20px rgba(76, 201, 240, 0.3); }
            to { text-shadow: 0 0 30px rgba(76, 201, 240, 0.6), 0 0 40px rgba(67, 97, 238, 0.4); }
        }

        .header p {
            color: var(--gray);
            font-size: 1.2rem;
            position: relative;
            display: inline-block;
        }

        .header p::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--success), transparent);
            bottom: -10px;
            left: 0;
            animation: slide 3s linear infinite;
        }

        @keyframes slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px 35px;
            border: 1px solid var(--card-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .card:hover::before {
            left: 100%;
        }

        .card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.7), 0 0 30px rgba(76, 201, 240, 0.2);
            border-color: rgba(76, 201, 240, 0.3);
        }

        .card h2 {
            font-size: 2rem;
            margin-bottom: 25px;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .card h2 i {
            font-size: 1.8rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Forms */
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            color: var(--gray);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1.3rem;
            transition: color 0.3s;
        }

        input[type="text"] {
            width: 100%;
            padding: 18px 20px 18px 55px;
            background: rgba(255, 255, 255, 0.07);
            border: 2px solid transparent;
            border-radius: 15px;
            color: var(--light);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.2), 0 0 20px rgba(67, 97, 238, 0.3);
        }

        input:focus + i {
            color: var(--success);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 30px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
            z-index: -1;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.5), 0 0 30px rgba(67, 97, 238, 0.4);
            background: linear-gradient(135deg, var(--primary-dark), #2d44c0);
        }

        .btn i {
            font-size: 1.3rem;
            transition: transform 0.3s;
        }

        .btn:hover i {
            transform: rotate(15deg) scale(1.2);
        }

        /* Discord Section */
        .discord-section {
            background: linear-gradient(135deg, rgba(114, 137, 218, 0.1) 0%, rgba(88, 101, 242, 0.15) 100%);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 50px 40px;
            border: 2px solid rgba(114, 137, 218, 0.3);
            margin-top: 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(88, 101, 242, 0.2), inset 0 0 50px rgba(114, 137, 218, 0.1);
            animation: discord-glow 3s ease-in-out infinite alternate;
        }

        @keyframes discord-glow {
            0% { box-shadow: 0 25px 60px rgba(88, 101, 242, 0.2), inset 0 0 50px rgba(114, 137, 218, 0.1); }
            100% { box-shadow: 0 25px 80px rgba(88, 101, 242, 0.4), inset 0 0 60px rgba(114, 137, 218, 0.2); }
        }

        .discord-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(114, 137, 218, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .discord-section h2 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            position: relative;
            text-shadow: 0 0 20px rgba(114, 137, 218, 0.5);
        }

        .discord-section h2 i {
            font-size: 2.2rem;
            animation: discord-spin 4s linear infinite, discord-float 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(114, 137, 218, 0.8));
        }

        @keyframes discord-spin {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }

        @keyframes discord-float {
            0%, 100% { transform: translateY(0) rotateY(0); }
            50% { transform: translateY(-15px) rotateY(180deg); }
        }

        .discord-section p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 35px;
            font-size: 1.3rem;
            position: relative;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.8;
        }

        .discord-section .discord-text {
            font-size: 1.5rem;
            background: linear-gradient(90deg, #fff, #b9bbbe, #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% 100%;
            animation: shimmer 3s infinite linear;
            font-weight: 700;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .btn-discord {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 22px 40px;
            background: linear-gradient(135deg, var(--discord-purple), #4752c4);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 1.3rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
            overflow: hidden;
            z-index: 1;
            width: auto;
            min-width: 300px;
            margin-top: 10px;
            box-shadow: 0 20px 40px rgba(88, 101, 242, 0.3);
            animation: btn-pulse 2s infinite;
        }

        @keyframes btn-pulse {
            0% { box-shadow: 0 20px 40px rgba(88, 101, 242, 0.3); }
            50% { box-shadow: 0 20px 60px rgba(88, 101, 242, 0.5), 0 0 40px rgba(88, 101, 242, 0.4); }
            100% { box-shadow: 0 20px 40px rgba(88, 101, 242, 0.3); }
        }

        .btn-discord::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: 0.5s;
            z-index: -1;
        }

        .btn-discord:hover::before {
            left: 100%;
        }

        .btn-discord:hover {
            transform: translateY(-8px) scale(1.08);
            background: linear-gradient(135deg, #4752c4, var(--discord-purple));
            box-shadow: 0 30px 70px rgba(88, 101, 242, 0.5), 0 0 50px rgba(88, 101, 242, 0.6);
        }

        .btn-discord:hover i {
            transform: rotate(360deg) scale(1.2);
        }

        .btn-discord i {
            font-size: 1.8rem;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .discord-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            animation: fadeInUp 0.8s ease-out forwards;
            opacity: 0;
        }

        .stat-item:nth-child(1) { animation-delay: 0.2s; }
        .stat-item:nth-child(2) { animation-delay: 0.4s; }
        .stat-item:nth-child(3) { animation-delay: 0.6s; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--discord-purple), #fff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: block;
            margin-bottom: 5px;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            font-weight: 500;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 80px;
            padding: 40px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .copyright {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            margin-top: 20px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .header h1 {
                font-size: 2.8rem;
            }
            
            .discord-section h2 {
                font-size: 2rem;
            }
            
            .btn-discord {
                min-width: 250px;
                padding: 20px 30px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .main-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .card {
                padding: 30px 25px;
            }
            
            .discord-section {
                padding: 40px 25px;
                margin-top: 40px;
            }
            
            .discord-section h2 {
                font-size: 1.8rem;
            }
            
            .btn-discord {
                min-width: 100%;
                padding: 18px 25px;
            }
            
            .discord-stats {
                gap: 30px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .card h2 {
                font-size: 1.6rem;
            }
            
            .discord-section h2 {
                font-size: 1.5rem;
            }
            
            .discord-section p {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Particle Background -->
    <div class="particles" id="particles"></div>
    <div class="glowing-orb orb-1"></div>
    <div class="glowing-orb orb-2"></div>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-key"></i>Public Authorized UID Manager</h1>
            <p>By Wondertoolbox.com UID Management System</p>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Add/Update Card -->
            <div class="card animate-fadeInUp">
                <h2><i class="fas fa-plus-circle"></i> Add / Update UID</h2>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="uid">Enter UID</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" 
                                   id="uid" 
                                   name="uid" 
                                   value="<?php echo htmlspecialchars($uid); ?>" 
                                   placeholder="e.g., 1234567890" 
                                   maxlength="20"
                                   required>
                        </div>
                    </div>
                    
                    <?php if ($message && isset($_POST['add_uid'])): ?>
                        <?php echo $message; ?>
                    <?php endif; ?>
                    
                    <button type="submit" name="add_uid" class="btn">
                        <i class="fas fa-rocket"></i> Add/Update UID
                    </button>
                </form>
            </div>

            <!-- Check UID Card -->
            <div class="card animate-fadeInUp" style="animation-delay: 0.2s">
                <h2><i class="fas fa-search"></i> Check UID Status</h2>
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="check_uid">Enter UID to Check</label>
                        <div class="input-with-icon">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   id="check_uid" 
                                   name="check_uid" 
                                   value="<?php echo htmlspecialchars($checkUid); ?>" 
                                   placeholder="Enter UID to check"
                                   maxlength="20"
                                   required>
                        </div>
                    </div>
                    
                    <?php if ($statusResult): ?>
                        <?php echo $statusResult; ?>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-shield-check"></i> Check Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Enhanced Discord Section -->
        <div class="discord-section animate-fadeInUp" style="animation-delay: 0.4s">
            <h2><i class="fab fa-discord"></i> Join Our Discord</h2>
            
            
           
            
            <a href="https://discord.gg/b7bbDcx3kd" class="btn-discord" target="_blank">
                <i class="fab fa-discord"></i> Join Discord Server
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> UID System By Wondertoolbox.com . All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Create particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random properties
                const size = Math.random() * 10 + 2;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const duration = Math.random() * 20 + 10;
                const delay = Math.random() * 20;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                particle.style.animationDuration = `${duration}s`;
                particle.style.animationDelay = `${delay}s`;
                
                // Random color
                const colors = [
                    'rgba(76, 201, 240, 0.3)',
                    'rgba(67, 97, 238, 0.3)',
                    'rgba(114, 137, 218, 0.3)',
                    'rgba(248, 150, 30, 0.3)'
                ];
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                
                particlesContainer.appendChild(particle);
            }
        }

        // Input validation
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            
            const uidInputs = document.querySelectorAll('input[name="uid"], input[name="check_uid"]');
            
            uidInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^\d]/g, '');
                });
            });
            
            // Auto-focus first input
            if (uidInputs[0]) uidInputs[0].focus();
            
            // Add floating animation to cards on scroll
            const cards = document.querySelectorAll('.card');
            
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, observerOptions);
            
            cards.forEach(card => {
                observer.observe(card);
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn, .btn-discord').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.3);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>