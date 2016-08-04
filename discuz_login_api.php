<?php
header('Content-type: text/html; charset=utf-8');
require './source/class/class_core.php';
include './config/config_ucenter.php';  
include './uc_client/client.php';  
$discuz = C::app();
$discuz->init();

if($_GET['action'] == 'login') { 
    //判断某个ip是否在给定的ip范围内
    $ip = $_SERVER["REMOTE_ADDR"];  
    $arrayip = array('192.168.2.*','192.168.200.*','192.168.1.*');//ip段  
    $ipregexp = implode('|', str_replace( array('*','.'), array('\d+','\.') ,$arrayip) );  
    $iply = preg_match("/^(".$ipregexp.")$/", $ip);
    //判断来源
    if($iply == 0){
        echo '谢绝来访！';
        exit();
    }
	$formUsername = $_GET['username']; 
    $formUsername = urldecode($formUsername);
    //$formPassword = $_GET['password']; 
    if ($formUsername == '') {
        echo '参数不能为空';
        exit();
    }
	$member = DB::fetch_first("SELECT * FROM pre_common_member WHERE username='$formUsername'");
    $uc_member = DB::fetch_first("SELECT * FROM pre_ucenter_members WHERE username='$formUsername'");

	//$Md5_formPassword = md5(md5($formPassword).$uc_member['salt']);

    // if($uc_member['password'] == $Md5_formPassword){
	   //  dsetcookie('auth', authcode("{$member['password']}\t{$member['uid']}", 'ENCODE'), '1234243');
	   //  header("location:./forum.php");
    // }
    // else
    if($uc_member['salt'] != "" ) {
    	dsetcookie('auth', authcode("{$member['password']}\t{$member['uid']}", 'ENCODE'), '1234243');
    	header("location:./forum.php");
    }else{
        $password = '123456';
        $uid = uc_user_register($_GET['username'], $password);
        // if($uid <= 0) {
        //     if($uid == -1) {
        //         echo '用户名不合法';
        //     } elseif($uid == -2) {
        //         echo '包含要允许注册的词语';
        //     } elseif($uid == -3) {
        //         echo '用户名已经存在';
        //     } elseif($uid == -4) {
        //         echo 'Email 格式有误';
        //     } elseif($uid == -5) {
        //         echo 'Email 不允许注册';
        //     } elseif($uid == -6) {
        //         echo '该 Email 已经被注册';
        //     } else {
        //         echo '未定义';
        //     }
        // } else {
            global $_G;  

            $cookietime = 31536000; 
            $username = $_GET['username']; 
            $query = DB::query("SELECT password, email FROM ".DB::table('ucenter_members')." WHERE uid='$uid'"); 
            $member = DB::fetch($query); 
            $password = $member['password'];  
            $email = $member['email']; 
            $ip = $_SERVER['REMOTE_ADDR']; 
            $time = time(); 
            $userdata = array( 
                'uid'=>$uid, 
                'username'=>$username, 
                'password'=>$password, 
                'email'=>$email, 
                'adminid'=>-1, 
                'groupid'=>31, 
                'regdate'=>$time, 
                'credits'=>0, 
                'timeoffset'=>9999 
            ); 
            DB::insert('common_member', $userdata); 

            $status_data = array( 
                'uid' => $uid, 
                'regip' => $ip, 
                'lastip' => $ip, 
                'lastvisit' => $time, 
                'lastactivity' => $time, 
                'lastpost' => 0, 
                'lastsendmail' => 0 
            ); 
            DB::insert('common_member_status', $status_data); 
            DB::insert('common_member_profile', array('uid' => $uid)); 
            DB::insert('common_member_field_forum', array('uid' => $uid)); 
            DB::insert('common_member_field_home', array('uid' => $uid)); 
            DB::insert('common_member_count', array('uid' => $uid)); 
            DB::query('UPDATE '.DB::table('common_setting')." SET svalue='$username' WHERE skey='lastmember'"); 
            $query = DB::query("SELECT uid, username, password FROM ".DB::table('common_member')." WHERE uid='$uid'"); 
            if ($member = DB::fetch($query)) 
            { 
                dsetcookie('auth', authcode("$member[password]\t$member[uid]", 'ENCODE'), $cookietime); 
                header("location:./forum.php");
            } 
        //}
    }
} 
?>