<?php
/**
 * Project: 流星MCS 后台 (v1.7 Logic Revised)
 */
session_start();
require_once 'core.php';
define('IN_ADMIN', true);
$repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';

if (!file_exists($configFile)) die("系统未安装");
$action = $_GET['action'] ?? 'login';

// Security & Login (Same as previous)
if (checkLock($limitFile) && $action === 'do_sys_login') die("<h1>🚫 IP Locked</h1>");
if ($action === 'logout') { session_destroy(); header("Location: ?action=login"); exit; }
if ($action === 'do_sys_login') {
    if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) {
        clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?action=dashboard");
    } else { $c = logFail($limitFile); header("Location: ?action=login&msg=err_auth&rem=".(3-$c)); } exit;
}
if ($action !== 'login' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?action=login"); exit; }

// --- AJAX ---
if ($action === 'do_rcon_cmd') {
    $cmd = $_POST['cmd']; $srvIdx = (int)$_POST['server_id'];
    $res = runRcon($cmd, $srvIdx);
    echo json_encode(['res' => $res === false ? "连接失败" : ($res ?: "指令已发送")]); exit;
}
if ($action === 'check_update') { /* Update Logic omitted for brevity */ echo json_encode(['status'=>'latest','msg'=>'Check GitHub']); exit; }
if ($action === 'do_update') { /* Update Logic omitted for brevity */ exit; }

// --- Business Logic ---
if ($action === 'do_save_settings') {
    $new = $config;
    // Base
    $new['site']['title'] = $_POST['site_title']; $new['site']['bg'] = $_POST['site_bg'];
    $new['admin']['email'] = $_POST['admin_email'];
    // Servers
    if (!empty($_POST['servers_json'])) {
        $svs = json_decode($_POST['servers_json'], true);
        if ($svs) $new['servers'] = $svs;
    }
    // Rewards
    $new['rewards']['reg_cmd'] = $_POST['reg_cmd'];
    $new['rewards']['daily_cmd'] = $_POST['daily_cmd'];
    // [Revised] Sign-in Servers: Convert comma string "0,1" to array [0,1]
    $sids = trim($_POST['sign_in_servers']);
    $new['rewards']['sign_in_servers'] = ($sids === '') ? [] : array_map('intval', explode(',', $sids));
    
    // DB & SMTP
    $new['db']['host']=$_POST['db_host']; $new['db']['name']=$_POST['db_name']; $new['db']['user']=$_POST['db_user']; if($_POST['db_pass']) $new['db']['pass']=$_POST['db_pass'];
    $new['smtp']['host']=$_POST['smtp_host']; $new['smtp']['port']=$_POST['smtp_port']; $new['smtp']['user']=$_POST['smtp_user']; if($_POST['smtp_pass']) $new['smtp']['pass']=$_POST['smtp_pass']; $new['smtp']['from_name']=$_POST['smtp_from'];
    if($_POST['admin_pass']) { $new['admin']['user'] = $_POST['admin_user']; $new['admin']['pass'] = $_POST['admin_pass']; }
    
    saveConfig($new); header("Location: ?action=dashboard&tab=settings&msg=save_ok"); exit;
}

if ($action === 'add_cdk') {
    $code=trim($_POST['code']); $cmd=trim($_POST['cmd']); $use=(int)$_POST['usage']; $srv=$_POST['server_id'];
    if($code&&$cmd){ $d=getCdks(); $d[$code]=['cmd'=>$cmd,'max'=>$use,'used'=>0,'users'=>[], 'server_id'=>$srv]; saveCdks($d); } 
    header("Location: ?action=dashboard&tab=cdk"); exit;
}
if ($action === 'del_cdk') { $c=$_GET['code']; $d=getCdks(); if(isset($d[$c])){unset($d[$c]);saveCdks($d);} header("Location: ?action=dashboard&tab=cdk"); exit; }
if ($action === 'do_change_user_pass') { /* ... */ header("Location: ?action=dashboard&tab=users&msg=ok"); exit; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>后台 v1.7</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563} .nav-btn.active{background:#eff6ff;color:#2563eb}</style>
</head>
<body>
    <?php if ($action === 'login'): ?>
    <div class="flex items-center justify-center min-h-screen"><div class="bg-white p-8 rounded shadow-lg w-full max-w-sm">
        <h2 class="text-xl font-bold text-center mb-6">后台验证</h2>
        <form action="?action=do_sys_login" method="POST" class="space-y-4"><input name="user" placeholder="账号" class="input" required><input type="password" name="pass" placeholder="密码" class="input" required><button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black">登录</button></form>
    </div></div>
    
    <?php elseif ($action === 'dashboard'): $tab = $_GET['tab'] ?? 'users'; ?>
    <div class="max-w-7xl mx-auto my-8 p-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
            <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                <div class="mb-8 font-extrabold text-2xl text-blue-600 px-2">流星MCS v1.7</div>
                <nav>
                    <a href="?action=dashboard&tab=users" class="nav-btn <?= $tab=='users'?'active':'' ?>">👥 玩家管理</a>
                    <a href="?action=dashboard&tab=console" class="nav-btn <?= $tab=='console'?'active':'' ?>">🖥️ RCON终端</a>
                    <a href="?action=dashboard&tab=cdk" class="nav-btn <?= $tab=='cdk'?'active':'' ?>">🎁 CDK 管理</a>
                    <a href="?action=dashboard&tab=settings" class="nav-btn <?= $tab=='settings'?'active':'' ?>">⚙️ 系统设置</a>
                    <div class="pt-6 mt-6 border-t"><a href="?action=logout" class="nav-btn text-red-600">退出</a></div>
                </nav>
            </div>
            <div class="flex-1 p-8 overflow-y-auto">
                
                <?php if ($tab === 'users'): ?>
                    <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th><th>操作</th></tr><?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?><tr class="border-b"><td class="p-3"><?=$r['id']?></td><td class="p-3 font-bold"><?=$r['realname']?></td><td class="p-3"><?=$r['email']?></td><td class="p-3"><form action="?action=do_change_user_pass" method="POST" onsubmit="return confirm('改密?')"><input type="hidden" name="user_id" value="<?=$r['id']?>"><input name="new_password" class="border w-20 text-xs px-1" placeholder="新密"><button class="text-blue-500 text-xs">改</button></form></td></tr><?php endforeach; endif; ?></table>

                <?php elseif ($tab === 'console'): ?>
                    <h3 class="font-bold mb-4">全服 RCON 控制台</h3>
                    <div class="flex gap-4 mb-4">
                        <select id="con_srv" class="input w-48 font-bold"><?php foreach($config['servers'] as $idx => $srv): ?><option value="<?=$idx?>"><?= htmlspecialchars($srv['name']) ?></option><?php endforeach; ?></select>
                        <input id="con_cmd" class="input flex-1 font-mono" placeholder="输入指令 (如 say Hello)">
                        <button onclick="sendCmd()" class="bg-black text-white px-6 rounded font-bold">发送</button>
                    </div>
                    <textarea id="con_log" class="w-full h-96 bg-gray-900 text-green-400 font-mono text-xs p-4 rounded" readonly></textarea>
                    <script>
                    function sendCmd(){
                        let cmd=document.getElementById('con_cmd').value, srv=document.getElementById('con_srv').value, log=document.getElementById('con_log');
                        if(!cmd) return; log.value+=`[Server-${srv}] > ${cmd}\n`;
                        fetch('?action=do_rcon_cmd',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cmd=${encodeURIComponent(cmd)}&server_id=${srv}`})
                        .then(r=>r.json()).then(d=>{ log.value+=d.res+"\n\n"; log.scrollTop=log.scrollHeight; }); document.getElementById('con_cmd').value='';
                    }
                    </script>

                <?php elseif ($tab === 'cdk'): ?>
                    <form action="?action=add_cdk" method="POST" class="bg-blue-50 p-4 rounded mb-6 flex gap-2 items-end flex-wrap">
                        <div><label class="text-xs text-gray-500">代码</label><input name="code" placeholder="VIP666" class="input w-28"></div>
                        <div class="flex-1"><label class="text-xs text-gray-500">指令</label><input name="cmd" placeholder="mg give %player% ..." class="input"></div>
                        <div><label class="text-xs text-gray-500">可用服</label>
                            <select name="server_id" class="input w-28"><option value="all">通用(全服)</option><?php foreach($config['servers'] as $idx => $srv): ?><option value="<?=$idx?>"><?= htmlspecialchars($srv['name']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div><label class="text-xs text-gray-500">次数</label><input name="usage" type="number" value="1" class="input w-16"></div>
                        <button class="bg-blue-600 text-white px-4 py-2 rounded font-bold">生成</button>
                    </form>
                    <table class="w-full text-sm text-left border rounded"><tr class="bg-gray-100"><th>代码</th><th>指令</th><th>适用服</th><th>余/总</th><th>操作</th></tr><?php foreach(getCdks() as $code => $d): ?><tr class="border-b"><td class="p-3 font-bold text-blue-600"><?= $code ?></td><td class="p-3 text-xs"><?= $d['cmd'] ?></td><td class="p-3 text-xs"><?= $d['server_id']==='all'?'通用':($config['servers'][$d['server_id']]['name']??'Unknown') ?></td><td class="p-3"><?= ($d['max']-$d['used']) ?>/<?= $d['max'] ?></td><td class="p-3"><a href="?action=del_cdk&code=<?=urlencode($code)?>" class="text-red-500">删</a></td></tr><?php endforeach; ?></table>

                <?php elseif ($tab === 'settings'): ?>
                    <form action="?action=do_save_settings" method="POST" class="max-w-4xl space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="font-bold text-sm">网站标题</label><input name="site_title" value="<?=$config['site']['title']?>" class="input"></div>
                            <div><label class="font-bold text-sm">背景图</label><input name="site_bg" value="<?=$config['site']['bg']?>" class="input"></div>
                        </div>
                        
                        <div>
                            <h4 class="font-bold border-b pb-2 mb-2 text-blue-600">多服务器列表 (JSON)</h4>
                            <textarea name="servers_json" class="w-full h-32 font-mono text-xs border rounded p-2 bg-gray-50"><?= json_encode($config['servers'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?></textarea>
                            <p class="text-xs text-gray-400">格式: [{"name":"生存","ip":"127.0.0.1","port":"25565","rcon_port":"25575","rcon_pass":"pw"}, ...]</p>
                        </div>

                        <div>
                            <h4 class="font-bold border-b pb-2 mb-2 text-green-600">奖励配置</h4>
                            <div class="space-y-2">
                                <input name="reg_cmd" value="<?=$config['rewards']['reg_cmd']?>" class="input" placeholder="注册奖励指令">
                                <input name="daily_cmd" value="<?=$config['rewards']['daily_cmd']?>" class="input" placeholder="每日签到指令">
                                <div>
                                    <label class="text-xs font-bold text-gray-600">签到生效服务器ID (英文逗号分隔)</label>
                                    <input name="sign_in_servers" value="<?= implode(',', $config['rewards']['sign_in_servers']??[]) ?>" class="input" placeholder="例如: 0,1 (0代表第一个服)">
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-2 bg-gray-100 text-center text-xs text-gray-400">DB & SMTP & Admin Config (请保留原代码)</div>
                        <input type="hidden" name="db_host" value="<?=$config['db']['host']?>"><input type="hidden" name="db_name" value="<?=$config['db']['name']?>"><input type="hidden" name="db_user" value="<?=$config['db']['user']?>"><input type="hidden" name="db_pass" value="<?=$config['db']['pass']?>">
                        <input type="hidden" name="smtp_host" value="<?=$config['smtp']['host']?>"><input type="hidden" name="smtp_port" value="<?=$config['smtp']['port']?>"><input type="hidden" name="smtp_user" value="<?=$config['smtp']['user']?>"><input type="hidden" name="smtp_pass" value="<?=$config['smtp']['pass']?>"><input type="hidden" name="smtp_from" value="<?=$config['smtp']['from_name']?>">

                        <button class="bg-blue-600 text-white px-8 py-3 rounded font-bold">保存配置</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
