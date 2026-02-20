<?php
/**
 * Project: Meteor Nexus (流星枢纽) Lite 单文件版
 * Version: v2.1.4 (Preview Link Fixed Edition)
 */
session_start();
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

// ==========================================
// 1. 核心逻辑与外部配置加载
// ==========================================
$configFile = 'config.php';
if (!file_exists($configFile)) {
    die("<!DOCTYPE html><html><body style='text-align:center;padding-top:50px;font-family:sans-serif;color:#333;'><h1 style='color:#eab308;'>⚠️ 找不到 config.php</h1><p>系统未初始化，请先运行 <b>install.php</b> 完成安装。</p></body></html>");
}
$config = include($configFile);
if (empty($config['display']['ip']) && !empty($config['servers'][0]['ip'])) { $config['display']['ip'] = $config['servers'][0]['ip']; $config['display']['port'] = $config['servers'][0]['port']; }

$pdo = null; $dbError = '';
if (!empty($config['db']['name'])) {
    try { $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4", $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
    catch (PDOException $e) { $dbError = $e->getMessage(); }
}

function saveConfig($newConfig) { global $configFile; return file_put_contents($configFile, "<?php\nreturn " . var_export($newConfig, true) . ";"); }
function hashAuthMe($p) { $s = bin2hex(random_bytes(8)); return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s); }
function verifyAuthMe($p, $hash) { $parts=explode('$', $hash); if(count($parts)===4&&$parts[1]==='SHA') return hash('sha256',hash('sha256',$p).$parts[2])===$parts[3]; return false; }

function runApiCmd($cmd, $serverIdx = 0) {
    global $config; if (!isset($config['servers'][$serverIdx])) return false;
    $s = $config['servers'][$serverIdx]; if (empty($s['api_key']) || empty($cmd)) return false;
    $port = $s['api_port'] ?? 8080; $url = "http://{$s['ip']}:{$port}/api/execute";
    $ch = curl_init($url); $payload = json_encode(['action' => 'command', 'command' => $cmd]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $s['api_key'], 'X-MetorCore-Key: ' . $s['api_key'], 'User-Agent: MeteorNexus/2.1.4-Lite']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($httpCode === 200) { $data = json_decode($response, true); return $data['result'] ?? "指令执行成功"; } return false;
}

class TinySMTP {
    private $sock;
    public function send($to,$sub,$body,$conf){
        if(!$to)return false; $h=($conf['secure']=='ssl'?'ssl://':'').$conf['host']; $this->sock=fsockopen($h,$conf['port']); if(!$this->sock)return false;
        $this->cmd(NULL); $this->cmd("EHLO ".$conf['host']); $this->cmd("AUTH LOGIN"); $this->cmd(base64_encode($conf['user'])); $this->cmd(base64_encode($conf['pass']));
        $this->cmd("MAIL FROM: <{$conf['user']}>"); $this->cmd("RCPT TO: <$to>"); $this->cmd("DATA");
        $head="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?".base64_encode($conf['from_name'])."?= <{$conf['user']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?".base64_encode($sub)."?=\r\n";
        fwrite($this->sock,"$head\r\n$body\r\n.\r\n"); $this->cmd("QUIT"); fclose($this->sock); return true;
    }
    private function cmd($c){ if($c)fwrite($this->sock,$c."\r\n"); while($s=fgets($this->sock,515)){if(substr($s,3,1)==" ")break;} }
}

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
// 2. 全局智能路由引擎
// ==========================================
$action = $_GET['action'] ?? ($_GET['a'] ?? 'home');
$isAdminRoute = (isset($_GET['a']) && $_GET['a'] === 'admin') || in_array($action, ['do_sys_login', 'admin_logout', 'check_update', 'do_update', 'do_api_cmd', 'do_save_settings', 'add_cdk', 'del_cdk', 'del_user', 'edit_user_pass', 'add_server', 'del_server', 'do_upload_official', 'do_save_official']);

if ($isAdminRoute) {
    // --- 3A. 后台系统代码 ---
    $repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';
    if (checkLock($limitFile) && $action === 'do_sys_login') die("<h1>🚫 IP Locked</h1>");
    if ($action === 'admin_logout') { unset($_SESSION['is_admin']); header("Location: ?a=admin"); exit; }
    if ($action === 'do_sys_login') { if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) { clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?a=admin"); } else { $c = logFail($limitFile); header("Location: ?a=admin&msg=err_auth&rem=".(3-$c)); } exit; }
    if ($action !== 'admin' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?a=admin"); exit; }

    if ($action === 'check_update') { $remoteVer = @file_get_contents($repoUrl . 'version.txt'); if ($remoteVer === false) echo json_encode(['status' => 'err', 'msg' => '连接 GitHub 失败']); else { $remoteVer = trim($remoteVer); $currentVer = $config['site']['ver']; if (version_compare($remoteVer, $currentVer, '>')) echo json_encode(['status' => 'new', 'ver' => $remoteVer, 'msg' => "发现新版本 v$remoteVer"]); else echo json_encode(['status' => 'latest', 'msg' => '已是最新']); } exit; }
    if ($action === 'do_update') { $c = @file_get_contents($repoUrl . 'lite.php'); $log = ""; $ok = true; if ($c) { if(file_put_contents(__FILE__, $c)) $log.="✅ 单文件核心(lite.php)自我更新成功\n"; else { $ok=false; $log.="❌ 覆盖核心失败，请检查文件写入权限\n"; } } else { $ok=false; $log.="❌ 无法拉取 lite.php 源码\n"; } $sc = @file_get_contents($repoUrl . 'config_sample.php'); if ($sc) { file_put_contents('ctmp.php', $sc); $tpl=include('ctmp.php'); $old=include('config.php'); @unlink('ctmp.php'); $new = array_replace_recursive($tpl, $old); $ver = trim(@file_get_contents($repoUrl . 'version.txt')); if($ver) $new['site']['ver'] = $ver; saveConfig($new); $log.="✅ 配置文件架构已同步升级\n"; } echo json_encode(['status' => $ok?'ok':'err', 'log' => $log]); exit; }

    if ($action === 'del_user') { $id = (int)$_GET['id']; if ($pdo && $id > 0) { $pdo->prepare("DELETE FROM authme WHERE id=?")->execute([$id]); } header("Location: ?a=admin&tab=users&msg=del_ok"); exit; }
    if ($action === 'edit_user_pass') { $id = (int)$_POST['id']; $newPass = $_POST['new_pass']; if ($pdo && !empty($newPass) && $id > 0) { $pdo->prepare("UPDATE authme SET password=? WHERE id=?")->execute([hashAuthMe($newPass), $id]); } header("Location: ?a=admin&tab=users&msg=pass_ok"); exit; }
    if ($action === 'do_api_cmd') { $res=runApiCmd($_POST['cmd'],(int)$_POST['server_id']); echo json_encode(['res'=>$res===false?"安全通讯握手失败":($res?:"指令已发送")]); exit; }
    if ($action === 'add_server') { $new = $config; $new['servers'][] = ['name' => $_POST['name'], 'ip' => $_POST['ip'], 'port' => (int)$_POST['port'], 'api_port' => (int)$_POST['api_port'], 'api_key' => $_POST['api_key']]; saveConfig($new); header("Location: ?a=admin&tab=servers"); exit; }
    if ($action === 'del_server') { $new = $config; $idx = (int)$_GET['id']; if (isset($new['servers'][$idx])) { unset($new['servers'][$idx]); $new['servers'] = array_values($new['servers']); saveConfig($new); } header("Location: ?a=admin&tab=servers"); exit; }

    if ($action === 'do_upload_official') {
        if (!class_exists('ZipArchive')) { header("Location: ?a=admin&tab=official&msg=err_nozip"); exit; }
        if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] == 0) {
            $zip = new ZipArchive;
            if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
                $blacklist = ['admin.php', 'core.php', 'config.php', 'install.php', 'lite.php', 'config_sample.php', 'user_data.json', 'cdk_data.json', 'login_limit.json', '.htaccess'];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i); $base = strtolower(basename($name));
                    if (empty($base) || strpos($name, '../') !== false) continue; 
                    if ($base === 'index.html' || $base === 'index.htm') { file_put_contents('official.html', $zip->getFromIndex($i)); continue; }
                    if ($base === 'index.php') { file_put_contents('official.php', $zip->getFromIndex($i)); continue; }
                    if (in_array($base, $blacklist)) continue; 
                    $zip->extractTo('./', array($name));
                }
                $zip->close(); header("Location: ?a=admin&tab=official&msg=zip_ok"); exit;
            } else { header("Location: ?a=admin&tab=official&msg=err_zip"); exit; }
        }
        header("Location: ?a=admin&tab=official&msg=err_up"); exit;
    }

    if ($action === 'do_save_official') { file_put_contents('official.html', $_POST['html_code']); header("Location: ?a=admin&tab=official&msg=save_ok"); exit; }

    if ($action === 'do_save_settings') {
        $new=$config; $new['site']['title']=$_POST['site_title']; $new['site']['bg']=$_POST['site_bg'];
        $new['modules']['official'] = (int)$_POST['module_official']; $new['modules']['auth'] = (int)$_POST['module_auth'];
        $new['route']['default'] = $_POST['route_default']; $new['route']['domain_official'] = trim($_POST['domain_official']);
        $new['route']['domain_auth'] = trim($_POST['domain_auth']); $new['route']['official_type'] = $_POST['official_type']; $new['route']['official_url'] = trim($_POST['official_url']);
        $new['rewards']['reg_cmd']=$_POST['reg_cmd']; $new['rewards']['daily_cmd']=$_POST['daily_cmd']; $new['rewards']['sign_in_servers']=explode(',',$_POST['sign_in_servers']);
        $new['db']['host']=$_POST['db_host']; $new['db']['name']=$_POST['db_name']; $new['db']['user']=$_POST['db_user']; if(!empty($_POST['db_pass'])) $new['db']['pass']=$_POST['db_pass'];
        $new['smtp']['host']=$_POST['smtp_host']; $new['smtp']['port']=$_POST['smtp_port']; $new['smtp']['user']=$_POST['smtp_user']; $new['smtp']['from_name']=$_POST['smtp_from']; if(!empty($_POST['smtp_pass'])) $new['smtp']['pass']=$_POST['smtp_pass']; if(isset($_POST['smtp_secure'])) $new['smtp']['secure']=$_POST['smtp_secure'];
        $new['admin']['user']=$_POST['admin_user']; if(!empty($_POST['admin_pass'])) $new['admin']['pass']=$_POST['admin_pass']; if(isset($_POST['admin_email'])) $new['admin']['email']=$_POST['admin_email'];
        unset($new['rcon']); unset($new['server']); unset($new['api']); 
        saveConfig($new); header("Location: ?a=admin&tab=settings&msg=save_ok"); exit;
    }
    if ($action === 'add_cdk') { $d=getCdks(); $d[$_POST['code']]=['cmd'=>$_POST['cmd'],'max'=>(int)$_POST['usage'],'server_id'=>$_POST['server_id'],'used'=>0,'users'=>[]]; saveCdks($d); header("Location: ?a=admin&tab=cdk"); exit; }
    if ($action === 'del_cdk') { $d=getCdks(); unset($d[$_GET['code']]); saveCdks($d); header("Location: ?a=admin&tab=cdk"); exit; }

    $tab = $_GET['tab'] ?? 'users';
    ?>
    <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>单文件后台 v<?= htmlspecialchars($config['site']['ver']) ?></title><script src="https://cdn.tailwindcss.com"></script><style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563} .nav-btn.active{background:#eff6ff;color:#2563eb}</style></head>
    <body>
        <?php if (!isset($_SESSION['is_admin'])): ?>
        <div class="flex items-center justify-center min-h-screen"><div class="bg-white p-8 rounded shadow-lg w-full max-w-sm"><h2 class="text-xl font-bold text-center mb-6">中枢节点验证</h2><form action="?a=admin&action=do_sys_login" method="POST" class="space-y-4"><input name="user" placeholder="账号" class="input" required><input type="password" name="pass" placeholder="密码" class="input" required><button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black">登录</button></form></div></div>
        <?php else: ?>
        <?php if(isset($_GET['msg'])): ?><div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>"><?= ['zip_ok'=>'🎉 官网部署成功！', 'err_zip'=>'❌ 压缩包损坏或无法打开', 'err_nozip'=>'❌ PHP 未开启 ZipArchive', 'err_up'=>'❌ 上传失败', 'save_ok'=>'✅ 保存成功', 'del_ok'=>'🗑️ 玩家已删除', 'pass_ok'=>'🔑 密码重置成功', 'err_auth'=>'🔒 账号或密码错误'][$_GET['msg']] ?? $_GET['msg'] ?></div><?php endif; ?>
        <div class="max-w-7xl mx-auto my-8 p-4">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
                <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                    <div class="mb-8 font-extrabold text-2xl text-blue-600 px-2">Meteor Nexus <span class="text-xs bg-red-500 text-white px-1 rounded block mt-1 w-max">LITE</span><span class="text-xs text-gray-400 block font-normal">v<?= htmlspecialchars($config['site']['ver']) ?></span></div>
                    <button onclick="checkUpdate()" id="u-btn" class="mb-4 text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded w-full shadow-sm">检查核心更新</button>
                    <nav>
                        <a href="?a=admin&tab=official" class="nav-btn <?= $tab=='official'?'active':'' ?>">📝 官网部署</a>
                        <a href="?a=admin&tab=users" class="nav-btn <?= $tab=='users'?'active':'' ?>">👥 玩家管理</a>
                        <a href="?a=admin&tab=servers" class="nav-btn <?= $tab=='servers'?'active':'' ?>">🌍 节点管理</a>
                        <a href="?a=admin&tab=console" class="nav-btn <?= $tab=='console'?'active':'' ?>">🖥️ MetorCore</a>
                        <a href="?a=admin&tab=cdk" class="nav-btn <?= $tab=='cdk'?'active':'' ?>">🎁 CDK 管理</a>
                        <a href="?a=admin&tab=settings" class="nav-btn <?= $tab=='settings'?'active':'' ?>">⚙️ 系统设置</a>
                        <div class="pt-6 mt-6 border-t"><a href="?a=admin&action=admin_logout" class="nav-btn text-red-600">退出中枢</a></div>
                    </nav>
                </div>
                <div class="flex-1 p-8 overflow-y-auto relative">
                    <div id="u-modal" class="hidden absolute inset-0 bg-white/90 z-50 flex items-center justify-center"><div class="bg-white border shadow-xl p-6 rounded text-center w-96"><h3 class="font-bold text-lg mb-2">发现新版本</h3><p id="u-ver" class="text-blue-600 mb-4 font-mono"></p><div id="u-btns" class="flex gap-2 justify-center"><button onclick="doUp()" class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 transition">立即更新</button><button onclick="document.getElementById('u-modal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300">取消</button></div><div id="u-progress" class="hidden mt-2"><div class="w-full bg-gray-200 rounded-full h-2.5 mb-2"><div class="bg-blue-600 h-2.5 rounded-full animate-pulse" style="width: 100%"></div></div><span class="text-xs text-gray-500 font-bold">正在拉取覆盖单文件，请勿刷新...</span></div></div></div>
                    <?php if ($dbError): ?><div class="bg-red-50 text-red-600 p-4 rounded mb-6 border border-red-200 flex items-center gap-2"><span class="text-xl">⚠️</span> <div><div class="font-bold">MySQL 连接失败！</div><div class="text-xs mt-1 font-mono"><?= htmlspecialchars($dbError) ?></div></div></div><?php endif; ?>
                    
                    <?php if ($tab === 'official'): ?>
                        <div class="mb-4 flex justify-between items-end">
                            <div><h2 class="text-xl font-bold text-gray-800">官网部署中心</h2><p class="text-xs text-gray-500 mt-1">上传 ZIP 自动智能转换 index.php 为 official.php 完美融合单文件环境。</p></div>
                            <a href="<?=!empty($config['route']['domain_official'])?(preg_match('#^https?://#',$config['route']['domain_official'])?$config['route']['domain_official']:'http://'.$config['route']['domain_official']):'?m=official'?>" target="_blank" class="text-sm bg-blue-100 text-blue-600 px-3 py-1 rounded font-bold shadow-sm">🚀 预览当前官网 -></a>
                        </div>
                        <form action="?a=admin&action=do_upload_official" method="POST" enctype="multipart/form-data" class="bg-indigo-50 p-5 rounded-lg border border-indigo-100 flex items-center gap-4 mb-6 shadow-inner"><div class="flex-1"><h3 class="font-bold text-indigo-800 text-base mb-1">📦 上传网站模板 (支持 HTML/PHP)</h3></div><input type="file" name="zip_file" accept=".zip" class="text-sm w-48 bg-white p-1 rounded border" required><button class="bg-indigo-600 text-white px-5 py-2 rounded font-bold shadow hover:bg-indigo-700 whitespace-nowrap">一键解压部署</button></form>
                        <form action="?a=admin&action=do_save_official" method="POST"><label class="block text-sm font-bold text-gray-600 mb-2">备用: HTML 单页代码编辑</label><textarea name="html_code" class="w-full h-[300px] bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm shadow-inner outline-none mb-4"><?= file_exists('official.html') ? htmlspecialchars(file_get_contents('official.html')) : '' ?></textarea><button class="bg-green-600 text-white px-6 py-2 rounded font-bold shadow hover:bg-green-700">💾 保存单页发布</button></form>
                    
                    <?php elseif ($tab === 'users'): ?>
                        <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th><th>安全操作</th></tr>
                        <?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 30") as $r): ?><tr class="border-b hover:bg-gray-50 transition"><td class="p-3 font-bold text-gray-400"><?=$r['id']?></td><td class="p-3 font-bold text-blue-600"><?=htmlspecialchars($r['realname'])?></td><td class="p-3 text-xs text-gray-500"><?=htmlspecialchars($r['email'])?></td><td class="p-3"><button onclick="cp(<?=$r['id']?>,'<?=htmlspecialchars($r['realname'])?>')" class="text-blue-500 bg-blue-50 px-3 py-1 rounded hover:bg-blue-500 hover:text-white transition font-bold shadow-sm">改密</button> <a href="?a=admin&action=del_user&id=<?=$r['id']?>" onclick="return confirm('永久删除玩家 [<?=htmlspecialchars($r['realname'])?>] 数据不可恢复，确认执行吗？');" class="text-red-500 bg-red-50 px-3 py-1 rounded hover:bg-red-500 hover:text-white transition font-bold shadow-sm ml-2">删除</a></td></tr><?php endforeach; endif; ?></table>
                        <form id="cp_form" action="?a=admin&action=edit_user_pass" method="POST" class="hidden"><input name="id" id="cp_id"><input name="new_pass" id="cp_pass"></form>
                        <script>function cp(id, name) { let p = prompt('请输入你要为玩家【' + name + '】设置的新密码:'); if(p) { document.getElementById('cp_id').value = id; document.getElementById('cp_pass').value = p; document.getElementById('cp_form').submit(); } }</script>
                    
                    <?php elseif ($tab === 'servers'): ?>
                        <div class="mb-6 bg-blue-50 p-5 rounded-lg border border-blue-100 shadow-sm"><h3 class="font-bold text-blue-800 mb-3 text-lg">添加新 MetorCore 节点</h3><form action="?a=admin&action=add_server" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-3"><input name="name" placeholder="节点名称" class="input col-span-2 md:col-span-1" required><input name="ip" placeholder="节点 IP" class="input col-span-2 md:col-span-1" required><input name="port" placeholder="游戏端口" value="25565" class="input" required><input name="api_port" placeholder="API 端口" value="8080" class="input" required><input name="api_key" placeholder="64位超长动态密钥" class="input col-span-2 md:col-span-3 font-mono text-xs" required><button class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 shadow-md">确认添加</button></form></div>
                        <table class="w-full text-sm text-left bg-white rounded-lg overflow-hidden shadow"><tr class="bg-gray-800 text-white"><th class="p-3">ID</th><th class="p-3">节点名称</th><th class="p-3">IP 地址</th><th class="p-3">游戏端口</th><th class="p-3">API 端口</th><th class="p-3">操作</th></tr><?php foreach($config['servers'] as $k => $v): ?><tr class="border-b hover:bg-gray-50 transition"><td class="p-3 font-bold text-gray-500"><?=$k?></td><td class="p-3 text-blue-600 font-bold"><?=htmlspecialchars($v['name'])?></td><td class="p-3 font-mono"><?=htmlspecialchars($v['ip'])?></td><td class="p-3"><?=$v['port']?></td><td class="p-3 bg-green-50 text-green-700 font-bold"><?=$v['api_port']?></td><td class="p-3"><a href="?a=admin&action=del_server&id=<?=$k?>" class="text-red-500 bg-red-50 px-2 py-1 rounded hover:bg-red-500 hover:text-white" onclick="return confirm('确定删除此节点吗？')">删除</a></td></tr><?php endforeach; ?></table>
                    
                    <?php elseif ($tab === 'console'): ?>
                        <div class="flex gap-2 mb-2"><select id="cs" class="input w-48"><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><input id="cc" class="input flex-1" placeholder="API Command..."><button onclick="sc()" class="bg-black text-white px-4 rounded">Send</button></div><textarea id="cl" class="w-full h-96 bg-gray-900 text-green-400 p-4 rounded text-xs font-mono" readonly></textarea><script>function sc(){let c=document.getElementById('cc').value,s=document.getElementById('cs').value,l=document.getElementById('cl');if(!c)return;l.value+=`> ${c}\n`;fetch('?a=admin&action=do_api_cmd',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cmd=${c}&server_id=${s}`}).then(r=>r.json()).then(d=>{l.value+=d.res+"\n\n";l.scrollTop=l.scrollHeight});document.getElementById('cc').value=''}</script>
                    
                    <?php elseif ($tab === 'cdk'): ?>
                        <form action="?a=admin&action=add_cdk" method="POST" class="bg-blue-50 p-4 rounded mb-4 flex gap-2"><input name="code" placeholder="Code" class="input w-32"><input name="cmd" placeholder="Cmd" class="input flex-1"><input name="usage" value="1" class="input w-16"><select name="server_id" class="input w-24"><option value="all">All</option><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><button class="bg-blue-600 text-white px-4 rounded">Add</button></form>
                        <table class="w-full text-sm text-left"><tr class="bg-gray-100"><th>Code</th><th>Cmd</th><th>Srv</th><th>Use</th><th>Op</th></tr><?php foreach(getCdks() as $k=>$d): ?><tr class="border-b"><td class="p-3 font-bold"><?=htmlspecialchars($k)?></td><td class="p-3 text-xs"><?=htmlspecialchars($d['cmd'])?></td><td class="p-3 text-xs"><?=$d['server_id']=='all'?'All':$config['servers'][$d['server_id']]['name']?></td><td class="p-3"><?=($d['max']-$d['used'])?></td><td class="p-3"><a href="?a=admin&action=del_cdk&code=<?=urlencode($k)?>" class="text-red-500">Del</a></td></tr><?php endforeach; ?></table>
                    
                    <?php elseif ($tab === 'settings'): ?>
                        <form action="?a=admin&action=do_save_settings" method="POST" class="space-y-4 max-w-2xl pb-8">
                            <div class="mt-4 mb-2 p-2 bg-indigo-100 text-indigo-800 font-bold rounded">🌐 站点模式与路由</div>
                            <div class="grid grid-cols-2 gap-4 bg-indigo-50/50 p-4 border border-indigo-100 rounded"><div><label class="text-xs font-bold text-gray-700">官网模块状态</label><select name="module_official" class="input font-bold text-indigo-700"><option value="1" <?=!empty($config['modules']['official'])?'selected':''?>>🟢 开启</option><option value="0" <?=empty($config['modules']['official'])?'selected':''?>>🔴 关闭</option></select></div><div><label class="text-xs font-bold text-gray-700">通行证/注册模块状态</label><select name="module_auth" class="input font-bold text-indigo-700"><option value="1" <?=!empty($config['modules']['auth'])?'selected':''?>>🟢 开启</option><option value="0" <?=empty($config['modules']['auth'])?'selected':''?>>🔴 关闭</option></select></div><div class="col-span-2"><label class="text-xs font-bold text-gray-700">根目录默认访问展示</label><select name="route_default" class="input"><option value="official" <?=($config['route']['default']??'')==='official'?'selected':''?>>🏠 展示官网 (Official)</option><option value="auth" <?=($config['route']['default']??'')==='auth'?'selected':''?>>👤 展示通行证与注册 (Auth Portal)</option></select></div><div><label class="text-xs font-bold text-gray-700">官网独立绑定域名 (选填)</label><input name="domain_official" value="<?=$config['route']['domain_official']??''?>" placeholder="如: www.ermcs.cn" class="input"></div><div><label class="text-xs font-bold text-gray-700">注册独立绑定域名 (选填)</label><input name="domain_auth" value="<?=$config['route']['domain_auth']??''?>" placeholder="如: pass.ermcs.cn" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-emerald-100 text-emerald-800 font-bold rounded">📂 官网挂载引擎</div>
                            <div class="grid grid-cols-2 gap-4 bg-emerald-50/50 p-4 border border-emerald-100 rounded"><div><label class="text-xs font-bold text-gray-700">官网加载模式</label><select name="official_type" class="input"><option value="local" <?=($config['route']['official_type']??'')==='local'?'selected':''?>>📄 原生融合 (推荐，ZIP一键部署)</option><option value="iframe" <?=($config['route']['official_type']??'')==='iframe'?'selected':''?>>🪟 独立文件夹无缝内嵌</option><option value="redirect" <?=($config['route']['official_type']??'')==='redirect'?'selected':''?>>🔗 直接 302 跳转</option></select></div><div><label class="text-xs font-bold text-gray-700">挂载文件夹/跳转链接</label><input name="official_url" value="<?=$config['route']['official_url']??''?>" placeholder="如: /home/ 或 https://..." class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">基础全局信息</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">总控标题</label><input name="site_title" value="<?=$config['site']['title']?>" class="input"></div><div><label class="text-xs font-bold">通行证背景大图链接</label><input name="site_bg" value="<?=$config['site']['bg']?>" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">奖励策略</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">注册指令</label><input name="reg_cmd" value="<?=$config['rewards']['reg_cmd']?>" class="input"></div><div><label class="text-xs font-bold">签到指令</label><input name="daily_cmd" value="<?=$config['rewards']['daily_cmd']?>" class="input"></div><div><label class="text-xs font-bold">签到生效服ID (逗号隔开)</label><input name="sign_in_servers" value="<?=implode(',',$config['rewards']['sign_in_servers'])?>" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">数据库连接 (AuthMe)</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">DB Host</label><input name="db_host" value="<?=$config['db']['host']?>" class="input"></div><div><label class="text-xs font-bold">DB Name</label><input name="db_name" value="<?=$config['db']['name']?>" class="input"></div><div><label class="text-xs font-bold">DB User</label><input name="db_user" value="<?=$config['db']['user']?>" class="input"></div><div><label class="text-xs font-bold">DB Pass (留空不修改)</label><input type="password" name="db_pass" placeholder="***" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">邮件推送 (SMTP)</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">SMTP Host</label><input name="smtp_host" value="<?=$config['smtp']['host']?>" class="input"></div><div><label class="text-xs font-bold">SMTP Port</label><input name="smtp_port" value="<?=$config['smtp']['port']?>" class="input"></div><div><label class="text-xs font-bold">SMTP User</label><input name="smtp_user" value="<?=$config['smtp']['user']?>" class="input"></div><div><label class="text-xs font-bold">SMTP Pass</label><input type="password" name="smtp_pass" placeholder="***" class="input"></div><div><label class="text-xs font-bold">发件人名称</label><input name="smtp_from" value="<?=$config['smtp']['from_name']?>" class="input"></div><div><label class="text-xs font-bold">加密方式 (ssl/tls)</label><input name="smtp_secure" value="<?=$config['smtp']['secure'] ?? 'ssl'?>" class="input"></div></div>
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">管理员凭据</div>
                            <div class="grid grid-cols-2 gap-4"><div><label class="text-xs font-bold">管理员账号</label><input name="admin_user" value="<?=$config['admin']['user']?>" class="input"></div><div><label class="text-xs font-bold">管理员密码</label><input type="password" name="admin_pass" placeholder="***" class="input"></div></div>
                            <button class="w-full bg-blue-600 text-white px-6 py-4 mt-4 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg text-lg">保存配置</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        function checkUpdate(){let b=document.getElementById('u-btn');b.innerText='...';fetch('?a=admin&action=check_update').then(r=>r.json()).then(d=>{b.innerText='检查核心更新';if(d.status=='new'){document.getElementById('u-ver').innerText=d.ver;document.getElementById('u-modal').classList.remove('hidden')}else alert(d.msg)})}
        function doUp(){ document.getElementById('u-btns').classList.add('hidden'); document.getElementById('u-progress').classList.remove('hidden'); fetch('?a=admin&action=do_update').then(r=>r.json()).then(d=>{ alert(d.log); location.reload(); }).catch(e=>{ alert('更新超时或失败。'); location.reload(); }); }
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php exit;
}
?>
