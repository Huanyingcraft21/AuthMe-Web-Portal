<?php
/**
 * Project: 流星MCS v1.7 (All-in-One)
 * Note: 单文件全功能版 | 多服支持 | 内置后台
 */
session_start();error_reporting(0);header('Content-Type:text/html;charset=utf-8');
$CF='config.php';if(!file_exists($CF))die('<body style="text-align:center;padding:50px;font-family:sans-serif"><h2>⚠️ 尚未安装</h2><p>请先运行 <a href="install.php">install.php</a> 进行安装。</p></body>');
$C=include$CF;$A=$_GET['a']??'';$M='';

// --- 核心库 (压缩版) ---
$D=null;try{$D=new PDO("mysql:host={$C['db']['host']};dbname={$C['db']['name']}",$C['db']['user'],$C['db']['pass']);}catch(E $e){}
function H($p){$s=bin2hex(random_bytes(8));return"\$SHA\$$s\$".hash('sha256',hash('sha256',$p).$s);}
function V($p,$h){$x=explode('$',$h);return@$x[1]=='SHA'&&hash('sha256',hash('sha256',$p).@$x[2])===@$x[3];}
function J($s,$m,$d=[]){die(json_encode(array_merge(['s'=>$s,'m'=>$m],$d)));}
function GD($f){return file_exists($f)?json_decode(file_get_contents($f),true):[];}
function SD($f,$d){file_put_contents($f,json_encode($d));}
function SC($n){global $CF;file_put_contents($CF,"<?php\nreturn ".var_export($n,true).";");}
// Net
class TR{private $s;function c($h,$p,$w){$this->s=@fsockopen($h,$p,$e,$r,2);if(!$this->s)return 0;$this->w(3,$w);return 1;}function m($c){$this->w(2,$c);return 1;}private function w($t,$d){$p=pack("VV",rand(),$t).$d."\x00\x00";fwrite($this->s,pack("V",strlen($p)).$p);}}
class TM{function S($t,$s,$b,$o){if(!$t)return;$h=($o['secure']=='ssl'?'ssl://':'').$o['host'];$k=@fsockopen($h,$o['port']);if(!$k)return;$this->W($k,["EHLO $h","AUTH LOGIN",base64_encode($o['user']),base64_encode($o['pass']),"MAIL FROM:<{$o['user']}>","RCPT TO:<$t>","DATA"]);fwrite($k,"Content-Type:text/html;charset=UTF-8\r\nSubject:=?UTF-8?B?".base64_encode($s)."?=\r\n\r\n$b\r\n.\r\n");$this->W($k,["QUIT"]);fclose($k);}function W($k,$a){foreach($a as $c){fwrite($k,"$c\r\n");while($x=fgets($k,515))if(substr($x,3,1)==' ')break;}}}
function Run($cmd,$i=0){global $C;$s=$C['servers'][$i]??0;if(!$s||!$s['rcon_pass'])return 0;$r=new TR;return($r->c($s['ip'],$s['rcon_port'],$s['rcon_pass']))?$r->m($cmd):0;}

// --- 🎮 前台逻辑 ---
if($A=='g'){ // Reg
 if($_POST['c']!=$_SESSION['c'])J(0,'❌ 验证码错误');$u=strtolower(trim($_POST['u']));$ip=$_SERVER['REMOTE_ADDR'];
 if($D->query("SELECT id FROM authme WHERE username='$u'")->fetch())J(0,'⚠️ 用户名已存在');
 $D->prepare("INSERT INTO authme(username,realname,password,email,ip,regdate,lastlogin)VALUES(?,?,?,?,?,?,?)")->execute([$u,$_POST['u'],H($_POST['p']),$_POST['e'],$ip,time()*1000,time()*1000]);
 if($c=$C['rewards']['reg_cmd'])Run(str_replace('%player%',$_POST['u'],$c),0);(new TM)->S($_POST['e'],"Welcome","Hi!",$C['smtp']);J(1,'🎉 注册成功');
}
if($A=='l'){ // Login
 $u=strtolower(trim($_POST['u']));$r=$D->query("SELECT * FROM authme WHERE username='$u'")->fetch();
 if($r&&V($_POST['p'],$r['password'])){$_SESSION['u']=$r;J(1,'OK');}J(0,'❌ 账号或密码错误');
}
if($A=='s'&&$u=$_SESSION['u']){ // Sign
 $f='user_data.json';$d=GD($f);$t=date('Ymd');if(($d[$u['username']]['l']??0)==$t)J(0,'📅 今日已签');$ok=0;
 foreach(($C['rewards']['sign_in_servers']??[])as$i)if(Run(str_replace('%player%',$u['realname'],$C['rewards']['daily_cmd']),$i))$ok++;
 if($ok){$d[$u['username']]['l']=$t;$d[$u['username']]['c']=($d[$u['username']]['c']??0)+1;SD($f,$d);J(1,'✅ 签到成功',$d[$u['username']]);}J(0,'❌ 连接服务器失败');
}
if($A=='k'&&$u=$_SESSION['u']){ // CDK
 $f='cdk_data.json';$d=GD($f);$k=trim($_POST['k']);$s=(int)$_POST['s'];$c=$d[$k]??0;
 if(!$c||$c['used']>=$c['max']||in_array($u['username'],$c['users']))J(0,'🚫 无效或已使用');
 if(isset($c['server_id'])&&$c['server_id']!=='all'&&(int)$c['server_id']!==$s)J(0,'⚠️ 此服无法使用该码');
 if(Run(str_replace('%player%',$u['realname'],$c['cmd']),($c['server_id']==='all'?$s:(int)$c['server_id']))){
  $d[$k]['used']++;$d[$k]['users'][]=$u['username'];SD($f,$d);J(1,'🎁 兑换成功');
 }J(0,'❌ 发放失败');
}

// --- 🔧 后台逻辑 ---
if($A=='alogin'){ if($_POST['u']===$C['admin']['user']&&$_POST['p']===$C['admin']['pass']){$_SESSION['adm']=1;J(1,'OK');}J(0,'❌ 密码错误'); }
if($A=='asave' && $_SESSION['adm']){
    $N=$C; foreach($_POST as $k=>$v)if(isset($N[$k]))$N[$k]=$v; 
    if($_POST['sv_json'])$N['servers']=json_decode($_POST['sv_json'],true);
    $N['rewards']['sign_in_servers']=explode(',',$_POST['sis']);
    $N['display']['ip']=$_POST['dip'];$N['display']['port']=$_POST['dpt'];
    SC($N);J(1,'✅ 配置已保存');
}
if($A=='acdk' && $_SESSION['adm']){
    $d=GD('cdk_data.json');$c=trim($_POST['c']);
    if($_POST['act']=='del'){unset($d[$c]);}else{$d[$c]=['cmd'=>$_POST['cmd'],'max'=>(int)$_POST['use'],'server_id'=>$_POST['sid'],'used'=>0,'users'=>[]];}
    SD('cdk_data.json',$d);J(1,'OK');
}
if($A=='arcon' && $_SESSION['adm']){ J(1,Run($_POST['cmd'],(int)$_POST['sid'])?'指令已发送':'连接失败'); }

// Commons
if($A=='p'){$n=rand(1111,9999);$_SESSION['c']=$n;$i=imagecreatetruecolor(60,34);imagefill($i,0,0,0x3b82f6);imagestring($i,5,12,9,$n,0xffffff);header('Content-type:image/png');imagepng($i);exit;}
if($A=='out'){session_destroy();header("Location:?");exit;}

// --- 渲染 ---
$BG=$C['site']['bg']?:'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920';
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $C['site']['title'] ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:url('<?= $BG ?>') center/cover fixed}.g{background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);border-radius:1rem;box-shadow:0 8px 32px rgba(0,0,0,0.2)}.i{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px}.b{width:100%;padding:10px;border-radius:6px;font-weight:bold;color:white;background:#2563eb}.h{display:none}</style>
</head><body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

<?php if($A=='admin' || isset($_SESSION['adm'])): ?>
    <?php if(!isset($_SESSION['adm'])): ?>
        <div class="g w-full max-w-sm p-8 text-center">
            <h2 class="text-2xl font-bold mb-4">🛡️ 管理员登录</h2>
            <input id="au" placeholder="Admin User" class="i"><input id="ap" type="password" placeholder="Password" class="i">
            <button onclick="req('?a=alogin',{u:v('au'),p:v('ap')},(d)=>{if(d.s)location.reload();else alert(d.m)})" class="b bg-gray-800">Login</button>
            <div class="mt-4"><a href="?" class="text-sm text-blue-600">返回前台</a></div>
        </div>
    <?php else: ?>
        <div class="g w-full max-w-4xl p-6 h-[85vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b">
                <h2 class="text-xl font-bold">🛠️ 控制面板 <span class="text-xs font-normal text-gray-500">v1.7 All-in-One</span></h2>
                <a href="?a=out" class="text-red-500 text-sm font-bold">退出</a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-bold mb-2 text-blue-600">⚙️ 系统设置</h3>
                    <div class="space-y-1">
                        <label class="text-xs">网站标题</label><input id="st" value="<?=$C['site']['title']?>" class="i">
                        <label class="text-xs">背景图URL</label><input id="bg" value="<?=$C['site']['bg']?>" class="i">
                        <label class="text-xs">前端展示IP (Proxy)</label><input id="dip" value="<?=$C['display']['ip']?>" class="i">
                        <label class="text-xs">展示端口</label><input id="dpt" value="<?=$C['display']['port']?>" class="i">
                        <label class="text-xs">RCON服务器列表 (JSON)</label>
                        <textarea id="svj" class="i font-mono text-xs h-24 bg-gray-50"><?=json_encode($C['servers'])?></textarea>
                        <button onclick="save()" class="b mt-2 bg-green-600">保存配置</button>
                    </div>
                </div>
                <div class="space-y-6">
                    <div>
                        <h3 class="font-bold mb-2 text-green-600">🎁 奖励配置</h3>
                        <label class="text-xs">注册指令</label><input id="rc" value="<?=$C['rewards']['reg_cmd']?>" class="i">
                        <label class="text-xs">签到指令</label><input id="dc" value="<?=$C['rewards']['daily_cmd']?>" class="i">
                        <label class="text-xs">签到服务器ID (逗号隔开)</label><input id="sis" value="<?=implode(',',$C['rewards']['sign_in_servers']??[])?>" class="i">
                    </div>
                    <div>
                        <h3 class="font-bold mb-2 text-gray-800">🖥️ 快捷 RCON</h3>
                        <div class="flex gap-2">
                            <select id="rsid" class="i w-32"><?php foreach($C['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select>
                            <input id="rcmd" placeholder="指令..." class="i">
                        </div>
                        <button onclick="req('?a=arcon',{cmd:v('rcmd'),sid:v('rsid')},(d)=>{alert(d.m)})" class="b bg-black">发送指令</button>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t">
                <h3 class="font-bold mb-2 text-purple-600">🎫 CDK 生成</h3>
                <div class="flex flex-wrap gap-2 mb-4">
                    <input id="ck" placeholder="代码" class="i w-32">
                    <input id="cc" placeholder="指令" class="i flex-1">
                    <input id="cu" placeholder="次数" type="number" class="i w-20" value="1">
                    <select id="cs" class="i w-28"><option value="all">全服</option><?php foreach($C['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select>
                    <button onclick="mkcdk()" class="b w-24 bg-purple-600">生成</button>
                </div>
                <div class="text-xs text-gray-500 bg-gray-50 p-2 rounded">
                    <?php $cdks=GD('cdk_data.json'); foreach($cdks as $k=>$d) echo "<div class='flex justify-between border-b py-1'><span><b>$k</b> ({$d['used']}/{$d['max']}) -> {$d['cmd']}</span><a href='#' onclick=\"rmcdk('$k')\" class='text-red-500'>删</a></div>"; ?>
                </div>
            </div>
        </div>
        <script>
        function save(){req('?a=asave',{site_title:v('st'),site_bg:v('bg'),sv_json:v('svj'),reg_cmd:v('rc'),daily_cmd:v('dc'),sis:v('sis'),dip:v('dip'),dpt:v('dpt')},(d)=>{alert(d.m)})}
        function mkcdk(){req('?a=acdk',{c:v('ck'),cmd:v('cc'),use:v('cu'),sid:v('cs'),act:'add'},(d)=>{location.reload()})}
        function rmcdk(k){if(confirm('Del?'))req('?a=acdk',{c:k,act:'del'},(d)=>{location.reload()})}
        </script>
    <?php endif; ?>

<?php elseif(isset($_SESSION['u'])): $U=$_SESSION['u']; $UD=GD('user_data.json')[$U['username']]??[]; ?>
    <div class="g w-full max-w-md p-8">
        <div class="flex items-center gap-4 mb-6 border-b pb-4">
            <img src="https://cravatar.eu/helmavatar/<?=$U['realname']?>/64.png" class="w-14 h-14 rounded">
            <div><div class="font-bold text-lg"><?=$U['realname']?></div><div class="text-xs text-gray-500">签到: <?=$UD['c']??0?> 天</div></div>
            <a href="?a=out" class="ml-auto text-xs bg-red-100 text-red-600 px-3 py-1 rounded">退出</a>
        </div>
        <button onclick="req('?a=s',{},(d)=>{alert(d.m);if(d.s)location.reload()})" class="b mb-6 bg-indigo-500 shadow-lg shadow-indigo-500/30">
            <?= ($UD['l']??0)==date('Ymd')?'✅ 今日已签':'📅 每日签到' ?>
        </button>
        <div class="space-y-2">
            <div class="text-xs font-bold text-gray-400">CDK 兑换</div>
            <select id="srv" class="i font-bold text-blue-900"><?php foreach($C['servers'] as $k=>$v) echo "<option value='$k'>🌍 {$v['name']}</option>"; ?></select>
            <div class="flex gap-2"><input id="key" placeholder="兑换码" class="i"><button onclick="req('?a=k',{k:v('key'),s:v('srv')},(d)=>{alert(d.m);if(d.s)v('key','')})" class="b w-24 bg-green-600">兑换</button></div>
        </div>
    </div>

<?php else: ?>
    <div class="g w-full max-w-sm p-8 text-center relative">
        <h1 class="text-2xl font-bold mb-6 text-blue-600"><?= $C['site']['title'] ?></h1>
        <?php if($C['display']['ip']): ?><div id="st" class="h bg-white/50 p-2 rounded mb-4 text-left text-xs flex gap-2"><div id="on" class="font-bold text-green-600">Checking...</div><div id="mo" class="truncate flex-1 text-gray-500">...</div></div><script>fetch('https://api.mcsrvstat.us/2/<?=$C['display']['ip']?>:<?=$C['display']['port']?>').then(r=>r.json()).then(d=>{document.getElementById('st').style.display='flex';document.getElementById('on').innerText=d.online?d.players.online+' On':'Off';document.getElementById('mo').innerText=d.motd.clean.join(' ')})</script><?php endif; ?>
        
        <div id="bl">
            <form onsubmit="return sub('?a=l',this)"><input name="u" placeholder="ID" class="i" required><input type="password" name="p" placeholder="PW" class="i" required><button class="b mt-2">登录</button></form>
            <p class="mt-4 text-xs text-gray-500">无账号? <a href="#" onclick="tog()" class="text-blue-600 font-bold">注册</a></p>
        </div>
        <div id="br" class="h">
            <form onsubmit="return sub('?a=g',this)"><input name="u" placeholder="Set ID" class="i" required><input name="e" placeholder="Email" class="i" required><input type="password" name="p" placeholder="Set PW" class="i" required><div class="flex gap-2"><input name="c" placeholder="Code" class="i" required><img src="?a=p" onclick="this.src='?a=p&'+Math.random()" class="h-10 rounded border cursor-pointer"></div><button class="b mt-2 bg-green-600">注册</button></form>
            <p class="mt-4 text-xs text-gray-500"><a href="#" onclick="tog()" class="text-blue-600 font-bold">返回登录</a></p>
        </div>
    </div>
<?php endif; ?>

<script>
v=i=>document.getElementById(i).value;
tog=()=>{document.getElementById('bl').classList.toggle('h');document.getElementById('br').classList.toggle('h');};
req=(u,d,f)=>{let m=new FormData();for(let k in d)m.append(k,d[k]);fetch(u,{method:'POST',body:m}).then(r=>r.json()).then(f)};
sub=(u,f)=>{req(u,Object.fromEntries(new FormData(f)),(d)=>{alert(d.m);if(d.s)location.reload()});return false};
</script>
</body></html>
