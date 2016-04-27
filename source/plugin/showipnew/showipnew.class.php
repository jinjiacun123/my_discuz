<?php

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class plugin_showipnew_forum{
	public function viewthread_sidebottom_output(){
		global $_G,$postlist;
		require_once libfile('function/misc');
		$usergroupid=unserialize($_G['cache']['plugin']['showipnew']['showipnewuser']);
		$showipnewdiv=$_G['cache']['plugin']['showipnew']['showipnewdiv'];
		$showipnewcolor=$_G['cache']['plugin']['showipnew']['showipnewcolor'];
		foreach($postlist as $k=>$v){
			$showipnew[]="<p class='cp_pls cl' style=color:".$showipnewcolor.">".lang('plugin/showipnew','come').convertip($v['useip'])."</p>";
		}

		if(in_array($_G['member']['groupid'],$usergroupid) || $_G['member']['groupid'] =='1' && $_G['adminid']>0){
			if($showipnewdiv==1){
				return $showipnew;
			}
		}
	}

	public function viewthread_postheader_output(){
		global $_G,$postlist;
		$usergroupid=unserialize($_G['cache']['plugin']['showipnew']['showipnewuser']);
		$showipnewdiv=$_G['cache']['plugin']['showipnew']['showipnewdiv'];
		$showipnewcolor=$_G['cache']['plugin']['showipnew']['showipnewcolor'];
		require_once libfile('function/misc');
		foreach($postlist as $k=>$v){
			$showipnew[]="<span style=color:".$showipnewcolor.">".lang('plugin/showipnew','come').convertip($v['useip'])."</span>";
		}
		if(in_array($_G['member']['groupid'],$usergroupid) || $_G['member']['groupid'] =='1' && $_G['adminid']>0){
			if($showipnewdiv==2){
				return $showipnew;
			}
		}
	}

}
