<?php
/**
 * Project: Meteor Nexus (流星枢纽) Lite 单文件版
 * Version: v2.2.0 (Leviathan Security + Full UI Restored)
 */

// ==========================================
// ☢️ [神盾 0] 终极会话隔离与底层环境保护
// ==========================================
$is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || $_SERVER['SERVER_PORT'] == 443;
if ($is_https) { session_name('__Secure-MeteorNexus'); } else { session_name('MeteorNexus'); }
session_set_cookie_params(['secure' => $is_https, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_only_cookies', 1);
ini_set('display_errors', 0); error_reporting(0);

// ==========================================
// ☢️ [神盾 1] 严格动词审查与企业级 HTTP 盔甲
// ==========================================
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'])) { header('HTTP/1.1 405 Method Not Allowed'); die('405 Method Not Allowed'); }
header("X-Frame-Options: DENY"); 
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https://cravatar.eu https://images.unsplash.com; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.mcsrvstat.us; frame-src 'none'; object-src 'none';");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");

$ip = $_SERVER['REMOTE_ADDR'];

// ==========================================
// ☢️ [神盾 2] Fail2Ban 永久黑名单与焦油坑反杀 (Tarpit)
// ==========================================
$banFile = 'banned_ips.json';
$bannedIps = file_exists($banFile) ? json_decode(file_get_contents($banFile), true) : [];
if (in_array($ip, $bannedIps)) { sleep(3); header('HTTP/1.1 403 Forbidden'); die("<!DOCTYPE html><html><body style='background:#111;color:#f00;text-align:center;padding-top:20%;font-family:monospace;'><h1>[ 403 FORBIDDEN ]</h1><p>YOUR IP ( {$ip} ) HAS BEEN PERMANENTLY BANNED BY LEVIATHAN DOME.</p></body></html>"); }

function ban_ip_and_die($reason) {
    global $banFile, $bannedIps, $ip;
    if (!in_array($ip, $bannedIps)) { $bannedIps[] = $ip; file_put_contents($banFile, json_encode($bannedIps), LOCK_EX); }
    sleep(5); // 焦油坑挂起
    header('HTTP/1.1 403 Forbidden'); die("<!DOCTYPE html><html><body style='background:#111;color:#f00;text-align:center;padding-top:20%;font-family:monospace;'><h1>[ FATAL SECURITY BREACH ]</h1><p>Triggered WAF Rule: {$reason}</p><p>IP BANNED.</p></body></html>");
}

// ==========================================
// ☢️ [神盾 3] 重型 WAF 恶意载荷深度核查
// ==========================================
$waf_pattern = '/(union\s+select|insert\s+into|update\s+|delete\s+from|drop\s+table|truncate\s+|alter\s+table|exec\(|eval\(|base64_decode|<script|javascript:|vbscript:|onload=|onerror=|document\.cookie|window\.location|etc\/passwd|cmd\.exe|powershell|wget\s+|curl\s+)/i';
function check_waf($data, $key = '') {
    global $waf_pattern; 
    if ($key === 'html_code') return; // 放行后台官网HTML代码编辑
    if (is_array($data)) { foreach ($data as $k => $v) check_waf($v, $k); } 
    else { if (preg_match($waf_pattern, $data)) ban_ip_and_die("WAF_PAYLOAD_DETECTED_IN_{$key}"); }
}
check_waf($_GET); check_waf($_POST); check_waf($_COOKIE);

// ==========================================
// ☢️ [神盾 4] 毫秒级全局 CC 攻击熔断
// ==========================================
$globalRateFile = 'global_ratelimit.json'; $now = microtime(true);
$rateData = file_exists($globalRateFile) ? json_decode(file_get_contents($globalRateFile), true) : [];
if (!isset($rateData[$ip])) $rateData[$ip] = [];
$rateData[$ip] = array_filter($rateData[$ip], function($t) use ($now) { return ($now - $t) < 60; });
$rateData[$ip][] = $now; file_put_contents($globalRateFile, json_encode($rateData), LOCK_EX);
if (count($rateData[$ip]) > 60 || count(array_filter($rateData[$ip], function($t) use ($now) { return ($now - $t) < 1; })) > 5) { 
    header('HTTP/1.1 429 Too Many Requests'); die("<h1 style='color:red;text-align:center;margin-top:20%'>429 RATE LIMITED (CC DEFENSE)</h1>"); 
}

session_start();
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// ☢️ [神盾] GET请求生成动态变异表单映射与生物探针哈希
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['f_u'] = 'usr_' . bin2hex(random_bytes(4));
    $_SESSION['f_p'] = 'pwd_' . bin2hex(random_bytes(4));
    $_SESSION['f_e'] = 'eml_' . bin2hex(random_bytes(4));
    $_SESSION['f_c'] = 'cpt_' . bin2hex(random_bytes(4));
    $_SESSION['f_a_u'] = 'adm_' . bin2hex(random_bytes(4));
    $_SESSION['f_a_p'] = 'apw_' . bin2hex(random_bytes(4));
    $_SESSION['bhv_hash'] = hash('sha256', $_SESSION['csrf_token'] . $ip);
    $_SESSION['form_render_time'] = microtime(true);
}

// ==========================================
// ☢️ [神盾 5] 跨站请求伪造 (CSRF) & Referer 溯源
// ==========================================
if ($method === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { header("Location: ?msg=err_csrf"); exit; }
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; $host = $_SERVER['HTTP_HOST'];
    if (!empty($referer) && parse_url($referer, PHP_URL_HOST) !== $host) ban_ip_and_die("CSRF_REFERER_MISMATCH");
}

header('Content-Type: text/html; charset=utf-8');

// ==========================================
// 1. 核心逻辑与外部配置加载
// ==========================================
$configFile = 'config.php';
if (!file_exists($configFile)) { die("<!DOCTYPE html><html><body><h1 style='text-align:center'>⚠️ 找不到 config.php</h1></body></html>"); }
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
    global $config; 
    if (!isset($config['servers'][$serverIdx])) return false; 
    $s = $config['servers'][$serverIdx]; 
    if (empty($s['api_key']) || empty($cmd)) return false; 
    $port = $s['api_port'] ?? 8080; 
    $safe_command = urlencode($cmd); 
    $url = "http://{$s['ip']}:{$port}/api/cmd?cmd=" . $safe_command; 
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $s['api_key'])); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
    $response = curl_exec($ch); 
    curl_close($ch); 
    if ($response !== false) return $response ?: "指令已发送"; 
    return false; 
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
function checkLock($f){ global $ip; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; foreach($d as $k=>$v){if(time()-$v['t']>3600)unset($d[$k]);} if(isset($d[$ip])&&$d[$ip]['c']>=3&&time()-$d[$ip]['t']<3600)return true; return false; }
function logFail($f){ global $ip; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; if(!isset($d[$ip]))$d[$ip]=['c'=>0,'t'=>time()]; $d[$ip]['c']++; $d[$ip]['t']=time(); file_put_contents($f,json_encode($d)); return $d[$ip]['c']; }
function clearFail($f){ global $ip; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(isset($d[$ip])){unset($d[$ip]);file_put_contents($f,json_encode($d));} }

// ==========================================
// ☢️ [神盾 6] 会话指纹基因锁
// ==========================================
$client_fp = hash('sha256', $ip . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
if (isset($_SESSION['user']) || isset($_SESSION['is_admin'])) {
    if (!isset($_SESSION['client_fp'])) $_SESSION['client_fp'] = $client_fp;
    elseif ($_SESSION['client_fp'] !== $client_fp) { session_destroy(); ban_ip_and_die("SESSION_HIJACKING_ATTEMPT"); }
}

$action = $_GET['action'] ?? ($_GET['a'] ?? 'home');
$isAdminRoute = (isset($_GET['a']) && $_GET['a'] === 'admin') || in_array($action, ['do_sys_login', 'admin_logout', 'check_update', 'do_update', 'do_api_cmd', 'do_save_settings', 'add_cdk', 'del_cdk', 'del_user', 'edit_user_pass', 'add_server', 'del_server', 'do_upload_official', 'do_save_official']);

if ($isAdminRoute) {
    // ==========================================
    // 后台中枢模块
    // ==========================================
    $repoUrl = 'https://raw.githubusercontent.com/Huanyingcraft21/AuthMe-Web-Portal/main/';
    
    // ☢️ 爆破死刑判定：登录失败超5次永久封禁
    $failData = file_exists($limitFile)?json_decode(file_get_contents($limitFile),true):[];
    if (isset($failData[$ip]) && $failData[$ip]['c'] >= 5 && time()-$failData[$ip]['t'] < 86400) ban_ip_and_die("BRUTE_FORCE_ADMIN");

    if ($action === 'admin_logout') { unset($_SESSION['is_admin']); session_destroy(); header("Location: ?a=admin"); exit; }
    
    if ($action === 'do_sys_login') { 
        // ☢️ 拦截所有带传统键名的僵尸脚本
        if (isset($_POST['user']) || isset($_POST['pass'])) ban_ip_and_die("STATIC_ADMIN_BOT_DETECTED");
        if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NON_HUMAN_ADMIN_LOGIN");

        if (($_POST[$_SESSION['f_a_u']]??'') === $config['admin']['user'] && ($_POST[$_SESSION['f_a_p']]??'') === $config['admin']['pass']) { 
            clearFail($limitFile); session_regenerate_id(true); $_SESSION['is_admin'] = true; $_SESSION['client_fp'] = $client_fp;
            if (!empty($config['admin']['email']) && !empty($config['smtp']['user'])) {
                $alertTime = date('Y-m-d H:i:s'); $smtp = new TinySMTP();
                $smtp->send($config['admin']['email'], "⚠️ [Meteor Nexus] 后台登录安全警报", "您的中枢后台刚刚被登录。<br><b>IP:</b> {$ip}<br><b>时间:</b> {$alertTime}", $config['smtp']);
            }
            header("Location: ?a=admin"); 
        } else { $c = logFail($limitFile); header("Location: ?a=admin&msg=err_auth&rem=".(5-$c)); } exit; 
    }
    
    if ($action !== 'admin' && $action !== 'do_sys_login' && !isset($_SESSION['is_admin'])) { header("Location: ?a=admin"); exit; }

    if ($action === 'check_update') { $remoteVer = @file_get_contents($repoUrl . 'version.txt'); if ($remoteVer === false) echo json_encode(['status' => 'err', 'msg' => '连接 GitHub 失败']); else { $remoteVer = trim($remoteVer); $currentVer = $config['site']['ver']; if (version_compare($remoteVer, $currentVer, '>')) echo json_encode(['status' => 'new', 'ver' => $remoteVer, 'msg' => "发现新版本 v$remoteVer"]); else echo json_encode(['status' => 'latest', 'msg' => '已是最新']); } exit; }
    if ($action === 'do_update') { $c = @file_get_contents($repoUrl . 'lite.php'); $log = ""; $ok = true; if ($c) { if(file_put_contents(__FILE__, $c)) $log.="✅ 单文件核心自我更新成功\n"; else { $ok=false; $log.="❌ 覆盖核心失败\n"; } } else { $ok=false; $log.="❌ 无法拉取源码\n"; } $sc = @file_get_contents($repoUrl . 'config_sample.php'); if ($sc) { file_put_contents('ctmp.php', $sc); $tpl=include('ctmp.php'); $old=include('config.php'); @unlink('ctmp.php'); $new = array_replace_recursive($tpl, $old); $ver = trim(@file_get_contents($repoUrl . 'version.txt')); if($ver) $new['site']['ver'] = $ver; saveConfig($new); $log.="✅ 配置文件同步升级\n"; } echo json_encode(['status' => $ok?'ok':'err', 'log' => $log]); exit; }

    if ($action === 'del_user') { $id = (int)$_GET['id']; if ($pdo && $id > 0) { $pdo->prepare("DELETE FROM authme WHERE id=?")->execute([$id]); } header("Location: ?a=admin&tab=users&msg=del_ok"); exit; }
    if ($action === 'edit_user_pass') { $id = (int)$_POST['id']; $newPass = $_POST['new_pass']; if ($pdo && !empty($newPass) && $id > 0) { $pdo->prepare("UPDATE authme SET password=? WHERE id=?")->execute([hashAuthMe($newPass), $id]); } header("Location: ?a=admin&tab=users&msg=pass_ok"); exit; }
    if ($action === 'do_api_cmd') { $res = runApiCmd($_POST['cmd'],(int)$_POST['server_id']); echo json_encode(['res'=>$res===false?"安全通讯握手失败":($res?:"指令已发送")]); exit; }
    if ($action === 'add_server') { $new = $config; $new['servers'][] = ['name' => $_POST['name'], 'ip' => $_POST['ip'], 'port' => (int)$_POST['port'], 'api_port' => (int)$_POST['api_port'], 'api_key' => $_POST['api_key']]; saveConfig($new); header("Location: ?a=admin&tab=servers"); exit; }
    if ($action === 'del_server') { $new = $config; $idx = (int)$_GET['id']; if (isset($new['servers'][$idx])) { unset($new['servers'][$idx]); $new['servers'] = array_values($new['servers']); saveConfig($new); } header("Location: ?a=admin&tab=servers"); exit; }

    if ($action === 'do_upload_official') {
        if (!class_exists('ZipArchive')) { header("Location: ?a=admin&tab=official&msg=err_nozip"); exit; }
        if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] == 0) {
            $zip = new ZipArchive;
            if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
                $blacklist = ['admin.php', 'core.php', 'config.php', 'install.php', 'lite.php', 'config_sample.php', 'user_data.json', 'cdk_data.json', 'login_limit.json', '.htaccess', 'global_ratelimit.json', 'banned_ips.json'];
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
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width">
        <title>后台 v<?= htmlspecialchars($config['site']['ver']) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .nav-btn{display:block;padding:0.6rem 1rem;margin-bottom:0.5rem;border-radius:0.5rem;font-weight:600;color:#4b5563;transition:0.2s} .nav-btn:hover{background:#e5e7eb} .nav-btn.active{background:#eff6ff;color:#2563eb}</style>
    </head>
    <body>
        <script>
            ['mousemove','keydown','touchstart'].forEach(e => 
                window.addEventListener(e, () => { document.querySelectorAll('.bhv_probe').forEach(el => el.value = '<?=$_SESSION['bhv_hash']?>'); }, {once:true})
            );
        </script>

        <?php if (!isset($_SESSION['is_admin'])): ?>
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white p-8 rounded shadow-lg w-full max-w-sm">
                <h2 class="text-xl font-bold text-center mb-6">中枢节点验证</h2>
                <form action="?a=admin&action=do_sys_login" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                    <input type="hidden" name="bhv_probe" class="bhv_probe" value="bot">
                    <input name="<?=$_SESSION['f_a_u']?>" placeholder="账号" class="input" required>
                    <input type="password" name="<?=$_SESSION['f_a_p']?>" placeholder="密码" class="input" required>
                    <button class="w-full bg-gray-800 text-white p-2 rounded hover:bg-black transition">登录</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <?php if(isset($_GET['msg'])): ?>
        <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
            <?= ['zip_ok'=>'🎉 官网解压部署成功！', 'err_zip'=>'❌ 压缩包损坏或无法打开', 'err_nozip'=>'❌ PHP 未开启 ZipArchive', 'err_up'=>'❌ 上传失败', 'save_ok'=>'✅ 保存成功', 'del_ok'=>'🗑️ 玩家数据已永久删除', 'pass_ok'=>'🔑 密码重置成功'][$_GET['msg']] ?? $_GET['msg'] ?>
        </div>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto my-8 p-4">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col md:flex-row min-h-[700px]">
                <div class="w-full md:w-56 bg-gray-50 p-6 border-r">
                    <div class="mb-8 font-extrabold text-2xl text-blue-600 px-2">
                        Meteor Nexus 
                        <span class="text-xs bg-red-500 text-white px-1 rounded block mt-1 w-max">v2.2 LITE-SEC</span>
                    </div>
                    <button onclick="checkUpdate()" id="u-btn" class="mb-4 text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded w-full shadow-sm hover:bg-blue-200 transition">检查核心更新</button>
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
                    <div id="u-modal" class="hidden absolute inset-0 bg-white/90 z-50 flex items-center justify-center">
                        <div class="bg-white border shadow-xl p-6 rounded text-center w-96">
                            <h3 class="font-bold text-lg mb-2">发现新版本</h3><p id="u-ver" class="text-blue-600 mb-4 font-mono"></p>
                            <div id="u-btns" class="flex gap-2 justify-center">
                                <button onclick="doUp()" class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 transition">立即更新</button>
                                <button onclick="document.getElementById('u-modal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 transition">取消</button>
                            </div>
                            <div id="u-progress" class="hidden mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2"><div class="bg-blue-600 h-2.5 rounded-full animate-pulse" style="width: 100%"></div></div>
                                <span class="text-xs text-gray-500 font-bold">正在拉取覆盖单文件，请勿刷新...</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($dbError): ?><div class="bg-red-50 text-red-600 p-4 rounded mb-6 border border-red-200 flex items-center gap-2"><span class="text-xl">⚠️</span> <div><div class="font-bold">MySQL 连接失败！</div><div class="text-xs mt-1 font-mono"><?= htmlspecialchars($dbError) ?></div></div></div><?php endif; ?>

                    <?php if ($tab === 'official'): ?>
                        <div class="mb-4 flex justify-between items-end">
                            <div><h2 class="text-xl font-bold text-gray-800">官网部署中心</h2><p class="text-xs text-gray-500 mt-1">上传 ZIP 自动智能转换 index 为 official 完美融合单文件环境。</p></div>
                            <a href="<?=!empty($config['route']['domain_official'])?(preg_match('#^https?://#',$config['route']['domain_official'])?$config['route']['domain_official']:'http://'.$config['route']['domain_official']):'?m=official'?>" target="_blank" class="text-sm bg-blue-100 text-blue-600 px-3 py-1 rounded font-bold shadow-sm hover:bg-blue-200 transition">🚀 预览官网 -></a>
                        </div>
                        <form action="?a=admin&action=do_upload_official" method="POST" enctype="multipart/form-data" class="bg-indigo-50 p-5 rounded-lg border border-indigo-100 flex items-center gap-4 mb-6 shadow-inner">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                            <div class="flex-1"><h3 class="font-bold text-indigo-800 text-base mb-1">📦 上传网站模板 (支持 HTML / PHP)</h3></div>
                            <input type="file" name="zip_file" accept=".zip" class="text-sm w-48 bg-white p-1 rounded border border-indigo-200" required>
                            <button class="bg-indigo-600 text-white px-5 py-2 rounded font-bold shadow hover:bg-indigo-700 whitespace-nowrap transition">一键解压部署</button>
                        </form>
                        <form action="?a=admin&action=do_save_official" method="POST">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                            <label class="block text-sm font-bold text-gray-600 mb-2">备用: HTML 单页代码编辑</label>
                            <textarea name="html_code" class="w-full h-[300px] bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm shadow-inner outline-none mb-4"><?= file_exists('official.html') ? htmlspecialchars(file_get_contents('official.html')) : '' ?></textarea>
                            <button class="bg-green-600 text-white px-6 py-2 rounded font-bold shadow hover:bg-green-700 transition">💾 保存单页发布</button>
                        </form>

                    <?php elseif ($tab === 'users'): ?>
                        <table class="w-full text-sm text-left">
                            <tr class="bg-gray-100"><th>ID</th><th>玩家</th><th>邮箱</th><th>安全操作</th></tr>
                            <?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 30") as $r): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 font-bold text-gray-400"><?=$r['id']?></td>
                                <td class="p-3 font-bold text-blue-600"><?=htmlspecialchars($r['realname'])?></td>
                                <td class="p-3 text-xs text-gray-500"><?=htmlspecialchars($r['email'])?></td>
                                <td class="p-3">
                                    <button onclick="cp(<?=$r['id']?>,'<?=htmlspecialchars($r['realname'])?>')" class="text-blue-500 bg-blue-50 px-3 py-1 rounded hover:bg-blue-500 hover:text-white transition font-bold shadow-sm">改密</button>
                                    <a href="?a=admin&action=del_user&id=<?=$r['id']?>" onclick="return confirm('警告：永久删除数据不可恢复，确认执行吗？');" class="text-red-500 bg-red-50 px-3 py-1 rounded hover:bg-red-500 hover:text-white transition font-bold shadow-sm ml-2">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </table>
                        <form id="cp_form" action="?a=admin&action=edit_user_pass" method="POST" class="hidden">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                            <input name="id" id="cp_id"><input name="new_pass" id="cp_pass">
                        </form>
                        <script>
                            function cp(id, name) { 
                                let p = prompt('请输入你要为玩家【' + name + '】设置的新密码:'); 
                                if(p) { document.getElementById('cp_id').value = id; document.getElementById('cp_pass').value = p; document.getElementById('cp_form').submit(); } 
                            }
                        </script>

                    <?php elseif ($tab === 'servers'): ?>
                        <div class="mb-6 bg-blue-50 p-5 rounded-lg border border-blue-100 shadow-sm">
                            <h3 class="font-bold text-blue-800 mb-3 text-lg">添加新 MetorCore 节点</h3>
                            <form action="?a=admin&action=add_server" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                                <input name="name" placeholder="节点名称" class="input col-span-2 md:col-span-1" required>
                                <input name="ip" placeholder="节点公网 IP 地址" class="input col-span-2 md:col-span-1" required>
                                <input name="port" placeholder="游戏端口" value="25565" class="input" required>
                                <input name="api_port" placeholder="API 端口" value="8080" class="input" required>
                                <input name="api_key" placeholder="64位超长动态密钥" class="input col-span-2 md:col-span-3 font-mono text-xs" required>
                                <button class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700 shadow-md col-span-2 md:col-span-1">确认添加</button>
                            </form>
                        </div>
                        <table class="w-full text-sm text-left bg-white rounded-lg overflow-hidden shadow">
                            <tr class="bg-gray-800 text-white">
                                <th class="p-3">ID</th><th class="p-3">节点名称</th><th class="p-3">IP 地址</th><th class="p-3">游戏端口</th><th class="p-3">API 端口</th><th class="p-3">操作</th>
                            </tr>
                            <?php foreach($config['servers'] as $k => $v): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="p-3 font-bold text-gray-500"><?=$k?></td>
                                <td class="p-3 text-blue-600 font-bold"><?=htmlspecialchars($v['name'])?></td>
                                <td class="p-3 font-mono"><?=htmlspecialchars($v['ip'])?></td>
                                <td class="p-3"><?=$v['port']?></td>
                                <td class="p-3 bg-green-50 text-green-700 font-bold"><?=$v['api_port']?></td>
                                <td class="p-3"><a href="?a=admin&action=del_server&id=<?=$k?>" class="text-red-500 bg-red-50 px-2 py-1 rounded hover:bg-red-500 hover:text-white transition" onclick="return confirm('确定删除此节点吗？')">删除</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>

                    <?php elseif ($tab === 'console'): ?>
                        <div class="flex gap-2 mb-2">
                            <select id="cs" class="input w-48"><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select>
                            <input id="cc" class="input flex-1" placeholder="API Command...">
                            <button onclick="sc()" class="bg-black text-white px-4 rounded hover:bg-gray-800">Send</button>
                        </div>
                        <textarea id="cl" class="w-full h-96 bg-gray-900 text-green-400 p-4 rounded text-xs font-mono" readonly></textarea>
                        <script>
                            function sc(){let c=document.getElementById('cc').value,s=document.getElementById('cs').value,l=document.getElementById('cl');if(!c)return;l.value+=`> ${c}\n`;fetch('?a=admin&action=do_api_cmd',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`cmd=${encodeURIComponent(c)}&server_id=${s}&csrf_token=<?=$_SESSION['csrf_token']?>`}).then(r=>r.json()).then(d=>{l.value+=d.res+"\n\n";l.scrollTop=l.scrollHeight});document.getElementById('cc').value=''}
                        </script>

                    <?php elseif ($tab === 'cdk'): ?>
                        <form action="?a=admin&action=add_cdk" method="POST" class="bg-blue-50 p-4 rounded mb-4 flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                            <input name="code" placeholder="Code" class="input w-32">
                            <input name="cmd" placeholder="Cmd" class="input flex-1">
                            <input name="usage" value="1" class="input w-16">
                            <select name="server_id" class="input w-24"><option value="all">All</option><?php foreach($config['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select>
                            <button class="bg-blue-600 text-white px-4 rounded hover:bg-blue-700">Add</button>
                        </form>
                        <table class="w-full text-sm text-left">
                            <tr class="bg-gray-100"><th>Code</th><th>Cmd</th><th>Srv</th><th>Use</th><th>Op</th></tr>
                            <?php foreach(getCdks() as $k=>$d): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-3 font-bold"><?=htmlspecialchars($k)?></td>
                                <td class="p-3 text-xs"><?=htmlspecialchars($d['cmd'])?></td>
                                <td class="p-3 text-xs"><?=$d['server_id']=='all'?'All':$config['servers'][$d['server_id']]['name']?></td>
                                <td class="p-3"><?=($d['max']-$d['used'])?></td>
                                <td class="p-3"><a href="?a=admin&action=del_cdk&code=<?=urlencode($k)?>" class="text-red-500 hover:underline">Del</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>

                    <?php elseif ($tab === 'settings'): ?>
                        <form action="?a=admin&action=do_save_settings" method="POST" class="space-y-4 max-w-2xl pb-8">
                            <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                            
                            <div class="mt-4 mb-2 p-2 bg-indigo-100 text-indigo-800 font-bold rounded">🌐 站点模式与路由</div>
                            <div class="grid grid-cols-2 gap-4 bg-indigo-50/50 p-4 border border-indigo-100 rounded">
                                <div><label class="text-xs font-bold text-gray-700">官网模块状态</label><select name="module_official" class="input font-bold text-indigo-700"><option value="1" <?=!empty($config['modules']['official'])?'selected':''?>>🟢 开启</option><option value="0" <?=empty($config['modules']['official'])?'selected':''?>>🔴 关闭</option></select></div>
                                <div><label class="text-xs font-bold text-gray-700">通行证/注册模块状态</label><select name="module_auth" class="input font-bold text-indigo-700"><option value="1" <?=!empty($config['modules']['auth'])?'selected':''?>>🟢 开启</option><option value="0" <?=empty($config['modules']['auth'])?'selected':''?>>🔴 关闭</option></select></div>
                                <div class="col-span-2"><label class="text-xs font-bold text-gray-700">根目录默认访问展示</label><select name="route_default" class="input"><option value="official" <?=($config['route']['default']??'')==='official'?'selected':''?>>🏠 展示官网</option><option value="auth" <?=($config['route']['default']??'')==='auth'?'selected':''?>>👤 展示通行证</option></select></div>
                                <div><label class="text-xs font-bold text-gray-700">官网独立绑定域名</label><input name="domain_official" value="<?=$config['route']['domain_official']??''?>" placeholder="如: www.ermcs.cn" class="input"></div>
                                <div><label class="text-xs font-bold text-gray-700">注册独立绑定域名</label><input name="domain_auth" value="<?=$config['route']['domain_auth']??''?>" placeholder="如: pass.ermcs.cn" class="input"></div>
                            </div>

                            <div class="mt-4 mb-2 p-2 bg-emerald-100 text-emerald-800 font-bold rounded">📂 官网挂载引擎</div>
                            <div class="grid grid-cols-2 gap-4 bg-emerald-50/50 p-4 border border-emerald-100 rounded">
                                <div><label class="text-xs font-bold text-gray-700">加载模式</label><select name="official_type" class="input"><option value="local" <?=($config['route']['official_type']??'')==='local'?'selected':''?>>📄 原生融合</option><option value="iframe" <?=($config['route']['official_type']??'')==='iframe'?'selected':''?>>🪟 无缝内嵌</option><option value="redirect" <?=($config['route']['official_type']??'')==='redirect'?'selected':''?>>🔗 302 跳转</option></select></div>
                                <div><label class="text-xs font-bold text-gray-700">挂载文件夹/跳转链接</label><input name="official_url" value="<?=$config['route']['official_url']??''?>" class="input"></div>
                            </div>

                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">基础全局信息</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-bold">后台总控标题</label><input name="site_title" value="<?=$config['site']['title']?>" class="input"></div>
                                <div><label class="text-xs font-bold">通行证大图链接</label><input name="site_bg" value="<?=$config['site']['bg']?>" class="input"></div>
                            </div>
                            
                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">奖励策略</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-bold">注册指令</label><input name="reg_cmd" value="<?=$config['rewards']['reg_cmd']?>" class="input"></div>
                                <div><label class="text-xs font-bold">签到指令</label><input name="daily_cmd" value="<?=$config['rewards']['daily_cmd']?>" class="input"></div>
                            </div>
                            <div><label class="text-xs font-bold">签到生效服ID (逗号隔开)</label><input name="sign_in_servers" value="<?=implode(',',$config['rewards']['sign_in_servers'])?>" class="input"></div>

                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">数据库连接 (AuthMe)</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-bold">DB Host</label><input name="db_host" value="<?=$config['db']['host']?>" class="input"></div>
                                <div><label class="text-xs font-bold">DB Name</label><input name="db_name" value="<?=$config['db']['name']?>" class="input"></div>
                                <div><label class="text-xs font-bold">DB User</label><input name="db_user" value="<?=$config['db']['user']?>" class="input"></div>
                                <div><label class="text-xs font-bold">DB Pass</label><input type="password" name="db_pass" placeholder="***" class="input"></div>
                            </div>

                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">邮件推送 (SMTP)</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-bold">SMTP Host</label><input name="smtp_host" value="<?=$config['smtp']['host']?>" class="input"></div>
                                <div><label class="text-xs font-bold">SMTP Port</label><input name="smtp_port" value="<?=$config['smtp']['port']?>" class="input"></div>
                                <div><label class="text-xs font-bold">SMTP User</label><input name="smtp_user" value="<?=$config['smtp']['user']?>" class="input"></div>
                                <div><label class="text-xs font-bold">SMTP Pass</label><input type="password" name="smtp_pass" placeholder="***" class="input"></div>
                                <div><label class="text-xs font-bold">发件人名称</label><input name="smtp_from" value="<?=$config['smtp']['from_name']?>" class="input"></div>
                                <div><label class="text-xs font-bold">加密方式 (ssl/tls)</label><input name="smtp_secure" value="<?=$config['smtp']['secure'] ?? 'ssl'?>" class="input"></div>
                            </div>

                            <div class="mt-4 mb-2 p-2 bg-blue-100 text-blue-800 font-bold rounded">管理员安全凭据</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="text-xs font-bold">中枢管理员账号</label><input name="admin_user" value="<?=$config['admin']['user']?>" class="input"></div>
                                <div><label class="text-xs font-bold">管理员密码</label><input type="password" name="admin_pass" placeholder="***" class="input"></div>
                                <div class="col-span-2"><label class="text-xs font-bold">安全接收邮箱 (预警用)</label><input name="admin_email" value="<?=$config['admin']['email']??''?>" class="input" placeholder="留空则不发送警报邮件"></div>
                            </div>

                            <button class="w-full bg-blue-600 text-white px-6 py-4 mt-4 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg text-lg">保存并更新架构配置</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        function checkUpdate(){let b=document.getElementById('u-btn');b.innerText='...';fetch('?a=admin&action=check_update').then(r=>r.json()).then(d=>{b.innerText='检查核心更新';if(d.status=='new'){document.getElementById('u-ver').innerText=d.ver;document.getElementById('u-modal').classList.remove('hidden')}else alert(d.msg)})}
        function doUp(){
            document.getElementById('u-btns').classList.add('hidden');
            document.getElementById('u-progress').classList.remove('hidden');
            fetch('?a=admin&action=do_update', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`csrf_token=<?=$_SESSION['csrf_token']?>`}).then(r=>r.json()).then(d=>{
                alert(d.log); location.reload();
            }).catch(e=>{
                alert('更新过程出现异常或超时。'); location.reload();
            });
        }
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php exit;
} else {
    // ==========================================
    // 3B. 前台路由引擎 (Official & Auth Modules)
    // ==========================================
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $mode = $config['route']['default'] ?? 'official';

    $d_off = strtolower(preg_replace('#^https?://#', '', trim($config['route']['domain_official'] ?? ''))); $d_off = preg_replace('#^www\.#', '', explode('/', $d_off)[0]);
    $d_auth = strtolower(preg_replace('#^https?://#', '', trim($config['route']['domain_auth'] ?? ''))); $d_auth = preg_replace('#^www\.#', '', explode('/', $d_auth)[0]);

    if (isset($_GET['m'])) { if ($_GET['m'] === 'official') $mode = 'official'; if ($_GET['m'] === 'auth') $mode = 'auth'; } 
    else { if (!empty($d_auth) && ($host === $d_auth || $host === "www.$d_auth")) $mode = 'auth'; elseif (!empty($d_off) && ($host === $d_off || $host === "www.$d_off")) $mode = 'official'; }

    if ($mode === 'official' && empty($config['modules']['official'])) $mode = 'auth';
    if ($mode === 'auth' && empty($config['modules']['auth'])) $mode = 'official';
    if (empty($config['modules']['official']) && empty($config['modules']['auth'])) die("<h1 style='text-align:center;margin-top:20vh;'>🚧 维护中</h1>");

    if ($mode === 'official') {
        $oType = $config['route']['official_type'] ?? 'local'; $oUrl = $config['route']['official_url'] ?? '';
        if ($oType === 'redirect' && !empty($oUrl)) { header("Location: $oUrl"); exit; }
        if ($oType === 'iframe' && !empty($oUrl)) { die("<!DOCTYPE html><html><head><meta charset='utf-8'><title>".htmlspecialchars($config['site']['title'])."</title><style>body,html{margin:0;padding:0;height:100%;overflow:hidden;}</style></head><body><iframe src='".htmlspecialchars($oUrl)."' width='100%' height='100%' frameborder='0'></iframe>".(!empty($config['modules']['auth']) ? "<a href='?m=auth' style='position:fixed;top:20px;right:20px;background:rgba(255,255,255,0.8);backdrop-filter:blur(5px);padding:8px 16px;border-radius:20px;box-shadow:0 4px 6px rgba(0,0,0,0.1);text-decoration:none;color:#333;font-family:sans-serif;font-size:13px;font-weight:bold;z-index:9999;'>👤 玩家通行证</a>" : "")."</body></html>"); }
        if (file_exists('official.php')) { include 'official.php'; exit; }
        if (file_exists('official.html')) { echo file_get_contents('official.html'); exit; }
        
        $bg = $config['site']['bg'] ?: 'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920'; $title = htmlspecialchars($config['site']['title']); $authBtn = !empty($config['modules']['auth']) ? "<a href='?m=auth' class='inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transition transform hover:scale-105'>进入玩家中心 / 注册通行证 -></a>" : "";
        die("<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>{$title} - 官方网站</title><script src='https://cdn.tailwindcss.com'></script></head><body class='text-gray-800' style='background: url(\"{$bg}\") no-repeat center center fixed; background-size: cover;'><div class='min-h-screen bg-black/40 backdrop-blur-sm flex flex-col items-center justify-center p-4 text-center'><div class='bg-white/10 p-2 rounded-full mb-6 backdrop-blur-md border border-white/20 shadow-2xl'><img src='https://cravatar.eu/helmavatar/Steve/128.png' class='w-24 h-24 rounded-full'></div><h1 class='text-5xl md:text-7xl font-extrabold text-white mb-6 tracking-tight drop-shadow-lg'>{$title}</h1><p class='text-lg md:text-2xl text-gray-200 mb-10 max-w-2xl drop-shadow-md leading-relaxed'>欢迎来到我们的 Minecraft 服务器。<br>这里是系统分配的默认展示页。管理员可在后台直接上传专属官网压缩包进行替换。</p><div class='space-x-4'>{$authBtn}</div></div></body></html>");
    }

    if ($action === 'do_login') { 
        if (isset($_POST['username']) || isset($_POST['password'])) ban_ip_and_die("STATIC_BOT_LOGIN"); // 变异表单防御
        if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS"); // 生物探针验证
        
        if (!$pdo) { header("Location: ?m=auth&action=login&msg=err_db"); exit; } 
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { header("Location: ?m=auth&msg=err_csrf"); exit; }
        
        $u = strtolower(trim($_POST[$_SESSION['f_u']] ?? '')); $p = $_POST[$_SESSION['f_p']] ?? ''; 
        $stmt = $pdo->prepare("SELECT * FROM authme WHERE username=?"); $stmt->execute([$u]); 
        if ($r = $stmt->fetch()) { if (verifyAuthMe($p, $r['password'])) { session_regenerate_id(true); $_SESSION['user'] = $r; $_SESSION['client_fp'] = $client_fp; header("Location: ?m=auth&action=user_center"); } else header("Location: ?m=auth&action=login&msg=err_pass"); } else header("Location: ?m=auth&action=login&msg=err_user"); exit; 
    }
    
    if ($action === 'do_logout') { session_destroy(); header("Location: ?m=auth"); exit; }
    
    if ($action === 'do_reg') { 
        if (isset($_POST['username']) || isset($_POST['password']) || isset($_POST['email'])) ban_ip_and_die("STATIC_BOT_REG");
        if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS");
        
        if (!$pdo) { header("Location: ?m=auth&action=login&msg=err_db"); exit; } 
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { header("Location: ?m=auth&msg=err_csrf"); exit; }
        if (empty($_SESSION['captcha']) || ($_POST[$_SESSION['f_c']]??'') != $_SESSION['captcha']) { header("Location: ?m=auth&msg=err_captcha"); exit; } 
        
        // ☢️ [神盾 7] 时间膨胀与代理穿透限制
        if (isset($_SESSION['form_render_time']) && (microtime(true) - $_SESSION['form_render_time'] < 2)) ban_ip_and_die("SUPERHUMAN_SPEED_BOT_DETECTED");
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA'])) { header("Location: ?m=auth&msg=err_proxy"); exit; }
        if (isset($_POST['hp_bot_trap']) && !empty($_POST['hp_bot_trap'])) ban_ip_and_die("HONEYPOT_TRIGGERED");

        $u = trim($_POST[$_SESSION['f_u']] ?? ''); $p = $_POST[$_SESSION['f_p']] ?? ''; $e = trim($_POST[$_SESSION['f_e']] ?? '');
        
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $u)) { header("Location: ?m=auth&msg=err_format"); exit; } 
        $u = strtolower($u);
        
        if (isset($_COOKIE['mt_device_lock'])) { header("Location: ?m=auth&msg=err_limit"); exit; } 
        $stmtL = $pdo->prepare("SELECT id FROM authme WHERE ip=?"); $stmtL->execute([$ip]); 
        if ($stmtL->fetch()) { header("Location: ?m=auth&msg=err_limit"); exit; } 
        
        $stmt = $pdo->prepare("SELECT id FROM authme WHERE username=?"); $stmt->execute([$u]); 
        if ($stmt->fetch()) { header("Location: ?m=auth&msg=err_exists"); exit; } 
        
        $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u, $_POST[$_SESSION['f_u']], hashAuthMe($p), $e, $ip, time()*1000, time()*1000]); 
        setcookie('mt_device_lock', hash('sha256', $ip), time() + 315360000, '/'); 
        
        if (!empty($config['rewards']['reg_cmd'])) { runApiCmd(str_replace('%player%', $_POST[$_SESSION['f_u']], $config['rewards']['reg_cmd']), 0); } 
        $smtp = new TinySMTP(); $smtp->send($e, "欢迎加入", "恭喜注册成功！", $config['smtp']); header("Location: ?m=auth&msg=reg_ok"); exit; 
    }

    if ($action === 'do_sign' && isset($_SESSION['user'])) { if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { echo json_encode(['s'=>0,'m'=>'❌ CSRF 拦截']); exit; } $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd'); if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; } $targets = $config['rewards']['sign_in_servers'] ?? []; $ok = 0; foreach ($targets as $sid) { if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']), $sid)) $ok++; } if ($ok > 0) { setUserData($u, 'last_sign', $today); $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count); echo json_encode(['s'=>1, 'm'=>"✅ 签到成功 (发放至 $ok 个服务器)"]); } else { echo json_encode(['s'=>0, 'm'=>'❌ 服务器 MetorCore API 握手失败']); } exit; }
    if ($action === 'do_cdk' && isset($_SESSION['user'])) { if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { echo json_encode(['s'=>0,'m'=>'❌ CSRF 拦截']); exit; } $code = trim($_POST['code']); $srvIdx = (int)$_POST['server_id']; $u = $_SESSION['user']['username']; $cdks = getCdks(); if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; } $c = $cdks[$code]; if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 已被抢光']); exit; } if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 您已领取过']); exit; } if (isset($c['server_id']) && $c['server_id'] !== 'all' && (int)$c['server_id'] !== $srvIdx) { echo json_encode(['s'=>0,'m'=>'❌ 此CDK不适用于该服务器']); exit; } $targetSrv = ($c['server_id'] === 'all') ? $srvIdx : (int)$c['server_id']; if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']), $targetSrv)) { $c['used']++; $c['users'][] = $u; updateCdk($code, $c); echo json_encode(['s'=>1,'m'=>'🎁 兑换成功！']); } else { echo json_encode(['s'=>0,'m'=>'❌ MetorCore 发放失败']); } exit; }
    
    // 找回密码 (API端同样接受生物探针验证)
    if ($action === 'do_fp_send') { 
        if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS");
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { echo json_encode(['s'=>0,'m'=>'❌ CSRF 拦截']); exit; }
        if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开，无法操作']); exit; } $u = strtolower(trim($_POST['u'])); $e = trim($_POST['e']); $stmt = $pdo->prepare("SELECT id, email FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch(); if (!$r || $r['email'] !== $e) { echo json_encode(['s'=>0, 'm'=>'❌ 用户名与邮箱不匹配']); exit; } $code = rand(100000, 999999); $t = time(); try { $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]); } catch (PDOException $e) { if ($e->getCode() == '42S22') { $pdo->exec("ALTER TABLE authme ADD COLUMN reset_code VARCHAR(10), ADD COLUMN reset_time BIGINT"); $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]); } else { echo json_encode(['s'=>0, 'm'=>'❌ 数据库异常']); exit; } } $smtp = new TinySMTP(); $smtp->send($e, "重置密码验证码", "您的验证码是: <b>$code</b> (10分钟内有效)", $config['smtp']); echo json_encode(['s'=>1, 'm'=>'✅ 验证码已发送至邮箱']); exit; 
    }
    
    if ($action === 'do_fp_reset') { 
        if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS");
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { echo json_encode(['s'=>0,'m'=>'❌ CSRF 拦截']); exit; }
        if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开，无法操作']); exit; } $u = strtolower(trim($_POST['u'])); $c = trim($_POST['code']); $p = $_POST['pass']; $stmt = $pdo->prepare("SELECT id, reset_code, reset_time FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch(); if (!$r || $r['reset_code'] !== $c) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码错误']); exit; } if (time() - $r['reset_time'] > 600) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码已过期']); exit; } $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE id=?")->execute([hashAuthMe($p), $r['id']]); echo json_encode(['s'=>1, 'm'=>'🎉 密码修改成功！请登录']); exit; 
    }
    
    if ($action === 'captcha') { $c=rand(1000,9999); $_SESSION['captcha']=$c; $i=imagecreatetruecolor(70,36); imagefill($i,0,0,0x3b82f6); imagestring($i,5,15,10,$c,0xffffff); header("Content-type: image/png"); imagepng($i); exit; }

    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($config['site']['title']) ?> - 流星通行证</title>
        <script src="https://cdn.tailwindcss.com"></script>
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
        
        <script>
            ['mousemove','keydown','touchstart'].forEach(e => 
                window.addEventListener(e, () => { document.querySelectorAll('.bhv_probe').forEach(el => el.value = '<?=$_SESSION['bhv_hash']?>'); }, {once:true})
            );
        </script>
        
        <?php if (!empty($config['modules']['official'])): ?>
            <a href="?m=official" class="fixed top-5 right-5 bg-white/80 backdrop-blur px-4 py-2 rounded-full shadow font-bold text-sm text-gray-700 hover:bg-white transition z-50">🏠 返回官网</a>
        <?php endif; ?>

        <?php if(isset($_GET['msg'])): ?>
        <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
            <?= ['reg_ok'=>'🎉 注册成功！', 'err_pass'=>'🔒 密码错误', 'err_exists'=>'⚠️ 账号已存在', 'err_captcha'=>'❌ 验证码错误', 'err_db'=>'❌ 数据库异常', 'err_limit'=>'⛔ 安全拦截：该设备/IP 一生仅限注册一个账号', 'err_format'=>'❌ 游戏名非法 (限3-16位字母数字下划线)', 'err_csrf'=>'❌ 请求伪造或超时，请刷新页面', 'err_proxy'=>'⛔ 安全拦截：禁止使用代理或VPN进行注册！'][$_GET['msg']] ?? $_GET['msg'] ?>
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
                <a href="?m=auth&action=do_logout" class="ml-auto text-xs bg-red-50 text-red-500 px-3 py-2 rounded hover:bg-red-100 transition">退出</a>
            </div>
            <button onclick="sign(this)" class="w-full mb-6 py-3 rounded-xl font-bold shadow transition border <?= ($udata['last_sign']??0)==date('Ymd') ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-indigo-50 text-indigo-600 border-indigo-100 hover:bg-indigo-100' ?>">
                <?= ($udata['last_sign']??0)==date('Ymd') ? '✅ 今日已签到' : '📅 每日签到' ?>
            </button>
            <div class="space-y-3">
                <label class="text-xs font-bold text-gray-400 uppercase">CDK 兑换</label>
                <select id="sel_srv" class="input font-bold text-blue-900">
                    <?php foreach($config['servers'] as $idx => $srv): ?><option value="<?=$idx?>">🌍 <?= htmlspecialchars($srv['name']) ?></option><?php endforeach; ?>
                </select>
                <div class="flex gap-2">
                    <input type="hidden" id="csrf" value="<?=$_SESSION['csrf_token']?>">
                    <input id="cdk" placeholder="输入兑换码..." class="input">
                    <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700 transition">兑换</button>
                </div>
            </div>
        </div>
        <script>
        function sign(b){ b.disabled=true; b.innerText='...'; fetch('?m=auth&action=do_sign',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`csrf_token=${document.getElementById('csrf').value}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) { b.innerText='✅ 已签到'; b.className='w-full mb-6 py-3 rounded-xl font-bold shadow transition border bg-gray-100 text-gray-400 cursor-not-allowed'; } else b.disabled=false; }); }
        function cdk(){ let c=document.getElementById('cdk').value; let s=document.getElementById('sel_srv').value; let t=document.getElementById('csrf').value; if(!c)return; fetch('?m=auth&action=do_cdk',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`code=${c}&server_id=${s}&csrf_token=${t}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); }
        </script>
        
        <?php else: ?>
        <div class="glass-card w-full max-w-sm p-8 text-center relative fade-in">
            <h1 class="text-3xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600 pb-1"><?= htmlspecialchars($config['site']['title']) ?></h1>
            
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
                <h2 class="text-xl font-bold text-gray-700 mb-4">通行证注册</h2>
                <form action="?m=auth&action=do_reg" method="POST" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                    <input type="hidden" name="bhv_probe" class="bhv_probe" value="bot">
                    <input type="text" name="hp_bot_trap" style="display:none;position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">
                    
                    <input name="<?=$_SESSION['f_u']?>" placeholder="Minecraft 角色名" class="input" required>
                    <input name="<?=$_SESSION['f_e']?>" type="email" placeholder="电子邮箱 (用于找回密码)" class="input" required>
                    <input type="password" name="<?=$_SESSION['f_p']?>" placeholder="设置密码" class="input" required>
                    <div class="flex gap-2">
                        <input name="<?=$_SESSION['f_c']?>" placeholder="验证码" class="input" required>
                        <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded cursor-pointer border border-gray-200">
                    </div>
                    <button class="btn-primary mt-2 bg-gradient-to-r from-green-500 to-emerald-600 border-none">确认注册</button>
                </form>
                <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">已有账号？点击登录</a></p>
            </div>

            <div id="box-login" class="hidden">
                <h2 class="text-xl font-bold text-gray-700 mb-4">通行证登录</h2>
                <form action="?m=auth&action=do_login" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                    <input type="hidden" name="bhv_probe" class="bhv_probe" value="bot">
                    <input name="<?=$_SESSION['f_u']?>" placeholder="游戏角色名" class="input" required>
                    <input type="password" name="<?=$_SESSION['f_p']?>" placeholder="密码" class="input" required>
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
                    <input type="hidden" id="csrf" value="<?=$_SESSION['csrf_token']?>">
                    <input type="hidden" id="bhv_probe_fp" class="bhv_probe" value="bot">
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
        
        // 找回密码 JS 逻辑 (加入了 CSRF 和生物探针传输)
        function sendCode() { 
            let u=document.getElementById('fp_u').value, e=document.getElementById('fp_e').value, t=document.getElementById('csrf').value, b=document.getElementById('bhv_probe_fp').value; 
            if(!u || !e) { alert('请填写用户名和邮箱'); return; } 
            fetch('?m=auth&action=do_fp_send', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&e=${e}&csrf_token=${t}&bhv_probe=${b}` })
            .then(r=>r.json()).then(d => alert(d.m)); 
        }
        
        function doReset() { 
            let u=document.getElementById('fp_u').value, c=document.getElementById('fp_c').value, p=document.getElementById('fp_p').value, t=document.getElementById('csrf').value, b=document.getElementById('bhv_probe_fp').value; 
            if(!c || !p) { alert('请填写完整信息'); return; } 
            fetch('?m=auth&action=do_fp_reset', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&code=${c}&pass=${p}&csrf_token=${t}&bhv_probe=${b}` })
            .then(r=>r.json()).then(d => { alert(d.m); if(d.s) toggle('box-login'); }); 
        }
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php exit;
}
?>
