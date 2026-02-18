<?php
/**
 * Project: 流星MCS 后台管理
 * Version: v1.6 Final (Auto Updater)
 */
session_start();
require_once 'core.php';
define('IN_ADMIN', true);

// 🛠️ GitHub 更新源 (请确保地址正确)
$repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';

if (!file_exists($configFile)) die("系统未安装");

$action = $_GET['action'] ?? 'login';

// 登录拦截
if (checkLock($limitFile) && $action === 'do_sys_login') die("<h1>🚫 IP Locked</h1>");
if ($action === 'logout') { session_destroy(); header("Location: ?action=login"); exit; }

// 登录
if ($action === 'do_sys_login') {
    if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) {
        clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?action=dashboard");
    } else { $c = logFail($limitFile); header("Location: ?action=login&msg=err_auth&rem=".(3-$c)); } exit;
}
if ($action !== 'login' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?action=login"); exit; }

// 自动更新逻辑
if ($action === 'check_update') {
    $remoteVer = @file_get_contents($repoUrl . 'version.txt');
    if ($remoteVer === false) { echo json_encode(['status' => 'err', 'msg' => '连接 GitHub 失败']); }
    else {
        $remoteVer = trim($remoteVer); $currentVer = $config['site']['ver'];
        if (version_compare($remoteVer, $currentVer, '>')) echo json_encode(['status' => 'new', 'ver' => $remoteVer, 'msg' => "发现 v$remoteVer"]);
        else echo json_encode(['status' => 'latest', 'msg' => '已是最新']);
    } exit;
}
if ($action === 'do_update') {
    $files = ['index.php', 'admin.php', 'core.php', 'install.php']; $log=""; $ok=true;
    foreach ($files as $f) {
        $c = @file_get_contents($repoUrl . $f);
        if ($c) { if(file_put_contents($f, $c)) $log.="✅ $f OK\n"; else { $ok=false; $log.="❌ $f Fail\n"; } }
    }
    // 配置合并
    $sc = @file_get_contents($repoUrl . 'config_sample.php');
    if ($sc) {
        file_put_contents('ctmp.php', $sc); $tpl=include('ctmp.php'); $old=include('config.php'); unlink('ctmp.php');
        $new = array_replace_recursive($tpl, $old);
        $ver = trim(@file_get_contents($repoUrl . 'version.txt'));
        if($ver) $new['site']['ver'] = $ver;
        saveConfig($new); $log.="✅ Config Merged\n";
    }
    echo json_encode(['status' => $ok?'ok':'err', 'log' => $log]); exit;
}

// 业务逻辑
if ($action === 'do_save_settings') {
    $new = $config;
    if(!isset($new['server'])) $new['server']=[]; if(!isset($new['rcon'])) $new['rcon']=[]; if(!isset($new['rewards'])) $new['rewards']=[];
    
    $new['site']['title'] = $_POST['site_title']; $new['site']['bg'] = $_POST['site_bg'];
    $new['admin']['email'] = $_POST['admin_email'];
    $new['server']['ip'] = $_POST['server_ip']; $new['server']['port'] = $_POST['server_port'];
    $new['rcon']['host'] = $_POST['rcon_host']; $new['rcon']['port'] = $_POST['rcon_port']; $new['rcon']['pass'] = $_POST['rcon_pass'];
    $new['rewards']['reg_cmd'] = $_POST['reg_cmd']; $new['rewards']['daily_cmd'] = $_POST['daily_cmd'];
    
    $new['db']['host']=$_POST['db_host']; $new['db']['name']=$_POST['db_name']; $new['db']['user']=$_POST['db_user'];
    if($_POST['db_pass']) $new['db']['pass']=$_POST['db_pass'];
    $new['smtp']['host']=$_POST['smtp_host']; $new['smtp']['port']=$_POST['smtp_port']; $new['smtp']['user']=$_POST['smtp_user']; 
    if($_POST['smtp_pass']) $new['smtp']['pass']=$_POST['smtp_pass']; $new['smtp']['from_name']=$_POST['smtp_from'];

    if($_POST['admin_pass']) { $new['admin']['user'] = $_POST['admin_user']; $new['admin']['pass'] = $_POST['admin_pass']; }
    header("Location: ?action=dashboard&tab=settings&msg=".(saveConfig($new)?"save_ok":"save_fail")); exit;
}
if ($action === 'add_cdk') {
    $code=trim($_POST['code']); $cmd=trim($_POST['cmd']); $use=(int)$_POST['usage'];
    if($code&&$cmd){$d=getCdks();$d[$code]=['cmd'=>$cmd,'max'=>$use,'used'=>0,'users'=>[]];saveCdks($d);} header("Location: ?action=dashboard&tab=cdk"); exit;
}
if ($action === 'del_cdk') { $c=$_GET['code']; $d=getCdks(); if(isset($d[$c])){unset($d[$c]);saveCdks($d);} header("Location: ?action=dashboard&tab=cdk"); exit; }
if ($action === 'test_mail') { $s=new TinySMTP(); $r=$s->send($config['smtp']['user'],"Test","OK",$config['smtp']); header("Location: ?action=dashboard&tab=settings&msg=".($r?'mail_ok':'mail_fail')); exit; }
if ($action === 'do_change_user_pass') {
    $uid=$_POST['user_id']; $p=$_POST['new_password'];
    if($uid&&$p){ try{ $h=hashAuthMe($p); $pdo->prepare("UPDATE authme SET password=? WHERE id=?")->execute([$h,$uid]); header("Location: ?action=dashboard&tab=users&msg=pass_changed"); }catch(E $e){header("Location: ?msg=err");} } exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>后台管理</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563} .nav-btn.active{background:#eff6ff;color:#2563eb}</style>
</head>
<body>
    <?php if(isset($_GET['msg'])): ?><div class="fixed top-4 left-1/2 -translate-x-1/2 bg-blue-600 text-white px-4 py-2 rounded shadow font-bold z-50"><?= $_GET['msg'] ?></div><?php endif; ?>

    <?php if ($action === 'login'): ?>
    <div class="flex items-center justify-center min-h-screen"><div class="bg-white p-8 rounded shadow-lg w-full max-w-sm">
        <h2 class="text-xl font-bold text-center mb-6">后台验证</h2>
        <form action="?action=do_sys_login" method="POST" class="space-y-4">
            <input name="user" placeholder="账号" class="input" required>
            <input type="password" name="pass" placeholder="密码" class="input" required>
            <button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black">登录</button>
        </form>
        <div class="mt-4 text-center"><a href="index.php" class="text-sm text-gray-400">返回首页</a></div>
    </div></div>
    
    <?php elseif ($action === 'dashboard'): $tab = $_GET['tab'] ?? 'users'; ?>
    <div class="max-w-7xl mx-auto my-8 p-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
            <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                <div class="mb-2 font-extrabold text-2xl text-blue-600 px-2">流星MCS</div>
                <div class="px-2 mb-6 text-xs"><span class="text-gray-400">Ver <?= $config['site']['ver'] ?></span><button onclick="checkUpdate()" id="update-btn" class="ml-2 text-blue-500 hover:underline">检查更新</button></div>
                <nav>
                    <a href="?action=dashboard&tab=users" class="nav-btn <?= $tab=='users'?'active':'' ?>">👥 玩家管理</a>
                    <a href="?action=dashboard&tab=cdk" class="nav-btn <?= $tab=='cdk'?'active':'' ?>">🎁 CDK 管理</a>
                    <a href="?action=dashboard&tab=settings" class="nav-btn <?= $tab=='settings'?'active':'' ?>">⚙️ 系统设置</a>
                    <div class="pt-6 mt-6 border-t"><a href="?action=logout" class="nav-btn text-red-600 hover:bg-red-50">退出登录</a></div>
                </nav>
            </div>
            <div class="flex-1 p-8 overflow-y-auto relative">
                <div id="update-modal" class="hidden absolute inset-0 bg-white/90 z-50 flex items-center justify-center p-4">
                    <div class="bg-white shadow-2xl border p-6 rounded-xl max-w-md w-full text-center">
                        <h3 class="text-xl font-bold mb-2">🚀 发现新版本</h3>
                        <p id="new-ver-txt" class="text-blue-600 font-mono text-lg mb-4"></p>
                        <textarea id="update-log" class="w-full h-32 text-xs border bg-gray-50 p-2 rounded mb-4" readonly></textarea>
                        <div class="flex gap-2 justify-center"><button onclick="doUpdate()" class="bg-green-600 text-white px-4 py-2 rounded font-bold">立即更新</button><button onclick="document.getElementById('update-modal').classList.add('hidden')" class="bg-gray-200 text-gray-700 px-4 py-2 rounded font-bold">取消</button></div>
                    </div>
                </div>

                <?php if ($tab === 'users'): ?>
                    <h3 class="text-xl font-bold mb-6">玩家列表</h3>
                    <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th><th>操作</th></tr><?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?><tr class="border-b"><td class="p-3"><?=$r['id']?></td><td class="p-3 flex items-center gap-3"><img src="https://cravatar.eu/helmavatar/<?=$r['realname']?>/24.png" class="rounded"><?=$r['realname']?></td><td class="p-3 text-gray-500"><?=$r['email']?></td><td class="p-3"><form action="?action=do_change_user_pass" method="POST" class="flex gap-2" onsubmit="return confirm('改密?')"><input type="hidden" name="user_id" value="<?=$r['id']?>"><input name="new_password" class="border rounded px-2 w-24" placeholder="新密码"><button class="text-blue-600 font-bold">改</button></form></td></tr><?php endforeach; endif; ?></table>
                <?php elseif ($tab === 'cdk'): ?>
                    <h3 class="text-xl font-bold mb-6">CDK 管理</h3>
                    <form action="?action=add_cdk" method="POST" class="bg-blue-50 p-4 rounded-lg mb-6 flex gap-3 items-end"><div><label class="text-xs font-bold text-gray-500">代码</label><input name="code" placeholder="VIP666" class="input w-32"></div><div class="flex-1"><label class="text-xs font-bold text-gray-500">指令 (%player% 为玩家)</label><input name="cmd" placeholder="mg give %player% diamond 1" class="input"></div><div><label class="text-xs font-bold text-gray-500">次数</label><input name="usage" type="number" value="1" class="input w-20"></div><button class="bg-blue-600 text-white px-6 py-2 rounded font-bold h-[42px]">生成</button></form>
                    <table class="w-full text-sm text-left border rounded"><tr class="bg-gray-100"><th>代码</th><th>指令</th><th>余/总</th><th>操作</th></tr><?php foreach(getCdks() as $code => $d): ?><tr class="border-b"><td class="p-3 font-mono font-bold text-blue-600"><?= $code ?></td><td class="p-3 text-gray-600 text-xs"><?= $d['cmd'] ?></td><td class="p-3"><?= ($d['max']-$d['used']) ?>/<?= $d['max'] ?></td><td class="p-3"><a href="?action=del_cdk&code=<?=urlencode($code)?>" class="text-red-500 font-bold">删除</a></td></tr><?php endforeach; ?></table>
                <?php elseif ($tab === 'settings'): ?>
                    <form action="?action=do_save_settings" method="POST" class="max-w-3xl space-y-6">
                        <div><h4 class="font-bold border-b pb-2 mb-4">站点基础</h4><div class="grid grid-cols-2 gap-4"><div><label class="text-sm font-bold text-gray-700">网站标题</label><input name="site_title" value="<?=$config['site']['title']?>" class="input"></div><div><label class="text-sm font-bold text-gray-700">管理员邮箱</label><input name="admin_email" value="<?=$config['admin']['email']??''?>" class="input"></div><div class="col-span-2"><label class="text-sm font-bold text-gray-700">背景图 URL</label><input name="site_bg" value="<?=$config['site']['bg']??''?>" class="input"></div></div></div>
                        <div><h4 class="font-bold border-b pb-2 mb-4 text-blue-600">MC 服务器 & RCON</h4><div class="grid grid-cols-4 gap-4"><div class="col-span-2"><input name="server_ip" value="<?=$config['server']['ip']?>" class="input" placeholder="Server IP"></div><div class="col-span-2"><input name="server_port" value="<?=$config['server']['port']?>" class="input" placeholder="Server Port"></div><div class="col-span-2"><input name="rcon_host" value="<?=$config['rcon']['host']?>" class="input" placeholder="RCON IP"></div><div><input name="rcon_port" value="<?=$config['rcon']['port']?>" class="input" placeholder="RCON Port"></div><div><input name="rcon_pass" value="<?=$config['rcon']['pass']?>" type="password" class="input" placeholder="RCON Pass"></div></div></div>
                        <div><h4 class="font-bold border-b pb-2 mb-4 text-green-600">奖励配置</h4><div class="space-y-3"><input name="reg_cmd" value="<?=$config['rewards']['reg_cmd']?>" class="input" placeholder="注册奖励指令"><input name="daily_cmd" value="<?=$config['rewards']['daily_cmd']?>" class="input" placeholder="签到奖励指令"></div></div>
                        <div><h4 class="font-bold border-b pb-2 mb-4 text-gray-500">DB & SMTP <a href="?action=test_mail" class="text-xs bg-gray-200 px-2 py-1 rounded ml-2">测试邮件</a></h4><div class="grid grid-cols-4 gap-2 mb-2"><input name="db_host" value="<?=$config['db']['host']?>" class="input"><input name="db_name" value="<?=$config['db']['name']?>" class="input"><input name="db_user" value="<?=$config['db']['user']?>" class="input"><input name="db_pass" placeholder="DB Pass" type="password" class="input"></div><div class="grid grid-cols-4 gap-2"><input name="smtp_host" value="<?=$config['smtp']['host']?>" class="input"><input name="smtp_user" value="<?=$config['smtp']['user']?>" class="input"><input name="smtp_pass" value="<?=$config['smtp']['pass']?>" type="password" class="input"><input name="smtp_from" value="<?=$config['smtp']['from_name']?>" class="input"></div></div>
                        <button class="bg-blue-600 text-white px-8 py-3 rounded font-bold shadow hover:bg-blue-700">保存所有配置</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function checkUpdate() { let b=document.getElementById('update-btn'); b.innerText='...'; fetch('?action=check_update').then(r=>r.json()).then(d=>{ b.innerText='检查更新'; if(d.status=='new'){ document.getElementById('new-ver-txt').innerText='v'+d.ver; document.getElementById('update-log').value=d.msg; document.getElementById('update-modal').classList.remove('hidden'); }else alert(d.msg); }).catch(e=>{alert('Check Fail');b.innerText='检查更新';}); }
    function doUpdate() { let l=document.getElementById('update-log'); l.value='Updating...'; fetch('?action=do_update').then(r=>r.json()).then(d=>{ l.value=d.log+"\n"+(d.status=='ok'?'🎉 Done! Refreshing...':'❌ Fail'); if(d.status=='ok')setTimeout(()=>location.reload(),2000); }); }
    </script>
    <?php endif; ?>
</body>
</html>
