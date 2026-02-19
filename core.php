<?php
/**
 * Project: 流星MCS Core
 * Version: v1.7.5 (Proxy Ready + Patched)
 */
error_reporting(0);
$configFile = 'config.php';
if (!file_exists($configFile) && !defined('IN_INSTALL')) die("Error: config.php missing.");

$config = [];
if (file_exists($configFile)) {
    $defaultConfig = [
        'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
        'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
        'admin' => ['user'=>'admin', 'pass'=>'password123', 'email'=>''],
        'site' => ['title'=>'流星MCS', 'ver'=>'1.7.5', 'bg'=>''],
        'display' => ['ip'=>'', 'port'=>'25565'], 
        'servers' => [['name'=>'Default', 'ip'=>'127.0.0.1', 'port'=>25565, 'rcon_port'=>25575, 'rcon_pass'=>'']],
        'rewards' => ['reg_cmd'=>'', 'daily_cmd'=>'']
    ];
    $loaded = include($configFile);
    $config = isset($loaded['host']) ? array_replace_recursive($defaultConfig, ['db'=>$loaded]) : array_replace_recursive($defaultConfig, $loaded);
}

// 兼容性修正
if (empty($config['display']['ip']) && !empty($config['servers'][0]['ip'])) {
    $config['display']['ip'] = $config['servers'][0]['ip'];
    $config['display']['port'] = $config['servers'][0]['port'];
}

// DB
$pdo = null;
if (!empty($config['db']['name'])) {
    try {
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {}
}

// Utils
function saveConfig($newConfig) { global $configFile; return file_put_contents($configFile, "<?php\nreturn " . var_export($newConfig, true) . ";"); }
function hashAuthMe($p) { $s = bin2hex(random_bytes(8)); return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s); }
// 修复：更改变量名为 $parts，防止覆写 $p 导致任何密码比对全为 false
function verifyAuthMe($p, $hash) { $parts=explode('$', $hash); if(count($parts)===4&&$parts[1]==='SHA') return hash('sha256',hash('sha256',$p).$parts[2])===$parts[3]; return false; }

// RCON
class TinyRcon {
    private $sock; private $id=0;
    public function connect($h,$p,$pw){ $this->sock=@fsockopen($h,$p,$e,$r,3); if(!$this->sock)return false; $this->write(3,$pw); return $this->read(); }
    public function cmd($c){ $this->write(2,$c); return $this->read(); }
    private function write($t,$d){ $p=pack("VV",++$this->id,$t).$d."\x00\x00"; fwrite($this->sock,pack("V",strlen($p)).$p); }
    private function read(){ $s=fread($this->sock,4); if(strlen($s)<4)return false; $l=unpack("V",$s)[1]; if($l>4096)$l=4096; return substr(fread($this->sock,$l),8,-2); }
}
function runRcon($cmd, $serverIdx = 0) {
    global $config;
    if (!isset($config['servers'][$serverIdx])) return false;
    $s = $config['servers'][$serverIdx];
    if (empty($s['rcon_pass']) || empty($cmd)) return false;
    $r = new TinyRcon();
    if ($r->connect($s['ip'], $s['rcon_port'], $s['rcon_pass'])) return $r->cmd($cmd);
    return false;
}

// SMTP
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

// Data Handling (修复：加入 LOCK_EX 防止高并发文件损坏)
$userDataFile='user_data.json'; $cdkFile='cdk_data.json';
function getUserData($u){ global $userDataFile; $d=file_exists($userDataFile)?json_decode(file_get_contents($userDataFile),true):[]; return $d[$u]??[]; }
function setUserData($u,$k,$v){ global $userDataFile; $d=file_exists($userDataFile)?json_decode(file_get_contents($userDataFile),true):[]; $d[$u][$k]=$v; file_put_contents($userDataFile,json_encode($d), LOCK_EX); }
function getCdks(){ global $cdkFile; return file_exists($cdkFile)?json_decode(file_get_contents($cdkFile),true):[]; }
if(!function_exists('saveCdks')){ function saveCdks($d){ global $cdkFile; file_put_contents($cdkFile,json_encode($d), LOCK_EX); } }
if(!function_exists('updateCdk')){ function updateCdk($c,$d){ $all=getCdks(); $all[$c]=$d; saveCdks($all); } }

// Security
$limitFile='login_limit.json';
if(!function_exists('checkLock')){ function checkLock($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; foreach($d as $k=>$v){if(time()-$v['t']>3600)unset($d[$k]);} if(isset($d[$ip])&&$d[$ip]['c']>=3&&time()-$d[$ip]['t']<3600)return true; return false; } }
if(!function_exists('logFail')){ function logFail($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!$d)$d=[]; if(!isset($d[$ip]))$d[$ip]=['c'=>0,'t'=>time()]; $d[$ip]['c']++; $d[$ip]['t']=time(); file_put_contents($f,json_encode($d)); return $d[$ip]['c']; } }
if(!function_exists('clearFail')){ function clearFail($f){ $ip=$_SERVER['REMOTE_ADDR']; $d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(isset($d[$ip])){unset($d[$ip]);file_put_contents($f,json_encode($d));} } }
?>
