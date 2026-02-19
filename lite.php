<?php
/**
 * Project: 流星MCS Lite 单文件版 (Extreme Edition)
 * Version: v1.8 (Patched)
 * Note: 集成前台、后台、核心与专属单文件更新机制
 */
session_start();
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

// ==========================================
// 1. 核心逻辑与配置加载 (Core)
// ==========================================
$configFile = 'config.php';
if (!file_exists($configFile) && !defined('IN_INSTALL')) die("Error: config.php missing. 请先运行 install.php");

$config = [];
if (file_exists($configFile)) {
    $defaultConfig = [
        'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
        'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
        'admin' => ['user'=>'admin', 'pass'=>'password123', 'email'=>''],
        'site' => ['title'=>'流星MCS', 'ver'=>'1.8', 'bg'=>''],
        'display' => ['ip'=>'', 'port'=>'25565'], 
        'servers' => [['name'=>'Default', 'ip'=>'127.0.0.1', 'port'=>25565, 'rcon_port'=>25575, 'rcon_pass'=>'']],
        'rewards' => ['reg_cmd'=>'', 'daily_cmd'=>'']
    ];
    $loaded = include($configFile);
    $config = isset($loaded['host']) ? array_replace_recursive($defaultConfig, ['db'=>$loaded]) : array_replace_recursive($defaultConfig, $loaded);
}

if (empty($config['display']['ip']) && !empty($config['servers'][0]['ip'])) {
    $config['display']['ip'] = $config['servers'][0]['ip'];
    $config['display']['port'] = $config['servers'][0]['port'];
}

$pdo = null;
if (!empty($config['db']['name'])) {
    try {
        $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4", $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {}
}

// 核心工具函数
function saveConfig($newConfig) { global $configFile; return file_put_contents($configFile, "<?php\nreturn " . var_export($newConfig, true) . ";"); }
function hashAuthMe($p) { $s = bin2hex(random_bytes(8)); return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s); }
function verifyAuthMe($p, $hash) { $parts=explode('$', $hash); if(count($parts)===4&&$parts[1]==='SHA') return hash('sha256',hash('sha256',$p).$parts[2])===$parts[3]; return false; }

// RCON 与 SMTP 类
class TinyRcon {
    private $sock; private $id=0;
    public function connect($h,$p,$pw){ $this->sock=@fsockopen($h,$p,$e,$r,3); if(!$this->sock)return false; $this->write(3,$pw); return $this->read(); }
    public function cmd($c){ $this->write(2,$c); return $this->read(); }
    private function write($t,$d){ $p=pack("VV",++$this->id,$t).$d."\x00\x00"; fwrite($this->sock,pack("V",strlen($p)).$p); }
    private function read(){ $s=fread($this->sock,4); if(strlen($s)<4)return false; $l=unpack("V",$s)[1]; if($l>4096)$l=4096; return substr(fread($this->sock,$l),8,-2); }
}
function runRcon($cmd, $serverIdx = 0) {
    global $config; if (!isset($config['servers'][$serverIdx])) return false;
    $s = $config['servers'][$serverIdx]; if (empty($s['rcon_pass']) || empty($cmd)) return false;
    $r = new TinyRcon(); if ($r->connect($s['ip'], $s['rcon_port'], $s['rcon_pass'])) return $r->cmd($cmd); return false;
}
class TinySMTP {
    private $sock;
    public function send($to,$sub,$body,$conf){
        if(!$to)return false; $h=($conf['secure']=='ssl'?'ssl://':'').$conf['host']; $this->sock=fsockopen($h,$conf['port']); if(!$this->sock)return false;
        $this->cmd(NULL); $this->cmd("EHLO ".$conf['host']); $this->cmd("AUTH LOGIN");
        $this->cmd(base64_encode($conf['user'])); $this->cmd(base64_encode($conf['pass']));
        $this->cmd("MAIL FROM: <{$conf['user']}>"); $this->cmd("RCPT TO: <$to>"); $this->cmd("DATA");
        $head="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?".base64_encode($conf['from_name'])."?= <{$conf['user']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?".base64_encode($sub)."?=\r\n";
        fwrite($this->sock,"$head\r\n$body\r\n.\r\n"); $this->cmd("QUIT"); fclose($this->sock); return true;
    }
    private function cmd($c){ if($c)fwrite($this->sock,$c."\r\n"); while($s=fgets($this->sock,515)){if(substr($s,3,1)==" ")break;} }
}

// JSON 数据存储 (加入防高并发破损 LOCK_EX)
$userDataFile='user_data.json'; $cdkFile='cdk_data.json'; $limitFile='login_limit.json';
function getUserData($u){ global $userDataFile; $d=file_exists($userDataFile)?json_decode(file_get_contents($userDataFile),true):[]; return $d[$u]??[]; }
function setUserData($u,$k,$v){ global $userDataFile; $d=file_exists($userDataFile)?json_decode(file_get_contents($userDataFile),true):[]; $d[$u][$k]=$v; file_put_contents($userDataFile,json_encode($d), LOCK_EX); }
function getCdks(){ global $cdkFile; return file_exists($cdkFile)?json_decode(file_get_contents($cdkFile),true):[]; }
function saveCdks($d){ global $cdkFile; file_put_contents($cdkFile,json_encode($d), LOCK_EX); }
function updateCdk($c,$d){ $all=getCdks(); $all[$c]=$d; saveCdks($all); }
function checkLock($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; foreach($d as $k=>$v){if(time()-$v['t']>3600)unset($d[$k]);} if(isset($d[$ip])&&$d[$ip]['c']>=3&&time()-$d[$ip]['t']<3600)return true; return false; }
function logFail($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; if(!isset($d[$ip]))$d[$ip]=['c'=>0,'t'=>time()]; $d[$ip]['c']++; $d[$ip]['t']=time(); file_put_contents($f,json_encode($d)); return $d[$ip]['c']; }
function clearFail($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(isset($d[$ip])){unset($d[$ip]);file_put_contents($f,json_encode($d));} }

// ==========================================
// 2. 路由分发 (Routing)
// ==========================================
$action = $_GET['action'] ?? ($_GET['a'] ?? 'home');
$isAdminRoute = (isset($_GET['a']) && $_GET['a'] === 'admin') || in_array($action, ['do_sys_login', 'admin_logout', 'check_update', 'do_update', 'do_rcon_cmd', 'do_save_settings', 'add_cdk', 'del_cdk']);

if ($isAdminRoute) {
    // ==========================================
    // 3A. 后台管理系统 (Admin Module)
    // ==========================================
    $repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';
    
    if (checkLock($limitFile) && $action === 'do_sys_login') die("<h1>🚫 IP Locked</h1>");
    if ($action === 'admin_logout') { unset($_SESSION['is_admin']); header("Location: ?a=admin"); exit; }
    
    if ($action === 'do_sys_login') {
        if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) {
            clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?a=admin");
        } else { $c = logFail($limitFile); header("Location: ?a=admin&msg=err_auth&rem=".(3-$c)); } exit;
    }
    
    if ($action !== 'admin' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?a=admin"); exit; }

    if ($action === 'check_update') {
        $remoteVer = @file_get_contents($repoUrl . 'version.txt');
        if ($remoteVer === false) echo json_encode(['status' => 'err', 'msg' => '连接 GitHub 失败']);
        else {
            $remoteVer = trim($remoteVer); $currentVer = $config['site']['ver'];
            if (version_compare($remoteVer, $currentVer, '>')) echo json_encode(['status' => 'new', 'ver' => $remoteVer, 'msg' => "发现新版本 v$remoteVer"]);
            else echo json_encode(['status' => 'latest', 'msg' => '已是最新']);
        } exit;
    }
    
    if ($action === 'do_update') {
        // 🔥 单文件专属更新逻辑：仅拉取 lite.php 覆盖自身
        $c = @file_get_contents($repoUrl . 'lite.php');
        $log = ""; $ok = true;
        if ($c) { 
            if(file_put_contents(__FILE__, $c)) $log.="✅ 单文件核心(lite.php)自我更新成功\n"; 
            else { $ok=false; $log.="❌ 覆盖核心失败，请检查文件写入权限\n"; }
        } else { $ok=false; $log.="❌ 无法拉取 lite.php 源码\n"; }
        
        $sc = @file_get_contents($repoUrl . 'config_sample.php');
        if ($sc) {
            file_put_contents('ctmp.php', $sc); $tpl=include('ctmp.php'); $old=include('config.php'); @unlink('ctmp.php');
            $new = array_replace_recursive($tpl, $old);
            $ver = trim(@file_get_contents($repoUrl . 'version.txt'));
            if($ver) $new['site']['ver'] = $ver; 
            saveConfig($new); $log.="✅ 配置文件与版本号已同步升级\n";
        }
        echo json_encode(['status' => $ok?'ok':'err', 'log' => $log]); exit;
    }

    if ($action === 'do_rcon_cmd') { $res=runRcon($_POST['cmd'],(int)$_POST['server_id']); echo json_encode(['res'=>$res===false?"连接失败":($res?:"指令已发送")]); exit; }
    
    if ($action === 'do_save_settings') {
        $new=$config; $new['site']['title']=$_POST['site_title']; $new['site']['bg']=$_POST['site_bg'];
        if(!empty($_POST['servers_json'])) { $parsed = json_decode($_POST['servers_json'], true); if(is_array($parsed)) $new['servers'] = $parsed; }
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
        if(isset($_POST['rcon_host'])) $new['rcon']['host']=$_POST['rcon_host'];
        if(isset($_POST['rcon_port'])) $new['rcon']['port']=$_POST['rcon_port'];
        if(!empty($_POST['rcon_pass'])) $new['rcon']['pass']=$_POST['rcon_pass'];
        saveConfig($new); header("Location: ?a=admin&tab=settings&msg=save_ok"); exit;
    }
    
    if ($action === 'add_cdk') { $d=getCdks(); $d[$_POST['code']]=['cmd'=>$_POST['cmd'],'max'=>(int)$_POST['usage'],'server_id'=>$_POST['server_id'],'used'=>0,'users'=>[]]; saveCdks($d); header("Location: ?a=admin&tab=cdk"); exit; }
    if ($action === 'del_cdk') { $d=getCdks(); unset($d[$_GET['code']]); saveCdks($d); header("Location: ?a=admin&tab=cdk"); exit; }

    // --- 后台 UI 输出 ---
    $tab = $_GET['tab'] ?? 'users';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>单文件后台 v1.8</title><script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563} .nav-btn.active{background:#eff6ff;color:#2563eb}</style></head>
    <body>
        <?php if (!isset($_SESSION['is_admin'])): ?>
        <div class="flex items-center justify-center min-h-screen"><div class="bg-white p-8 rounded shadow-lg w-full max-w-sm"><h2 class="text-xl font-bold text-center mb-6">后台验证</h2><form action="?action=do_sys_login" method="POST" class="space-y-4"><input name="user" placeholder="账号" class="input" required><input type="password" name="pass" placeholder="密码" class="input" required><button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black">登录</button></form></div></div>
        <?php else: ?>
        <div class="max-w-7xl mx-auto my-8 p-4">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
                <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                    <div class="mb-8 font-extrabold text-2xl text-blue-600 px-2">流星 AWP <span class="text-xs bg-red-500 text-white px-1 rounded block mt-1 w-max">LITE EDITION</span><span class="text-xs text-gray-400 block font-normal">v<?= htmlspecialchars($config['site']['ver']) ?></span></div>
                    <button onclick="checkUpdate()" id="u-btn" class="mb-4 text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded w-full">检查核心更新</button>
                    <nav>
                        <a href="?a=admin&tab=users" class="nav-btn <?= $tab=='users'?'active':'' ?>">👥 玩家管理</a>
                        <a href="?a=admin&tab=console" class="nav-btn <?= $tab=='console'?'active':'' ?>">🖥️ RCON终端</a>
                        <a href="?a=admin&tab=cdk" class="nav-btn <?= $tab=='cdk'?'active':'' ?>">🎁 CDK 管理</a>
                        <a href="?a=admin&tab=settings" class="nav-btn <?= $tab=='settings'?'active':'' ?>">⚙️ 系统设置</a>
                        <div class="pt-6 mt-6 border-t"><a href="?action=admin_logout" class="nav-btn text-red-600">安全退出</a></div>
                    </nav>
                </div>
                <div class="flex-1 p-8 overflow-y-auto relative">
                    <div id="u-modal" class="hidden absolute inset-0 bg-white/90 z-50 flex items-center justify-center"><div class="bg-white border shadow-xl p-6 rounded text-center w-96"><h3 class="font-bold text-lg mb-2">发现新版本</h3><p id="u-ver" class="text-blue-600 mb-4 font-mono"></p><div class="flex gap-2 justify-center"><button onclick="doUp()" class="bg-green-600 text-white px-4 py-2 rounded">更新</button><button onclick="document.getElementById('u-modal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">取消</button></div></div></div>
                    
                    <?php if ($tab === 'users'): ?>
                        <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th></tr><?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?><tr class="border-b"><td class="p-3"><?=$r['id']?></td><td class="p-3"><?=htmlspecialchars($r['realname'])?></td><td class="p-3"><?=htmlspecialchars($r['email'])?></td></tr><?php endforeach; endif; ?></table>
                    <?php elseif ($tab === 'console'): ?>
                        <div class="flex gap-2 mb-2"><select id="cs" class="input w-48"><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><input id="cc" class="input flex-1" placeholder="Command..."><button onclick="sc()" class="bg-black text-white px-4 rounded">Send</button></div><textarea id="cl" class="w-full h-96 bg-gray-900 text-green-400 p-4 rounded text-xs font-mono" readonly></textarea><script>function sc(){let c=document.getElementById('cc').value,s=document.getElementById('cs').value,l=document.getElementById('cl');if(!c)return;l.value+=`> ${c}\n`;fetch('?action=do_rcon_cmd',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cmd=${c}&server_id=${s}`}).then(r=>r.json()).then(d=>{l.value+=d.res+"\n\n";l.scrollTop=l.scrollHeight});document.getElementById('cc').value=''}</script>
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
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">多服务器群组 (JSON)</div>
                            <div><label class="text-xs font-bold">多服后端 RCON 列表</label><textarea name="servers_json" class="input h-24 font-mono text-xs"><?=json_encode($config['servers'])?></textarea></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">数据库连接 (AuthMe)</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">DB Host</label><input name="db_host" value="<?=$config['db']['host']?>" class="input"></div><div><label class="text-xs font-bold">DB Name</label><input name="db_name" value="<?=$config['db']['name']?>" class="input"></div><div><label class="text-xs font-bold">DB User</label><input name="db_user" value="<?=$config['db']['user']?>" class="input"></div><div><label class="text-xs font-bold">DB Pass (留空不修改)</label><input type="password" name="db_pass" placeholder="***" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">邮件推送 (SMTP)</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">SMTP Host</label><input name="smtp_host" value="<?=$config['smtp']['host']?>" class="input"></div><div><label class="text-xs font-bold">SMTP Port</label><input name="smtp_port" value="<?=$config['smtp']['port']?>" class="input"></div><div><label class="text-xs font-bold">SMTP User</label><input name="smtp_user" value="<?=$config['smtp']['user']?>" class="input"></div><div><label class="text-xs font-bold">SMTP Pass (留空不修改)</label><input type="password" name="smtp_pass" placeholder="***" class="input"></div><div><label class="text-xs font-bold">发件人名称</label><input name="smtp_from" value="<?=$config['smtp']['from_name']?>" class="input"></div><div><label class="text-xs font-bold">加密方式 (ssl/tls)</label><input name="smtp_secure" value="<?=$config['smtp']['secure'] ?? 'ssl'?>" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">管理员与全局设置</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">管理员账号</label><input name="admin_user" value="<?=$config['admin']['user']?>" class="input"></div><div><label class="text-xs font-bold">管理员密码 (留空不修改)</label><input type="password" name="admin_pass" placeholder="***" class="input"></div><div class="col-span-2"><label class="text-xs font-bold">管理员邮箱</label><input name="admin_email" value="<?=$config['admin']['email'] ?? ''?>" class="input"></div></div>
                            <button class="w-full bg-green-600 text-white px-6 py-3 mt-4 rounded font-bold hover:bg-green-700 transition shadow">保存所有设置</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        function checkUpdate(){let b=document.getElementById('u-btn');b.innerText='...';fetch('?action=check_update').then(r=>r.json()).then(d=>{b.innerText='检查核心更新';if(d.status=='new'){document.getElementById('u-ver').innerText=d.ver;document.getElementById('u-modal').classList.remove('hidden')}else alert(d.msg)})}
        function doUp(){fetch('?action=do_update').then(r=>r.json()).then(d=>{alert(d.log);location.reload()})}
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;

} else {
    // ==========================================
    // 3B. 前台用户中心 (Frontend Module)
    // ==========================================

    if ($action === 'do_login') {
        $u = strtolower(trim($_POST['username'])); $p = $_POST['password'];
        $stmt = $pdo->prepare("SELECT * FROM authme WHERE username=?"); $stmt->execute([$u]);
        if ($r = $stmt->fetch()) {
            if (verifyAuthMe($p, $r['password'])) { $_SESSION['user'] = $r; header("Location: ?action=user_center"); } 
            else header("Location: ?action=login&msg=err_pass");
        } else header("Location: ?action=login&msg=err_user");
        exit;
    }

    if ($action === 'do_logout') { session_destroy(); header("Location: ?"); exit; }

    if ($action === 'do_reg') {
        if (empty($_SESSION['captcha']) || $_POST['captcha'] != $_SESSION['captcha']) { header("Location: ?msg=err_captcha"); exit; }
        $u = strtolower(trim($_POST['username'])); $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("SELECT id FROM authme WHERE username=?"); $stmt->execute([$u]);
        if ($stmt->fetch()) { header("Location: ?msg=err_exists"); exit; }
        
        $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")
            ->execute([$u, $_POST['username'], hashAuthMe($_POST['password']), $_POST['email'], $ip, time()*1000, time()*1000]);
        
        if (!empty($config['rewards']['reg_cmd'])) { runRcon(str_replace('%player%', $_POST['username'], $config['rewards']['reg_cmd']), 0); }
        $smtp = new TinySMTP(); $smtp->send($_POST['email'], "欢迎加入", "恭喜注册成功！", $config['smtp']);
        header("Location: ?msg=reg_ok"); exit;
    }

    if ($action === 'do_sign' && isset($_SESSION['user'])) {
        $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd');
        if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; }
        $targets = $config['rewards']['sign_in_servers'] ?? []; $ok = 0;
        foreach ($targets as $sid) { if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']), $sid)) $ok++; }
        
        if ($ok > 0) {
            setUserData($u, 'last_sign', $today); $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count);
            echo json_encode(['s'=>1, 'm'=>"✅ 签到成功 (发放至 $ok 个服务器)"]);
        } else { echo json_encode(['s'=>0, 'm'=>'❌ 服务器连接失败']); } exit;
    }

    if ($action === 'do_cdk' && isset($_SESSION['user'])) {
        $code = trim($_POST['code']); $srvIdx = (int)$_POST['server_id']; $u = $_SESSION['user']['username']; $cdks = getCdks();
        if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; }
        $c = $cdks[$code];
        if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 已被抢光']); exit; }
        if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 您已领取过']); exit; }
        if (isset($c['server_id']) && $c['server_id'] !== 'all' && (int)$c['server_id'] !== $srvIdx) { echo json_encode(['s'=>0,'m'=>'❌ 此CDK不适用于该服务器']); exit; }
        
        $targetSrv = ($c['server_id'] === 'all') ? $srvIdx : (int)$c['server_id'];
        if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']), $targetSrv)) {
            $c['used']++; $c['users'][] = $u; updateCdk($code, $c); echo json_encode(['s'=>1,'m'=>'🎁 兑换成功！']);
        } else { echo json_encode(['s'=>0,'m'=>'❌ 发放失败']); } exit;
    }

    if ($action === 'do_fp_send') {
        $u = strtolower(trim($_POST['u'])); $e = trim($_POST['e']);
        $stmt = $pdo->prepare("SELECT id, email FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch();
        if (!$r || $r['email'] !== $e) { echo json_encode(['s'=>0, 'm'=>'❌ 用户名与邮箱不匹配']); exit; }
        
        $code = rand(100000, 999999); $t = time();
        try { $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]); } catch (PDOException $e) {
            if ($e->getCode() == '42S22') { 
                $pdo->exec("ALTER TABLE authme ADD COLUMN reset_code VARCHAR(10), ADD COLUMN reset_time BIGINT");
                $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]);
            } else { echo json_encode(['s'=>0, 'm'=>'❌ 数据库异常']); exit; }
        }
        $smtp = new TinySMTP(); $smtp->send($e, "重置密码验证码", "您的验证码是: <b>$code</b> (10分钟内有效)", $config['smtp']);
        echo json_encode(['s'=>1, 'm'=>'✅ 验证码已发送至邮箱']); exit;
    }

    if ($action === 'do_fp_reset') {
        $u = strtolower(trim($_POST['u'])); $c = trim($_POST['code']); $p = $_POST['pass'];
        $stmt = $pdo->prepare("SELECT id, reset_code, reset_time FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch();
        if (!$r || $r['reset_code'] !== $c) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码错误']); exit; }
        if (time() - $r['reset_time'] > 600) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码已过期']); exit; }
        $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE id=?")->execute([hashAuthMe($p), $r['id']]);
        echo json_encode(['s'=>1, 'm'=>'🎉 密码修改成功！请登录']); exit;
    }

    if ($action === 'captcha') { 
        $c=rand(1000,9999); $_SESSION['captcha']=$c;
        $i=imagecreatetruecolor(70,36); imagefill($i,0,0,0x3b82f6); imagestring($i,5,15,10,$c,0xffffff);
        header("Content-type: image/png"); imagepng($i); exit; 
    }

    // --- 前台 UI 输出 ---
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= htmlspecialchars($config['site']['title']) ?></title><script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { background: url('<?= $config['site']['bg'] ?: "https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920" ?>') no-repeat center center fixed; background-size: cover; }
            .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255,255,255,0.5); }
            .input { width: 100%; padding: 0.7rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: rgba(255,255,255,0.8); outline: none; transition: 0.2s; }
            .input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
            .btn-primary { background: #2563eb; color: white; font-weight: bold; padding: 0.75rem; border-radius: 0.5rem; width: 100%; transition: transform 0.1s; }
            .btn-primary:active { transform: scale(0.98); }
            .hidden { display: none; }
            .fade-in { animation: fadeIn 0.3s ease-in; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

        <?php if(isset($_GET['msg'])): ?>
        <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
            <?= ['reg_ok'=>'🎉 注册成功！', 'err_pass'=>'🔒 密码错误', 'err_exists'=>'⚠️ 账号已存在', 'err_captcha'=>'❌ 验证码错误'][$_GET['msg']] ?? $_GET['msg'] ?>
        </div>
        <?php endif; ?>

        <?php if ($action === 'user_center' && isset($_SESSION['user'])): $user=$_SESSION['user']; $udata=getUserData($user['username']); ?>
        <div class="glass-card w-full max-w-md p-8 fade-in">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200">
                <img src="https://cravatar.eu/helmavatar/<?=$user['realname']?>/64.png" class="w-16 h-16 rounded-xl shadow-md">
                <div>
                    <h2 class="text-xl font-bold text-gray-800"><?=$user['realname']?></h2>
                    <div class="text-sm text-gray-500">签到: <span class="font-bold text-blue-600"><?=$udata['sign_count']??0?></span> 天</div>
                </div>
                <a href="?action=do_logout" class="ml-auto text-xs bg-red-50 text-red-500 px-3 py-2 rounded hover:bg-red-100 transition">退出</a>
            </div>

            <button onclick="sign(this)" class="w-full mb-6 py-3 rounded-xl font-bold shadow transition border <?= ($udata['last_sign']??0)==date('Ymd') ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-indigo-50 text-indigo-600 border-indigo-100 hover:bg-indigo-100' ?>">
                <?= ($udata['last_sign']??0)==date('Ymd') ? '✅ 今日已签到' : '📅 每日签到' ?>
            </button>

            <div class="space-y-3">
                <label class="text-xs font-bold text-gray-400 uppercase">CDK 兑换</label>
                <select id="sel_srv" class="input font-bold text-blue-900">
                    <?php foreach($config['servers'] as $idx => $srv): ?>
                        <option value="<?=$idx?>">🌍 <?= htmlspecialchars($srv['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <input id="cdk" placeholder="输入兑换码..." class="input">
                    <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700 transition">兑换</button>
                </div>
            </div>
        </div>
        <script>
        function sign(b){ b.disabled=true; b.innerText='...'; fetch('?action=do_sign').then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) { b.innerText='✅ 已签到'; b.className='w-full mb-6 py-3 rounded-xl font-bold shadow transition border bg-gray-100 text-gray-400 cursor-not-allowed'; } else b.disabled=false; }); }
        function cdk(){ let c=document.getElementById('cdk').value; let s=document.getElementById('sel_srv').value; if(!c)return; fetch('?action=do_cdk',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`code=${c}&server_id=${s}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); }
        </script>

        <?php else: ?>
        <div class="glass-card w-full max-w-sm p-8 text-center relative fade-in">
            <h1 class="text-3xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600 pb-1">
                <?= htmlspecialchars($config['site']['title']) ?>
            </h1>

            <?php if(!empty($config['display']['ip'])): ?>
            <div id="status" class="hidden bg-white/60 p-2 rounded-lg mb-6 flex items-center gap-3 border border-white/50 text-left">
                <img id="icon" src="" class="w-10 h-10 rounded bg-gray-200">
                <div class="flex-1 min-w-0">
                    <div id="motd" class="text-xs text-gray-500 truncate">Loading...</div>
                    <div id="online" class="text-sm font-bold text-green-600">Connecting...</div>
                </div>
            </div>
            <script>
                fetch('https://api.mcsrvstat.us/2/<?= $config['display']['ip'] ?>:<?= $config['display']['port'] ?>').then(r=>r.json()).then(d=>{
                    document.getElementById('status').classList.remove('hidden');
                    document.getElementById('icon').src = d.icon || `https://api.mcsrvstat.us/icon/<?= $config['display']['ip'] ?>`;
                    document.getElementById('online').innerText = d.online ? `🟢 ${d.players.online} 人在线` : '🔴 服务器离线';
                    if(d.online) document.getElementById('motd').innerText = d.motd.clean.join(' ');
                });
            </script>
            <?php endif; ?>
            
            <div id="box-reg">
                <h2 class="text-xl font-bold text-gray-700 mb-4">新用户注册</h2>
                <form action="?action=do_reg" method="POST" class="space-y-3">
                    <input name="username" placeholder="Minecraft 角色名" class="input" required>
                    <input name="email" type="email" placeholder="电子邮箱 (用于找回密码)" class="input" required>
                    <input type="password" name="password" placeholder="设置密码" class="input" required>
                    <div class="flex gap-2">
                        <input name="captcha" placeholder="验证码" class="input" required>
                        <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded cursor-pointer border border-gray-200">
                    </div>
                    <button class="btn-primary mt-2 bg-gradient-to-r from-green-500 to-emerald-600 border-none">确认注册</button>
                </form>
                <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">已有账号？点击登录</a></p>
            </div>

            <div id="box-login" class="hidden">
                <h2 class="text-xl font-bold text-gray-700 mb-4">玩家登录</h2>
                <form action="?action=do_login" method="POST" class="space-y-4">
                    <input name="username" placeholder="游戏角色名" class="input" required>
                    <input type="password" name="password" placeholder="密码" class="input" required>
                    <button class="btn-primary shadow-lg shadow-blue-500/30">立即登录</button>
                </form>
                <div class="mt-6 flex justify-between text-sm">
                    <a href="#" onclick="toggle('box-reg')" class="text-gray-400 hover:text-gray-600">注册账号</a>
                    <a href="#" onclick="toggle('box-fp')" class="text-blue-600 font-bold hover:underline">忘记密码?</a>
                </div>
            </div>

            <div id="box-fp" class="hidden">
                <h2 class="text-xl font-bold text-gray-700 mb-4">重置密码</h2>
                <div class="space-y-3 text-left">
                    <input id="fp_u" placeholder="您的游戏名" class="input">
                    <div class="flex gap-2">
                        <input id="fp_e" placeholder="绑定的邮箱" class="input">
                        <button onclick="sendCode()" class="bg-gray-500 text-white px-3 rounded text-xs whitespace-nowrap hover:bg-gray-600">发送验证码</button>
                    </div>
                    <input id="fp_c" placeholder="邮箱收到的验证码" class="input">
                    <input id="fp_p" type="password" placeholder="设置新密码" class="input">
                    <button onclick="doReset()" class="btn-primary bg-orange-500 hover:bg-orange-600 border-none">提交重置</button>
                </div>
                <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">返回登录</a></p>
            </div>
        </div>
        <script>
        function toggle(id) { 
            ['box-login','box-reg','box-fp'].forEach(x => document.getElementById(x).classList.add('hidden')); 
            document.getElementById(id).classList.remove('hidden'); 
        }
        function sendCode() {
            let u=document.getElementById('fp_u').value, e=document.getElementById('fp_e').value;
            if(!u || !e) { alert('请填写用户名和邮箱'); return; }
            fetch('?action=do_fp_send', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&e=${e}` })
            .then(r=>r.json()).then(d => alert(d.m));
        }
        function doReset() {
            let u=document.getElementById('fp_u').value, c=document.getElementById('fp_c').value, p=document.getElementById('fp_p').value;
            if(!c || !p) { alert('请填写完整信息'); return; }
            fetch('?action=do_fp_reset', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&code=${c}&pass=${p}` })
            .then(r=>r.json()).then(d => { alert(d.m); if(d.s) toggle('box-login'); });
        }
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
?>
