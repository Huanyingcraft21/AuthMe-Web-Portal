<?php
/**
 * Project: 流星MCS v1.8 (All-in-One)
 * Note: 单文件全功能 | 修复版本读取 | 找回密码 | 多服
 */
session_start();error_reporting(0);header('Content-Type:text/html;charset=utf-8');
$CF='config.php';if(!file_exists($CF))die('<body style="text-align:center;padding:50px;font-family:sans-serif"><h2>⚠️ 尚未安装</h2><p>请先运行 <a href="install.php">install.php</a>。</p></body>');
$C=include$CF;$A=$_GET['a']??'';

// --- 核心库 ---
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

// --- 🎮 业务 ---
if($A=='g'){ // Reg
 if($_POST['c']!=$_SESSION['c'])J(0,'❌ 验证码错误');$u=strtolower(trim($_POST['u']));$ip=$_SERVER['REMOTE_ADDR'];
 if($D->query("SELECT id FROM authme WHERE username='$u'")->fetch())J(0,'⚠️ 用户名已存在');
 $D->prepare("INSERT INTO authme(username,realname,password,email,ip,regdate,lastlogin)VALUES(?,?,?,?,?,?,?)")->execute([$u,$_POST['u'],H($_POST['p']),$_POST['e'],$ip,time()*1000,time()*1000]);
 if($c=$C['rewards']['reg_cmd'])Run(str_replace('%player%',$_POST['u'],$c),0);(new TM)->S($_POST['e'],"Welcome","Hi!",$C['smtp']);J(1,'🎉 注册成功');
}
if($A=='l'){ // Login
 $u=strtolower(trim($_POST['u']));$r=$D->query("SELECT * FROM authme WHERE username='$u'")->fetch();
 if($r&&V($_POST['p'],$r['password'])){$_SESSION['u']=$r;J(1,'OK');}J(0,'❌ 失败');
}
if($A=='fp_s'){ // FP Send
    $u=strtolower(trim($_POST['u'])); $e=trim($_POST['e']);
    $r=$D->query("SELECT id,email FROM authme WHERE username='$u'")->fetch();
    if(!$r || $r['email']!==$e)J(0,'❌ 信息不匹配');
    $c=rand(100000,999999); $D->prepare("UPDATE authme SET reset_code=?,reset_time=? WHERE id=?")->execute([$c,time(),$r['id']]);
    (new TM)->S($e,"Reset Code","Code: <b>$c</b> (10min)",$C['smtp']);J(1,'✅ 发送成功');
}
if($A=='fp_d'){ // FP Do
    $u=strtolower(trim($_POST['u'])); $c=trim($_POST['c']); $p=$_POST['p'];
    $r=$D->query("SELECT id,reset_code,reset_time FROM authme WHERE username='$u'")->fetch();
    if(!$r || $r['reset_code']!==$c)J(0,'❌ 验证码错');
    if(time()-$r['reset_time']>600)J(0,'❌ 过期');
    $D->prepare("UPDATE authme SET password=?,reset_code=NULL WHERE id=?")->execute([H($p),$r['id']]);J(1,'🎉 密码修改成功');
}

// User Actions
if($A=='s'&&$u=$_SESSION['u']){ // Sign
 $f='user_data.json';$d=GD($f);$t=date('Ymd');if(($d[$u['username']]['l']??0)==$t)J(0,'📅 已签');$ok=0;
 foreach(($C['rewards']['sign_in_servers']??[])as$i)if(Run(str_replace('%player%',$u['realname'],$C['rewards']['daily_cmd']),$i))$ok++;
 if($ok){$d[$u['username']]['l']=$t;$d[$u['username']]['c']=($d[$u['username']]['c']??0)+1;SD($f,$d);J(1,'✅ 成功',$d[$u['username']]);}J(0,'❌ 失败');
}
if($A=='k'&&$u=$_SESSION['u']){ // CDK
 $f='cdk_data.json';$d=GD($f);$k=trim($_POST['k']);$s=(int)$_POST['s'];$c=$d[$k]??0;
 if(!$c||$c['used']>=$c['max']||in_array($u['username'],$c['users']))J(0,'🚫 无效');
 if(isset($c['server_id'])&&$c['server_id']!=='all'&&(int)$c['server_id']!==$s)J(0,'⚠️ 服不匹配');
 if(Run(str_replace('%player%',$u['realname'],$c['cmd']),($c['server_id']==='all'?$s:(int)$c['server_id']))){
  $d[$k]['used']++;$d[$k]['users'][]=$u['username'];SD($f,$d);J(1,'🎁 成功');
 }J(0,'❌ 失败');
}

// Admin
if($A=='alogin'){ if($_POST['u']===$C['admin']['user']&&$_POST['p']===$C['admin']['pass']){$_SESSION['adm']=1;J(1,'OK');}J(0,'❌ 错'); }
if($A=='asave' && $_SESSION['adm']){
    $N=$C; foreach($_POST as $k=>$v)if(isset($N[$k]))$N[$k]=$v; 
    if($_POST['sv_json'])$N['servers']=json_decode($_POST['sv_json'],true);
    $N['rewards']['sign_in_servers']=explode(',',$_POST['sis']);
    $N['display']['ip']=$_POST['dip'];$N['display']['port']=$_POST['dpt'];
    SC($N);J(1,'✅ 保存');
}
if($A=='acdk' && $_SESSION['adm']){
    $d=GD('cdk_data.json');$c=trim($_POST['c']);
    if($_POST['act']=='del'){unset($d[$c]);}else{$d[$c]=['cmd'=>$_POST['cmd'],'max'=>(int)$_POST['use'],'server_id'=>$_POST['sid'],'used'=>0,'users'=>[]];}
    SD('cdk_data.json',$d);J(1,'OK');
}
if($A=='arcon' && $_SESSION['adm']){ J(1,Run($_POST['cmd'],(int)$_POST['sid'])?'指令发送':'失败'); }

// Commons
if($A=='p'){$n=rand(1111,9999);$_SESSION['c']=$n;$i=imagecreatetruecolor(60,34);imagefill($i,0,0,0x3b82f6);imagestring($i,5,12,9,$n,0xffffff);header('Content-type:image/png');imagepng($i);exit;}
if($A=='out'){session_destroy();header("Location:?");exit;}

// --- 渲染 ---
$BG=$C['site']['bg']?:'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920';
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $C['site']['title'] ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:url('<?= $BG ?>') center/cover fixed}.g{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:1rem;box-shadow:0 8px 32px rgba(0,0,0,0.2)}.i{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;margin-bottom:8px}.b{width:100%;padding:10px;border-radius:6px;font-weight:bold;color:white;background:#2563eb}.h{display:none}</style>
</head><body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

<?php if($A=='admin' || isset($_SESSION['adm'])): ?>
    <?php if(!isset($_SESSION['adm'])): ?>
        <div class="g w-full max-w-sm p-8 text-center"><h2 class="text-xl font-bold mb-4">🛡️ 管理员</h2><input id="au" placeholder="User" class="i"><input id="ap" type="password" placeholder="Pass" class="i"><button onclick="req('?a=alogin',{u:v('au'),p:v('ap')},(d)=>{if(d.s)location.reload();else alert(d.m)})" class="b bg-gray-800">登录</button><div class="mt-4"><a href="?" class="text-sm text-blue-600">返回前台</a></div></div>
    <?php else: ?>
        <div class="g w-full max-w-4xl p-6 h-[85vh] overflow-y-auto">
            <div class="flex justify-between border-b pb-4 mb-4"><h2 class="text-xl font-bold">🛠️ 控制台 <span class="text-xs text-gray-500">v<?= $C['site']['ver'] ?></span></h2><a href="?a=out" class="text-red-500 font-bold">退出</a></div>
            <div class="grid md:grid-cols-2 gap-6"><div><h3 class="font-bold text-blue-600 mb-2">设置</h3><input id="st" value="<?=$C['site']['title']?>" class="i"><input id="bg" value="<?=$C['site']['bg']?>" class="i"><input id="dip" value="<?=$C['display']['ip']?>" class="i"><input id="dpt" value="<?=$C['display']['port']?>" class="i"><textarea id="svj" class="i h-24 font-mono text-xs"><?=json_encode($C['servers'])?></textarea><button onclick="save()" class="b bg-green-600">保存</button></div><div><h3 class="font-bold text-green-600 mb-2">奖励 & RCON</h3><input id="rc" value="<?=$C['rewards']['reg_cmd']?>" class="i"><input id="dc" value="<?=$C['rewards']['daily_cmd']?>" class="i"><input id="sis" value="<?=implode(',',$C['rewards']['sign_in_servers'])?>" class="i"><div class="flex gap-2 mt-4"><select id="rsid" class="i w-32"><?php foreach($C['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><input id="rcmd" placeholder="CMD" class="i"></div><button onclick="req('?a=arcon',{cmd:v('rcmd'),sid:v('rsid')},(d)=>{alert(d.m)})" class="b bg-black">RCON</button></div></div>
            <div class="mt-6 pt-6 border-t"><h3 class="font-bold text-purple-600 mb-2">CDK</h3><div class="flex gap-2"><input id="ck" placeholder="Code" class="i w-32"><input id="cc" placeholder="Cmd" class="i flex-1"><input id="cu" value="1" class="i w-16"><select id="cs" class="i w-24"><option value="all">All</option><?php foreach($C['servers'] as $k=>$v)echo"<option value='$k'>{$v['name']}</option>"?></select><button onclick="mkcdk()" class="b w-24 bg-purple-600">Add</button></div><div class="text-xs text-gray-500 bg-gray-50 p-2 rounded"><?php foreach(GD('cdk_data.json') as $k=>$d) echo "<div class='flex justify-between border-b py-1'><span><b>$k</b> ({$d['used']}/{$d['max']})</span><a href='#' onclick=\"rmcdk('$k')\" class='text-red-500'>Del</a></div>"; ?></div></div>
        </div>
        <script>function save(){req('?a=asave',{site_title:v('st'),site_bg:v('bg'),sv_json:v('svj'),reg_cmd:v('rc'),daily_cmd:v('dc'),sis:v('sis'),dip:v('dip'),dpt:v('dpt')},(d)=>{alert(d.m)})}function mkcdk(){req('?a=acdk',{c:v('ck'),cmd:v('cc'),use:v('cu'),sid:v('cs'),act:'add'},(d)=>{location.reload()})}function rmcdk(k){if(confirm('Del?'))req('?a=acdk',{c:k,act:'del'},(d)=>{location.reload()})}</script>
    <?php endif; ?>

<?php elseif(isset($_SESSION['u'])): $U=$_SESSION['u']; $UD=GD('user_data.json')[$U['username']]??[]; ?>
    <div class="g w-full max-w-md p-8">
        <div class="flex items-center gap-4 mb-6 border-b pb-4"><img src="https://cravatar.eu/helmavatar/<?=$U['realname']?>/64.png" class="w-14 h-14 rounded"><div><div class="font-bold text-lg"><?=$U['realname']?></div><div class="text-xs text-gray-500">签到: <?=$UD['c']??0?> 天</div></div><a href="?a=out" class="ml-auto text-xs bg-red-100 text-red-600 px-3 py-1 rounded">退出</a></div>
        <button onclick="req('?a=s',{},(d)=>{alert(d.m);if(d.s)location.reload()})" class="b mb-6 bg-indigo-500 shadow-lg shadow-indigo-500/30"><?= ($UD['l']??0)==date('Ymd')?'✅ 今日已签':'📅 每日签到' ?></button>
        <div class="space-y-2"><div class="text-xs font-bold text-gray-400">CDK 兑换</div><select id="srv" class="i font-bold text-blue-900"><?php foreach($C['servers'] as $k=>$v) echo "<option value='$k'>🌍 {$v['name']}</option>"; ?></select><div class="flex gap-2"><input id="key" placeholder="Code" class="i"><button onclick="req('?a=k',{k:v('key'),s:v('srv')},(d)=>{alert(d.m);if(d.s)v('key','')})" class="b w-24 bg-green-600">兑换</button></div></div>
    </div>

<?php else: ?>
    <div class="g w-full max-w-sm p-8 text-center relative">
        <h1 class="text-2xl font-bold mb-6 text-blue-600"><?= $C['site']['title'] ?></h1>
        <?php if($C['display']['ip']): ?><div id="st" class="h bg-white/60 p-2 rounded mb-4 text-left text-xs flex gap-2"><div id="on" class="font-bold text-green-600">...</div><div id="mo" class="truncate flex-1 text-gray-500">...</div></div><script>fetch('https://api.mcsrvstat.us/2/<?=$C['display']['ip']?>:<?=$C['display']['port']?>').then(r=>r.json()).then(d=>{document.getElementById('st').style.display='flex';document.getElementById('on').innerText=d.online?d.players.online+' On':'Off';document.getElementById('mo').innerText=d.motd.clean.join(' ')})</script><?php endif; ?>
        
        <div id="b-log">
            <form onsubmit="return sub('?a=l',this)"><input name="u" placeholder="用户" class="i mb-3" required><input type="password" name="p" placeholder="密码" class="i mb-4" required><button class="b shadow-lg shadow-blue-500/30">登录</button></form>
            <div class="mt-4 flex justify-between text-xs text-gray-500"><a href="#" onclick="go('b-reg')" class="text-blue-600 font-bold">注册</a><a href="#" onclick="go('b-fp')" class="hover:text-gray-700">忘记密码?</a></div>
        </div>
        <div id="b-reg" class="h">
            <form onsubmit="return sub('?a=g',this)"><input name="u" placeholder="用户名" class="i" required><input name="e" placeholder="邮箱" class="i" required><input type="password" name="p" placeholder="密码" class="i" required><div class="flex gap-2"><input name="c" placeholder="验证码" class="i" required><img src="?a=p" onclick="this.src='?a=p&'+Math.random()" class="h-10 rounded border cursor-pointer"></div><button class="b mt-2 bg-green-600">注册</button></form>
            <p class="mt-4 text-xs"><a href="#" onclick="go('b-log')" class="text-blue-600 font-bold">返回登录</a></p>
        </div>
        <div id="b-fp" class="h">
            <h3 class="font-bold text-gray-700 mb-2">重置密码</h3>
            <div class="flex gap-2 mb-2"><input id="fu" placeholder="用户名" class="i"><input id="fe" placeholder="邮箱" class="i"></div>
            <button onclick="req('?a=fp_s',{u:v('fu'),e:v('fe')},(d)=>{alert(d.m)})" class="b bg-gray-500 text-xs py-2 mb-3">发送验证码</button>
            <input id="fc" placeholder="验证码" class="i"><input id="fp" type="password" placeholder="新密码" class="i"><button onclick="req('?a=fp_d',{u:v('fu'),c:v('fc'),p:v('fp')},(d)=>{alert(d.m);if(d.s)go('b-log')})" class="b mt-2 bg-orange-500">重置</button>
            <p class="mt-4 text-xs"><a href="#" onclick="go('b-log')" class="text-blue-600 font-bold">返回登录</a></p>
        </div>
    </div>
<?php endif; ?>

<script>
v=i=>document.getElementById(i).value;
go=t=>{['b-log','b-reg','b-fp'].forEach(x=>document.getElementById(x).classList.add('h'));document.getElementById(t).classList.remove('h')};
req=(u,d,f)=>{let m=new FormData();for(let k in d)m.append(k,d[k]);fetch(u,{method:'POST',body:m}).then(r=>r.json()).then(f)};
sub=(u,f)=>{req(u,Object.fromEntries(new FormData(f)),(d)=>{alert(d.m);if(d.s)location.reload()});return false};
</script>
</body></html>
