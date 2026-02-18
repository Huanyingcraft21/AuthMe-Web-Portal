<?php
/**
 * Project: 流星MCS 智能安装程序
 * Version: v1.6 Final (Cloud Installer)
 * Note: 支持在线下载标准版/Lite版，自动配置环境
 */
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

// ==========================================
// 🛠️ 仓库配置 (指向你的 main 分支)
// ==========================================
$repoBase = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';
// ==========================================

// 检查是否已安装
if (file_exists('config.php')) {
    die("<!DOCTYPE html><html><body style='text-align:center;padding-top:50px;font-family:sans-serif'>
    <h1 style='color:green'>✅ 系统已安装</h1>
    <p>检测到 <b>config.php</b> 已存在。</p>
    <p>如需重装，请先手动删除 config.php 文件。</p>
    </body></html>");
}

$step = $_GET['step'] ?? 1;
$error = '';

// --- 逻辑处理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'];
    $dbName = $_POST['db_name'];
    $dbUser = $_POST['db_user'];
    $dbPass = $_POST['db_pass'];
    $adminUser = $_POST['admin_user'];
    $adminPass = $_POST['admin_pass'];
    $installType = $_POST['install_type']; // 'standard' or 'lite'

    try {
        // 1. 测试数据库连接
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. 创建数据库和表
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARSET utf8mb4");
        $pdo->exec("USE `$dbName`");
        $pdo->exec("CREATE TABLE IF NOT EXISTS authme (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            username VARCHAR(255) NOT NULL UNIQUE, 
            realname VARCHAR(255), 
            password VARCHAR(255) NOT NULL, 
            email VARCHAR(255), 
            ip VARCHAR(45), 
            lastlogin BIGINT, 
            regdate BIGINT, 
            x DOUBLE DEFAULT 0, y DOUBLE DEFAULT 0, z DOUBLE DEFAULT 0, 
            world VARCHAR(255) DEFAULT 'world', 
            yaw FLOAT DEFAULT 0, pitch FLOAT DEFAULT 0, 
            isLogged SMALLINT DEFAULT 0, 
            hasSession SMALLINT DEFAULT 0, 
            totp VARCHAR(255), 
            reset_code VARCHAR(10), reset_time BIGINT
        )");

        // 3. 下载文件逻辑 (云端拉取)
        $downloadLog = [];
        
        if ($installType === 'lite') {
            // === Lite 单文件版 ===
            // 下载 lite.php -> 存为 index.php
            $content = @file_get_contents($repoBase . 'lite.php');
            if ($content && strlen($content) > 100) {
                file_put_contents('index.php', $content);
                $downloadLog[] = "✅ 单文件核心 (lite.php -> index.php) 下载成功";
                // 清理可能存在的标准版文件
                if(file_exists('admin.php')) unlink('admin.php');
                if(file_exists('core.php')) unlink('core.php');
            } else {
                throw new Exception("无法从 GitHub 下载 Lite 版文件，请检查网络或仓库地址。");
            }
        } else {
            // === Standard 标准版 ===
            // 下载三件套
            $files = ['index.php', 'admin.php', 'core.php'];
            foreach ($files as $f) {
                $content = @file_get_contents($repoBase . $f);
                if ($content && strlen($content) > 100) {
                    file_put_contents($f, $content);
                    $downloadLog[] = "✅ 核心文件 ($f) 下载成功";
                } else {
                    $downloadLog[] = "⚠️ 文件 ($f) 下载失败，可能需要手动上传";
                }
            }
        }

        // 4. 生成 config.php
        $configData = [
            'db' => ['host'=>$dbHost, 'name'=>$dbName, 'user'=>$dbUser, 'pass'=>$dbPass],
            'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
            'admin' => ['user'=>$adminUser, 'pass'=>$adminPass, 'email'=>''],
            'site' => ['title'=>'流星MCS', 'ver'=>'1.6', 'bg'=>''],
            'server' => ['ip'=>'', 'port'=>'25565'],
            'rcon' => ['host'=>$dbHost, 'port'=>25575, 'pass'=>''], // 默认尝试填DB Host
            'rewards' => ['reg_cmd'=>'', 'daily_cmd'=>'']
        ];
        
        if (file_put_contents('config.php', "<?php\nreturn " . var_export($configData, true) . ";")) {
            // 5. 跳转成功
            $installLog = implode("<br>", $downloadLog);
            $finalUrl = ($installType === 'lite') ? 'index.php' : 'admin.php';
            $finalName = ($installType === 'lite') ? '访问首页' : '进入后台';
            
            die("<!DOCTYPE html><html><head><meta charset='utf-8'><script src='https://cdn.tailwindcss.com'></script></head>
            <body class='bg-gray-100 flex items-center justify-center min-h-screen'>
                <div class='bg-white p-8 rounded-xl shadow-xl max-w-md w-full text-center'>
                    <div class='text-5xl mb-4'>🎉</div>
                    <h2 class='text-2xl font-bold text-gray-800 mb-4'>安装成功！</h2>
                    <div class='bg-gray-50 text-left text-xs p-4 rounded border mb-6 text-gray-500 font-mono'>
                        数据库连接...OK<br>
                        数据表创建...OK<br>
                        配置文件...OK<br>
                        $installLog
                    </div>
                    <p class='mb-6 text-gray-600'>系统已部署为 <b>".($installType=='lite'?'Lite 单文件版':'Standard 标准版')."</b></p>
                    <a href='$finalUrl' class='block w-full bg-green-600 text-white font-bold py-3 rounded-lg hover:bg-green-700 transition'>$finalName</a>
                    <p class='mt-4 text-xs text-red-400'>为了安全，请手动删除 install.php</p>
                </div>
            </body></html>");
        } else {
            throw new Exception("无法写入 config.php，请检查目录权限 (需 777)。");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 流星MCS v1.6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .option-card { border: 2px solid #e5e7eb; cursor: pointer; transition: all 0.2s; }
        .option-card:hover { border-color: #93c5fd; }
        .option-card.selected { border-color: #2563eb; background-color: #eff6ff; }
        .input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; margin-top: 0.25rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-blue-600 p-6 text-white text-center">
            <h1 class="text-2xl font-bold">流星MCS 安装向导</h1>
            <p class="text-blue-100 text-sm mt-1">Version 1.6 Final</p>
        </div>

        <form method="POST" class="p-8">
            <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded mb-6 border border-red-100 flex items-center gap-2">
                <span class="text-xl">⚠️</span> <div><?= $error ?></div>
            </div>
            <?php endif; ?>

            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2"><span class="bg-blue-100 text-blue-600 w-6 h-6 rounded-full flex items-center justify-center text-xs">1</span> 选择安装版本</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="option-card p-4 rounded-lg selected" onclick="selectType('standard')" id="card-std">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-blue-700">Standard 标准版</span>
                        <span class="text-xs bg-blue-600 text-white px-2 py-0.5 rounded">推荐</span>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        功能全开：含后台管理、RCON奖励、CDK系统、邮件通知。适合正式运营。
                    </p>
                </div>
                <div class="option-card p-4 rounded-lg" onclick="selectType('lite')" id="card-lite">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-green-700">Lite 单文件版</span>
                        <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded">极简</span>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        仅保留注册功能。安装程序会自动下载 lite.php 并重命名为 index.php。
                    </p>
                </div>
                <input type="hidden" name="install_type" id="install_type" value="standard">
            </div>

            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2"><span class="bg-blue-100 text-blue-600 w-6 h-6 rounded-full flex items-center justify-center text-xs">2</span> 数据库连接</h3>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="col-span-2 md:col-span-1">
                    <label class="text-xs font-bold text-gray-500">数据库地址</label>
                    <input type="text" name="db_host" value="127.0.0.1" class="input" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label class="text-xs font-bold text-gray-500">数据库名</label>
                    <input type="text" name="db_name" value="authme" class="input" required>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">用户名</label>
                    <input type="text" name="db_user" placeholder="root" class="input" required>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500">密码</label>
                    <input type="password" name="db_pass" placeholder="数据库密码" class="input">
                </div>
            </div>

            <div id="admin-section">
                <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2"><span class="bg-blue-100 text-blue-600 w-6 h-6 rounded-full flex items-center justify-center text-xs">3</span> 后台管理员</h3>
                <div class="grid grid-cols-2 gap-4 mb-8 bg-gray-50 p-4 rounded border">
                    <div>
                        <label class="text-xs font-bold text-gray-500">管理员账号</label>
                        <input type="text" name="admin_user" value="admin" class="input" required>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500">管理员密码</label>
                        <input type="text" name="admin_pass" value="password123" class="input" required>
                    </div>
                </div>
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow transition transform active:scale-95">
                开始安装
            </button>
            <p class="text-center text-xs text-gray-400 mt-4">安装过程需要连接 GitHub 下载文件，请确保网络通畅。</p>
        </form>
    </div>

    <script>
        function selectType(type) {
            document.getElementById('install_type').value = type;
            document.getElementById('card-std').className = 'option-card p-4 rounded-lg ' + (type==='standard' ? 'selected' : '');
            document.getElementById('card-lite').className = 'option-card p-4 rounded-lg ' + (type==='lite' ? 'selected' : '');
            
            // Lite 版不需要设置管理员密码（因为Lite版通常没有复杂后台，或者使用简单验证）
            // 但为了统一 config.php 结构，我们还是保留输入框，只是视觉上提示一下
            const adminSec = document.getElementById('admin-section');
            if(type === 'lite') {
                // adminSec.style.opacity = '0.5'; 
                // 实际上 v1.6 Lite 如果你还没写代码，建议 Lite 也共用 config，所以还是留着吧
            } else {
                // adminSec.style.opacity = '1';
            }
        }
    </script>
</body>
</html>
