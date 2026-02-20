<?php
/**
 * Project: 流星MCS 后台管理
 * Version: v1.9 (MetorCore API Edition)
 */
session_start();
require_once 'core.php';
define('IN_ADMIN', true);
$repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';

if (!file_exists($configFile)) die("系统未安装");
$action = $_GET['action'] ?? 'login';

if (checkLock($limitFile) && $action === 'do_sys_login') die("<h1>🚫 IP Locked</h1>");
if ($action === 'logout') { session_destroy(); header("Location: ?action=login"); exit; }

if ($action === 'do_sys_login') {
    if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) {
        clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?action=dashboard");
    } else { $c = logFail($limitFile); header("Location: ?action=login&msg=err_auth&rem=".(3-$c)); } exit;
}
if ($action !== 'login' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?action=login"); exit; }

if ($action === 'check_update') {
    $remoteVer = @file_get_contents($repoUrl . 'version.txt');
    if ($remoteVer === false) { echo json_encode(['status' => 'err', 'msg' => '连接 GitHub 失败']); }
    else {
        $remoteVer = trim($remoteVer); $currentVer = $config['site']['ver'];
        if (version_compare($remoteVer, $currentVer, '>')) echo json_encode(['status' => 'new', 'ver' => $remoteVer, 'msg' => "发现新版本 v$remoteVer"]);
        else echo json_encode(['status' => 'latest', 'msg' => '已是最新']);
    } exit;
}

if ($action === 'do_update') {
    $files = ['index.php', 'admin.php', 'core.php', 'install.php', 'lite.php']; $log=""; $ok=true;
    foreach ($files as $f) {
        $c = @file_get_contents($repoUrl . $f);
        if ($c) { if(file_put_contents($f, $c)) $log.="✅ $f OK\n"; else { $ok=false; $log.="❌ $f Fail\n"; } }
    }
    $sc = @file_get_contents($repoUrl . 'config_sample.php');
    if ($sc) {
        file_put_contents('ctmp.php', $sc); $tpl=include('ctmp.php'); $old=include('config.php'); unlink('ctmp.php');
        $new = array_replace_recursive($tpl, $old);
        $ver = trim(@file_get_contents($repoUrl . 'version.txt'));
        if($ver) $new['site']['ver'] = $ver; 
        saveConfig($new); $log.="✅ Config & Version Updated ($ver)\n";
    }
    echo json_encode(['status' => $ok?'ok':'err', 'log' => $log]); exit;
}

if ($action === 'do_api_cmd') { 
    $res = runApiCmd($_POST['cmd'], (int)$_POST['server_id']); 
    echo json_encode(['res' => $res === false ? "安全通讯握手失败，请检查 API 配置与密钥" : ($res ?: "指令已发送")]); 
    exit; 
}

if ($action === 'do_save_settings') {
    $new=$config; $new['site']['title']=$_POST['site_title']; $new['site']['bg']=$_POST['site_bg'];
    
    if(!empty($_POST['servers_json'])) { 
        $parsed = json_decode($_POST['servers_json'], true); 
        if(is_array($parsed)) $new['servers'] = $parsed; 
    }
    
    $new['rewards']['reg_cmd']=$_POST['reg_cmd']; $new['rewards']['daily_cmd']=$_POST['daily_cmd'];
    $new['rewards']['sign_in_servers']=explode(',',$_POST['sign_in_servers']);
    $new['display']['ip']=$_POST['display_ip']; $new['display']['port']=$_POST['display_port'];
    
    $new['db']['host']=$_POST['db_host']; $new['db']['name']=$_POST['db_name']; $new['db']['user']=$_POST['db_user']; 
    if(!empty($_POST['db_pass'])) $new['db']['pass']=$_POST['db_pass'];
    
    $new['smtp']['host']=$_POST['smtp_host']; $new['smtp']['port']=$_POST['smtp_port']; $new['smtp']['user']=$_POST['smtp_user']; $new['smtp']['from_name']=$_POST['smtp_from'];
    if(!empty($_POST['smtp_pass'])) $new['smtp']['pass']=$_POST['smtp_pass'];
    if(isset($_POST['smtp_secure'])) $new['smtp']['secure']=$_POST['smtp_secure'];
    
    $new['admin']['user']=$_POST['admin_user'];
    if(!empty($_POST['admin_pass'])) $new['admin']['pass']=$_POST['admin_pass'];
    if(isset($_POST['admin_email'])) $new['admin']['email']=$_POST['admin_email'];
    
    if(isset($_POST['server_ip'])) $new['server']['ip']=$_POST['server_ip'];
    if(isset($_POST['server_port'])) $new['server']['port']=$_POST['server_port'];
    if(isset($_POST['api_host'])) $new['api']['host']=$_POST['api_host'];
    if(isset($_POST['api_port'])) $new['api']['port']=$_POST['api_port'];
    if(!empty($_POST['api_key'])) $new['api']['key']=$_POST['api_key'];
    
    saveConfig($new); header("Location: ?action=dashboard&tab=settings&msg=save_ok"); exit;
}
if ($action === 'add_cdk') { $d=getCdks(); $d[$_POST['code']]=['cmd'=>$_POST['cmd'],'max'=>(int)$_POST['usage'],'server_id'=>$_POST['server_id'],'used'=>0,'users'=>[]]; saveCdks($d); header("Location: ?action=dashboard&tab=cdk"); exit; }
if ($action === 'del_cdk') { $d=getCdks(); unset($d[$_GET['code']]); saveCdks($d); header("Location: ?action=dashboard&tab=cdk"); exit; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>后台 v1.9</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563} .nav-btn.active{background:#eff6ff;color:#2563eb}</style>
</head>
<body>
    <?php if ($action === 'login'): ?>
    <div class="flex items-center justify-center min-h-screen"><div class="bg-white p-8 rounded shadow-lg w-full max-w-sm"><h2 class="text-xl font-bold text-center mb-6">后台验证</h2><form action="?action=do_sys_login" method="POST" class="space-y-4"><input name="user" placeholder="账号" class="input" required><input type="password" name="pass" placeholder="密码" class="input" required><button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black">登录</button></form></div></div>
    
    <?php elseif ($action === 'dashboard'): $tab = $_GET['tab'] ?? 'users'; ?>
    <div class="max-w-7xl mx-auto my-8 p-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
            <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                <div class="mb-8 font-extrabold text-2xl text-blue-600 px-2">流星AWP <span class="text-xs text-gray-400 block font-normal">v<?= htmlspecialchars($config['site']['ver']) ?></span></div>
                <button onclick="checkUpdate()" id="u-btn" class="mb-4 text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded">检查更新</button>
                <nav>
                    <a href="?action=dashboard&tab=users" class="nav-btn <?= $tab=='users'?'active':'' ?>">👥 玩家管理</a>
                    <a href="?action=dashboard&tab=console" class="nav-btn <?= $tab=='console'?'active':'' ?>">🖥️ MetorCore终端</a>
                    <a href="?action=dashboard&tab=cdk" class="nav-btn <?= $tab=='cdk'?'active':'' ?>">🎁 CDK 管理</a>
                    <a href="?action=dashboard&tab=settings" class="nav-btn <?= $tab=='settings'?'active':'' ?>">⚙️ 系统设置</a>
                    <div class="pt-6 mt-6 border-t"><a href="?action=logout" class="nav-btn text-red-600">退出</a></div>
                </nav>
            </div>
            <div class="flex-1 p-8 overflow-y-auto relative">
                <div id="u-modal" class="hidden absolute inset-0 bg-white/90 z-50 flex items-center justify-center"><div class="bg-white border shadow-xl p-6 rounded text-center w-96"><h3 class="font-bold text-lg mb-2">发现新版本</h3><p id="u-ver" class="text-blue-600 mb-4 font-mono"></p><div class="flex gap-2 justify-center"><button onclick="doUp()" class="bg-green-600 text-white px-4 py-2 rounded">更新</button><button onclick="document.getElementById('u-modal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">取消</button></div></div></div>

                <?php if ($tab === 'users'): ?>
                    <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th></tr><?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?><tr class="border-b"><td class="p-3"><?=$r['id']?></td><td class="p-3"><?=htmlspecialchars($r['realname'])?></td><td class="p-3"><?=htmlspecialchars($r['email'])?></td></tr><?php endforeach; endif; ?></table>
                
                <?php elseif ($tab === 'console'): ?>
                    <div class="flex gap-2 mb-2"><select id="cs" class="input w-48"><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><input id="cc" class="input flex-1" placeholder="API Command..."><button onclick="sc()" class="bg-black text-white px-4 rounded">Send</button></div>
                    <textarea id="cl" class="w-full h-96 bg-gray-900 text-green-400 p-4 rounded text-xs font-mono" readonly></textarea>
                    <p class="text-xs text-gray-400 mt-2">提示: 此终端已接入 MetorCore 强加密引擎，兼容 1.8-最新版 及 Velocity 架构。</p>
                    <script>function sc(){let c=document.getElementById('cc').value,s=document.getElementById('cs').value,l=document.getElementById('cl');if(!c)return;l.value+=`> ${c}\n`;fetch('?action=do_api_cmd',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cmd=${c}&server_id=${s}`}).then(r=>r.json()).then(d=>{l.value+=d.res+"\n\n";l.scrollTop=l.scrollHeight});document.getElementById('cc').value=''}</script>
                
                <?php elseif ($tab === 'cdk'): ?>
                    <form action="?action=add_cdk" method="POST" class="bg-blue-50 p-4 rounded mb-4 flex gap-2"><input name="code" placeholder="Code" class="input w-32"><input name="cmd" placeholder="Cmd" class="input flex-1"><input name="usage" value="1" class="input w-16"><select name="server_id" class="input w-24"><option value="all">All</option><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><button class="bg-blue-600 text-white px-4 rounded">Add</button></form>
                    <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>Code</th><th>Cmd</th><th>Srv</th><th>Use</th><th>Op</th></tr><?php foreach(getCdks() as $k=>$d): ?><tr class="border-b"><td class="p-3 font-bold"><?=htmlspecialchars($k)?></td><td class="p-3 text-xs"><?=htmlspecialchars($d['cmd'])?></td><td class="p-3 text-xs"><?=$d['server_id']=='all'?'All':$config['servers'][$d['server_id']]['name']?></td><td class="p-3"><?=($d['max']-$d['used'])?></td><td class="p-3"><a href="?action=del_cdk&code=<?=urlencode($k)?>" class="text-red-500">Del</a></td></tr><?php endforeach; ?></table>
                
                <?php elseif ($tab === 'settings'): ?>
                    <form action="?action=do_save_settings" method="POST" class="space-y-4 max-w-2xl pb-8">
                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">基本设置</div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">标题</label><input name="site_title" value="<?=$config['site']['title']?>" class="input"></div><div><label class="text-xs font-bold">背景链接</label><input name="site_bg" value="<?=$config['site']['bg']?>" class="input"></div></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">前端IP</label><input name="display_ip" value="<?=$config['display']['ip']?>" class="input"></div><div><label class="text-xs font-bold">前端端口</label><input name="display_port" value="<?=$config['display']['port']?>" class="input"></div></div>
                        
                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">奖励策略</div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">注册指令</label><input name="reg_cmd" value="<?=$config['rewards']['reg_cmd']?>" class="input"></div><div><label class="text-xs font-bold">签到指令</label><input name="daily_cmd" value="<?=$config['rewards']['daily_cmd']?>" class="input"></div></div>
                        <div><label class="text-xs font-bold">签到生效服ID (逗号隔开)</label><input name="sign_in_servers" value="<?=implode(',',$config['rewards']['sign_in_servers'])?>" class="input"></div>
                        
                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">多服务器群组 MetorCore API (JSON)</div>
                        <div><label class="text-xs font-bold">后端 API 列表 (替换原 RCON)</label><textarea name="servers_json" class="input h-24 font-mono text-xs"><?=json_encode($config['servers'])?></textarea></div>

                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">数据库连接 (AuthMe)</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-xs font-bold">DB Host</label><input name="db_host" value="<?=$config['db']['host']?>" class="input"></div>
                            <div><label class="text-xs font-bold">DB Name</label><input name="db_name" value="<?=$config['db']['name']?>" class="input"></div>
                            <div><label class="text-xs font-bold">DB User</label><input name="db_user" value="<?=$config['db']['user']?>" class="input"></div>
                            <div><label class="text-xs font-bold">DB Pass (留空不修改)</label><input type="password" name="db_pass" placeholder="***" class="input"></div>
                        </div>

                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">邮件推送 (SMTP)</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-xs font-bold">SMTP Host</label><input name="smtp_host" value="<?=$config['smtp']['host']?>" class="input"></div>
                            <div><label class="text-xs font-bold">SMTP Port</label><input name="smtp_port" value="<?=$config['smtp']['port']?>" class="input"></div>
                            <div><label class="text-xs font-bold">SMTP User</label><input name="smtp_user" value="<?=$config['smtp']['user']?>" class="input"></div>
                            <div><label class="text-xs font-bold">SMTP Pass (留空不修改)</label><input type="password" name="smtp_pass" placeholder="***" class="input"></div>
                            <div><label class="text-xs font-bold">发件人名称</label><input name="smtp_from" value="<?=$config['smtp']['from_name']?>" class="input"></div>
                            <div><label class="text-xs font-bold">加密方式 (ssl/tls)</label><input name="smtp_secure" value="<?=$config['smtp']['secure'] ?? 'ssl'?>" class="input"></div>
                        </div>

                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">管理员与全局设置</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-xs font-bold">管理员账号</label><input name="admin_user" value="<?=$config['admin']['user']?>" class="input"></div>
                            <div><label class="text-xs font-bold">管理员密码 (留空不修改)</label><input type="password" name="admin_pass" placeholder="***" class="input"></div>
                            <div class="col-span-2"><label class="text-xs font-bold">管理员邮箱</label><input name="admin_email" value="<?=$config['admin']['email'] ?? ''?>" class="input"></div>
                        </div>
                        
                        <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">单服模式备用选项 (Server/API)</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-xs font-bold">单服 IP</label><input name="server_ip" value="<?=$config['server']['ip'] ?? ''?>" class="input"></div>
                            <div><label class="text-xs font-bold">单服 Port</label><input name="server_port" value="<?=$config['server']['port'] ?? '25565'?>" class="input"></div>
                            <div><label class="text-xs font-bold">单服 API Host</label><input name="api_host" value="<?=$config['api']['host'] ?? ''?>" class="input"></div>
                            <div><label class="text-xs font-bold">单服 API Port</label><input name="api_port" value="<?=$config['api']['port'] ?? '8080'?>" class="input"></div>
                            <div class="col-span-2"><label class="text-xs font-bold">单服 API Key (64位安全密钥，留空不改)</label><input type="password" name="api_key" placeholder="***" class="input"></div>
                        </div>

                        <button class="w-full bg-green-600 text-white px-6 py-3 mt-4 rounded font-bold hover:bg-green-700 transition shadow">保存所有设置</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function checkUpdate(){let b=document.getElementById('u-btn');b.innerText='...';fetch('?action=check_update').then(r=>r.json()).then(d=>{b.innerText='检查更新';if(d.status=='new'){document.getElementById('u-ver').innerText=d.ver;document.getElementById('u-modal').classList.remove('hidden')}else alert(d.msg)})}
    function doUp(){fetch('?action=do_update').then(r=>r.json()).then(d=>{alert(d.log);location.reload()})}
    </script>
    <?php endif; ?>
</body>
</html>
