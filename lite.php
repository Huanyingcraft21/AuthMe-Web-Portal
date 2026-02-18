<?php
/**
 * Project: 流星MCS Lite (Premium UI Edition)
 * Note: 极简内核 + 豪华界面
 */
session_start();error_reporting(0);header('Content-Type: text/html; charset=utf-8');
$C=include'config.php';$A=$_GET['a']??0;
if(!is_array($C))die('<body style="text-align:center;padding:50px;font-family:sans-serif"><h2>⚠️ Config Missing</h2><p>Please run <b>install.php</b> first.</p></body>');

// --- 极缩内核 ---
$D=null;try{$D=new PDO("mysql:host={$C['db']['host']};dbname={$C['db']['name']}",$C['db']['user'],$C['db']['pass']);}catch(E $e){}
function H($p){$s=bin2hex(random_bytes(8));return"\$SHA\$$s\$".hash('sha256',hash('sha256',$p).$s);}
function V($p,$h){$x=explode('$',$h);return@$x[1]=='SHA'&&hash('sha256',hash('sha256',$p).@$x[2])===@$x[3];}
function J($s,$m,$d=[]){die(json_encode(array_merge(['s'=>$s,'m'=>$m],$d)));}
// SMTP
function S($t,$s,$b){global $C;$o=$C['smtp'];if(!$t)return;$h=($o['secure']=='ssl'?'ssl://':'').$o['host'];$k=@fsockopen($h,$o['port']);if(!$k)return;foreach(["EHLO $h","AUTH LOGIN",base64_encode($o['user']),base64_encode($o['pass']),"MAIL FROM:<{$o['user']}>","RCPT TO:<$t>","DATA"]as$v)W($k,$v);fwrite($k,"Content-Type:text/html;charset=UTF-8\r\nSubject:=?UTF-8?B?".base64_encode($s)."?=\r\n\r\n$b\r\n.\r\n");W($k,"QUIT");fclose($k);}
function W($k,$c){fwrite($k,"$c\r\n");while($x=fgets($k,515))if(substr($x,3,1)==' ')break;}
// RCON
function R($c,$i=0){global $C;$s=$C['servers'][$i]??0;if(!$s||!$s['rcon_pass'])return 0;$k=@fsockopen($s['ip'],$s['rcon_port'],$e,$r,2);if(!$k)return 0;$p=pack("VV",1,3).$s['rcon_pass']."\0\0";fwrite($k,pack("V",strlen($p)).$p);fread($k,4096);$p=pack("VV",2,2).$c."\0\0";fwrite($k,pack("V",strlen($p)).$p);return 1;}

// --- 业务逻辑 ---
if($A=='g'){ // Reg
 if($_POST['c']!=$_SESSION['c'])J(0,'❌ 验证码错误');$u=strtolower(trim($_POST['u']));$ip=$_SERVER['REMOTE_ADDR'];
 if($D->query("SELECT id FROM authme WHERE username='$u'")->fetch())J(0,'⚠️ 用户名已存在');
 if($D->query("SELECT id FROM authme WHERE ip='$ip'")->fetch())J(0,'⚠️ IP限制注册');
 $D->prepare("INSERT INTO authme(username,realname,password,email,ip,regdate,lastlogin)VALUES(?,?,?,?,?,?,?)")->execute([$u,$_POST['u'],H($_POST['p']),$_POST['e'],$ip,time()*1000,time()*1000]);
 if($c=$C['rewards']['reg_cmd'])R(str_replace('%player%',$_POST['u'],$c),0);S($_POST['e'],"Welcome","Registered!",0);J(1,'🎉 注册成功！请登录');
}
if($A=='l'){ // Login
 $u=strtolower(trim($_POST['u']));$r=$D->query("SELECT * FROM authme WHERE username='$u'")->fetch();
 if($r&&V($_POST['p'],$r['password'])){$_SESSION['u']=$r;J(1,'登录成功');}J(0,'❌ 账号或密码错误');
}
if($A=='s'&&$u=$_SESSION['u']){ // Sign
 $f='user_data.json';$d=file_exists($f)?json_decode(file_get_contents($f),true):[];$t=date('Ymd');
 if(($d[$u['username']]['l']??0)==$t)J(0,'📅 今日已签');$ok=0;
 foreach(($C['rewards']['sign_in_servers']??[])as$i)if(R(str_replace('%player%',$u['realname'],$C['rewards']['daily_cmd']),$i))$ok++;
 if($ok){$d[$u['username']]['l']=$t;$d[$u['username']]['c']=($d[$u['username']]['c']??0)+1;file_put_contents($f,json_encode($d));J(1,'✅ 签到成功',$d[$u['username']]);}J(0,'❌ 连接服务器失败');
}
if($A=='k'&&$u=$_SESSION['u']){ // CDK
 $f='cdk_data.json';$d=file_exists($f)?json_decode(file_get_contents($f),true):[];$k=trim($_POST['k']);$s=(int)$_POST['s'];$c=$d[$k]??0;
 if(!$c||$c['used']>=$c['max']||in_array($u['username'],$c['users']))J(0,'🚫 无效或已使用');
 if(isset($c['server_id'])&&$c['server_id']!=='all'&&(int)$c['server_id']!==$s)J(0,'⚠️ 此服无法使用该码');
 $ts=($c['server_id']==='all')?$s:(int)$c['server_id'];
 if(R(str_replace('%player%',$u['realname'],$c['cmd']),$ts)){$d[$k]['used']++;$d[$k]['users'][]=$u['username'];file_put_contents($f,json_encode($d));J(1,'🎁 兑换成功');}J(0,'❌ 发放失败');
}
if($A=='p'){$n=rand(1111,9999);$_SESSION['c']=$n;$i=imagecreatetruecolor(60,34);imagefill($i,0,0,0x3b82f6);imagestring($i,5,12,9,$n,0xffffff);header('Content-type:image/png');imagepng($i);exit;}
if($A=='o'){session_destroy();header("Location:?");exit;}

// --- 渲染配置 ---
$U=$_SESSION['u']??0; $BG=$C['site']['bg']?:'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920';
$SIP=$C['display']['ip']??($C['servers'][0]['ip']??''); $SPT=$C['display']['port']??25565;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= $C['site']['title'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body{background:url('<?= $BG ?>') no-repeat center center fixed;background-size:cover}
        .glass{background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);border-radius:1rem;box-shadow:0 8px 32px rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.4)}
        .inp{width:100%;padding:0.7rem;border:1px solid #e5e7eb;border-radius:0.5rem;background:rgba(255,255,255,0.8);transition:0.2s;outline:none}
        .inp:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.2)}
        .btn{width:100%;padding:0.75rem;border-radius:0.5rem;font-weight:bold;color:white;background:linear-gradient(135deg,#2563eb,#1d4ed8);transition:0.2s}
        .btn:active{transform:scale(0.98)}
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

<?php if($U): $D=file_exists('user_data.json')?json_decode(file_get_contents('user_data.json'),true):[]; $UD=$D[$U['username']]??[]; ?>
    <div class="glass w-full max-w-md p-8 animate-[fadeIn_0.5s]">
        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200">
            <img src="https://cravatar.eu/helmavatar/<?=$U['realname']?>/64.png" class="w-16 h-16 rounded-xl shadow-md">
            <div>
                <h2 class="text-xl font-bold"><?=$U['realname']?></h2>
                <div class="text-sm text-gray-500">累计签到: <span id="sc" class="font-bold text-blue-600"><?=$UD['c']??0?></span> 天</div>
            </div>
            <a href="?a=o" class="ml-auto text-xs text-red-500 bg-red-50 px-3 py-2 rounded hover:bg-red-100">退出</a>
        </div>

        <button onclick="req('?a=s',{},(d)=>{if(d.s){this.innerText='已签到';document.getElementById('sc').innerText=d.c}alert(d.m)})" class="w-full bg-indigo-50 text-indigo-700 font-bold py-3 rounded-xl mb-6 border border-indigo-100 hover:bg-indigo-100 transition">
            <?= ($UD['l']??0)==date('Ymd')?'✅ 今日已签':'📅 每日签到' ?>
        </button>

        <div class="space-y-3">
            <label class="text-xs font-bold text-gray-400 uppercase">CDK 兑换中心</label>
            <select id="s" class="inp font-bold text-blue-900">
                <?php foreach($C['servers'] as $k=>$v) echo "<option value='$k'>🌍 {$v['name']}</option>"; ?>
            </select>
            <div class="flex gap-2">
                <input id="k" placeholder="输入礼包码" class="inp">
                <button onclick="req('?a=k',{k:val('k'),s:val('s')},(d)=>{alert(d.m);if(d.s)val('k','')})" class="bg-green-600 text-white px-6 rounded-lg font-bold shadow hover:bg-green-700">兑换</button>
            </div>
        </div>
        <div class="mt-8 text-center text-xs text-gray-400">Lite v1.7.5</div>
    </div>

<?php else: ?>
    <div class="glass w-full max-w-sm p-8 text-center shadow-2xl relative overflow-hidden">
        <h1 class="text-3xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600"><?= $C['site']['title'] ?></h1>
        
        <?php if($SIP): ?>
        <div id="st" class="hidden bg-white/60 p-2 rounded-lg mb-6 flex items-center gap-3 border border-white/50 text-left">
            <img id="icon" class="w-10 h-10 rounded bg-gray-200">
            <div class="flex-1 min-w-0"><div id="motd" class="text-xs text-gray-500 truncate">Loading...</div><div id="on" class="text-sm font-bold text-green-600">Checking...</div></div>
        </div>
        <script>
            fetch('https://api.mcsrvstat.us/2/<?= $SIP ?>:<?= $SPT ?>').then(r=>r.json()).then(d=>{
                document.getElementById('st').classList.remove('hidden'); document.getElementById('icon').src=d.icon;
                document.getElementById('on').innerText=d.online?d.players.online+' 在线':'离线';
                document.getElementById('motd').innerText=d.motd.clean.join(' ');
            });
        </script>
        <?php endif; ?>

        <div id="box-l">
            <form onsubmit="return sub('?a=l',this)">
                <input name="u" placeholder="游戏角色名" class="inp mb-3" required>
                <input type="password" name="p" placeholder="密码" class="inp mb-4" required>
                <button class="btn shadow-lg shadow-blue-500/30">立即登录</button>
            </form>
            <p class="mt-4 text-sm text-gray-500">首次使用? <a href="#" onclick="tog()" class="text-blue-600 font-bold">注册账号</a></p>
        </div>

        <div id="box-r" class="hidden">
            <form onsubmit="return sub('?a=g',this)">
                <input name="u" placeholder="设置游戏名" class="inp mb-3" required>
                <input name="e" type="email" placeholder="电子邮箱" class="inp mb-3" required>
                <input type="password" name="p" placeholder="设置密码" class="inp mb-3" required>
                <div class="flex gap-2 mb-4"><input name="c" placeholder="验证码" class="inp" required><img src="?a=p" onclick="this.src='?a=p&'+Math.random()" class="h-11 rounded border cursor-pointer"></div>
                <button class="btn bg-gradient-to-r from-green-500 to-emerald-600 shadow-lg shadow-green-500/30">确认注册</button>
            </form>
            <p class="mt-4 text-sm text-gray-500"><a href="#" onclick="tog()" class="text-blue-600 font-bold">返回登录</a></p>
        </div>
    </div>
<?php endif; ?>

<script>
val=(i,v)=>{let e=document.getElementById(i);if(v!==undefined)e.value=v;return e.value};
tog=()=>{document.getElementById('box-l').classList.toggle('hidden');document.getElementById('box-r').classList.toggle('hidden');};
req=(u,d,cb)=>{let f=new FormData();for(let k in d)f.append(k,d[k]);fetch(u,{method:'POST',body:f}).then(r=>r.json()).then(cb)};
sub=(u,f)=>{req(u,Object.fromEntries(new FormData(f)),(d)=>{alert(d.m);if(d.s)location.reload()});return false};
</script>
</body>
</html>
