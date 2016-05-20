<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: admincp_members.php 35200 2015-02-04 03:50:59Z hypowang $
 */

if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

@set_time_limit(600);
if($operation != 'export') {
	cpheader();
}

require_once libfile('function/delete');

$_G['setting']['memberperpage'] = 20;
$page = max(1, $_G['page']);
$start_limit = ($page - 1) * $_G['setting']['memberperpage'];
$search_condition = array_merge($_GET, $_POST);

if(!is_array($search_condition['groupid']) && $search_condition['groupid']) {
	$search_condition['groupid'][0] = $search_condition['groupid'];
}
foreach($search_condition as $k => $v) {
	if(in_array($k, array('action', 'operation', 'formhash', 'confirmed', 'submit', 'page', 'deletestart', 'allnum', 'includeuc','includepost','current','pertask','lastprocess','deleteitem')) || $v === '') {
		unset($search_condition[$k]);
	}
}
$search_condition = searchcondition($search_condition);
$tmpsearch_condition = $search_condition;
unset($tmpsearch_condition['tablename']);
$member = array();
$tableext = '';
if(in_array($operation, array('ban', 'edit', 'group', 'credit', 'medal', 'access'), true)) {
	if(empty($_GET['uid']) && empty($_GET['username'])) {
		cpmsg('members_nonexistence', 'action=members&operation='.$operation.(!empty($_GET['highlight']) ? "&highlight={$_GET['highlight']}" : ''), 'form', array(), '<input type="text" name="username" value="" class="txt" />');
	}
	$member = !empty($_GET['uid']) ? C::t('common_member')->fetch($_GET['uid'], false, 1) : C::t('common_member')->fetch_by_username($_GET['username'], 1);
	if(!$member) {
		cpmsg('members_edit_nonexistence', '', 'error');
	}
	$tableext = isset($member['_inarchive']) ? '_archive' : '';
}

if($operation == 'search') {

	if(!submitcheck('submit', 1)) {

		shownav('user', 'nav_members');
		showsubmenu('nav_members', array(
			array('search', 'members&operation=search', 1),
			array('clean', 'members&operation=clean', 0),
			array('nav_repeat', 'members&operation=repeat', 0),
		));
		showtips('members_admin_tips');
		if(!empty($_GET['vid']) && ($_GET['vid'] > 0 && $_GET['vid'] < 8)) {
			$_GET['verify'] = array('verify'.intval($_GET['vid']));
		}
		showsearchform('search');
		if($_GET['more']) {
			print <<<EOF
		<script type="text/javascript">
			$('btn_more').click();
		</script>

EOF;
		}
	} else {

		$membernum = countmembers($search_condition, $urladd);

		$members = '';
		if($membernum > 0) {
			$multipage = multi($membernum, $_G['setting']['memberperpage'], $page, ADMINSCRIPT."?action=members&operation=search&submit=yes".$urladd);

			$usergroups = array();
			foreach(C::t('common_usergroup')->range() as $group) {
				switch($group['type']) {
					case 'system': $group['grouptitle'] = '<b>'.$group['grouptitle'].'</b>'; break;
					case 'special': $group['grouptitle'] = '<i>'.$group['grouptitle'].'</i>'; break;
				}
				$usergroups[$group['groupid']] = $group;
			}

			$uids = searchmembers($search_condition, $_G['setting']['memberperpage'], $start_limit);
			if($uids) {
				$allmember = C::t('common_member')->fetch_all($uids);
				$allcount = C::t('common_member_count')->fetch_all($uids);
				foreach($allmember as $uid=>$member) {
					$member = array_merge($member, (array)$allcount[$uid]);
					$memberextcredits = array();
					if($_G['setting']['extcredits']) {
						foreach($_G['setting']['extcredits'] as $id => $credit) {
							$memberextcredits[] = $_G['setting']['extcredits'][$id]['title'].': '.$member['extcredits'.$id].' ';
						}
					}
					$lockshow = $member['status'] == '-1' ? '<em class="lightnum">['.cplang('lock').']</em>' : '';
					$freezeshow = $member['freeze'] ? '<em class="lightnum">['.cplang('freeze').']</em>' : '';
					$members .= showtablerow('', array('class="td25"', '', 'title="'.implode("\n", $memberextcredits).'"'), array(
						"<input type=\"checkbox\" name=\"uidarray[]\" value=\"$member[uid]\"".($member['adminid'] == 1 ? 'disabled' : '')." class=\"checkbox\">",
						($_G['setting']['connect']['allow'] && $member['conisbind'] ? '<img class="vmiddle" src="static/image/common/connect_qq.gif" /> ' : '')."<a href=\"home.php?mod=space&uid=$member[uid]\" target=\"_blank\">$member[username]</a>",
						$member['credits'],
						$member['posts'],
						$usergroups[$member['adminid']]['grouptitle'],
						$usergroups[$member['groupid']]['grouptitle'].$lockshow.$freezeshow,
						"<a href=\"".ADMINSCRIPT."?action=members&operation=group&uid=$member[uid]\" class=\"act\">$lang[usergroup]</a><a href=\"".ADMINSCRIPT."?action=members&operation=access&uid=$member[uid]\" class=\"act\">$lang[members_access]</a>".
						($_G['setting']['extcredits'] ? "<a href=\"".ADMINSCRIPT."?action=members&operation=credit&uid=$member[uid]\" class=\"act\">$lang[credits]</a>" : "<span disabled>$lang[edit]</span>").
						"<a href=\"".ADMINSCRIPT."?action=members&operation=medal&uid=$member[uid]\" class=\"act\">$lang[medals]</a>".
						"<a href=\"".ADMINSCRIPT."?action=members&operation=repeat&uid=$member[uid]\" class=\"act\">$lang[members_repeat]</a>".
						"<a href=\"".ADMINSCRIPT."?action=members&operation=edit&uid=$member[uid]\" class=\"act\">$lang[detail]</a>".
						"<a href=\"".ADMINSCRIPT."?action=members&operation=ban&uid=$member[uid]\" class=\"act\">$lang[members_ban]</a>"
					), TRUE);
				}
			}
		}

		shownav('user', 'nav_members');
		showsubmenu('nav_members');
		showtips('members_export_tips');
		foreach($search_condition as $k => $v) {
			if($k == 'username') {
				$v = explode(',', $v);
				$tmpv = array();
				foreach($v as $subvalue) {
					$tmpv[] = rawurlencode($subvalue);
				}
				$v = implode(',', $tmpv);
			}
			if(is_array($v)) {
				foreach($v as $value ) {
					$condition_str .= '&'.$k.'[]='.$value;
				}
			} else {
				$condition_str .= '&'.$k.'='.$v;
			}
		}
		showformheader("members&operation=clean".$condition_str);
		showtableheader(cplang('members_search_result', array('membernum' => $membernum)).'<a href="'.ADMINSCRIPT.'?action=members&operation=search" class="act lightlink normal">'.cplang('research').'</a>&nbsp;&nbsp;&nbsp;<a href='.ADMINSCRIPT.'?action=members&operation=export'.$condition_str.'>'.$lang['members_search_export'].'</a>');

		if($membernum) {
			showsubtitle(array('', 'username', 'credits', 'posts', 'admingroup', 'usergroup', ''));
			echo $members;
			$condition_str = str_replace('&tablename=master', '', $condition_str);
			showsubmit('deletesubmit', cplang('delete'), ($tmpsearch_condition ? '<input type="checkbox" name="chkall" onclick="checkAll(\'prefix\', this.form, \'uidarray\');if(this.checked){$(\'deleteallinput\').style.display=\'\';}else{$(\'deleteall\').checked = false;$(\'deleteallinput\').style.display=\'none\';}" class="checkbox">'.cplang('select_all') : ''), ' &nbsp;&nbsp;&nbsp;<span id="deleteallinput" style="display:none"><input id="deleteall" type="checkbox" name="deleteall" class="checkbox">'.cplang('members_search_deleteall', array('membernum' => $membernum)).'</span>', $multipage);
		}
		showtablefooter();
		showformfooter();

	}

} elseif($operation == 'export') {
	$uids = searchmembers($search_condition, 10000);
	$detail = '';
	if($uids && is_array($uids)) {

		$allprofile = C::t('common_member_profile')->fetch_all($uids);
		$allusername = C::t('common_member')->fetch_all_username_by_uid($uids);
		foreach($allprofile as $uid=>$profile) {
			unset($profile['uid']);
			$profile = array_merge(array('uid'=>$uid, 'username'=>$allusername[$uid]),$profile);
			foreach($profile as $key => $value) {
				$value = preg_replace('/\s+/', ' ', $value);
				if($key == 'gender') $value = lang('space', 'gender_'.$value);
				$detail .= strlen($value) > 11 && is_numeric($value) ? '['.$value.'],' : $value.',';
			}
			$detail = $detail."\n";
		}
	}
	$title = array('realname' => '', 'gender' => '', 'birthyear' => '', 'birthmonth' => '', 'birthday' => '', 'constellation' => '',
		'zodiac' => '', 'telephone' => '', 'mobile' => '', 'idcardtype' => '', 'idcard' => '', 'address' => '', 'zipcode' => '','nationality' => '',
		'birthprovince' => '', 'birthcity' => '', 'birthdist' => '', 'birthcommunity' => '', 'resideprovince' => '', 'residecity' => '', 'residedist' => '',
		'residecommunity' => '', 'residesuite' => '', 'graduateschool' => '', 'education' => '', 'company' => '', 'occupation' => '',
		'position' => '', 'revenue' => '', 'affectivestatus' => '', 'lookingfor' => '', 'bloodtype' => '', 'height' => '', 'weight' => '',
		'alipay' => '', 'icq' => '', 'qq' => '', 'yahoo' => '', 'msn' => '', 'taobao' => '', 'site' => '', 'bio' => '', 'interest' => '',
		'field1' => '', 'field2' => '', 'field3' => '', 'field4' => '', 'field5' => '', 'field6' => '', 'field7' => '', 'field8' => '');
	foreach(C::t('common_member_profile_setting')->range() as $value) {
		if(isset($title[$value['fieldid']])) {
			$title[$value['fieldid']] = $value['title'];
		}
	}
	foreach($title as $k => $v) {
		$subject .= ($v ? $v : $k).",";
	}
	$detail = "UID,".$lang['username'].",".$subject."\n".$detail;
	$filename = date('Ymd', TIMESTAMP).'.csv';

	ob_end_clean();
	header('Content-Encoding: none');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.$filename);
	header('Pragma: no-cache');
	header('Expires: 0');
	if($_G['charset'] != 'gbk') {
		$detail = diconv($detail, $_G['charset'], 'GBK');
	}
	echo $detail;
	exit();

} elseif($operation == 'repeat') {

	if(empty($_GET['uid']) && empty($_GET['username']) && empty($_GET['ip'])) {

		shownav('user', 'nav_members');
		showsubmenu('nav_members', array(
			array('search', 'members&operation=search', 0),
			array('clean', 'members&operation=clean', 0),
			array('nav_repeat', 'members&operation=repeat', 1),
		));

		showformheader("members&operation=repeat");
		showtableheader();
		showsetting('members_search_repeatuser', 'username', '', 'text');
		showsetting('members_search_uid', 'uid', '', 'text');
		showsetting('members_search_repeatip', 'ip', $_GET['inputip'], 'text');
		showsubmit('submit', 'submit');
		showtablefooter();
		showformfooter();

	} else {

		$ips = array();
		$urladd = '';
		if(!empty($_GET['username'])) {
			$uid = C::t('common_member')->fetch_uid_by_username($_GET['username']);
			$searchmember = $uid ? C::t('common_member_status')->fetch($uid) : '';
			$searchmember['username'] = $_GET['username'];
			$urladd .= '&username='.$_GET['username'];
		} elseif(!empty($_GET['uid'])) {
			$searchmember = C::t('common_member_status')->fetch($_GET['uid']);
			$themember = C::t('common_member')->fetch($_GET['uid']);
			$searchmember['username'] = $themember['username'];
			$urladd .= '&uid='.$_GET['uid'];
			unset($_GET['uid']);
		} elseif(!empty($_GET['ip'])) {
			$regip = $lastip = $_GET['ip'];
			$ips[] = $_GET['ip'];
			$search_condition['lastip'] = $_GET['ip'];
			$urladd .= '&ip='.$_GET['ip'];
		}

		if($searchmember) {
			$ips = array();
			foreach(array('regip', 'lastip') as $iptype) {
				if($searchmember[$iptype] != '' && $searchmember[$iptype] != 'hidden') {
					$ips[] = $searchmember[$iptype];
				}
			}
			$ips = !empty($ips) ? array_unique($ips) : array('unknown');
		}
		$searchmember['username'] .= ' (IP '.dhtmlspecialchars($ids).')';
		$membernum = !empty($ips) ? C::t('common_member_status')->count_by_ip($ips) : C::t('common_member_status')->count();

		$members = '';
		if($membernum) {
			$usergroups = array();
			foreach(C::t('common_usergroup')->range() as $group) {
				switch($group['type']) {
					case 'system': $group['grouptitle'] = '<b>'.$group['grouptitle'].'</b>'; break;
					case 'special': $group['grouptitle'] = '<i>'.$group['grouptitle'].'</i>'; break;
				}
				$usergroups[$group['groupid']] = $group;
			}

			$uids = searchmembers($search_condition, $_G['setting']['memberperpage'], $start_limit);
			$conditions = 'm.uid IN ('.dimplode($uids).')';
			$_G['setting']['memberperpage'] = 100;
			$start_limit = ($page - 1) * $_G['setting']['memberperpage'];
			$multipage = multi($membernum, $_G['setting']['memberperpage'], $page, ADMINSCRIPT."?action=members&operation=repeat&submit=yes".$urladd);
			$allstatus = !empty($ips) ? C::t('common_member_status')->fetch_all_by_ip($ips, $start_limit, $_G['setting']['memberperpage'])
					: C::t('common_member_status')->range($start_limit, $_G['setting']['memberperpage']);
			$allcount = C::t('common_member_count')->fetch_all(array_keys($allstatus));
			$allmember = C::t('common_member')->fetch_all(array_keys($allstatus));
			foreach($allstatus as $uid => $member) {
				$member = array_merge($member, (array)$allcount[$uid], (array)$allmember[$uid]);
				$memberextcredits = array();
				foreach($_G['setting']['extcredits'] as $id => $credit) {
					$memberextcredits[] = $_G['setting']['extcredits'][$id]['title'].': '.$member['extcredits'.$id];
				}
				$members .= showtablerow('', array('class="td25"', '', 'title="'.implode("\n", $memberextcredits).'"'), array(
					"<input type=\"checkbox\" name=\"uidarray[]\" value=\"$member[uid]\"".($member['adminid'] == 1 ? 'disabled' : '')." class=\"checkbox\">",
					"<a href=\"home.php?mod=space&uid=$member[uid]\" target=\"_blank\">$member[username]</a>",
					$member['credits'],
					$member['posts'],
					$usergroups[$member['adminid']]['grouptitle'],
					$usergroups[$member['groupid']]['grouptitle'],
					"<a href=\"".ADMINSCRIPT."?action=members&operation=group&uid=$member[uid]\" class=\"act\">$lang[usergroup]</a><a href=\"".ADMINSCRIPT."?action=members&operation=access&uid=$member[uid]\" class=\"act\">$lang[members_access]</a>".
					($_G['setting']['extcredits'] ? "<a href=\"".ADMINSCRIPT."?action=members&operation=credit&uid=$member[uid]\" class=\"act\">$lang[credits]</a>" : "<span disabled>$lang[edit]</span>").
					"<a href=\"".ADMINSCRIPT."?action=members&operation=medal&uid=$member[uid]\" class=\"act\">$lang[medals]</a>".
					"<a href=\"".ADMINSCRIPT."?action=members&operation=repeat&uid=$member[uid]\" class=\"act\">$lang[members_repeat]</a>".
					"<a href=\"".ADMINSCRIPT."?action=members&operation=edit&uid=$member[uid]\" class=\"act\">$lang[detail]</a>"
				), TRUE);
			}
		}

		shownav('user', 'nav_repeat');
		showsubmenu($lang['nav_repeat'].' - '.$searchmember['username']);
		showformheader("members&operation=clean");
		$searchadd = '';
		if(is_array($ips)) {
			foreach($ips as $ip) {
				$searchadd .= '<a href="'.ADMINSCRIPT.'?action=members&operation=repeat&inputip='.rawurlencode($ip).'" class="act lightlink normal">'.cplang('search').'IP '.dhtmlspecialchars($ip).'</a>';
			}
		}
		showtableheader(cplang('members_search_result', array('membernum' => $membernum)).'<a href="'.ADMINSCRIPT.'?action=members&operation=repeat" class="act lightlink normal">'.cplang('research').'</a>'.$searchadd);
		showsubtitle(array('', 'username', 'credits', 'posts', 'admingroup', 'usergroup', ''));
		echo $members;
		showtablerow('', array('class="td25"', 'class="lineheight" colspan="7"'), array('', cplang('members_admin_comment')));
		showsubmit('submit', 'submit', '<input type="checkbox" name="chkall" onclick="checkAll(\'prefix\', this.form, \'uidarray\')" class="checkbox">'.cplang('del'), '', $multipage);
		showtablefooter();
		showformfooter();

	}

} elseif($operation == 'clean') {

	if(!submitcheck('submit', 1) && !submitcheck('deletesubmit', 1)) {

		shownav('user', 'nav_members');
		showsubmenu('nav_members', array(
			array('search', 'members&operation=search', 0),
			array('clean', 'members&operation=clean', 1),
			array('nav_repeat', 'members&operation=repeat', 0),
		));

		showsearchform('clean');

	} else {

		if((!$tmpsearch_condition && empty($_GET['uidarray'])) || (submitcheck('deletesubmit', 1) && empty($_GET['uidarray']))) {
			cpmsg('members_no_find_deluser', '', 'error');
		}
		if(!empty($_GET['deleteall'])) {
			unset($search_condition['uidarray']);
			$_GET['uidarray'] = '';
		}
		$uids = 0;
		$extra = '';
		$delmemberlimit = 300;
		$deletestart = intval($_GET['deletestart']);

		if(!empty($_GET['uidarray'])) {
			$uids = array();
			$allmember = C::t('common_member')->fetch_all($_GET['uidarray']);
			$count = count($allmember);
			$membernum = 0;
			foreach($allmember as $uid => $member) {
				if($member['adminid'] !== 1 && $member['groupid'] !== 1) {
					if($count < 2000 || !empty($_GET['uidarray'])) {
						$extra .= '<input type="hidden" name="uidarray[]" value="'.$member['uid'].'" />';
					}
					$uids[] = $member['uid'];
					$membernum ++;
				}
			}
		} elseif($tmpsearch_condition) {
			$membernum = countmembers($search_condition, $urladd);
			$uids = searchmembers($search_condition, $delmemberlimit, 0);
		}
		$allnum = intval($_GET['allnum']);
		$conditions = $uids ? 'm.uid IN ('.dimplode($uids).')' : '0';

		if((empty($membernum) || empty($uids))) {
			if($deletestart) {
				cpmsg('members_delete_succeed', '', 'succeed', array('numdeleted' => $allnum));
			}
			cpmsg('members_no_find_deluser', '', 'error');
		}
		if(!submitcheck('confirmed')) {

			cpmsg('members_delete_confirm', "action=members&operation=clean&submit=yes&confirmed=yes".$urladd, 'form', array('membernum' => $membernum), $extra.'<br /><label><input type="checkbox" name="includepost" value="1" class="checkbox" />'.$lang['members_delete_all'].'</label>'.($isfounder ? '&nbsp;<label><input type="checkbox" name="includeuc" value="1" class="checkbox" />'.$lang['members_delete_ucdata'].'</label>' : ''), '');

		} else {

			if(!submitcheck('includepost')) {

				require_once libfile('function/delete');
				$numdeleted = deletemember($uids, 0);

				if($isfounder && !empty($_GET['includeuc'])) {
					loaducenter();
					uc_user_delete($uids);
					$_GET['includeuc'] = 1;
				} else {
					$_GET['includeuc'] = 0;
				}
				if($_GET['uidarray']) {
					cpmsg('members_delete_succeed', '', 'succeed', array('numdeleted' => $numdeleted));
				} else {
					$allnum += $membernum < $delmemberlimit ? $membernum : $delmemberlimit;
					$nextlink = "action=members&operation=clean&confirmed=yes&submit=yes".(!empty($_GET['includeuc']) ? '&includeuc=yes' : '')."&allnum=$allnum&deletestart=".($deletestart+$delmemberlimit).$urladd;
					cpmsg(cplang('members_delete_user_processing_next', array('deletestart' => $deletestart, 'nextdeletestart' => $deletestart+$delmemberlimit)), $nextlink, 'loadingform', array());
				}

			} else {

				if(empty($uids)) {
					cpmsg('members_no_find_deluser', '', 'error');
				}
				$numdeleted = $numdeleted ? $numdeleted : count($uids);
				$pertask = 1000;
				$current = $_GET['current'] ? intval($_GET['current']) : 0;
				$deleteitem = $_GET['deleteitem'] ? trim($_GET['deleteitem']) : 'post';
				$nextdeleteitem = $deleteitem;

				$next = $current + $pertask;

				if($deleteitem == 'post') {
					$threads = $fids = $threadsarray = array();
					foreach(C::t('forum_thread')->fetch_all_by_authorid($uids, $pertask) as $thread) {
						$threads[$thread['fid']][] = $thread['tid'];
					}

					if($threads) {
						require_once libfile('function/post');
						foreach($threads as $fid => $tids) {
							deletethread($tids);
						}
						if($_G['setting']['globalstick']) {
							require_once libfile('function/cache');
							updatecache('globalstick');
						}
					} else {
						$next = 0;
						$nextdeleteitem = 'blog';
					}
				}

				if($deleteitem == 'blog') {
					$blogs = array();
					$query = C::t('home_blog')->fetch_blogid_by_uid($uids, 0, $pertask);
					foreach($query as $blog) {
						$blogs[] = $blog['blogid'];
					}

					if($blogs) {
						deleteblogs($blogs);
					} else {
						$next = 0;
						$nextdeleteitem = 'pic';
					}
				}

				if($deleteitem == 'pic') {
					$pics = array();
					$query = C::t('home_pic')->fetch_all_by_uid($uids, 0, $pertask);
					foreach($query as $pic) {
						$pics[] = $pic['picid'];
					}

					if($pics) {
						deletepics($pics);
					} else {
						$next = 0;
						$nextdeleteitem = 'doing';
					}
				}

				if($deleteitem == 'doing') {
					$doings = array();
					$query = C::t('home_doing')->fetch_all_by_uid_doid($uids, '', '', 0, $pertask);
					foreach ($query as $doings) {
						$doings[] = $doing['doid'];
					}

					if($doings) {
						deletedoings($doings);
					} else {
						$next = 0;
						$nextdeleteitem = 'share';
					}
				}

				if($deleteitem == 'share') {
					$shares = array();
					foreach(C::t('home_share')->fetch_all_by_uid($uids, $pertask) as $share) {
						$shares[] = $share['sid'];
					}

					if($shares) {
						deleteshares($shares);
					} else {
						$next = 0;
						$nextdeleteitem = 'feed';
					}
				}

				if($deleteitem == 'feed') {
					C::t('home_follow_feed')->delete_by_uid($uids);
					$nextdeleteitem = 'comment';
				}

				if($deleteitem == 'comment') {
					$comments = array();
					$query = C::t('home_comment')->fetch_all_by_uid($uids, 0, $pertask);
					foreach($query as $comment) {
						$comments[] = $comment['cid'];
					}

					if($comments) {
						deletecomments($comments);
					} else {
						$next = 0;
						$nextdeleteitem = 'allitem';
					}
				}

				if($deleteitem == 'allitem') {
					require_once libfile('function/delete');
					$numdeleted = deletemember($uids);

					if($isfounder && !empty($_GET['includeuc'])) {
						loaducenter();
						uc_user_delete($uids);
					}
					if(!empty($_GET['uidarray'])) {
						cpmsg('members_delete_succeed', '', 'succeed', array('numdeleted' => $numdeleted));
					} else {
						$allnum += $membernum < $delmemberlimit ? $membernum : $delmemberlimit;
						$nextlink = "action=members&operation=clean&confirmed=yes&submit=yes&includepost=yes".(!empty($_GET['includeuc']) ? '&includeuc=yes' : '')."&allnum=$allnum&deletestart=".($deletestart+$delmemberlimit).$urladd;
						cpmsg(cplang('members_delete_user_processing_next', array('deletestart' => $deletestart, 'nextdeletestart' => $deletestart+$delmemberlimit)), $nextlink, 'loadingform', array());
					}
				}
				$nextlink = "action=members&operation=clean&confirmed=yes&submit=yes&includepost=yes".(!empty($_GET['includeuc']) ? '&includeuc=yes' : '')."&current=$next&pertask=$pertask&lastprocess=$processed&allnum=$allnum&deletestart=$deletestart".$urladd;
				if(empty($_GET['uidarray'])) {
					$deladdmsg = cplang('members_delete_user_processing', array('deletestart' => $deletestart, 'nextdeletestart' => $deletestart+$delmemberlimit)).'<br>';
				} else {
					$deladdmsg = '';
				}
				if($nextdeleteitem != $deleteitem) {
					$nextlink .= "&deleteitem=$nextdeleteitem";
					cpmsg(cplang('members_delete_processing_next', array('deladdmsg' => $deladdmsg, 'item' => cplang('members_delete_'.$deleteitem), 'nextitem' => cplang('members_delete_'.$nextdeleteitem))), $nextlink, 'loadingform', array(), $extra);
				} else {
					$nextlink .= "&deleteitem=$deleteitem";
					cpmsg(cplang('members_delete_processing', array('deladdmsg' => $deladdmsg, 'item' => cplang('members_delete_'.$deleteitem), 'current' => $current, 'next' => $next)), $nextlink, 'loadingform', array(), $extra);
				}
			}
		}
	}

} elseif($operation == 'newsletter') {

	if(!submitcheck('newslettersubmit')) {
		loadcache('newsletter_detail');
		$newletter_detail = get_newsletter('newsletter_detail');
		$newletter_detail = dunserialize($newletter_detail);
		if($newletter_detail && $newletter_detail['uid'] == $_G['uid']) {
			if($_GET['goon'] == 'yes') {
				cpmsg("$lang[members_newsletter_send]: ".cplang('members_newsletter_processing', array('current' => $newletter_detail['current'], 'next' => $newletter_detail['next'], 'search_condition' => $newletter_detail['search_condition'])), $newletter_detail['action'], 'loadingform');
			} elseif($_GET['goon'] == 'no') {
				del_newsletter('newsletter_detail');
			} else {
				cpmsg('members_edit_continue', '', '', '', '<input type="button" class="btn" value="'.$lang[ok].'" onclick="location.href=\''.ADMINSCRIPT.'?action=members&operation=newsletter&goon=yes\'">&nbsp;&nbsp;<input type="button" class="btn" value="'.$lang[cancel].'" onclick="location.href=\''.ADMINSCRIPT.'?action=members&operation=newsletter&goon=no\';">');
				exit;
			}
		}
		if($_GET['do'] == 'mobile') {
			shownav('user', 'nav_members_newsletter_mobile');
			showsubmenusteps('nav_members_newsletter_mobile', array(
				array('nav_members_select', !$_GET['submit']),
				array('nav_members_notify', $_GET['submit']),
			));
			showtips('members_newsletter_mobile_tips');
		} else {
			shownav('user', 'nav_members_newsletter');
			showsubmenusteps('nav_members_newsletter', array(
				array('nav_members_select', !$_GET['submit']),
				array('nav_members_notify', $_GET['submit']),
			), array(), array(array('members_grouppmlist', 'members&operation=grouppmlist', 0)));
		}
		showsearchform('newsletter');

		if(submitcheck('submit')) {
			$dostr = '';
			if($_GET['do'] == 'mobile') {
				$search_condition['token_noempty'] = 'token';
				$dostr = '&do=mobile';
			}
			$membernum = countmembers($search_condition, $urladd);

			showtagheader('div', 'newsletter', TRUE);
			showformheader('members&operation=newsletter'.$urladd.$dostr);
			showhiddenfields(array('notifymember' => 1));
			echo '<table class="tb tb1">';

			if(!$membernum) {
				showtablerow('', 'class="lineheight"', $lang['members_search_nonexistence']);
			} else {
				showtablerow('class="first"', array('class="th11"'), array(
					cplang('members_newsletter_members'),
					cplang('members_search_result', array('membernum' => $membernum))."<a href=\"###\" onclick=\"$('searchmembers').style.display='';$('newsletter').style.display='none';$('step1').className='current';$('step2').className='';\" class=\"act\">$lang[research]</a>"
				));
				showtablefooter();

				shownewsletter();

				$search_condition = serialize($search_condition);
				showsubmit('newslettersubmit', 'submit', 'td', '<input type="hidden" name="conditions" value=\''.$search_condition.'\' />');

			}

			showtablefooter();
			showformfooter();
			showtagfooter('div');

		}

	} else {

		$search_condition = dunserialize($_POST['conditions']);
		$membernum = countmembers($search_condition, $urladd);
		notifymembers('newsletter', 'newsletter');

	}

} elseif($operation == 'grouppmlist') {

	if(!empty($_GET['delete']) && ($isfounder || C::t('common_grouppm')->count_by_id_authorid($_GET['delete'], $_G['uid']))) {
		if(!empty($_GET['confirm'])) {
			C::t('common_grouppm')->delete($_GET['delete']);
			C::t('common_member_grouppm')->delete_by_gpmid($_GET['delete']);
		} else {
			cpmsg('members_grouppm_delete_confirm', 'action=members&operation=grouppmlist&delete='.intval($_GET['delete']).'&confirm=yes', 'form');
		}
	}
	shownav('user', 'nav_members_newsletter');
	showsubmenu('nav_members_newsletter', array(
		array('members_grouppmlist_newsletter', 'members&operation=newsletter', 0),
		array('members_grouppmlist', 'members&operation=grouppmlist', 1)
	));
	if($do) {
		$unreads = C::t('common_member_grouppm')->count_by_gpmid($do, 0);
	}

	showtableheader();
	$id = empty($do) ? 0 : $do;
	$authorid = $isfounder ? 0 : $_G['uid'];
	$grouppms = C::t('common_grouppm')->fetch_all_by_id_authorid($id, $authorid);
	if(!empty($grouppms)) {
		$users = C::t('common_member')->fetch_all(C::t('common_grouppm')->get_uids());
		foreach($grouppms as $grouppm) {
			showtablerow('', array('valign="top" class="td25"', 'valign="top"'), array(
			    '<a href="home.php?mod=space&uid='.$grouppm['authorid'].'" target="_blank">'.avatar($grouppm['authorid'], 'small').'</a>',
			    '<a href="home.php?mod=space&uid='.$grouppm['authorid'].'" target="_blank"><b>'.$users[$grouppm['authorid']]['username'].'</b></a> ('.dgmdate($grouppm['dateline']).'):<br />'.
			    $grouppm['message'].'<br /><br />'.
			    (!$do ?
				'<a href="'.ADMINSCRIPT.'?action=members&operation=grouppmlist&do='.$grouppm['id'].'">'.cplang('members_grouppmlist_view', array('number' => $grouppm['numbers'])).'</a>' :
				'<a href="'.ADMINSCRIPT.'?action=members&operation=grouppmlist&do='.$grouppm['id'].'">'.cplang('members_grouppmlist_view_all').'</a>('.$grouppm['numbers'].') &nbsp; '.
				'<a href="'.ADMINSCRIPT.'?action=members&operation=grouppmlist&do='.$grouppm['id'].'&filter=unread">'.cplang('members_grouppmlist_view_unread').'</a>('.$unreads.') &nbsp; '.
				'<a href="'.ADMINSCRIPT.'?action=members&operation=grouppmlist&do='.$grouppm['id'].'&filter=read">'.cplang('members_grouppmlist_view_read').'</a>('.($grouppm['numbers'] - $unreads).')'),
				'<a href="'.ADMINSCRIPT.'?action=members&operation=grouppmlist&delete='.$grouppm['id'].'">'.cplang('delete').'</a>'
			));
		}
	} else {
		showtablerow('', '', cplang('members_newsletter_empty'));
	}
	showtablefooter();
	if($do) {
		$_GET['filter'] = in_array($_GET['filter'], array('read', 'unread')) ? $_GET['filter'] : '';
		$filteradd = $_GET['filter'] ? '&filter='.$_GET['filter'] : '';
		$ppp = 100;
		$start_limit = ($page - 1) * $ppp;
		if($_GET['filter'] != 'unread') {
			$count = C::t('common_member_grouppm')->count_by_gpmid($do, 1);
		} else {
			$count = $unreads;
		}
		$multipage = multi($count, $ppp, $page, ADMINSCRIPT."?action=members&operation=grouppmlist&do=$do".$filteradd);
		$alldata = C::t('common_member_grouppm')->fetch_all_by_gpmid($gpmid, $_GET['filter'] == 'read' ? 1 : 0, $start_limit, $ppp);
		$allmember = $gpmuser ? C::t('common_member')->fetch_all_username_by_uid(array_keys($gpmuser)) : array();
		foreach($alldata as $uid => $gpmuser) {
			echo '<div style="margin-bottom:5px;float:left;width:24%"><b><a href="home.php?mod=space&uid='.$uid.'" target="_blank">'.$allmember[$uid].'</a></b><br />&nbsp;';
			if($gpmuser['status'] == 0) {
				echo '<span class="lightfont">'.cplang('members_grouppmlist_status_0').'</span>';
			} else {
				echo dgmdate($gpmuser['dateline'], 'u').' '.cplang('members_grouppmlist_status_1');
				if($gpmuser['status'] == -1) {
					echo ', <span class="error">'.cplang('members_grouppmlist_status_-1').'</span>';
				}
			}
			echo '</div>';
		}
		echo $multipage;
	}

} elseif($operation == 'reward') {

	if(!submitcheck('rewardsubmit')) {

		shownav('user', 'nav_members_reward');
		showsubmenusteps('nav_members_reward', array(
			array('nav_members_select', !$_GET['submit']),
			array('nav_members_reward', $_GET['submit']),
		));

		showsearchform('reward');

		if(submitcheck('submit', 1)) {

			$membernum = countmembers($search_condition, $urladd);
			showtagheader('div', 'reward', TRUE);
			showformheader('members&operation=reward'.$urladd);
			echo '<table class="tb tb1">';

			if(!$membernum) {
				showtablerow('', 'class="lineheight"', $lang['members_search_nonexistence']);
				showtablefooter();
			} else {

				$creditscols = array('credits_title');
				$creditsvalue = $resetcredits = array();
				$js_extcreditids = '';
				for($i=1; $i<=8; $i++) {
					$js_extcreditids .= (isset($_G['setting']['extcredits'][$i]) ? ($js_extcreditids ? ',' : '').$i : '');
					$creditscols[] = isset($_G['setting']['extcredits'][$i]) ? $_G['setting']['extcredits'][$i]['title'] : 'extcredits'.$i;
					$creditsvalue[] = isset($_G['setting']['extcredits'][$i]) ? '<input type="text" class="txt" size="3" id="addextcredits['.$i.']" name="addextcredits['.$i.']" value="0"> '.$_G['setting']['extcredits']['$i']['unit'] : '<input type="text" class="txt" size="3" value="N/A" disabled>';
					$resetcredits[] = isset($_G['setting']['extcredits'][$i]) ? '<input type="checkbox" id="resetextcredits['.$i.']" name="resetextcredits['.$i.']" value="1" class="radio" disabled> '.$_G['setting']['extcredits']['$i']['unit'] : '<input type="checkbox" disabled  class="radio">';
				}
				$creditsvalue = array_merge(array('<input type="radio" name="updatecredittype" id="updatecredittype0" value="0" class="radio" onclick="var extcredits = new Array('.$js_extcreditids.'); for(k in extcredits) {$(\'resetextcredits[\'+extcredits[k]+\']\').disabled = true; $(\'addextcredits[\'+extcredits[k]+\']\').disabled = false;}" checked="checked" /><label for="updatecredittype0">'.$lang['members_reward_value'].'</label>'), $creditsvalue);
				$resetcredits = array_merge(array('<input type="radio" name="updatecredittype" id="updatecredittype1" value="1" class="radio" onclick="var extcredits = new Array('.$js_extcreditids.'); for(k in extcredits) {$(\'addextcredits[\'+extcredits[k]+\']\').disabled = true; $(\'resetextcredits[\'+extcredits[k]+\']\').disabled = false;}" /><label for="updatecredittype1">'.$lang['members_reward_clean'].'</label>'), $resetcredits);

				showtablerow('class="first"', array('class="th11"'), array(
					cplang('members_reward_members'),
					cplang('members_search_result', array('membernum' => $membernum))."<a href=\"###\" onclick=\"$('searchmembers').style.display='';$('reward').style.display='none';$('step1').className='current';$('step2').className='';\" class=\"act\">$lang[research]</a>"
				));

				echo '<tr><td class="th12">'.cplang('nav_members_reward').'</td><td>';
				showtableheader('', 'noborder');
				showsubtitle($creditscols);
				showtablerow('', array('class="td23"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"'), $creditsvalue);
				showtablerow('', array('class="td23"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"'), $resetcredits);
				showtablefooter();
				showtablefooter();

				showtagheader('div', 'messagebody');
				shownewsletter();
				showtagfooter('div');
				showsubmit('rewardsubmit', 'submit', 'td', '<input class="checkbox" type="checkbox" name="notifymember" value="1" onclick="$(\'messagebody\').style.display = this.checked ? \'\' : \'none\'" id="credits_notify" /><label for="credits_notify">'.cplang('members_reward_notify').'</label>');

			}

			showtablefooter();
			showformfooter();
			showtagfooter('div');

		}

	} else {
		if(!empty($_POST['conditions'])) $search_condition = dunserialize($_POST['conditions']);
		$membernum = countmembers($search_condition, $urladd);
		notifymembers('reward', 'creditsnotify');

	}

} elseif($operation == 'confermedal') {

	$medals = '';
	foreach(C::t('forum_medal')->fetch_all_data(1) as $medal) {
		$medals .= showtablerow('', array('class="td25"', 'class="td23"'), array(
			"<input class=\"checkbox\" type=\"checkbox\" name=\"medals[$medal[medalid]]\" value=\"1\" />",
			"<img src=\"static/image/common/$medal[image]\" />",
			$medal['name']
		), TRUE);
	}

	if(!$medals) {
		cpmsg('members_edit_medals_nonexistence', 'action=medals', 'error');
	}

	if(!submitcheck('confermedalsubmit')) {

		shownav('extended', 'nav_medals', 'nav_members_confermedal');
		showsubmenusteps('nav_members_confermedal', array(
			array('nav_members_select', !$_GET['submit']),
			array('nav_members_confermedal', $_GET['submit']),
		), array(
			array('admin', 'medals', 0),
			array('nav_medals_confer', 'members&operation=confermedal', 1),
			array('nav_medals_mod', 'medals&operation=mod', 0)
		));

		showsearchform('confermedal');

		if(submitcheck('submit', 1)) {

			$membernum = countmembers($search_condition, $urladd);

			showtagheader('div', 'confermedal', TRUE);
			showformheader('members&operation=confermedal'.$urladd);
			echo '<table class="tb tb1">';

			if(!$membernum) {
				showtablerow('', 'class="lineheight"', $lang['members_search_nonexistence']);
				showtablefooter();
			} else {

				showtablerow('class="first"', array('class="th11"'), array(
					cplang('members_confermedal_members'),
					cplang('members_search_result', array('membernum' => $membernum))."<a href=\"###\" onclick=\"$('searchmembers').style.display='';$('confermedal').style.display='none';$('step1').className='current';$('step2').className='';\" class=\"act\">$lang[research]</a>"
				));

				echo '<tr><td class="th12">'.cplang('members_confermedal').'</td><td>';
				showtableheader('', 'noborder');
				showsubtitle(array('medals_grant', 'medals_image', 'name'));
				echo $medals;
				showtablefooter();
				showtablefooter();

				showtagheader('div', 'messagebody');
				shownewsletter();
				showtagfooter('div');
				showsubmit('confermedalsubmit', 'submit', 'td', '<input class="checkbox" type="checkbox" name="notifymember" value="1" onclick="$(\'messagebody\').style.display = this.checked ? \'\' : \'none\'" id="grant_notify"/><label for="grant_notify">'.cplang('medals_grant_notify').'</label>');

			}

			showtablefooter();
			showformfooter();
			showtagfooter('div');

		}

	} else {
		if(!empty($_POST['conditions'])) $search_condition = dunserialize($_POST['conditions']);
		$membernum = countmembers($search_condition, $urladd);
		notifymembers('confermedal', 'medalletter');

	}
} elseif($operation == 'confermagic') {

	$magics = '';
	foreach(C::t('common_magic')->fetch_all_data(1) as $magic) {
		$magics .= showtablerow('', array('class="td25"', 'class="td23"', 'class="td25"', ''), array(
			"<input class=\"checkbox\" type=\"checkbox\" name=\"magic[]\" value=\"$magic[magicid]\" />",
			"<img src=\"static/image/magic/$magic[identifier].gif\" />",
			$magic['name'],
			'<input class="txt" type="text" name="magicnum['.$magic['magicid'].']" value="1" size="3">'
		), TRUE);
	}

	if(!$magics) {
		cpmsg('members_edit_magics_nonexistence', 'action=magics', 'error');
	}

	if(!submitcheck('confermagicsubmit')) {

		shownav('extended', 'nav_magics', 'nav_members_confermagic');
		showsubmenusteps('nav_members_confermagic', array(
			array('nav_members_select', !$_GET['submit']),
			array('nav_members_confermagic', $_GET['submit']),
		), array(
			array('admin', 'magics&operation=admin', 0),
			array('nav_magics_confer', 'members&operation=confermagic', 1)
		));

		showsearchform('confermagic');

		if(submitcheck('submit', 1)) {

			$membernum = countmembers($search_condition, $urladd);

			showtagheader('div', 'confermedal', TRUE);
			showformheader('members&operation=confermagic'.$urladd);
			echo '<table class="tb tb1">';

			if(!$membernum) {
				showtablerow('', 'class="lineheight"', $lang['members_search_nonexistence']);
				showtablefooter();
			} else {

				showtablerow('class="first"', array('class="th11"'), array(
					cplang('members_confermagic_members'),
					cplang('members_search_result', array('membernum' => $membernum))."<a href=\"###\" onclick=\"$('searchmembers').style.display='';$('confermedal').style.display='none';$('step1').className='current';$('step2').className='';\" class=\"act\">$lang[research]</a>"
				));

				echo '<tr><td class="th12">'.cplang('members_confermagic').'</td><td>';
				showtableheader('', 'noborder');
				showsubtitle(array('nav_magics_confer', 'nav_magics_image', 'nav_magics_name', 'nav_magics_num'));
				echo $magics;
				showtablefooter();
				showtablefooter();

				showtagheader('div', 'messagebody');
				shownewsletter();
				showtagfooter('div');
				showsubmit('confermagicsubmit', 'submit', 'td', '<input class="checkbox" type="checkbox" name="notifymember" value="1" onclick="$(\'messagebody\').style.display = this.checked ? \'\' : \'none\'" id="grant_notify"/><label for="grant_notify">'.cplang('magics_grant_notify').'</label>');

			}

			showtablefooter();
			showformfooter();
			showtagfooter('div');

		}

	} else {
		if(!empty($_POST['conditions'])) $search_condition = dunserialize($_POST['conditions']);
		$membernum = countmembers($search_condition, $urladd);
		notifymembers('confermagic', 'magicletter');
	}
} elseif($operation == 'add') {

	if(!submitcheck('addsubmit')) {
		//入职时间
		$html_rztime = array();
		$birthyeayhtml = '';
		$nowy = dgmdate($_G['timestamp'], 'Y');
		for ($i=0; $i<100; $i++) {
			$they = $nowy - $i;
			$selectstr = $they == $space['birthyear']?' selected':'';
			$birthyeayhtml .= "<option value=\"$they\"$selectstr>$they</option>";
		}
		$birthmonthhtml = '';
		for ($i=1; $i<13; $i++) {
			$selectstr = $i == $space['birthmonth']?' selected':'';
			$birthmonthhtml .= "<option value=\"$i\"$selectstr>$i</option>";
		}
		$birthdayhtml = '';
		if(empty($space['birthmonth']) || in_array($space['birthmonth'], array(1, 3, 5, 7, 8, 10, 12))) {
			$days = 31;
		} elseif(in_array($space['birthmonth'], array(4, 6, 9, 11))) {
			$days = 30;
		} elseif($space['birthyear'] && (($space['birthyear'] % 400 == 0) || ($space['birthyear'] % 4 == 0 && $space['birthyear'] % 400 != 0))) {
			$days = 29;
		} else {
			$days = 28;
		}
		for ($i=1; $i<=$days; $i++) {
			$selectstr = $i == $space['birthday']?' selected':'';
			$birthdayhtml .= "<option value=\"$i\"$selectstr>$i</option>";
		}
		$html_rztime = '<select name="birthyear" id="birthyear" class="ps" onchange="showbirthday();" tabindex="1">'
				.'<option value="">'.lang('space', 'year').'</option>'
				.$birthyeayhtml
				.'</select>'
				.'&nbsp;&nbsp;'
				.'<select name="birthmonth" id="birthmonth" class="ps" onchange="showbirthday();" tabindex="1">'
				.'<option value="">'.lang('space', 'month').'</option>'
				.$birthmonthhtml
				.'</select>'
				.'&nbsp;&nbsp;'
				.'<select name="birthday" id="birthday" class="ps" tabindex="1">'
				.'<option value="">'.lang('space', 'day').'</option>'
				.$birthdayhtml
				.'</select>';

	    //所属
	    $ss_select = '';
	    $ss_query = C::t('common_member_profile_setting')->ss_common_member_profile_setting('field1');
	    $ss_query_arr = explode("\n", $ss_query[0]['choices']);
	    $ss_select .= "<option value=\"\">".$lang['qingxuanze']."</option>\n";
	    foreach ($ss_query_arr as $value) {
	    	$ss_select .= "<option value=\"$value\">$value</option>\n";
	    }

        //用户组
		$groupselect = array();
		$query = C::t('common_usergroup')->fetch_all_by_not_groupid(array(5, 6, 7));
		foreach($query as $group) {
			$group['type'] = $group['type'] == 'special' && $group['radminid'] ? 'specialadmin' : $group['type'];
			if($group['type'] == 'member' && $group['creditshigher'] == 0) {
				$groupselect[$group['type']] .= "<option value=\"$group[groupid]\" selected>$group[grouptitle]</option>\n";
			} else {
				$groupselect[$group['type']] .= "<option value=\"$group[groupid]\">$group[grouptitle]</option>\n";
			}
		}
		$groupselect = '<optgroup label="'.$lang['usergroups_member'].'">'.$groupselect['member'].'</optgroup>'.
			($groupselect['special'] ? '<optgroup label="'.$lang['usergroups_special'].'">'.$groupselect['special'].'</optgroup>' : '').
			($groupselect['specialadmin'] ? '<optgroup label="'.$lang['usergroups_specialadmin'].'">'.$groupselect['specialadmin'].'</optgroup>' : '').
			'<optgroup label="'.$lang['usergroups_system'].'">'.$groupselect['system'].'</optgroup>';
		//输出页面
		shownav('user', 'nav_members_add');
		showsubmenu('members_add');
		showformheader('members&operation=add');
		showtableheader();
		showsetting('username', 'newusername', '', 'text');
		showsetting('password', 'newpassword', '', 'text');
		//showsetting('email', 'newemail', '', 'text');
		showsetting('sex', 'gender', '', 'radio1');
		showsetting('entry_time', '', '', $html_rztime);
		showsetting('subordinate_position', '', '', '<select name="newsubordinate">'.$ss_select.'</select>');
		showsetting('usergroup', '', '', '<select name="newgroupid">'.$groupselect.'</select>');
		//showsetting('members_add_email_notify', 'emailnotify', '', 'radio');
		showsubmit('addsubmit');
		showtablefooter();
		showformfooter();
	} else {
		$dataarr = array(
			array(
				'newusername'=>'张三3',
				'newpassword'=>'123456',
				'gender'=>1,
				'birthyear'=>'2015',
				'birthmonth'=>'1',
				'birthday'=>'1',
				'newsubordinate'=>'发展部-培训实验组-培训实验组一区',
				'newgroupid'=>'31'
			),
			array(
				'newusername'=>'张三4',
				'newpassword'=>'123456',
				'gender'=>2,
				'birthyear'=>'2015',
				'birthmonth'=>'2',
				'birthday'=>'2',
				'newsubordinate'=>'发展部-培训实验组-培训实验组二区',
				'newgroupid'=>'32'
			)
		);
		// $file = fopen('D:\wamp\www\ds\text.csv','r'); 
		// while ($data = fgetcsv($file)) { //每次读取CSV里面的一行内容
		//   $goods_list[] = $data;
		// }
		$goods_list = array ( 0 => array ( 0 => '﻿付魁', 1 => '1', 2 => '2012/3/1', 3 => '办公室', 4 => '38', ), 1 => array ( 0 => '章小青', 1 => '0', 2 => '2013/3/18', 3 => '财务部', 4 => '34', ), 2 => array ( 0 => '薛艳丽', 1 => '0', 2 => '2015/12/3', 3 => '行政部', 4 => '35', ), 3 => array ( 0 => '陈青莲', 1 => '0', 2 => '2014/1/3', 3 => '行政部', 4 => '34', ), 4 => array ( 0 => '赵莉', 1 => '0', 2 => '2014/5/8', 3 => '质检部', 4 => '34', ), 5 => array ( 0 => '张贤俊', 1 => '1', 2 => '2015/3/9', 3 => '质检部', 4 => '34', ), 6 => array ( 0 => '季峥', 1 => '1', 2 => '2014/6/3', 3 => '研发部', 4 => '34', ), 7 => array ( 0 => '张欢斌', 1 => '1', 2 => '2012/11/27', 3 => 'IT运维部', 4 => '34', ), 8 => array ( 0 => '仲骏', 1 => '1', 2 => '2015/1/16', 3 => 'IT运维部', 4 => '34', ), 9 => array ( 0 => '谭腊飞', 1 => '0', 2 => '2012/11/27', 3 => '开户管理部', 4 => '34', ), 10 => array ( 0 => '孔程群', 1 => '1', 2 => '2012/4/23', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '36', ), 11 => array ( 0 => '丁俊', 1 => '1', 2 => '2013/10/21', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '34', ), 12 => array ( 0 => '梅乐', 1 => '1', 2 => '2012/11/26', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 13 => array ( 0 => '刘翠凤', 1 => '0', 2 => '2014/3/7', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 14 => array ( 0 => '程新华', 1 => '1', 2 => '2014/7/7', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 15 => array ( 0 => '杨天', 1 => '1', 2 => '2014/10/27', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 16 => array ( 0 => '魏颖', 1 => '0', 2 => '2014/11/3', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 17 => array ( 0 => '李海斌', 1 => '1', 2 => '2015/3/10', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 18 => array ( 0 => '任红丽', 1 => '0', 2 => '2015/3/30', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 19 => array ( 0 => '左其敏', 1 => '1', 2 => '2015/8/31', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 20 => array ( 0 => '王洋', 1 => '1', 2 => '2015/12/21', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 21 => array ( 0 => '董燕', 1 => '0', 2 => '2016/1/30', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 22 => array ( 0 => '吴文清', 1 => '1', 2 => '2016/3/1', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 23 => array ( 0 => '潘高乐', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 24 => array ( 0 => '刘泉太', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展一区-发展一部-一部一组', 4 => '31', ), 25 => array ( 0 => '邓宅秀', 1 => '1', 2 => '2012/12/18', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 26 => array ( 0 => '梅秀斌', 1 => '1', 2 => '2013/4/1', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 27 => array ( 0 => '丁如霞', 1 => '0', 2 => '2013/7/29', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 28 => array ( 0 => '江丽珍', 1 => '0', 2 => '2012/9/10', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 29 => array ( 0 => '冉亚楠', 1 => '0', 2 => '2013/10/21', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 30 => array ( 0 => '章兵', 1 => '1', 2 => '2014/2/24', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 31 => array ( 0 => '许卉', 1 => '0', 2 => '2014/6/9', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 32 => array ( 0 => '马成龙', 1 => '1', 2 => '2015/12/7', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 33 => array ( 0 => '徐达', 1 => '1', 2 => '2015/12/21', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 34 => array ( 0 => '马志远', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 35 => array ( 0 => '白士荣', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展一区-发展一部-一部三组', 4 => '31', ), 36 => array ( 0 => '刘小普', 1 => '1', 2 => '2014/7/21', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 37 => array ( 0 => '司玉品', 1 => '0', 2 => '2014/10/27', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 38 => array ( 0 => '柏娟', 1 => '0', 2 => '2014/12/1', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 39 => array ( 0 => '刘航', 1 => '1', 2 => '2014/12/15', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 40 => array ( 0 => '丛薇', 1 => '0', 2 => '2015/6/8', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 41 => array ( 0 => '郜忠亮', 1 => '1', 2 => '2015/11/23', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 42 => array ( 0 => '刘端', 1 => '1', 2 => '2015/12/7', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 43 => array ( 0 => '罗琦', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 44 => array ( 0 => '杨家龙', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 45 => array ( 0 => '朱传家', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 46 => array ( 0 => '刘进升', 1 => '1', 2 => '2016/5/5', 3 => '发展部-发展一区-发展一部-一部五组', 4 => '31', ), 47 => array ( 0 => '崔征', 1 => '1', 2 => '2014/2/17', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 48 => array ( 0 => '张雪玉', 1 => '0', 2 => '2013/7/1', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 49 => array ( 0 => '卓倩倩', 1 => '0', 2 => '2014/6/3', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 50 => array ( 0 => '段语晰', 1 => '0', 2 => '2014/8/4', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 51 => array ( 0 => '秦晶晶', 1 => '0', 2 => '2014/8/4', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 52 => array ( 0 => '夏伟伟', 1 => '1', 2 => '2015/3/5', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 53 => array ( 0 => '郑佩璇', 1 => '0', 2 => '2015/5/18', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 54 => array ( 0 => '刘保军', 1 => '1', 2 => '2015/5/18', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 55 => array ( 0 => '瞿文', 1 => '1', 2 => '2015/7/13', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 56 => array ( 0 => '梅乐迎', 1 => '1', 2 => '2015/8/24', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 57 => array ( 0 => '程西', 1 => '1', 2 => '2015/11/2', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 58 => array ( 0 => '陈丹', 1 => '0', 2 => '2016/2/22', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 59 => array ( 0 => '郝黎洋', 1 => '0', 2 => '2016/2/29', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 60 => array ( 0 => '史江雷', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '31', ), 61 => array ( 0 => '黄登成', 1 => '1', 2 => '2012/5/7', 3 => '发展部-发展三区-发展四部-四部一组', 4 => '35', ), 62 => array ( 0 => '秦静', 1 => '0', 2 => '2013/7/29', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 63 => array ( 0 => '李茜', 1 => '0', 2 => '2013/11/5', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 64 => array ( 0 => '黄艳', 1 => '0', 2 => '2015/3/10', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 65 => array ( 0 => '潘少平', 1 => '1', 2 => '2015/7/13', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 66 => array ( 0 => '王晋', 1 => '1', 2 => '2015/8/11', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 67 => array ( 0 => '李亚雄', 1 => '1', 2 => '2015/10/26', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 68 => array ( 0 => '方志琴', 1 => '0', 2 => '2016/3/1', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 69 => array ( 0 => '罗巧丽', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 70 => array ( 0 => '柳川国', 1 => '1', 2 => '2016/4/5', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 71 => array ( 0 => '陈丹1', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展三区-发展四部-四部二组', 4 => '31', ), 72 => array ( 0 => '唐伟宁', 1 => '1', 2 => '2013/2/26', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '34', ), 73 => array ( 0 => '宗朋通', 1 => '1', 2 => '2014/11/10', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 74 => array ( 0 => '周良峰', 1 => '1', 2 => '2014/11/10', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 75 => array ( 0 => '周航', 1 => '1', 2 => '2014/12/8', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 76 => array ( 0 => '李平', 1 => '1', 2 => '2015/7/20', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 77 => array ( 0 => '朱海娟', 1 => '0', 2 => '2015/10/19', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 78 => array ( 0 => '姜维', 1 => '1', 2 => '2015/10/26', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 79 => array ( 0 => '徐桃', 1 => '0', 2 => '2015/11/30', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 80 => array ( 0 => '侯飞', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 81 => array ( 0 => '王旭', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 82 => array ( 0 => '周卫芳', 1 => '0', 2 => '2016/4/5', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 83 => array ( 0 => '杜颖娜', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 84 => array ( 0 => '阚雪婷', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展三区-发展四部-四部四组', 4 => '31', ), 85 => array ( 0 => '欧加海', 1 => '1', 2 => '2016/3/3', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 86 => array ( 0 => '杨玉强', 1 => '1', 2 => '2012/5/7', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '34', ), 87 => array ( 0 => '戴雨峰', 1 => '1', 2 => '2015/7/20', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 88 => array ( 0 => '杨琪', 1 => '1', 2 => '2015/7/20', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 89 => array ( 0 => '吴云祥', 1 => '1', 2 => '2015/7/27', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 90 => array ( 0 => '卜婷婷', 1 => '0', 2 => '2015/9/1', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 91 => array ( 0 => '谢卫华', 1 => '1', 2 => '2015/9/7', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 92 => array ( 0 => '李忠良', 1 => '1', 2 => '2015/10/12', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 93 => array ( 0 => '李慧丹', 1 => '0', 2 => '2015/10/26', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 94 => array ( 0 => '谢亮亮', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 95 => array ( 0 => '谢尹', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 96 => array ( 0 => '秦洪帅', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 97 => array ( 0 => '强超', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展三区-发展四部-四部五组', 4 => '31', ), 98 => array ( 0 => '高晓', 1 => '0', 2 => '2014/3/13', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '34', ), 99 => array ( 0 => '毛琦琛', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 100 => array ( 0 => '肖倩', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 101 => array ( 0 => '吴建银', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 102 => array ( 0 => '施林', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 103 => array ( 0 => '周新', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 104 => array ( 0 => '刘飞洪', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 105 => array ( 0 => '郭苏欢', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 106 => array ( 0 => '吴新浩', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 107 => array ( 0 => '刘彦君', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展三区-发展四部-四部六组', 4 => '31', ), 108 => array ( 0 => '赵伊博', 1 => '1', 2 => '2014/3/3', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '34', ), 109 => array ( 0 => '马姜军', 1 => '1', 2 => '2015/4/20', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 110 => array ( 0 => '付雅双', 1 => '0', 2 => '2015/4/20', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 111 => array ( 0 => '翁仁杰', 1 => '1', 2 => '2015/5/18', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 112 => array ( 0 => '曾繁', 1 => '1', 2 => '2015/6/15', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 113 => array ( 0 => '翟龙龙', 1 => '1', 2 => '2015/8/3', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 114 => array ( 0 => '刘述进', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 115 => array ( 0 => '孙晓东', 1 => '1', 2 => '2015/10/14', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 116 => array ( 0 => '吕晓红', 1 => '0', 2 => '2015/11/23', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 117 => array ( 0 => '李卫敏', 1 => '0', 2 => '2016/1/18', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 118 => array ( 0 => '王柯', 1 => '1', 2 => '2016/3/1', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 119 => array ( 0 => '于玉领', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 120 => array ( 0 => '毛震', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 121 => array ( 0 => '张函', 1 => '0', 2 => '2016/4/25', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 122 => array ( 0 => '董庆胜', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展五部-五部二组', 4 => '31', ), 123 => array ( 0 => '陈四龙', 1 => '1', 2 => '2014/10/13', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 124 => array ( 0 => '安玲玲', 1 => '0', 2 => '2015/5/11', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 125 => array ( 0 => '邓志超', 1 => '1', 2 => '2015/5/11', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 126 => array ( 0 => '徐巍', 1 => '1', 2 => '2015/5/11', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 127 => array ( 0 => '王继祥', 1 => '1', 2 => '2015/6/1', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 128 => array ( 0 => '梁天', 1 => '1', 2 => '2015/6/15', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 129 => array ( 0 => '郑山玲', 1 => '0', 2 => '2015/6/15', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 130 => array ( 0 => '郭亚勤', 1 => '0', 2 => '2015/7/13', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 131 => array ( 0 => '侯国正', 1 => '1', 2 => '2015/8/17', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 132 => array ( 0 => '鲍乘乘', 1 => '1', 2 => '2015/10/11', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 133 => array ( 0 => '梁海', 1 => '1', 2 => '2015/10/12', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 134 => array ( 0 => '李秀竹', 1 => '0', 2 => '2016/1/19', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 135 => array ( 0 => '蔡金刚', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 136 => array ( 0 => '刘世敏', 1 => '0', 2 => '2016/3/22', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 137 => array ( 0 => '廖伦凯', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展五部-五部三组', 4 => '31', ), 138 => array ( 0 => '唐志平', 1 => '1', 2 => '2012/8/28', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '34', ), 139 => array ( 0 => '涂金明', 1 => '1', 2 => '2014/4/15', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '34', ), 140 => array ( 0 => '叶永强', 1 => '1', 2 => '2014/5/19', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 141 => array ( 0 => '曾崇隆', 1 => '1', 2 => '2013/6/13', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 142 => array ( 0 => '梁修亮', 1 => '1', 2 => '2014/11/3', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 143 => array ( 0 => '周芳元', 1 => '0', 2 => '2015/3/5', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 144 => array ( 0 => '李德丽', 1 => '0', 2 => '2015/3/23', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 145 => array ( 0 => '陈平', 1 => '0', 2 => '2015/6/29', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 146 => array ( 0 => '杜刘飞', 1 => '1', 2 => '2015/11/9', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 147 => array ( 0 => '王佳玉', 1 => '0', 2 => '2016/2/22', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 148 => array ( 0 => '曾俊', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 149 => array ( 0 => '王刚', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 150 => array ( 0 => '常义东', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 151 => array ( 0 => '张敬', 1 => '0', 2 => '2016/4/11', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 152 => array ( 0 => '方猛', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展五部-五部四组', 4 => '31', ), 153 => array ( 0 => '钱大彬', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 154 => array ( 0 => '韩宪鹏', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 155 => array ( 0 => '姚建军', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 156 => array ( 0 => '李青云', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 157 => array ( 0 => '吴燕霞', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 158 => array ( 0 => '李林杰', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 159 => array ( 0 => '叶赛清', 1 => '1', 2 => '2016/3/8', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 160 => array ( 0 => '李露', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 161 => array ( 0 => '曾丽丽', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 162 => array ( 0 => '武倩', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 163 => array ( 0 => '邓小涛', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 164 => array ( 0 => '周润华', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 165 => array ( 0 => '朱娜娜', 1 => '0', 2 => '2016/4/25', 3 => '发展部-发展一区-发展五部-五部五组', 4 => '31', ), 166 => array ( 0 => '管业锋', 1 => '1', 2 => '2012/7/9', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '35', ), 167 => array ( 0 => '仓业磊', 1 => '1', 2 => '2013/2/26', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '34', ), 168 => array ( 0 => '何阿乔', 1 => '1', 2 => '2015/4/20', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 169 => array ( 0 => '杨环', 1 => '1', 2 => '2015/4/20', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 170 => array ( 0 => '马昭旭', 1 => '1', 2 => '2015/4/20', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 171 => array ( 0 => '黄静松', 1 => '1', 2 => '2015/4/20', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 172 => array ( 0 => '彭先波', 1 => '1', 2 => '2015/4/27', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 173 => array ( 0 => '江涛', 1 => '1', 2 => '2015/5/18', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 174 => array ( 0 => '周惠', 1 => '0', 2 => '2015/6/15', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 175 => array ( 0 => '张勇', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 176 => array ( 0 => '汪杨', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 177 => array ( 0 => '牟健', 1 => '1', 2 => '2015/11/30', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 178 => array ( 0 => '施婷', 1 => '0', 2 => '2015/11/30', 3 => '发展部-发展一区-发展七部-七部一组', 4 => '31', ), 179 => array ( 0 => '邵红光', 1 => '1', 2 => '2014/9/22', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 180 => array ( 0 => '顾倪杰', 1 => '1', 2 => '2015/8/17', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 181 => array ( 0 => '朱文韬', 1 => '1', 2 => '2015/8/17', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 182 => array ( 0 => '于洋洋', 1 => '0', 2 => '2015/8/17', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 183 => array ( 0 => '李祖胜', 1 => '1', 2 => '2015/9/7', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 184 => array ( 0 => '杨绍辉', 1 => '1', 2 => '2015/9/7', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 185 => array ( 0 => '程徐彬', 1 => '1', 2 => '2015/10/19', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 186 => array ( 0 => '邓正华', 1 => '1', 2 => '2015/11/2', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 187 => array ( 0 => '郑朝玮', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 188 => array ( 0 => '姜丽媛', 1 => '0', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 189 => array ( 0 => '朱战战', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 190 => array ( 0 => '林丹', 1 => '0', 2 => '2016/5/4', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 191 => array ( 0 => '李洋', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展一区-发展七部-七部二组', 4 => '31', ), 192 => array ( 0 => '宋余良', 1 => '1', 2 => '2013/12/16', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '34', ), 193 => array ( 0 => '高星星', 1 => '0', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 194 => array ( 0 => '明廷山', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 195 => array ( 0 => '潘姜源', 1 => '0', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 196 => array ( 0 => '刘韧', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 197 => array ( 0 => '吴卫贤', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 198 => array ( 0 => '刘宗恒', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 199 => array ( 0 => '张冬冬', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 200 => array ( 0 => '陈明', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 201 => array ( 0 => '谢飞', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 202 => array ( 0 => '刘兆平', 1 => '0', 2 => '2016/3/15', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 203 => array ( 0 => '赵剑伟', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 204 => array ( 0 => '马仕兰', 1 => '0', 2 => '2016/4/5', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 205 => array ( 0 => '李坤鹏', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 206 => array ( 0 => '刘辉', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展一区-发展七部-七部三组', 4 => '31', ), 207 => array ( 0 => '余洋', 1 => '1', 2 => '2013/8/28', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '34', ), 208 => array ( 0 => '谯东', 1 => '1', 2 => '2014/3/12', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 209 => array ( 0 => '冯雪丽', 1 => '0', 2 => '2015/6/29', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 210 => array ( 0 => '刘正莉', 1 => '0', 2 => '2015/7/27', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 211 => array ( 0 => '张雷', 1 => '1', 2 => '2015/8/17', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 212 => array ( 0 => '张文科', 1 => '1', 2 => '2016/1/19', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 213 => array ( 0 => '李凯', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 214 => array ( 0 => '朱秀娟', 1 => '0', 2 => '2016/2/22', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 215 => array ( 0 => '何晶', 1 => '1', 2 => '2016/1/11', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 216 => array ( 0 => '陆瀛', 1 => '1', 2 => '2016/1/11', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 217 => array ( 0 => '段贝贝', 1 => '0', 2 => '2016/2/29', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 218 => array ( 0 => '左宗沛', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 219 => array ( 0 => '许文文', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 220 => array ( 0 => '应佐南', 1 => '1', 2 => '2016/4/18', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 221 => array ( 0 => '李俊楠', 1 => '0', 2 => '2016/4/25', 3 => '发展部-发展一区-发展七部-七部四组', 4 => '31', ), 222 => array ( 0 => '胡苏北', 1 => '1', 2 => '2014/7/14', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '34', ), 223 => array ( 0 => '梁龙潭', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 224 => array ( 0 => '叶娟君', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 225 => array ( 0 => '姚彬', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 226 => array ( 0 => '单锋勇', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 227 => array ( 0 => '马少梅', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 228 => array ( 0 => '郝强', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 229 => array ( 0 => '张欢欢', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 230 => array ( 0 => '吴艳子', 1 => '0', 2 => '2016/4/5', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 231 => array ( 0 => '蒋守领', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 232 => array ( 0 => '周宇飞', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 233 => array ( 0 => '李明刚', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展三区-发展八部-八部一组', 4 => '31', ), 234 => array ( 0 => '崔林', 1 => '0', 2 => '2014/12/1', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '34', ), 235 => array ( 0 => '魏志超', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 236 => array ( 0 => '廖加林', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 237 => array ( 0 => '余刘斌', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 238 => array ( 0 => '王云祥', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 239 => array ( 0 => '王海霞', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 240 => array ( 0 => '李科', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 241 => array ( 0 => '张斌', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 242 => array ( 0 => '贾卫玲', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 243 => array ( 0 => '董艳旗', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 244 => array ( 0 => '刘少德比力格', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 245 => array ( 0 => '陈先丰', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展三区-发展八部-八部二组', 4 => '31', ), 246 => array ( 0 => '魏子威', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 247 => array ( 0 => '张恒', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 248 => array ( 0 => '张宇', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 249 => array ( 0 => '李志响', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 250 => array ( 0 => '水海洋', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 251 => array ( 0 => '曹颖', 1 => '0', 2 => '2016/4/18', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 252 => array ( 0 => '姜忠岳', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 253 => array ( 0 => '姜哲辉', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 254 => array ( 0 => '徐卜', 1 => '0', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 255 => array ( 0 => '金郁麟', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展一区-发展九部-九部一组', 4 => '31', ), 256 => array ( 0 => '宋汉联', 1 => '1', 2 => '2012/8/1', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '34', ), 257 => array ( 0 => '张素宏', 1 => '1', 2 => '2014/3/24', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 258 => array ( 0 => '鲍马超', 1 => '1', 2 => '2014/7/14', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 259 => array ( 0 => '秦晓波', 1 => '1', 2 => '2014/10/27', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 260 => array ( 0 => '张健军', 1 => '1', 2 => '2014/12/9', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 261 => array ( 0 => '马小虎', 1 => '1', 2 => '2015/3/5', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 262 => array ( 0 => '魏晗露', 1 => '0', 2 => '2015/4/7', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 263 => array ( 0 => '汤高萍', 1 => '0', 2 => '2015/7/27', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 264 => array ( 0 => '霍鹏程', 1 => '1', 2 => '2016/1/18', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 265 => array ( 0 => '王静静', 1 => '0', 2 => '2016/2/22', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 266 => array ( 0 => '曹务旭', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 267 => array ( 0 => '刘振苹', 1 => '1', 2 => '2013/6/17', 3 => '发展部-发展一区-发展九部-九部二组', 4 => '31', ), 268 => array ( 0 => '顾娴', 1 => '0', 2 => '2016/5/16', 3 => '发展部-培训实验组-培训实验一区', 4 => '31', ), 269 => array ( 0 => '周丹', 1 => '0', 2 => '2016/5/16', 3 => '发展部-培训实验组-培训实验一区', 4 => '31', ), 270 => array ( 0 => '李思思', 1 => '0', 2 => '2012/11/12', 3 => '办公室', 4 => '37', ), 271 => array ( 0 => '张哲', 1 => '0', 2 => '2015/4/27', 3 => '总经办', 4 => '34', ), 272 => array ( 0 => '蒋艳', 1 => '0', 2 => '2014/10/12', 3 => '策划部', 4 => '34', ), 273 => array ( 0 => '吴松', 1 => '1', 2 => '2014/5/26', 3 => 'IT运维部-二区运维', 4 => '34', ), 274 => array ( 0 => '郭峰', 1 => '1', 2 => '2012/10/25', 3 => '研发部', 4 => '34', ), 275 => array ( 0 => '程春松', 1 => '1', 2 => '2014/6/9', 3 => '研发部', 4 => '34', ), 276 => array ( 0 => '陈玲玲', 1 => '0', 2 => '2015/11/12', 3 => '客服部', 4 => '34', ), 277 => array ( 0 => '吴盼', 1 => '1', 2 => '2015/10/26', 3 => '客服部', 4 => '33', ), 278 => array ( 0 => '杨勇', 1 => '1', 2 => '2014/2/24', 3 => '市场综合部-督导组', 4 => '33', ), 279 => array ( 0 => '周园园', 1 => '0', 2 => '2013/5/27', 3 => '市场综合部-招聘组', 4 => '33', ), 280 => array ( 0 => '吉静', 1 => '0', 2 => '2016/2/29', 3 => '发展部-培训实验组-培训实验一区', 4 => '34', ), 281 => array ( 0 => '陈姝', 1 => '0', 2 => '2014/6/11', 3 => '质检部-二区质检', 4 => '34', ), 282 => array ( 0 => '赵永胜', 1 => '1', 2 => '2013/2/26', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '36', ), 283 => array ( 0 => '杨利平', 1 => '0', 2 => '2013/3/5', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '34', ), 284 => array ( 0 => '郭延志', 1 => '1', 2 => '2014/4/15', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 285 => array ( 0 => '刘荣方', 1 => '0', 2 => '2014/7/14', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 286 => array ( 0 => '田玉峰', 1 => '1', 2 => '2015/3/10', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 287 => array ( 0 => '吴彬', 1 => '1', 2 => '2015/5/18', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 288 => array ( 0 => '徐茂林', 1 => '1', 2 => '2015/6/23', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 289 => array ( 0 => '汪勇', 1 => '1', 2 => '2015/7/20', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 290 => array ( 0 => '唐端华', 1 => '1', 2 => '2015/8/3', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 291 => array ( 0 => '青格尔', 1 => '0', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 292 => array ( 0 => '郑淞尹', 1 => '0', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 293 => array ( 0 => '王梦园', 1 => '1', 2 => '2015/10/19', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 294 => array ( 0 => '孙海涛', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 295 => array ( 0 => '叶永欢', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部一组', 4 => '31', ), 296 => array ( 0 => '宋徽', 1 => '1', 2 => '2012/8/7', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '34', ), 297 => array ( 0 => '刘灿', 1 => '0', 2 => '2013/3/18', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 298 => array ( 0 => '刘利刚', 1 => '1', 2 => '2014/8/25', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 299 => array ( 0 => '马强', 1 => '1', 2 => '2015/3/10', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 300 => array ( 0 => '程正松', 1 => '1', 2 => '2015/4/13', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 301 => array ( 0 => '江涛1', 1 => '1', 2 => '2015/7/27', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 302 => array ( 0 => '高亮', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 303 => array ( 0 => '张纹川', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 304 => array ( 0 => '孙杰超', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 305 => array ( 0 => '夏琪美', 1 => '0', 2 => '2015/8/17', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 306 => array ( 0 => '胡欢欢', 1 => '0', 2 => '2015/11/2', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 307 => array ( 0 => '胡国顺', 1 => '1', 2 => '2014/10/20', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 308 => array ( 0 => '柴勇', 1 => '1', 2 => '2016/3/16', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 309 => array ( 0 => '王家鹏', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 310 => array ( 0 => '林德盟', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展二部-二部二组', 4 => '31', ), 311 => array ( 0 => '鲍广宇', 1 => '1', 2 => '2013/4/15', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '34', ), 312 => array ( 0 => '杨桃', 1 => '0', 2 => '2014/10/27', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 313 => array ( 0 => '于强强', 1 => '1', 2 => '2014/11/3', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 314 => array ( 0 => '苏军飞', 1 => '1', 2 => '2014/11/18', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 315 => array ( 0 => '韦卫', 1 => '0', 2 => '2015/3/5', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 316 => array ( 0 => '唐初', 1 => '1', 2 => '2015/4/7', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 317 => array ( 0 => '王宁', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 318 => array ( 0 => '马晓军', 1 => '1', 2 => '2015/10/19', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 319 => array ( 0 => '黄颖樑', 1 => '1', 2 => '2015/11/2', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 320 => array ( 0 => '李胜', 1 => '1', 2 => '2015/12/21', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 321 => array ( 0 => '郝新健', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 322 => array ( 0 => '徐恒', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 323 => array ( 0 => '王成梅', 1 => '0', 2 => '2016/3/22', 3 => '发展部-发展二区-发展二部-二部三组', 4 => '31', ), 324 => array ( 0 => '吴岳兵', 1 => '1', 2 => '2014/6/9', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '34', ), 325 => array ( 0 => '徐晶燕', 1 => '0', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 326 => array ( 0 => '刘国伟', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 327 => array ( 0 => '袁华浪', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 328 => array ( 0 => '孙孝伟', 1 => '1', 2 => '2015/8/10', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 329 => array ( 0 => '刘拥军', 1 => '1', 2 => '2015/8/24', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 330 => array ( 0 => '孟浩宇', 1 => '1', 2 => '2015/9/21', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 331 => array ( 0 => '汪永财', 1 => '1', 2 => '2015/10/9', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 332 => array ( 0 => '田琳晓', 1 => '0', 2 => '2015/11/16', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 333 => array ( 0 => '吴博', 1 => '1', 2 => '2015/11/16', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 334 => array ( 0 => '何天阳', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 335 => array ( 0 => '江云燕', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 336 => array ( 0 => '陈达', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 337 => array ( 0 => '林小妹', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 338 => array ( 0 => '蔡翱城', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展二部-二部五组', 4 => '31', ), 339 => array ( 0 => '王万景', 1 => '0', 2 => '2014/5/19', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '34', ), 340 => array ( 0 => '纪翠晴', 1 => '0', 2 => '2015/10/26', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 341 => array ( 0 => '黄蕊', 1 => '1', 2 => '2015/11/9', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 342 => array ( 0 => '刘跟七', 1 => '1', 2 => '2015/11/30', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 343 => array ( 0 => '陈连学', 1 => '1', 2 => '2015/12/7', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 344 => array ( 0 => '李志远', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 345 => array ( 0 => '吴海宾', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 346 => array ( 0 => '徐佳明', 1 => '1', 2 => '2016/2/22', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 347 => array ( 0 => '花秀凡', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 348 => array ( 0 => '苗金朴', 1 => '0', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 349 => array ( 0 => '李玉龙', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 350 => array ( 0 => '盛雪梅', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 351 => array ( 0 => '孔德伟', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 352 => array ( 0 => '杨雪', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 353 => array ( 0 => '谷彬阳', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展二区-发展二部-二部六组', 4 => '31', ), 354 => array ( 0 => '刘栋骏', 1 => '1', 2 => '2014/4/28', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '34', ), 355 => array ( 0 => '谢荆荆', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 356 => array ( 0 => '宋伟康', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 357 => array ( 0 => '尚晓倩', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 358 => array ( 0 => '李超男', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 359 => array ( 0 => '廖冬纯', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 360 => array ( 0 => '卓兰香', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 361 => array ( 0 => '杨小龙', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 362 => array ( 0 => '魏凯强', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 363 => array ( 0 => '侯志勇', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 364 => array ( 0 => '韩甜甜', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 365 => array ( 0 => '徐海兵', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 366 => array ( 0 => '陈婷', 1 => '0', 2 => '2016/4/25', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 367 => array ( 0 => '张东阁', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展二区-发展二部-二部七组', 4 => '31', ), 368 => array ( 0 => '赵一林', 1 => '1', 2 => '2013/2/26', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '35', ), 369 => array ( 0 => '袁浩', 1 => '1', 2 => '2013/2/26', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '34', ), 370 => array ( 0 => '冯永龙', 1 => '1', 2 => '2014/3/17', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 371 => array ( 0 => '杜先瑞', 1 => '1', 2 => '2014/7/21', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 372 => array ( 0 => '欧阳双双', 1 => '0', 2 => '2015/3/10', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 373 => array ( 0 => '王荣辉', 1 => '1', 2 => '2015/3/30', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 374 => array ( 0 => '胡江南', 1 => '1', 2 => '2015/10/26', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 375 => array ( 0 => '罗冬亚', 1 => '0', 2 => '2016/2/15', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 376 => array ( 0 => '乔贝', 1 => '0', 2 => '2016/2/29', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 377 => array ( 0 => '胡俊磊', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 378 => array ( 0 => '杨城', 1 => '1', 2 => '2016/3/28', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 379 => array ( 0 => '谢喜兰', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 380 => array ( 0 => '赵彬', 1 => '1', 2 => '2016/4/25', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 381 => array ( 0 => '方鹏程', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展二区-发展三部-三部一组', 4 => '31', ), 382 => array ( 0 => '于海霞', 1 => '0', 2 => '2013/11/18', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '34', ), 383 => array ( 0 => '宋春飞', 1 => '1', 2 => '2014/2/17', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 384 => array ( 0 => '郑良武', 1 => '1', 2 => '2014/10/20', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 385 => array ( 0 => '赵良江', 1 => '1', 2 => '2015/3/5', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 386 => array ( 0 => '沈成玲', 1 => '0', 2 => '2015/3/30', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 387 => array ( 0 => '牛小萌', 1 => '0', 2 => '2015/8/3', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 388 => array ( 0 => '张泽豪', 1 => '1', 2 => '2015/11/23', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 389 => array ( 0 => '周涛', 1 => '1', 2 => '2015/11/30', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 390 => array ( 0 => '牛伟民', 1 => '1', 2 => '2015/12/7', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 391 => array ( 0 => '余昭友', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 392 => array ( 0 => '张志方', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 393 => array ( 0 => '谢燕燕', 1 => '0', 2 => '2016/5/6', 3 => '发展部-发展二区-发展三部-三部二组', 4 => '31', ), 394 => array ( 0 => '李国卫', 1 => '1', 2 => '2013/3/18', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '34', ), 395 => array ( 0 => '吴桦杰', 1 => '1', 2 => '2014/11/24', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 396 => array ( 0 => '靳洁', 1 => '0', 2 => '2014/11/24', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 397 => array ( 0 => '张冰', 1 => '1', 2 => '2014/11/24', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 398 => array ( 0 => '刘双', 1 => '1', 2 => '2015/3/16', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 399 => array ( 0 => '高凯飞', 1 => '1', 2 => '2015/6/23', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 400 => array ( 0 => '庄素瑶', 1 => '0', 2 => '2015/8/3', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 401 => array ( 0 => '韩佳最', 1 => '1', 2 => '2015/11/16', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 402 => array ( 0 => '何梦玲', 1 => '0', 2 => '2015/12/7', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 403 => array ( 0 => '高丽', 1 => '0', 2 => '2016/2/17', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 404 => array ( 0 => '王万友', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 405 => array ( 0 => '刘鑫淼', 1 => '0', 2 => '2016/3/22', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 406 => array ( 0 => '王虹乃', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 407 => array ( 0 => '刘彩姣', 1 => '0', 2 => '2016/3/22', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 408 => array ( 0 => '朱义鹏', 1 => '1', 2 => '2016/4/5', 3 => '发展部-发展二区-发展三部-三部五组', 4 => '31', ), 409 => array ( 0 => '赵应修', 1 => '1', 2 => '2015/3/5', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '34', ), 410 => array ( 0 => '赵挺', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 411 => array ( 0 => '江帆', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 412 => array ( 0 => '刘俊', 1 => '1', 2 => '2016/3/8', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 413 => array ( 0 => '崔长伟', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 414 => array ( 0 => '闫鹏程', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 415 => array ( 0 => '邢学成', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 416 => array ( 0 => '赵范杰', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 417 => array ( 0 => '刘忠辉', 1 => '1', 2 => '2016/3/7', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 418 => array ( 0 => '王彦力', 1 => '0', 2 => '2016/3/15', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 419 => array ( 0 => '覃野', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 420 => array ( 0 => '郑灿灿', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 421 => array ( 0 => '居效文', 1 => '1', 2 => '2016/5/4', 3 => '发展部-发展二区-发展三部-三部六组', 4 => '31', ), 422 => array ( 0 => '陈思萍', 1 => '0', 2 => '2012/12/24', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '35', ), 423 => array ( 0 => '杨美平', 1 => '0', 2 => '2014/6/30', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '34', ), 424 => array ( 0 => '阮鑫', 1 => '1', 2 => '2015/8/24', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 425 => array ( 0 => '李光杰', 1 => '1', 2 => '2015/8/24', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 426 => array ( 0 => '杨光', 1 => '1', 2 => '2015/8/24', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 427 => array ( 0 => '岳莉莉', 1 => '0', 2 => '2015/11/16', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 428 => array ( 0 => '黎仪海', 1 => '1', 2 => '2015/11/23', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 429 => array ( 0 => '徐中华', 1 => '0', 2 => '2016/2/16', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 430 => array ( 0 => '熊仁盛', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 431 => array ( 0 => '曾德勇', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 432 => array ( 0 => '鞠云瑞', 1 => '1', 2 => '2016/3/22', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 433 => array ( 0 => '杨慧', 1 => '0', 2 => '2016/3/22', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 434 => array ( 0 => '汪敏飞', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 435 => array ( 0 => '蒋秋燕', 1 => '0', 2 => '2016/3/28', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 436 => array ( 0 => '卢诗文', 1 => '1', 2 => '2016/4/5', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 437 => array ( 0 => '暴敬美', 1 => '0', 2 => '2016/4/5', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 438 => array ( 0 => '赵津得', 1 => '1', 2 => '2016/4/6', 3 => '发展部-发展二区-发展六部-六部三组', 4 => '31', ), 439 => array ( 0 => '唐梁', 1 => '1', 2 => '2014/3/10', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '34', ), 440 => array ( 0 => '胡东东', 1 => '1', 2 => '2014/3/10', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 441 => array ( 0 => '何源成', 1 => '1', 2 => '2014/3/10', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 442 => array ( 0 => '尹中甜', 1 => '0', 2 => '2014/3/10', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 443 => array ( 0 => '邹胜', 1 => '1', 2 => '2014/3/10', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 444 => array ( 0 => '徐维亮', 1 => '1', 2 => '2014/5/12', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 445 => array ( 0 => '陶庆', 1 => '1', 2 => '2014/6/17', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 446 => array ( 0 => '赵文桥', 1 => '1', 2 => '2014/8/18', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 447 => array ( 0 => '郑株', 1 => '1', 2 => '2014/8/18', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 448 => array ( 0 => '王祝叶', 1 => '0', 2 => '2015/4/20', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 449 => array ( 0 => '刘亚飞', 1 => '1', 2 => '2015/7/6', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 450 => array ( 0 => '吕威峰', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 451 => array ( 0 => '张晓强', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 452 => array ( 0 => '胡霏霏', 1 => '0', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 453 => array ( 0 => '邓陶', 1 => '0', 2 => '2016/3/15', 3 => '发展部-发展二区-发展六部-六部五组', 4 => '31', ), 454 => array ( 0 => '费红敏', 1 => '0', 2 => '2015/3/30', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '34', ), 455 => array ( 0 => '朱广辉', 1 => '1', 2 => '2015/4/7', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 456 => array ( 0 => '张利容', 1 => '0', 2 => '2015/5/26', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 457 => array ( 0 => '钱可', 1 => '1', 2 => '2015/6/8', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 458 => array ( 0 => '王勇', 1 => '1', 2 => '2015/7/14', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 459 => array ( 0 => '李霞B', 1 => '0', 2 => '2015/8/3', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 460 => array ( 0 => '费东波', 1 => '1', 2 => '2015/11/9', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 461 => array ( 0 => '张理建', 1 => '1', 2 => '2015/11/9', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 462 => array ( 0 => '许波', 1 => '1', 2 => '2015/11/16', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 463 => array ( 0 => '胡家君', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 464 => array ( 0 => '王威', 1 => '0', 2 => '2016/3/7', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 465 => array ( 0 => '蓝声宏', 1 => '1', 2 => '2016/2/29', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 466 => array ( 0 => '蒋奇迹', 1 => '1', 2 => '2016/3/15', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), 467 => array ( 0 => '李开平', 1 => '1', 2 => '2016/4/11', 3 => '发展部-发展二区-发展六部-六部六组', 4 => '31', ), );
		$arrdata = array();
		foreach ($goods_list as $v){
		    $shijian = explode('/',$v[2]);
		    $arrdata[] = array(
		        //'newusername' => iconv("GBK","UTF-8",$v[0]),
		        'newusername' => $v[0],
		        'gender' => $v[1],
		        'birthyear' => $shijian[0],
		        'birthmonth' => $shijian[1],
		        'birthday' => $shijian[2],
		        //'newsubordinate' => iconv("GB2312","UTF-8",$v[3]),
		        'newsubordinate' => $v[3],
		        'newgroupid' => $v[4],
		    ); 
		} 
		// print_r($arrdata);
		// exit();
		$l='C:\wamp\www\ds\textex.txt';
        foreach($arrdata as $k=>$val){
        $lang = file_get_contents($l);
        if ($lang >= $k) {
        	continue;
        }
        //print_r($k);
		$newusername = $val['newusername'];
		$newpassword = '123456';
		$gender      = $val['gender'];
		$birthyear   = $val['birthyear'];
		$birthmonth  = $val['birthmonth'];
		$birthday    = $val['birthday'];
		$newsubordinate = $val['newsubordinate'];
		$_GET['newgroupid'] = $val['newgroupid'];
		//$newemail = strtolower(trim($_GET['newemail']));

		if(!$newusername || !isset($_GET['confirmed']) && !$newpassword || !$birthyear || !$birthmonth || !$birthday || !$newsubordinate) {
			cpmsg('members_add_invalid', '', 'error');
		}
		if(C::t('common_member')->fetch_uid_by_username($newusername) || C::t('common_member_archive')->fetch_uid_by_username($newusername)) {
			cpmsg('members_add_username_duplicate', '', 'error');
		}
		
        // if(!eregi("[^\x80-\xff]","$newusername")){
		//   if(strlen($newusername)>6 && strlen($newusername)>12){
        //     cpmsg('members_add_username_duplicate_xm_ex', '', 'error');
		//   }
		// }else{
		// 	   cpmsg('members_add_username_duplicate_xm', '', 'error');
		// }
		loaducenter();
        //$newemail = "1111@qq.com";
		$uid = uc_user_register(addslashes($newusername), $newpassword, $newemail);
		if($uid <= 0) {
			if($uid == -1) {
				cpmsg('members_add_illegal', '', 'error');
			} elseif($uid == -2) {
				cpmsg('members_username_protect', '', 'error');
			} elseif($uid == -3) {
				if(empty($_GET['confirmed'])) {
					cpmsg('members_add_username_activation', 'action=members&operation=add&addsubmit=yes&newgroupid='.$_GET['newgroupid'].'&newusername='.rawurlencode($newusername), 'form');
				} else {
					list($uid,, $newemail) = uc_get_user(addslashes($newusername));
				}
			} elseif($uid == -4) {
				cpmsg('members_email_illegal', '', 'error');
			} elseif($uid == -5) {
				cpmsg('members_email_domain_illegal', '', 'error');
			} elseif($uid == -6) {
				cpmsg('members_email_duplicate', '', 'error');
			}
		}

		$group = C::t('common_usergroup')->fetch($_GET['newgroupid']);
		$newadminid = in_array($group['radminid'], array(1, 2, 3)) ? $group['radminid'] : ($group['type'] == 'special' ? -1 : 0);
		if($group['radminid'] == 1) {
			cpmsg('members_add_admin_none', '', 'error');
		}
		if(in_array($group['groupid'], array(5, 6, 7))) {
			cpmsg('members_add_ban_all_none', '', 'error');
		}

		$profile = $verifyarr = array();
		loadcache('fields_register');
		$init_arr = explode(',', $_G['setting']['initcredits']);
		$password = md5(random(10));
		C::t('common_member')->insert($uid, $newusername, $password, $newemail, 'Manual Acting', $_GET['newgroupid'], $init_arr, $newadminid);
		if($_GET['emailnotify']) {
			if(!function_exists('sendmail')) {
				include libfile('function/mail');
			}
			$add_member_subject = lang('email', 'add_member_subject');
			$add_member_message = lang('email', 'add_member_message', array(
				'newusername' => $newusername,
				'bbname' => $_G['setting']['bbname'],
				'adminusername' => $_G['member']['username'],
				'siteurl' => $_G['siteurl'],
				'newpassword' => $newpassword,
			));
			if(!sendmail("$newusername <$newemail>", $add_member_subject, $add_member_message)) {
				runlog('sendmail', "$newemail sendmail failed.");
			}
		}
        C::t('common_member_profile')->insert($uid, $newusername, $gender, $birthyear, $birthmonth, $birthday, $newsubordinate);
		updatecache('setting');
		//cpmsg('members_add_succeed', '', 'succeed', array('username' => $newusername, 'uid' => $uid));
		file_put_contents($l, $k);
        }
        echo "成功";
        exit();
	}

} elseif($operation == 'group') {
	$membermf = C::t('common_member_field_forum'.$tableext)->fetch($_GET['uid']);
	$membergroup = C::t('common_usergroup')->fetch($member['groupid']);
	$member = array_merge($member, (array)$membermf, $membergroup);

	if(!submitcheck('editsubmit')) {

		$checkadminid = array(($member['adminid'] >= 0 ? $member['adminid'] : 0) => 'checked');

		$member['groupterms'] = dunserialize($member['groupterms']);

		if($member['groupterms']['main']) {
			$expirydate = dgmdate($member['groupterms']['main']['time'], 'Y-n-j');
			$expirydays = ceil(($member['groupterms']['main']['time'] - TIMESTAMP) / 86400);
			$selecteaid = array($member['groupterms']['main']['adminid'] => 'selected');
			$selectegid = array($member['groupterms']['main']['groupid'] => 'selected');
		} else {
			$expirydate = $expirydays = '';
			$selecteaid = array($member['adminid'] => 'selected');
			$selectegid = array(($member['type'] == 'member' ? 0 : $member['groupid']) => 'selected');
		}

		$extgroups = $expgroups = '';
		$radmingids = 0;
		$extgrouparray = explode("\t", $member['extgroupids']);
		$groups = array('system' => '', 'special' => '', 'member' => '');
		$group = array('groupid' => 0, 'radminid' => 0, 'type' => '', 'grouptitle' => $lang['usergroups_system_0'], 'creditshigher' => 0, 'creditslower' => '0');
		$query = array_merge(array($group), (array)C::t('common_usergroup')->fetch_all_not(array(6, 7)));
		foreach($query as $group) {
			if($group['groupid'] && !in_array($group['groupid'], array(4, 5, 6, 7, 8)) && ($group['type'] == 'system' || $group['type'] == 'special')) {
				$extgroups .= showtablerow('', array('class="td27"', 'style="width:70%"'), array(
					'<input class="checkbox" type="checkbox" name="extgroupidsnew[]" value="'.$group['groupid'].'" '.(in_array($group['groupid'], $extgrouparray) ? 'checked' : '').' id="extgid_'.$group['groupid'].'" /><label for="extgid_'.$group['groupid'].'"> '.$group['grouptitle'].'</label>',
					'<input type="text" class="txt" size="9" name="extgroupexpirynew['.$group['groupid'].']" value="'.(in_array($group['groupid'], $extgrouparray) && !empty($member['groupterms']['ext'][$group['groupid']]) ? dgmdate($member['groupterms']['ext'][$group['groupid']], 'Y-n-j') : '').'" onclick="showcalendar(event, this)" />'
				), TRUE);
			}
			if($group['groupid'] && $group['type'] == 'member' && !($member['credits'] >= $group['creditshigher'] && $member['credits'] < $group['creditslower']) && $member['groupid'] != $group['groupid']) {
				continue;
			}

			$expgroups .= '<option name="expgroupidnew" value="'.$group['groupid'].'" '.$selectegid[$group['groupid']].'>'.$group['grouptitle'].'</option>';

			if($group['groupid'] != 0) {
				$group['type'] = $group['type'] == 'special' && $group['radminid'] ? 'specialadmin' : $group['type'];
				$groups[$group['type']] .= '<option value="'.$group['groupid'].'"'.($member['groupid'] == $group['groupid'] ? 'selected="selected"' : '').' gtype="'.$group['type'].'">'.$group['grouptitle'].'</option>';
				if($group['type'] == 'special' && !$group['radminid']) {
					$radmingids .= ','.$group['groupid'];
				}
			}

		}

		if(!$groups['member']) {
			$group = C::t('common_usergroup')->fetch_new_groupid(true);
			$groups['member'] = '<option value="'.$group['groupid'].'" gtype="member">'.$group['grouptitle'].'</option>';
		}

		shownav('user', 'members_group');
		showsubmenu('members_group_member', array(), '', array('username' => $member['username']));
		echo '<script src="static/js/calendar.js" type="text/javascript"></script>';
		showformheader("members&operation=group&uid=$member[uid]");
		showtableheader('usergroup', 'nobottom');
		showsetting('members_group_group', '', '', '<select name="groupidnew" onchange="if(in_array(this.value, ['.$radmingids.'])) {$(\'relatedadminid\').style.display = \'\';$(\'adminidnew\').name=\'adminidnew[\' + this.value + \']\';} else {$(\'relatedadminid\').style.display = \'none\';$(\'adminidnew\').name=\'adminidnew[0]\';}"><optgroup label="'.$lang['usergroups_system'].'">'.$groups['system'].'<optgroup label="'.$lang['usergroups_special'].'">'.$groups['special'].'<optgroup label="'.$lang['usergroups_specialadmin'].'">'.$groups['specialadmin'].'<optgroup label="'.$lang['usergroups_member'].'">'.$groups['member'].'</select>');
		showtagheader('tbody', 'relatedadminid', $member['type'] == 'special' && !$member['radminid'], 'sub');
		showsetting('members_group_related_adminid', '', '', '<select id="adminidnew" name="adminidnew['.$member['groupid'].']"><option value="0"'.($member['adminid'] == 0 ? ' selected' : '').'>'.$lang['none'].'</option><option value="3"'.($member['adminid'] == 3 ? ' selected' : '').'>'.$lang['usergroups_system_3'].'</option><option value="2"'.($member['adminid'] == 2 ? ' selected' : '').'>'.$lang['usergroups_system_2'].'</option><option value="1"'.($member['adminid'] == 1 ? ' selected' : '').'>'.$lang['usergroups_system_1'].'</option></select>');
		showtagfooter('tbody');
		showsetting('members_group_validity', 'expirydatenew', $expirydate, 'calendar');
		showsetting('members_group_orig_adminid', '', '', '<select name="expgroupidnew">'.$expgroups.'</select>');
		showsetting('members_group_orig_groupid', '', '', '<select name="expadminidnew"><option value="0" '.$selecteaid[0].'>'.$lang['usergroups_system_0'].'</option><option value="1" '.$selecteaid[1].'>'.$lang['usergroups_system_1'].'</option><option value="2" '.$selecteaid[2].'>'.$lang['usergroups_system_2'].'</option><option value="3" '.$selecteaid[3].'>'.$lang['usergroups_system_3'].'</option></select>');
		showtablefooter();

		showtableheader('members_group_extended', 'noborder fixpadding');
		showsubtitle(array('usergroup', 'validity'));
		echo $extgroups;
		showtablerow('', 'colspan="2"', cplang('members_group_extended_comment'));
		showtablefooter();

		showtableheader('members_edit_reason', 'notop');
		showsetting('members_group_ban_reason', 'reason', '', 'textarea');
		showsubmit('editsubmit');
		showtablefooter();

		showformfooter();

	} else {

		$group = C::t('common_usergroup')->fetch($_GET['groupidnew']);
		if(!$group) {
			cpmsg('undefined_action', '', 'error');
		}

		if(strlen(is_array($_GET['extgroupidsnew']) ? implode("\t", $_GET['extgroupidsnew']) : '') > 30) {
			cpmsg('members_edit_groups_toomany', '', 'error');
		}

		if($member['groupid'] != $_GET['groupidnew'] && isfounder($member)) {
			cpmsg('members_edit_groups_isfounder', '', 'error');
		}

		$_GET['adminidnew'] = $_GET['adminidnew'][$_GET['groupidnew']];
		switch($group['type']) {
			case 'member':
				$_GET['groupidnew'] = in_array($_GET['adminidnew'], array(1, 2, 3)) ? $_GET['adminidnew'] : $_GET['groupidnew'];
				break;
			case 'special':
				if($group['radminid']) {
					$_GET['adminidnew'] = $group['radminid'];
				} elseif(!in_array($_GET['adminidnew'], array(1, 2, 3))) {
					$_GET['adminidnew'] = -1;
				}
				break;
			case 'system':
				$_GET['adminidnew'] = in_array($_GET['groupidnew'], array(1, 2, 3)) ? $_GET['groupidnew'] : -1;
				break;
		}

		$groupterms = array();

		if($_GET['expirydatenew']) {

			$maingroupexpirynew = strtotime($_GET['expirydatenew']);

			$group = C::t('common_usergroup')->fetch($_GET['expgroupidnew']);
			if(!$group) {
				$_GET['expgroupidnew'] = in_array($_GET['expadminidnew'], array(1, 2, 3)) ? $_GET['expadminidnew'] : $_GET['expgroupidnew'];
			} else {
				switch($group['type']) {
					case 'special':
						if($group['radminid']) {
							$_GET['expadminidnew'] = $group['radminid'];
						} elseif(!in_array($_GET['expadminidnew'], array(1, 2, 3))) {
							$_GET['expadminidnew'] = -1;
						}
						break;
					case 'system':
						$_GET['expadminidnew'] = in_array($_GET['expgroupidnew'], array(1, 2, 3)) ? $_GET['expgroupidnew'] : -1;
						break;
				}
			}

			if($_GET['expgroupidnew'] == $_GET['groupidnew']) {
				cpmsg('members_edit_groups_illegal', '', 'error');
			} elseif($maingroupexpirynew > TIMESTAMP) {
				if($_GET['expgroupidnew'] || $_GET['expadminidnew']) {
					$groupterms['main'] = array('time' => $maingroupexpirynew, 'adminid' => $_GET['expadminidnew'], 'groupid' => $_GET['expgroupidnew']);
				} else {
					$groupterms['main'] = array('time' => $maingroupexpirynew);
				}
				$groupterms['ext'][$_GET['groupidnew']] = $maingroupexpirynew;
			}

		}

		if(is_array($_GET['extgroupexpirynew'])) {
			foreach($_GET['extgroupexpirynew'] as $extgroupid => $expiry) {
				if(is_array($_GET['extgroupidsnew']) && in_array($extgroupid, $_GET['extgroupidsnew']) && !isset($groupterms['ext'][$extgroupid]) && $expiry && ($expiry = strtotime($expiry)) > TIMESTAMP) {
					$groupterms['ext'][$extgroupid] = $expiry;
				}
			}
		}

		$grouptermsnew = serialize($groupterms);
		$groupexpirynew = groupexpiry($groupterms);
		$extgroupidsnew = $_GET['extgroupidsnew'] && is_array($_GET['extgroupidsnew']) ? implode("\t", $_GET['extgroupidsnew']) : '';

		C::t('common_member'.$tableext)->update($member['uid'], array('groupid'=>$_GET['groupidnew'], 'adminid'=>$_GET['adminidnew'], 'extgroupids'=>$extgroupidsnew, 'groupexpiry'=>$groupexpirynew));
		if(C::t('common_member_field_forum'.$tableext)->fetch($member['uid'])) {
			C::t('common_member_field_forum'.$tableext)->update($member['uid'], array('groupterms' => $grouptermsnew));
		} else {
			C::t('common_member_field_forum'.$tableext)->insert(array('uid' => $member['uid'], 'groupterms' => $grouptermsnew));
		}

		if($_GET['groupidnew'] != $member['groupid'] && (in_array($_GET['groupidnew'], array(4, 5)) || in_array($member['groupid'], array(4, 5)))) {
			$my_opt = in_array($_GET['groupidnew'], array(4, 5)) ? 'banuser' : 'unbanuser';
			$log_handler = Cloud::loadClass('Cloud_Service_SearchHelper');
			$log_handler->myThreadLog($my_opt, array('uid' => $member['uid']));
			banlog($member['username'], $member['groupid'], $_GET['groupidnew'], $groupexpirynew, $_GET['reason']);
		}

		cpmsg('members_edit_groups_succeed', "action=members&operation=group&uid=$member[uid]", 'succeed');

	}

} elseif($operation == 'credit' && $_G['setting']['extcredits']) {

	if($tableext) {
		cpmsg('members_edit_credits_failure', '', 'error');
	}
	$membercount = C::t('common_member_count'.$tableext)->fetch($member['uid']);
	$membergroup = C::t('common_usergroup')->fetch($member['groupid']);
	$member = array_merge($member, $membercount, $membergroup);

	if(!submitcheck('creditsubmit')) {

		eval("\$membercredit = @round({$_G[setting][creditsformula]});");

		if(($jscreditsformula = C::t('common_setting')->fetch('creditsformula'))) {
			$jscreditsformula = str_replace(array('digestposts', 'posts', 'threads'), array($member['digestposts'], $member['posts'],$member['threads']), $jscreditsformula);
		}

		$creditscols = array('members_credit_ranges', 'credits');
		$creditsvalue = array($member['type'] == 'member' ? "$member[creditshigher]~$member[creditslower]" : 'N/A', '<input type="text" class="txt" name="jscredits" id="jscredits" value="'.$membercredit.'" size="6" disabled style="padding:0;width:6em;border:none; background-color:transparent">');
		for($i = 1; $i <= 8; $i++) {
			$jscreditsformula = str_replace('extcredits'.$i, "extcredits[$i]", $jscreditsformula);
			$creditscols[] = isset($_G['setting']['extcredits'][$i]) ? $_G['setting']['extcredits'][$i]['title'] : 'extcredits'.$i;
			$creditsvalue[] = isset($_G['setting']['extcredits'][$i]) ? '<input type="text" class="txt" size="3" name="extcreditsnew['.$i.']" id="extcreditsnew['.$i.']" value="'.$member['extcredits'.$i].'" onkeyup="membercredits()"> '.$_G['setting']['extcredits']['$i']['unit'] : '<input type="text" class="txt" size="3" value="N/A" disabled>';
		}

		echo <<<EOT
<script language="JavaScript">
	var extcredits = new Array();
	function membercredits() {
		var credits = 0;
		for(var i = 1; i <= 8; i++) {
			e = $('extcreditsnew['+i+']');
			if(e && parseInt(e.value)) {
				extcredits[i] = parseInt(e.value);
			} else {
				extcredits[i] = 0;
			}
		}
		$('jscredits').value = Math.round($jscreditsformula);
	}
</script>
EOT;
		shownav('user', 'members_credit');
		showsubmenu('members_credit');
		showtips('members_credit_tips');
		showformheader("members&operation=credit&uid={$_GET['uid']}");
		showtableheader('<em class="right"><a href="'.ADMINSCRIPT.'?action=logs&operation=credit&srch_uid='.$_GET['uid'].'&frame=yes" target="_blank">'.cplang('members_credit_logs').'</a></em>'.cplang('members_credit').' - '.$member['username'].'('.$member['grouptitle'].')', 'nobottom');
		showsubtitle($creditscols);
		showtablerow('', array('', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"', 'class="td28"'), $creditsvalue);
		showtablefooter();
		showtableheader('', 'notop');
		showtitle('members_edit_reason');
		showsetting('members_credit_reason', 'reason', '', 'textarea');
		showsubmit('creditsubmit');
		showtablefooter();
		showformfooter();

	} else {

		$diffarray = array();
		$sql = $comma = '';
		if(is_array($_GET['extcreditsnew'])) {
			foreach($_GET['extcreditsnew'] as $id => $value) {
				if($member['extcredits'.$id] != ($value = intval($value))) {
					$diffarray[$id] = $value - $member['extcredits'.$id];
					$sql .= $comma."extcredits$id='$value'";
					$comma = ', ';
				}
			}
		}

		if($diffarray) {
			foreach($diffarray as $id => $diff) {
				$logs[] = dhtmlspecialchars("$_G[timestamp]\t{$_G[member][username]}\t$_G[adminid]\t$member[username]\t$id\t$diff\t0\t\t{$_GET['reason']}");
			}
			updatemembercount($_GET['uid'], $diffarray);
			writelog('ratelog', $logs);
		}

		cpmsg('members_edit_credits_succeed', "action=members&operation=credit&uid={$_GET['uid']}", 'succeed');

	}

} elseif($operation == 'medal') {

	$membermf = C::t('common_member_field_forum'.$tableext)->fetch($_GET['uid']);
	$member = array_merge($member, $membermf);

	if(!submitcheck('medalsubmit')) {

		$medals = '';
		$membermedals = array();
		loadcache('medals');
		foreach (explode("\t", $member['medals']) as $key => $membermedal) {
			list($medalid, $medalexpiration) = explode("|", $membermedal);
			if(isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
				$membermedals[$key] = $medalid;
			} else {
				unset($membermedals[$key]);
			}
		}

		foreach(C::t('forum_medal')->fetch_all_data(1) as $medal) {
			$medals .= showtablerow('', array('class="td25"', 'class="td23"'), array(
				"<input class=\"checkbox\" type=\"checkbox\" name=\"medals[$medal[medalid]]\" value=\"1\" ".(in_array($medal['medalid'], $membermedals) ? 'checked' : '')." />",
				"<img src=\"static/image/common/$medal[image]\" />",
				$medal['name']

			), TRUE);
		}

		if(!$medals) {
			cpmsg('members_edit_medals_nonexistence', '', 'error');
		}

		shownav('user', 'nav_members_confermedal');
		showsubmenu('nav_members_confermedal');
		showformheader("members&operation=medal&uid={$_GET['uid']}");
		showtableheader("$lang[members_confermedal_to] <a href='home.php?mod=space&uid={$_GET['uid']}' target='_blank'>$member[username]</a>", 'fixpadding');
		showsubtitle(array('medals_grant', 'medals_image', 'name'));
		echo $medals;
		showsubmit('medalsubmit');
		showtablefooter();
		showformfooter();

	} else {

		$medalsdel = $medalsadd = $medalsnew = $origmedalsarray = $medalsarray = array();
		if(is_array($_GET['medals'])) {
			foreach($_GET['medals'] as $medalid => $newgranted) {
				if($newgranted) {
					$medalsarray[] = $medalid;
				}
			}
		}
		loadcache('medals');
		foreach($member['medals'] = explode("\t", $member['medals']) as $key => $modmedalid) {
			list($medalid, $medalexpiration) = explode("|", $modmedalid);
			if(isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
				$origmedalsarray[] = $medalid;
			}
		}
		foreach(array_unique(array_merge($origmedalsarray, $medalsarray)) as $medalid) {
			if($medalid) {
				$orig = in_array($medalid, $origmedalsarray);
				$new = in_array($medalid, $medalsarray);
				if($orig != $new) {
					if($orig && !$new) {
						$medalsdel[] = $medalid;
					} elseif(!$orig && $new) {
						$medalsadd[] = $medalid;
					}
				}
			}
		}
		if(!empty($medalsarray)) {
			foreach(C::t('forum_medal')->fetch_all_by_id($medalsarray) as $modmedal) {
				if(empty($modmedal['expiration'])) {
					$medalsnew[] = $modmedal[medalid];
					$medalstatus = 0;
				} else {
					$modmedal['expiration'] = TIMESTAMP + $modmedal['expiration'] * 86400;
					$medalsnew[] = $modmedal[medalid].'|'.$modmedal['expiration'];
					$medalstatus = 1;
				}
				if(in_array($modmedal['medalid'], $medalsadd)) {
					$data = array(
						'uid' => $_GET['uid'],
						'medalid' => $modmedal['medalid'],
						'type' => 0,
						'dateline' => $_G['timestamp'],
						'expiration' => $modmedal['expiration'],
						'status' => $medalstatus,
					);
					C::t('forum_medallog')->insert($data);
					C::t('common_member_medal')->insert(array('uid' => $_GET['uid'], 'medalid' => $modmedal['medalid']), 0, 1);
				}
			}
		}
		if(!empty($medalsdel)) {
			C::t('forum_medallog')->update_type_by_uid_medalid(4, $_GET['uid'], $medalsdel);
			C::t('common_member_medal')->delete_by_uid_medalid($_GET['uid'], $medalsdel);
		}
		$medalsnew = implode("\t", $medalsnew);

		C::t('common_member_field_forum'.$tableext)->update($_GET['uid'], array('medals' => $medalsnew));

		cpmsg('members_edit_medals_succeed', "action=members&operation=medal&uid={$_GET['uid']}", 'succeed');

	}

} elseif($operation == 'ban') {

	$membermf = C::t('common_member_field_forum'.$tableext)->fetch($_GET['uid']);
	$membergroup = C::t('common_usergroup')->fetch($member['groupid']);
	$membergroupfield = C::t('common_usergroup_field')->fetch($member['groupid']);
	$member = array_merge($member, $membermf, $membergroup, $membergroupfield);

	if(($member['type'] == 'system' && in_array($member['groupid'], array(1, 2, 3, 6, 7, 8))) || $member['type'] == 'special') {
		cpmsg('members_edit_illegal', '', 'error', array('grouptitle' => $member['grouptitle'], 'uid' => $member['uid']));
	}

	if($member['allowadmincp']) {
		cpmsg('members_edit_illegal_portal', '', 'error',array('uid' => $member['uid']));
	}

	$member['groupterms'] = dunserialize($member['groupterms']);
	$member['banexpiry'] = !empty($member['groupterms']['main']['time']) && ($member['groupid'] == 4 || $member['groupid'] == 5) ? dgmdate($member['groupterms']['main']['time'], 'Y-n-j') : '';

	if(!submitcheck('bansubmit')) {

		echo '<script src="static/js/calendar.js" type="text/javascript"></script>';
		shownav('user', 'members_ban_user');
		showsubmenu($lang['members_ban_user'].($member['username'] ? ' - '.$member['username'] : ''));
		showtips('members_ban_tips');
		showformheader('members&operation=ban');
		showtableheader();
		showsetting('members_ban_username', 'username', $member['username'], 'text', null, null, '<input type="button" id="crimebtn" class="btn" style="margin-top:-1px;display:none;" onclick="getcrimerecord();" value="'.$lang['crime_checkrecord'].'" />', 'onkeyup="showcrimebtn(this);" id="banusername"');
		if($member) {

			showtagheader('tbody', 'member_status', 1);
			showtablerow('', 'class="td27" colspan="2"', cplang('members_edit_current_status').'<span class="normal">: '.($member['groupid'] == 4 ? $lang['members_ban_post'] : ($member['groupid'] == 5 ? $lang['members_ban_visit'] : ($member['status'] == -1 ? $lang['members_ban_status'] : $lang['members_ban_none']))).'</span>');

			include_once libfile('function/member');
			$clist = crime('getactionlist', $member['uid']);

			if($clist) {
				echo '<tr><td class="td27" colspan="2">'.$lang[members_ban_crime_record].':</td></tr>';
				echo '<tr><td colspan="2" style="padding:0 !important;border-top:none;"><table style="width:100%;">';
				showtablerow('class="partition"', array('width="15%"', 'width="10%"', 'width="20%"', '', 'width="15%"'), array($lang['crime_user'], $lang['crime_action'], $lang['crime_dateline'], $lang['crime_reason'], $lang['crime_operator']));
				foreach($clist as $crime) {
					showtablerow('', '', array('<a href="home.php?mod=space&uid='.$member['uid'].'">'.$member['username'], $lang[$crime['action']], date('Y-m-d H:i:s', $crime['dateline']), $crime['reason'], '<a href="home.php?mod=space&uid='.$crime['operatorid'].'" target="_blank">'.$crime['operator'].'</a>'));
				}
				echo '</table></td></tr>';
			}
			showtagfooter('tbody');
		}
		showsetting('members_ban_type', array('bannew', array(
			array('', $lang['members_ban_none'], array('validity' => 'none')),
			array('post', $lang['members_ban_post'], array('validity' => '')),
			array('visit', $lang['members_ban_visit'], array('validity' => '')),
			array('status', $lang['members_ban_status'], array('validity' => 'none'))
		)), '', 'mradio');
		showtagheader('tbody', 'validity', false, 'sub');
		showsetting('members_ban_validity', '', '', selectday('banexpirynew', array(0, 1, 3, 5, 7, 14, 30, 60, 90, 180, 365)));
		showtagfooter('tbody');
		print <<<EOF
			<tr>
				<td class="td27" colspan="2">$lang[members_ban_clear_content]:</td>
			</tr>
			<tr>
				<td colspan="2">
					<ul class="dblist" onmouseover="altStyle(this);">
						<li style="width: 100%;"><input type="checkbox" name="chkall" onclick="checkAll('prefix', this.form, 'clear')" class="checkbox">&nbsp;$lang[select_all]</li>
						<li style="width: 8%;"><input type="checkbox" value="post" name="clear[post]" class="checkbox">&nbsp;$lang[members_ban_delpost]</li>
						<li style="width: 8%;"><input type="checkbox" value="follow" name="clear[follow]" class="checkbox">&nbsp;$lang[members_ban_delfollow]</li>
						<li style="width: 8%;"><input type="checkbox" value="postcomment" name="clear[postcomment]" class="checkbox">&nbsp;$lang[members_ban_postcomment]</li>
						<li style="width: 8%;"><input type="checkbox" value="doing" name="clear[doing]" class="checkbox">&nbsp;$lang[members_ban_deldoing]</li>
						<li style="width: 8%;"><input type="checkbox" value="blog" name="clear[blog]" class="checkbox">&nbsp;$lang[members_ban_delblog]</li>
						<li style="width: 8%;"><input type="checkbox" value="album" name="clear[album]" class="checkbox">&nbsp;$lang[members_ban_delalbum]</li>
						<li style="width: 8%;"><input type="checkbox" value="share" name="clear[share]" class="checkbox">&nbsp;$lang[members_ban_delshare]</li>
						<li style="width: 8%;"><input type="checkbox" value="avatar" name="clear[avatar]" class="checkbox">&nbsp;$lang[members_ban_delavatar]</li>
						<li style="width: 8%;"><input type="checkbox" value="comment" name="clear[comment]" class="checkbox">&nbsp;$lang[members_ban_delcomment]</li>
					</ul>
				</td>
			</tr>
EOF;

		showsetting('members_ban_reason', 'reason', '', 'textarea');
		showsubmit('bansubmit');
		showtablefooter();
		showformfooter();
		$basescript = ADMINSCRIPT;
		print <<<EOF
			<script type="text/javascript">
				var oldbanusername = '$member[username]';
				function showcrimebtn(obj) {
					if(oldbanusername == obj.value) {
						return;
					}
					oldbanusername = obj.value;
					$('crimebtn').style.display = '';
					if($('member_status')) {
						$('member_status').style.display = 'none';
					}
				}
				function getcrimerecord() {
					if($('banusername').value) {
						window.location.href = '$basescript?action=members&operation=ban&username=' + $('banusername').value;
					}
				}
			</script>
EOF;

	} else {

		if(empty($member)) {
			cpmsg('members_edit_nonexistence');
		}

		$setarr = array();
		$reason = trim($_GET['reason']);
		if(!$reason && ($_G['group']['reasonpm'] == 1 || $_G['group']['reasonpm'] == 3)) {
			cpmsg('members_edit_reason_invalid', '', 'error');
		}
		$my_data = array();
		$mylogtype = '';
		if(in_array($_GET['bannew'], array('post', 'visit', 'status'))) {
			$my_data = array('uid' => $member['uid']);
			if($_GET['delpost']) {
				$my_data['otherid'] = 1;
			}
			$mylogtype = 'banuser';
		} elseif($member['groupid'] == 4 || $member['groupid'] == 5 || $member['status'] == '-1') {
			$my_data = array('uid' => $member['uid']);
			$mylogtype = 'unbanuser';
		}
		if($_GET['bannew'] == 'post' || $_GET['bannew'] == 'visit') {
			$groupidnew = $_GET['bannew'] == 'post' ? 4 : 5;
			$_GET['banexpirynew'] = !empty($_GET['banexpirynew']) ? TIMESTAMP + $_GET['banexpirynew'] * 86400 : 0;
			$_GET['banexpirynew'] = $_GET['banexpirynew'] > TIMESTAMP ? $_GET['banexpirynew'] : 0;
			if($_GET['banexpirynew']) {
				$member['groupterms']['main'] = array('time' => $_GET['banexpirynew'], 'adminid' => $member['adminid'], 'groupid' => $member['groupid']);
				$member['groupterms']['ext'][$groupidnew] = $_GET['banexpirynew'];
				$setarr['groupexpiry'] = groupexpiry($member['groupterms']);
			} else {
				$setarr['groupexpiry'] = 0;
			}
			$adminidnew = -1;
			$my_data['expiry'] = groupexpiry($member['groupterms']);
			$postcomment_cache_pid = array();
			foreach(C::t('forum_postcomment')->fetch_all_by_authorid($member['uid']) as $postcomment) {
				$postcomment_cache_pid[$postcomment['pid']] = $postcomment['pid'];
			}
			C::t('forum_postcomment')->delete_by_authorid($member['uid'], false, true);
			if($postcomment_cache_pid) {
				C::t('forum_postcache')->delete($postcomment_cache_pid);
			}
			if(!$member['adminid']) {
				$member_status = C::t('common_member_status')->fetch($member['uid']);
				if($member_status) {
					captcha::report($member_status['lastip']);
				}
			}
		} elseif($member['groupid'] == 4 || $member['groupid'] == 5) {
			if(!empty($member['groupterms']['main']['groupid'])) {
				$groupidnew = $member['groupterms']['main']['groupid'];
				$adminidnew = $member['groupterms']['main']['adminid'];
				unset($member['groupterms']['main']);
				unset($member['groupterms']['ext'][$member['groupid']]);
				$setarr['groupexpiry'] = groupexpiry($member['groupterms']);
			}
			$groupnew = C::t('common_usergroup')->fetch_by_credits($member['credits']);
			$groupidnew = $groupnew['groupid'];
			$adminidnew = 0;
		} else {
			$update = false;
			$groupidnew = $member['groupid'];
			$adminidnew = $member['adminid'];
			if(in_array('avatar', $_GET['clear'])) {
				$setarr['avatarstatus'] = 0;
				loaducenter();
				uc_user_deleteavatar($member['uid']);
			}
		}
		if(!empty($my_data) && !empty($mylogtype)) {
			$log_handler = Cloud::loadClass('Cloud_Service_SearchHelper');
			$log_handler->myThreadLog($mylogtype, $my_data);
		}


		$setarr['adminid'] = $adminidnew;
		$setarr['groupid'] = $groupidnew;
		$setarr['status'] = $_GET['bannew'] == 'status' ? -1 : 0;
		C::t('common_member'.$tableext)->update($member['uid'], $setarr);

		if($_G['group']['allowbanuser'] && (DB::affected_rows())) {
			banlog($member['username'], $member['groupid'], $groupidnew, $_GET['banexpirynew'], $reason, $_GET['bannew'] == 'status' ? -1 : 0);
		}

		C::t('common_member_field_forum'.$tableext)->update($member['uid'],array('groupterms' => ($member['groupterms'] ? serialize($member['groupterms']) : '')));

		$crimeaction = $noticekey = '';
		include_once libfile('function/member');
		if($_GET['bannew'] == 'post') {
			$crimeaction = 'crime_banspeak';
			$noticekey = 'member_ban_speak';
			$from_idtype = 'banspeak';
		} elseif($_GET['bannew'] == 'visit') {
			$crimeaction = 'crime_banvisit';
			$noticekey = 'member_ban_visit';
			$from_idtype = 'banvisit';
		} elseif($_GET['bannew'] == 'status') {
			$crimeaction = 'crime_banstatus';
			$noticekey = 'member_ban_status';
			$from_idtype = 'banstatus';
		}
		if($crimeaction) {
			crime('recordaction', $member['uid'], $crimeaction, lang('forum/misc', 'crime_reason', array('reason' => $reason)));
		}
		if($noticekey) {
			$notearr = array(
				'user' => "<a href=\"home.php?mod=space&uid=$_G[uid]\">$_G[username]</a>",
				'day' => intval($_POST['banexpirynew']),
				'reason' => $reason,
				'from_id' => 0,
				'from_idtype' => $from_idtype
			);
			notification_add($member['uid'], 'system', $noticekey, $notearr, 1);
		}

		if($_G['adminid'] == 1 && !empty($_GET['clear']) && is_array($_GET['clear'])) {
			require_once libfile('function/delete');
			$membercount = array();
			if(in_array('post', $_GET['clear'])) {
				if($member['uid']) {
					require_once libfile('function/post');

					$tidsdelete = array();
					loadcache('posttableids');
					$posttables = empty($_G['cache']['posttableids']) ? array(0) : $_G['cache']['posttableids'];
					foreach($posttables as $posttableid) {
						$pidsthread = $pidsdelete = array();
						$postlist = C::t('forum_post')->fetch_all_by_authorid($posttableid, $member['uid'], false);
						if($postlist) {
							foreach($postlist as $post) {
								$prune['forums'][] = $post['fid'];
								$prune['thread'][$post['tid']]++;
								if($post['first']) {
									$tidsdelete[] = $post['tid'];
								}
								$pidsdelete[] = $post['pid'];
								$pidsthread[$post['pid']] = $post['tid'];
							}
							foreach($pidsdelete as $key=>$pid) {
								if(in_array($pidsthread[$pid], $tidsdelete)) {
									unset($pidsdelete[$key]);
									unset($prune['thread'][$pidsthread[$pid]]);
									updatemodlog($pidsthread[$pid], 'DEL');
								} else {
									updatemodlog($pidsthread[$pid], 'DLP');
								}
							}
						}
						deletepost($pidsdelete, 'pid', false, $posttableid, true);
					}
					unset($postlist);
					if($tidsdelete) {
						deletethread($tidsdelete, true, true, true);
					}
					if(!empty($prune)) {
						foreach($prune['thread'] as $tid => $decrease) {
							updatethreadcount($tid);
						}
						foreach(array_unique($prune['forums']) as $fid) {
						}
					}

					if($_G['setting']['globalstick']) {
						updatecache('globalstick');
					}
				}
				$membercount['posts'] = 0;
				$membercount['threads'] = 0;
			}
			if(in_array('follow', $_GET['clear'])) {
				C::t('home_follow_feed')->delete_by_uid($member['uid']);
				$membercount['feeds'] = 0;
			}
			if(in_array('blog', $_GET['clear'])) {
				$blogids = array();
				$query = C::t('home_blog')->fetch_blogid_by_uid($member['uid']);
				foreach($query as $value) {
					$blogids[] = $value['blogid'];
				}
				if(!empty($blogids)) {
					C::t('common_moderate')->delete($blogids, 'blogid');
				}
				C::t('home_blog')->delete_by_uid($member['uid']);
				C::t('home_blogfield')->delete_by_uid($member['uid']);
				C::t('home_feed')->delete_by_uid_idtype($member['uid'], 'blogid');

				$membercount['blogs'] = 0;
			}
			if(in_array('album', $_GET['clear'])) {
				C::t('home_album')->delete_by_uid($member['uid']);
				$picids = array();
				$query = C::t('home_pic')->fetch_all_by_uid($member['uid']);
				foreach($query as $value) {
					$picids[] = $value['picid'];
					deletepicfiles($value);
				}
				if(!empty($picids)) {
					C::t('common_moderate')->delete($picids, 'picid');
				}
				C::t('home_pic')->delete_by_uid($member['uid']);
				C::t('home_feed')->delete_by_uid_idtype($member['uid'], 'albumid');

				$membercount['albums'] = 0;
			}
			if(in_array('share', $_GET['clear'])) {
				$shareids = array();
				foreach(C::t('home_share')->fetch_all_by_uid($member['uid']) as $value) {
					$shareids[] = $value['sid'];
				}
				if(!empty($shareids)) {
					C::t('common_moderate')->delete($shareids, 'sid');
				}
				C::t('home_share')->delete_by_uid($member['uid']);
				C::t('home_feed')->delete_by_uid_idtype($member['uid'], 'sid');

				$membercount['sharings'] = 0;
			}

			if(in_array('doing', $_GET['clear'])) {
				$doids = array();
				$query = C::t('home_doing')->fetch_all_by_uid_doid(array($member['uid']));
				foreach ($query as $value) {
					$doids[$value['doid']] = $value['doid'];
				}
				if(!empty($doids)) {
					C::t('common_moderate')->delete($doids, 'doid');
				}
				C::t('home_doing')->delete_by_uid($member['uid']);
				C::t('common_member_field_home')->update($member['uid'], array('recentnote' => '', 'spacenote' => ''));

				C::t('home_docomment')->delete_by_doid_uid(($doids ? $doids : null), $member['uid']);
				C::t('home_feed')->delete_by_uid_idtype($member['uid'], 'doid');

				$membercount['doings'] = 0;
			}
			if(in_array('comment', $_GET['clear'])) {
				$delcids = array();
				$query = C::t('home_comment')->fetch_all_by_uid($member['uid'], 0, 1);
				foreach($query as $value) {
					$key = $value['idtype'].'_cid';
					$delcids[$key] = $value['cid'];
				}
				if(!empty($delcids)) {
					foreach($delcids as $key => $ids) {
						C::t('common_moderate')->delete($ids, $key);
					}
				}
				C::t('home_comment')->delete_by_uid_idtype($member['uid']);
			}
			if(in_array('postcomment', $_GET['clear'])) {
				$postcomment_cache_pid = array();
				foreach(C::t('forum_postcomment')->fetch_all_by_authorid($member['uid']) as $postcomment) {
					$postcomment_cache_pid[$postcomment['pid']] = $postcomment['pid'];
				}
				C::t('forum_postcomment')->delete_by_authorid($member['uid']);
				if($postcomment_cache_pid) {
					C::t('forum_postcache')->delete($postcomment_cache_pid);
				}
			}

			if($membercount) {
				DB::update('common_member_count'.$tableext, $membercount, "uid='$member[uid]'");
			}

		}

		cpmsg('members_edit_succeed', 'action=members&operation=ban&uid='.$member['uid'], 'succeed');

	}

} elseif($operation == 'access') {

	require_once libfile('function/forumlist');
	$forumlist = '<SELECT name="addfid">'.forumselect(FALSE, 0, 0, TRUE).'</select>';

	loadcache('forums');

	if(!submitcheck('accesssubmit')) {

		shownav('user', 'members_access_edit');
		showsubmenu('members_access_edit');
		showtips('members_access_tips');
		showtableheader(cplang('members_access_now').' - '.$member['username'], 'nobottom fixpadding');
		showsubtitle(array('forum', 'members_access_view', 'members_access_post', 'members_access_reply', 'members_access_getattach', 'members_access_getimage', 'members_access_postattach', 'members_access_postimage', 'members_access_adminuser', 'members_access_dateline'));


		$accessmasks = C::t('forum_access')->fetch_all_by_uid($_GET['uid']);
		foreach ($accessmasks as $id => $access) {
			$adminuser = C::t('common_member'.$tableext)->fetch($access['adminuser']);
			$access['dateline'] = $access['dateline'] ? dgmdate($access['dateline']) : '';
			$forum = $_G['cache']['forums'][$id];
			showtablerow('', '', array(
					($forum['type'] == 'forum' ? '' : '|-----')."&nbsp;<a href=\"".ADMINSCRIPT."?action=forums&operation=edit&fid=$forum[fid]&anchor=perm\">$forum[name]</a>",
					accessimg($access['allowview']),
					accessimg($access['allowpost']),
					accessimg($access['allowreply']),
					accessimg($access['allowgetattach']),
					accessimg($access['allowgetimage']),
					accessimg($access['allowpostattach']),
					accessimg($access['allowpostimage']),
					$adminuser['username'],
					$access['dateline'],
			));
		}

		if(empty($accessmasks)) {
			showtablerow('', '', array(
					'-',
					'-',
					'-',
					'-',
					'-',
					'-',
					'-',
					'-',
					'-',
					'-',
			));
		}

		showtablefooter();
		showformheader("members&operation=access&uid={$_GET['uid']}");
		showtableheader(cplang('members_access_add'), 'notop fixpadding');
		showsetting('members_access_add_forum', '', '', $forumlist);
		foreach(array('view', 'post', 'reply', 'getattach', 'getimage', 'postattach', 'postimage') as $perm) {
			showsetting('members_access_add_'.$perm, array('allow'.$perm.'new', array(
				array(0, cplang('default')),
				array(1, cplang('members_access_allowed')),
				array(-1, cplang('members_access_disallowed')),
			), TRUE), 0, 'mradio');
		}
		showsubmit('accesssubmit', 'submit');
		showtablefooter();
		showformfooter();

	} else {

		$addfid = intval($_GET['addfid']);
		if($addfid && $_G['cache']['forums'][$addfid]) {
			$allowviewnew = !$_GET['allowviewnew'] ? 0 : ($_GET['allowviewnew'] > 0 ? 1 : -1);
			$allowpostnew = !$_GET['allowpostnew'] ? 0 : ($_GET['allowpostnew'] > 0 ? 1 : -1);
			$allowreplynew = !$_GET['allowreplynew'] ? 0 : ($_GET['allowreplynew'] > 0 ? 1 : -1);
			$allowgetattachnew = !$_GET['allowgetattachnew'] ? 0 : ($_GET['allowgetattachnew'] > 0 ? 1 : -1);
			$allowgetimagenew = !$_GET['allowgetimagenew'] ? 0 : ($_GET['allowgetimagenew'] > 0 ? 1 : -1);
			$allowpostattachnew = !$_GET['allowpostattachnew'] ? 0 : ($_GET['allowpostattachnew'] > 0 ? 1 : -1);
			$allowpostimagenew = !$_GET['allowpostimagenew'] ? 0 : ($_GET['allowpostimagenew'] > 0 ? 1 : -1);

			if($allowviewnew == -1) {
				$allowpostnew = $allowreplynew = $allowgetattachnew = $allowgetimagenew = $allowpostattachnew = $allowpostimagenew = -1;
			} elseif($allowpostnew == 1 || $allowreplynew == 1 || $allowgetattachnew == 1 || $allowgetimagenew == 1 || $allowpostattachnew == 1 || $allowpostimagenew == 1) {
				$allowviewnew = 1;
			}

			if(!$allowviewnew && !$allowpostnew && !$allowreplynew && !$allowgetattachnew && !$allowgetimagenew && !$allowpostattachnew && !$allowpostimagenew) {
				C::t('forum_access')->delete_by_fid($addfid, $_GET['uid']);
				if(!C::t('forum_access')->count_by_uid($_GET['uid'])) {
					C::t('common_member'.$tableext)->update($_GET['uid'], array('accessmasks'=>0));
				}
			} else {
				$data = array('uid' => $_GET['uid'], 'fid' => $addfid, 'allowview' => $allowviewnew, 'allowpost' => $allowpostnew, 'allowreply' => $allowreplynew, 'allowgetattach' => $allowgetattachnew, 'allowgetimage' => $allowgetimagenew, 'allowpostattach' => $allowpostattachnew, 'allowpostimage' => $allowpostimagenew, 'adminuser' => $_G['uid'], 'dateline' => $_G['timestamp']);
				C::t('forum_access')->insert($data, 0, 1);
				C::t('common_member'.$tableext)->update($_GET['uid'], array('accessmasks'=>1));
			}
			updatecache('forums');

		}
		cpmsg('members_access_succeed', 'action=members&operation=access&uid='.$_GET['uid'], 'succeed');

	}

} elseif($operation == 'edit') {

	$uid = $member['uid'];
	if(!empty($_G['setting']['connect']['allow']) && $do == 'bindlog') {
		$member = array_merge($member, C::t('#qqconnect#common_member_connect')->fetch($uid));
		showsubmenu("$lang[members_edit] - $member[username]", array(
			array('connect_member_info', 'members&operation=edit&uid='.$uid,  0),
			array('connect_member_bindlog', 'members&operation=edit&do=bindlog&uid='.$uid,  1),
		));
		if($member['conopenid']) {
			showtableheader();
			showtitle('connect_member_bindlog_uin');
			showsubtitle(array('connect_member_bindlog_username', 'connect_member_bindlog_date', 'connect_member_bindlog_type'));
			$bindlogs = $bindloguids = $usernames = array();
			foreach(C::t('#qqconnect#connect_memberbindlog')->fetch_all_by_openids($member['conopenid']) as $bindlog) {
				$bindlogs[$bindlog['dateline']] = $bindlog;
				$bindloguids[] = $bindlog['uid'];
			}
			$usernames = C::t('common_member')->fetch_all_username_by_uid($bindloguids);
			foreach($bindlogs as $k => $v) {
				showtablerow('', array(), array(
					$usernames[$v['uid']],
					dgmdate($k),
					cplang('connect_member_bindlog_type_'.$v['type']),
				));
			}
			showtablefooter();
		}

		showtableheader();
		showtitle('connect_member_bindlog_uid');
		showsubtitle(array('connect_member_bindlog_date', 'connect_member_bindlog_type'));
		foreach(C::t('#qqconnect#connect_memberbindlog')->fetch_all_by_uids($member['uid']) as $bindlog) {
			showtablerow('', array(), array(
				dgmdate($bindlog['dateline']),
				cplang('connect_member_bindlog_type_'.$bindlog['type']),
			));
		}
		showtablefooter();
		exit;
	}
	$member = array_merge($member, C::t('common_member_field_forum'.$tableext)->fetch($uid),
			C::t('common_member_field_home'.$tableext)->fetch($uid),
			C::t('common_member_count'.$tableext)->fetch($uid),
			C::t('common_member_status'.$tableext)->fetch($uid),
			C::t('common_member_profile'.$tableext)->fetch($uid),
			C::t('common_usergroup')->fetch($member['groupid']),
			C::t('common_usergroup_field')->fetch($member['groupid']));
	if(!empty($_G['setting']['connect']['allow'])) {
		$member = array_merge($member, C::t('#qqconnect#common_member_connect')->fetch($uid));
		$uin = C::t('common_uin_black')->fetch_by_uid($uid);
		$member = array_merge($member, array('uinblack'=>$uin['uin']));
	}
	loadcache(array('profilesetting'));
	$fields = array();
	foreach($_G['cache']['profilesetting'] as $fieldid=>$field) {
		if($field['available']) {
			$_G['cache']['profilesetting'][$fieldid]['unchangeable'] = 0;
			$fields[$fieldid] = $field['title'];
		}
	}

	if(!submitcheck('editsubmit')) {

		require_once libfile('function/editor');

		$styleselect = "<select name=\"styleidnew\">\n<option value=\"\">$lang[use_default]</option>";
		foreach(C::t('common_style')->fetch_all_data() as $style) {
			$styleselect .= "<option value=\"$style[styleid]\" ".($style['styleid'] == $member['styleid'] ? 'selected="selected"' : '').">$style[name]</option>\n";
		}
		$styleselect .= '</select>';

		$tfcheck = array($member['timeformat'] => 'checked');
		$gendercheck = array($member['gender'] => 'checked');
		$pscheck = array($member['pmsound'] => 'checked');

		$member['regdate'] = dgmdate($member['regdate'], 'Y-n-j h:i A');
		$member['lastvisit'] = dgmdate($member['lastvisit'], 'Y-n-j h:i A');

		$member['bio'] = html2bbcode($member['bio']);
		$member['signature'] = html2bbcode($member['sightml']);

		shownav('user', 'members_edit');
		showsubmenu("$lang[members_edit] - $member[username]", array(
			array('connect_member_info', 'members&operation=edit&uid='.$uid,  1),
			!empty($_G['setting']['connect']['allow']) ? array('connect_member_bindlog', 'members&operation=edit&do=bindlog&uid='.$uid,  0) : array(),
		));
		showformheader("members&operation=edit&uid=$uid", 'enctype');
		showtableheader();
		$status = array($member['status'] => ' checked');
		showsetting('members_edit_username', '', '', ($_G['setting']['connect']['allow'] && $member['conisbind'] ? ' <img class="vmiddle" src="static/image/common/connect_qq.gif" />' : '').' '.$member['username']);
		showsetting('members_edit_avatar', '', '', ' <img src="'.avatar($uid, 'middle', true, false, true).'?random='.random(2).'" onerror="this.onerror=null;this.src=\''.$_G['setting']['ucenterurl'].'/images/noavatar_middle.gif\'" /><br /><br /><input name="clearavatar" class="checkbox" type="checkbox" value="1" /> '.$lang['members_edit_avatar_clear']);
		$hrefext = "&detail=1&users=$member[username]&searchsubmit=1&perpage=50&fromumanage=1";
		showsetting('members_edit_statistics', '', '', "<a href=\"".ADMINSCRIPT."?action=prune$hrefext\" class=\"act\">$lang[posts]($member[posts])</a>".
				"<a href=\"".ADMINSCRIPT."?action=doing$hrefext\" class=\"act\">$lang[doings]($member[doings])</a>".
				"<a href=\"".ADMINSCRIPT."?action=blog$hrefext\" class=\"act\">$lang[blogs]($member[blogs])</a>".
				"<a href=\"".ADMINSCRIPT."?action=album$hrefext\" class=\"act\">$lang[albums]($member[albums])</a>".
				"<a href=\"".ADMINSCRIPT."?action=share$hrefext\" class=\"act\">$lang[shares]($member[sharings])</a> <br>&nbsp;$lang[setting_styles_viewthread_userinfo_oltime]: $member[oltime]$lang[hourtime]");
		showsetting('members_edit_password', 'passwordnew', '', 'text');
		if(!empty($_G['setting']['connect']['allow']) && (!empty($member['conopenid']) || !empty($member['uinblack']))) {
			if($member['conisbind'] && !$member['conisregister']) {
				showsetting('members_edit_unbind', 'connectunbind', 0, 'radio');
			}
			showsetting('members_edit_uinblack', 'uinblack', $member['uinblack'], 'radio', '', 0, cplang('members_edit_uinblack_comment').($member['conisregister'] ? cplang('members_edit_uinblack_notice') : ''));
		}
		showsetting('members_edit_clearquestion', 'clearquestion', 0, 'radio');
		showsetting('members_edit_status', 'statusnew', $member['status'], 'radio');
		showsetting('members_edit_email', 'emailnew', $member['email'], 'text');
		showsetting('members_edit_email_emailstatus', 'emailstatusnew', $member['emailstatus'], 'radio');
		showsetting('members_edit_posts', 'postsnew', $member['posts'], 'text');
		showsetting('members_edit_digestposts', 'digestpostsnew', $member['digestposts'], 'text');
		showsetting('members_edit_regip', 'regipnew', $member['regip'], 'text');
		showsetting('members_edit_regdate', 'regdatenew', $member['regdate'], 'text');
		showsetting('members_edit_lastvisit', 'lastvisitnew', $member['lastvisit'], 'text');
		showsetting('members_edit_lastip', 'lastipnew', $member['lastip'], 'text');
		showsetting('members_edit_addsize', 'addsizenew', $member['addsize'], 'text');
		showsetting('members_edit_addfriend', 'addfriendnew', $member['addfriend'], 'text');

		showsetting('members_edit_timeoffset', 'timeoffsetnew', $member['timeoffset'], 'text');
		showsetting('members_edit_invisible', 'invisiblenew', $member['invisible'], 'radio');

		showtitle('members_edit_option');
		showsetting('members_edit_cstatus', 'cstatusnew', $member['customstatus'], 'text');
		showsetting('members_edit_signature', 'signaturenew', $member['signature'], 'textarea');

		if($fields) {
			showtitle('members_profile');
			include_once libfile('function/profile');
			foreach($fields as $fieldid=>$fieldtitle) {
				$html = profile_setting($fieldid, $member);
				if($html) {
					showsetting($fieldtitle, '', '', $html);
				}
			}
		}

		showsubmit('editsubmit');
		showtablefooter();
		showformfooter();

	} else {

		loaducenter();
		require_once libfile('function/discuzcode');

		$questionid = $_GET['clearquestion'] ? 0 : '';
		$ucresult = uc_user_edit(addslashes($member['username']), $_GET['passwordnew'], $_GET['passwordnew'], addslashes(strtolower(trim($_GET['emailnew']))), 1, $questionid);
		if($ucresult < 0) {
			if($ucresult == -4) {
				cpmsg('members_email_illegal', '', 'error');
			} elseif($ucresult == -5) {
				cpmsg('members_email_domain_illegal', '', 'error');
			} elseif($ucresult == -6) {
				cpmsg('members_email_duplicate', '', 'error');
			}
		}

		if($_GET['clearavatar']) {
			C::t('common_member'.$tableext)->update($_GET['uid'], array('avatarstatus'=>0));
			uc_user_deleteavatar($uid);
		}

		$creditsnew = intval($creditsnew);

		$regdatenew = strtotime($_GET['regdatenew']);
		$lastvisitnew = strtotime($_GET['lastvisitnew']);

		$secquesadd = $_GET['clearquestion'] ? ", secques=''" : '';

		$signaturenew = censor($_GET['signaturenew']);
		$sigstatusnew = $signaturenew ? 1 : 0;
		$sightmlnew = discuzcode($signaturenew, 1, 0, 0, 0, ($member['allowsigbbcode'] ? ($member['allowcusbbcode'] ? 2 : 1) : 0), $member['allowsigimgcode'], 0);

		$oltimenew = round($_GET['totalnew'] / 60);

		$fieldadd = '';
		$fieldarr = array();
		include_once libfile('function/profile');
		foreach($_POST as $field_key=>$field_val) {
			if(isset($fields[$field_key]) && (profile_check($field_key, $field_val) || $_G['adminid'] == 1)) {
				$fieldarr[$field_key] = $field_val;
			}
		}
		if($_GET['deletefile'] && is_array($_GET['deletefile'])) {
			foreach($_GET['deletefile'] as $key => $value) {
				if(isset($fields[$key]) && $_G['cache']['profilesetting'][$key]['formtype'] == 'file') {
					@unlink(getglobal('setting/attachdir').'./profile/'.$member[$key]);
					$fieldarr[$key] = '';
				}
			}

		}
		if($_FILES) {
			$upload = new discuz_upload();

			foreach($_FILES as $key => $file) {
				if(isset($fields[$key])) {
					$upload->init($file, 'profile');
					$attach = $upload->attach;

					if(!$upload->error()) {
						$upload->save();

						if(!$upload->get_image_info($attach['target'])) {
							@unlink($attach['target']);
							continue;
						}
						$attach['attachment'] = dhtmlspecialchars(trim($attach['attachment']));
						@unlink(getglobal('setting/attachdir').'./profile/'.$member[$key]);
						$fieldarr[$key] = $attach['attachment'];
					}
				}
			}
		}

		$memberupdate = array();
		if($ucresult >= 0) {
			$memberupdate['email'] = strtolower(trim($_GET['emailnew']));
		}
		if($ucresult >= 0 && !empty($_GET['passwordnew'])) {
			$memberupdate['password'] = md5(random(10));
		}
		$addsize = intval($_GET['addsizenew']);
		$addfriend = intval($_GET['addfriendnew']);
		$status = intval($_GET['statusnew']) ? -1 : 0;
		$emailstatusnew = intval($_GET['emailstatusnew']);
		if(!empty($_G['setting']['connect']['allow'])) {
			if($member['uinblack'] && empty($_GET['uinblack'])) {
				C::t('common_uin_black')->delete($member['uinblack']);
				updatecache('connect_blacklist');
			} elseif(!$member['uinblack'] && !empty($_GET['uinblack'])) {
				connectunbind($member);
				C::t('common_uin_black')->insert(array('uin' => $member['conopenid'], 'uid' => $uid, 'dateline' => TIMESTAMP), false, true);
				updatecache('connect_blacklist');
			}
			if($member['conisbind'] && !$member['conisregister'] && !empty($_GET['connectunbind'])) {
				connectunbind($member);
			}
		}
		$memberupdate = array_merge($memberupdate, array('regdate'=>$regdatenew, 'emailstatus'=>$emailstatusnew, 'status'=>$status, 'timeoffset'=>$_GET['timeoffsetnew']));
		C::t('common_member'.$tableext)->update($uid, $memberupdate);
		C::t('common_member_field_home'.$tableext)->update($uid, array('addsize' => $addsize, 'addfriend' => $addfriend));
		C::t('common_member_count'.$tableext)->update($uid, array('posts' => $_GET['postsnew'], 'digestposts' => $_GET['digestpostsnew']));
		C::t('common_member_status'.$tableext)->update($uid, array('regip' => $_GET['regipnew'], 'lastvisit' => $lastvisitnew, 'lastip' => $_GET['lastipnew'], 'invisible' => $_GET['invisiblenew']));
		C::t('common_member_field_forum'.$tableext)->update($uid, array('customstatus' => $_GET['cstatusnew'], 'sightml' => $sightmlnew));
		if(!empty($fieldarr)) {
			C::t('common_member_profile'.$tableext)->update($uid, $fieldarr);
		}


		manyoulog('user', $uid, 'update');
		cpmsg('members_edit_succeed', 'action=members&operation=edit&uid='.$uid, 'succeed');

	}

} elseif($operation == 'ipban') {

	if(!$_GET['ipact']) {
		if(!submitcheck('ipbansubmit')) {

			require_once libfile('function/misc');

			$iptoban = explode('.', getgpc('ip'));

			$ipbanned = '';
			foreach(C::t('common_banned')->fetch_all_order_dateline() as $banned) {
				for($i = 1; $i <= 4; $i++) {
					if($banned["ip$i"] == -1) {
						$banned["ip$i"] = '*';
					}
				}
				$disabled = $_G['adminid'] != 1 && $banned['admin'] != $_G['member']['username'] ? 'disabled' : '';
				$banned['dateline'] = dgmdate($banned['dateline'], 'Y-m-d');
				$banned['expiration'] = dgmdate($banned['expiration'], 'Y-m-d');
				$theip = "$banned[ip1].$banned[ip2].$banned[ip3].$banned[ip4]";
				$ipbanned .= showtablerow('', array('class="td25"'), array(
					"<input class=\"checkbox\" type=\"checkbox\" name=\"delete[$banned[id]]\" value=\"$banned[id]\" $disabled />",
					$theip,
					convertip($theip, "./"),
					$banned[admin],
					$banned[dateline],
					"<input type=\"text\" class=\"txt\" size=\"10\" name=\"expirationnew[$banned[id]]\" value=\"$banned[expiration]\" $disabled />"
				), TRUE);
			}
			shownav('user', 'nav_members_ipban');
			showsubmenu('nav_members_ipban', array(
				array('nav_members_ipban', 'members&operation=ipban', 1),
				array('nav_members_ipban_output', 'members&operation=ipban&ipact=input', 0)
			));
			showtips('members_ipban_tips');
			showformheader('members&operation=ipban');
			showtableheader();
			showsubtitle(array('', 'ip', 'members_ipban_location', 'operator', 'start_time', 'end_time'));
			echo $ipbanned;
			showtablerow('', array('', 'class="td28" colspan="3"', 'class="td28" colspan="2"'), array(
				$lang['add_new'],
				'<input type="text" class="txt" name="ip1new" value="'.$iptoban[0].'" size="3" maxlength="3">.<input type="text" class="txt" name="ip2new" value="'.$iptoban[1].'" size="3" maxlength="3">.<input type="text" class="txt" name="ip3new" value="'.$iptoban[2].'" size="3" maxlength="3">.<input type="text" class="txt" name="ip4new" value="'.$iptoban[3].'" size="3" maxlength="3">',
				$lang['validity'].': <input type="text" class="txt" name="validitynew" value="30" size="3"> '.$lang['days']
			));
			showsubmit('ipbansubmit', 'submit', 'del');
			showtablefooter();
			showformfooter();

		} else {

			if(!empty($_GET['delete'])) {
				C::t('common_banned')->delete_by_id($_GET['delete'], $_G['adminid'], $_G['username']);
			}

			if($_GET['ip1new'] != '' && $_GET['ip2new'] != '' && $_GET['ip3new'] != '' && $_GET['ip4new'] != '') {
				$own = 0;
				$ip = explode('.', $_G['clientip']);
				for($i = 1; $i <= 4; $i++) {
					if(!is_numeric($_GET['ip'.$i.'new']) || $_GET['ip'.$i.'new'] < 0) {
						if($_G['adminid'] != 1) {
							cpmsg('members_ipban_nopermission', '', 'error');
						}
						$_GET['ip'.$i.'new'] = -1;
						$own++;
					} elseif($_GET['ip'.$i.'new'] == $ip[$i - 1]) {
						$own++;
					}
					$_GET['ip'.$i.'new'] = intval($_GET['ip'.$i.'new']);
				}

				if($own == 4) {
					cpmsg('members_ipban_illegal', '', 'error');
				}

				foreach(C::t('common_banned')->fetch_all_order_dateline() as $banned) {
					$exists = 0;
					for($i = 1; $i <= 4; $i++) {
						if($banned["ip$i"] == -1) {
							$exists++;
						} elseif($banned["ip$i"] == ${"ip".$i."new"}) {
							$exists++;
						}
					}
					if($exists == 4) {
						cpmsg('members_ipban_invalid', '', 'error');
					}
				}

				$expiration = TIMESTAMP + $_GET['validitynew'] * 86400;

				C::app()->session->update_by_ipban($_GET['ip1new'], $_GET['ip2new'], $_GET['ip3new'], $_GET['ip4new']);
				$data = array(
					'ip1' => $_GET['ip1new'],
					'ip2' => $_GET['ip2new'],
					'ip3' => $_GET['ip3new'],
					'ip4' => $_GET['ip4new'],
					'admin' => $_G['username'],
					'dateline' => $_G['timestamp'],
					'expiration' => $expiration,
				);
				C::t('common_banned')->insert($data);
				captcha::report($_GET['ip1new'].'.'.$_GET['ip2new'].'.'.$_GET['ip3new'].'.'.$_GET['ip4new']);
			}

			if(is_array($_GET['expirationnew'])) {
				foreach($_GET['expirationnew'] as $id => $expiration) {
					C::t('common_banned')->update_expiration_by_id($id, strtotime($expiration), $_G['adminid'], $_G['username']);
				}
			}

			updatecache('ipbanned');
			cpmsg('members_ipban_succeed', 'action=members&operation=ipban', 'succeed');

		}
	} elseif($_GET['ipact'] == 'input') {
		if($_G['adminid'] != 1) {
			cpmsg('members_ipban_nopermission', '', 'error');
		}
		if(!submitcheck('ipbansubmit')) {
			shownav('user', 'nav_members_ipban');
			showsubmenu('nav_members_ipban', array(
				array('nav_members_ipban', 'members&operation=ipban', 0),
				array('nav_members_ipban_output', 'members&operation=ipban&ipact=input', 1)
			));
			showtips('members_ipban_input_tips');
			showformheader('members&operation=ipban&ipact=input');
			showtableheader();
			showsetting('members_ipban_input', 'inputipbanlist', '', 'textarea');
			showsubmit('ipbansubmit', 'submit');
			showtablefooter();
			showformfooter();
		} else {
			$iplist = explode("\n", $_GET['inputipbanlist']);
			foreach($iplist as $banip) {
				if(strpos($banip, ',') !== false) {
					list($banipaddr, $expiration) = explode(',', $banip);
					$expiration = strtotime($expiration);
				} else {
					list($banipaddr, $expiration) = explode(';', $banip);
					$expiration = TIMESTAMP + ($expiration ? $expiration : 30) * 86400;
				}
				if(!trim($banipaddr)) {
					continue;
				}

				$ipnew = explode('.', $banipaddr);
				for($i = 0; $i < 4; $i++) {
					if(strpos($ipnew[$i], '*') !== false) {
						$ipnew[$i] = -1;
					} else {
						$ipnew[$i] = intval($ipnew[$i]);
					}
				}
				$checkexists = C::t('common_banned')->fetch_by_ip($ipnew[0], $ipnew[1], $ipnew[2], $ipnew[3]);
				if($checkexists) {
					continue;
				}

				C::app()->session->update_by_ipban($ipnew[0], $ipnew[1], $ipnew[2], $ipnew[3]);
				$data = array(
					'ip1' => $ipnew[0],
					'ip2' => $ipnew[1],
					'ip3' => $ipnew[2],
					'ip4' => $ipnew[3],
					'admin' => $_G['username'],
					'dateline' => $_G['timestamp'],
					'expiration' => $expiration,
				);
				C::t('common_banned')->insert($data, false, true);
			}

			updatecache('ipbanned');
			cpmsg('members_ipban_succeed', 'action=members&operation=ipban&ipact=input', 'succeed');
		}
	} elseif($_GET['ipact'] == 'output') {
		ob_end_clean();
		dheader('Cache-control: max-age=0');
		dheader('Expires: '.gmdate('D, d M Y H:i:s', TIMESTAMP - 31536000).' GMT');
		dheader('Content-Encoding: none');
		dheader('Content-Disposition: attachment; filename=IPBan.csv');
		dheader('Content-Type: text/plain');
		foreach(C::t('common_banned')->fetch_all_order_dateline() as $banned) {
			for($i = 1; $i <= 4; $i++) {
				$banned['ip'.$i] = $banned['ip'.$i] < 0 ? '*' : $banned['ip'.$i];
			}
			$banned['expiration'] = dgmdate($banned['expiration']);
			echo "$banned[ip1].$banned[ip2].$banned[ip3].$banned[ip4],$banned[expiration]\n";
		}
		define('FOOTERDISABLED' , 1);
		exit();
	}

} elseif($operation == 'profile') {

	$fieldid = $_GET['fieldid'] ? $_GET['fieldid'] : '';
	shownav('user', 'nav_members_profile');
	if($fieldid) {
		$_G['setting']['privacy'] = !empty($_G['setting']['privacy']) ? $_G['setting']['privacy'] : array();
		$_G['setting']['privacy'] = is_array($_G['setting']['privacy']) ? $_G['setting']['privacy'] : dunserialize($_G['setting']['privacy']);

		$field = C::t('common_member_profile_setting')->fetch($fieldid);
		$fixedfields1 = array('uid', 'constellation', 'zodiac');
		$fixedfields2 = array('gender', 'birthday', 'birthcity', 'residecity');
		$field['isfixed1'] = in_array($fieldid, $fixedfields1);
		$field['isfixed2'] = $field['isfixed1'] || in_array($fieldid, $fixedfields2);
		$field['customable'] = preg_match('/^field[1-8]$/i', $fieldid);
		$profilegroup = C::t('common_setting')->fetch('profilegroup', true);
		$profilevalidate = array();
		include libfile('spacecp/profilevalidate', 'include');
		$field['validate'] = $field['validate'] ? $field['validate'] : ($profilevalidate[$fieldid] ? $profilevalidate[$fieldid] : '');
		if(!submitcheck('editsubmit')) {
			showsubmenu($lang['members_profile'].'-'.$field['title'], array(
				array('members_profile_list', 'members&operation=profile', 0),
				array($lang['edit'], 'members&operation=profile&fieldid='.$_GET['fieldid'], 1)
			));
			showformheader('members&operation=profile&fieldid='.$fieldid);
			showtableheader();
			if($field['customable']) {
				showsetting('members_profile_edit_name', 'title', $field['title'], 'text');
				showsetting('members_profile_edit_desc', 'description', $field['description'], 'text');
			} else {
				showsetting('members_profile_edit_name', '', '', ' '.$field['title']);
				showsetting('members_profile_edit_desc', '', '', ' '.$field['description']);
			}
			if(!$field['isfixed2']) {
				if($field['fieldid'] == 'realname') {
					showsetting('members_profile_edit_form_type', array('formtype', array(
						array('text', $lang['members_profile_edit_text'], array('valuenumber' => '', 'fieldchoices' => 'none', 'fieldvalidate'=>''))
					)), $field['formtype'], 'mradio');
				} else {
					showsetting('members_profile_edit_form_type', array('formtype', array(
							array('text', $lang['members_profile_edit_text'], array('valuenumber' => '', 'fieldchoices' => 'none', 'fieldvalidate'=>'')),
							array('textarea', $lang['members_profile_edit_textarea'], array('valuenumber' => '', 'fieldchoices' => 'none', 'fieldvalidate'=>'')),
							array('radio', $lang['members_profile_edit_radio'], array('valuenumber' => 'none', 'fieldchoices' => '', 'fieldvalidate'=>'none')),
							array('checkbox', $lang['members_profile_edit_checkbox'], array('valuenumber' => '', 'fieldchoices' => '', 'fieldvalidate'=>'none')),
							array('select', $lang['members_profile_edit_select'], array('valuenumber' => 'none', 'fieldchoices' => '', 'fieldvalidate'=>'none')),
							array('list', $lang['members_profile_edit_list'], array('valuenumber' => '', 'fieldchoices' => '')),
							array('file', $lang['members_profile_edit_file'], array('valuenumber' => '', 'fieldchoices' => 'none', 'fieldvalidate'=>'none'))
						)), $field['formtype'], 'mradio');
				}
				showtagheader('tbody', 'valuenumber', !in_array($field['formtype'], array('radio', 'select')), 'sub');
				showsetting('members_profile_edit_value_number', 'size', $field['size'], 'text');
				showtagfooter('tbody');

				showtagheader('tbody', 'fieldchoices', !in_array($field['formtype'], array('file','text', 'textarea')), 'sub');
				showsetting('members_profile_edit_choices', 'choices', $field['choices'], 'textarea');
				showtagfooter('tbody');

				showtagheader('tbody', 'fieldvalidate', in_array($field['formtype'], array('text', 'textarea')), 'sub');
				showsetting('members_profile_edit_validate', 'validate', $field['validate'], 'text');
				showtagfooter('tbody');
			}
			if(!$field['isfixed1']) {
				showsetting('members_profile_edit_available', 'available', $field['available'], 'radio');
				showsetting('members_profile_edit_unchangeable', 'unchangeable', $field['unchangeable'], 'radio');
				showsetting('members_profile_edit_needverify', 'needverify', $field['needverify'], 'radio');
				showsetting('members_profile_edit_required', 'required', $field['required'], 'radio');
			}
			showsetting('members_profile_edit_invisible', 'invisible', $field['invisible'], 'radio');
			$privacyselect = array(
				array('0', cplang('members_profile_edit_privacy_public')),
				array('1', cplang('members_profile_edit_privacy_friend')),
				array('3', cplang('members_profile_edit_privacy_secret'))
			);
			showsetting('members_profile_edit_default_privacy', array('privacy', $privacyselect), $_G['setting']['privacy']['profile'][$fieldid], 'select');
			showsetting('members_profile_edit_showincard', 'showincard', $field['showincard'], 'radio');
			showsetting('members_profile_edit_showinregister', 'showinregister', $field['showinregister'], 'radio');
			showsetting('members_profile_edit_allowsearch', 'allowsearch', $field['allowsearch'], 'radio');
			if(!empty($profilegroup)) {
				$groupstr = '';
				foreach($profilegroup as $key => $value) {
					if($value['available']) {
						if(in_array($fieldid, $value['field'])) {
							$checked = ' checked="checked" ';
							$class = ' class="checked" ';
						} else {
							$class = $checked = '';
						}
						$groupstr .= "<li $class style=\"float: left; width: 10%;\"><input type=\"checkbox\" value=\"$key\" name=\"profilegroup[$key]\" class=\"checkbox\" $checked>&nbsp;$value[title]</li>";
					}
				}
				if(!empty($groupstr)) {
					print <<<EOF
						<tr>
							<td class="td27" colspan="2">$lang[setting_profile_group]:</td>
						</tr>
						<tr>
							<td colspan="2">
								<ul class="dblist" onmouseover="altStyle(this);">
									<li style="width: 100%;"><input type="checkbox" name="chkall" onclick="checkAll('prefix', this.form, 'profilegroup')" class="checkbox">&nbsp;$lang[select_all]</li>
									$groupstr
								</ul>
							</td>
						</tr>
EOF;
				}
			}

			showsetting('members_profile_edit_display_order', 'displayorder', $field['displayorder'], 'text');
			showsubmit('editsubmit');
			showtablefooter();
			showformfooter();

		} else {

			$setarr = array(
				'invisible' => intval($_POST['invisible']),
				'showincard' => intval($_POST['showincard']),
				'showinregister' => intval($_POST['showinregister']),
				'allowsearch' => intval($_POST['allowsearch']),
				'displayorder' => intval($_POST['displayorder'])
			);
			if($field['customable']) {
				$_POST['title'] = dhtmlspecialchars(trim($_POST['title']));
				if(empty($_POST['title'])) {
					cpmsg('members_profile_edit_title_empty_error', 'action=members&operation=profile&fieldid='.$fieldid, 'error');
				}
				$setarr['title'] = $_POST['title'];
				$setarr['description'] = dhtmlspecialchars(trim($_POST['description']));
			}
			if(!$field['isfixed1']) {
				$setarr['required'] = intval($_POST['required']);
				$setarr['available'] = intval($_POST['available']);
				$setarr['unchangeable'] = intval($_POST['unchangeable']);
				$setarr['needverify'] = intval($_POST['needverify']);
			}
			if(!$field['isfixed2']) {
				$setarr['formtype'] = $fieldid == 'realname' ? 'text' : strtolower(trim($_POST['formtype']));
				$setarr['size'] = intval($_POST['size']);
				if($_POST['choices']) {
					$_POST['choices'] = trim($_POST['choices']);
					$ops = explode("\n", $_POST['choices']);
					$parts = array();
					foreach ($ops as $op) {
						$parts[] = dhtmlspecialchars(trim($op));
					}
					$_POST['choices'] = implode("\n", $parts);
				}
				$setarr['choices'] = $_POST['choices'];
				if($_POST['validate'] && $_POST['validate'] != $profilevalidate[$fieldid]) {
					$setarr['validate'] = $_POST['validate'];
				} elseif(empty($_POST['validate'])) {
					$setarr['validate'] = '';
				}
			}
			C::t('common_member_profile_setting')->update($fieldid, $setarr);
			if($_GET['fieldid'] == 'birthday') {
				C::t('common_member_profile_setting')->update('birthmonth', $setarr);
				C::t('common_member_profile_setting')->update('birthyear', $setarr);
			} elseif($_GET['fieldid'] == 'birthcity') {
				C::t('common_member_profile_setting')->update('birthprovince', $setarr);
				$setarr['required'] = 0;
				C::t('common_member_profile_setting')->update('birthdist', $setarr);
				C::t('common_member_profile_setting')->update('birthcommunity', $setarr);
			} elseif($_GET['fieldid'] == 'residecity') {
				C::t('common_member_profile_setting')->update('resideprovince', $setarr);
				$setarr['required'] = 0;
				C::t('common_member_profile_setting')->update('residedist', $setarr);
				C::t('common_member_profile_setting')->update('residecommunity', $setarr);
			} elseif($_GET['fieldid'] == 'idcard') {
				C::t('common_member_profile_setting')->update('idcardtype', $setarr);
			}

			foreach($profilegroup as $type => $pgroup) {
				if(is_array($_GET['profilegroup']) && in_array($type, $_GET['profilegroup'])) {
					$profilegroup[$type]['field'][$fieldid] = $fieldid;
				} else {
					unset($profilegroup[$type]['field'][$fieldid]);
				}
			}
			C::t('common_setting')->update('profilegroup', $profilegroup);
			require_once libfile('function/cache');
			if(!isset($_G['setting']['privacy']['profile']) || $_G['setting']['privacy']['profile'][$fieldid] != $_POST['privacy']) {
				$_G['setting']['privacy']['profile'][$fieldid] = $_POST['privacy'];
				C::t('common_setting')->update('privacy', $_G['setting']['privacy']);
			}
			updatecache(array('profilesetting','fields_required', 'fields_optional', 'fields_register', 'setting'));
			include_once libfile('function/block');
			loadcache('profilesetting', true);
			blockclass_cache();
			cpmsg('members_profile_edit_succeed', 'action=members&operation=profile', 'succeed');
		}
	} else {

		$list = array();
		foreach(C::t('common_member_profile_setting')->range() as $fieldid => $value) {
			$list[$fieldid] = array(
				'title'=>$value['title'],
				'displayorder'=>$value['displayorder'],
				'available'=>$value['available'],
				'invisible'=>$value['invisible'],
				'showincard'=>$value['showincard'],
				'showinregister'=>$value['showinregister']);
		}

		unset($list['birthyear']);
		unset($list['birthmonth']);
		unset($list['birthprovince']);
		unset($list['birthdist']);
		unset($list['birthcommunity']);
		unset($list['resideprovince']);
		unset($list['residedist']);
		unset($list['residecommunity']);
		unset($list['idcardtype']);

		if(!submitcheck('ordersubmit')) {
			$_GET['anchor'] = in_array($_GET['action'], array('members', 'setting')) ? $_GET['action'] : 'members';
			$current = array($_GET['anchor'] => 1);
			$profilenav = array(
					array('members_profile_list', 'members&operation=profile', $current['members']),
					array('members_profile_group', 'setting&operation=profile', $current['setting']),
				);
			showsubmenu($lang['members_profile'], $profilenav);
			showtips('members_profile_tips');
			showformheader('members&operation=profile');
			showtableheader('', '', 'id="profiletable_header"');
			$tdstyle = array('class="td22"', 'class="td28" width="100"', 'class="td28" width="100"', 'class="td28" width="100"', 'class="td28" width="100"', 'class="td28"', 'class="td28"');
			showsubtitle(array('members_profile_edit_name', 'members_profile_edit_display_order', 'members_profile_edit_available', 'members_profile_edit_profile_view', 'members_profile_edit_card_view', 'members_profile_edit_reg_view', ''), 'header tbm', $tdstyle);
			showtablefooter();
			echo '<script type="text/javascript">floatbottom(\'profiletable_header\');</script>';
			showtableheader('members_profile', 'nobottom', 'id="porfiletable"');
			showsubtitle(array('members_profile_edit_name', 'members_profile_edit_display_order', 'members_profile_edit_available', 'members_profile_edit_profile_view', 'members_profile_edit_card_view', 'members_profile_edit_reg_view', ''), 'header', $tdstyle);
			foreach($list as $fieldid => $value) {
				$value['available'] = '<input type="checkbox" class="checkbox" name="available['.$fieldid.']" '.($value['available'] ? 'checked="checked" ' : '').'value="1">';
				$value['invisible'] = '<input type="checkbox" class="checkbox" name="invisible['.$fieldid.']" '.(!$value['invisible'] ? 'checked="checked" ' : '').'value="1">';
				$value['showincard'] = '<input type="checkbox" class="checkbox" name="showincard['.$fieldid.']" '.($value['showincard'] ? 'checked="checked" ' : '').'value="1">';
				$value['showinregister'] = '<input type="checkbox" class="checkbox" name="showinregister['.$fieldid.']" '.($value['showinregister'] ? 'checked="checked" ' : '').'value="1">';
				$value['displayorder'] = '<input type="text" name="displayorder['.$fieldid.']" value="'.$value['displayorder'].'" size="5">';
				$value['edit'] = '<a href="'.ADMINSCRIPT.'?action=members&operation=profile&fieldid='.$fieldid.'" title="" class="act">'.$lang[edit].'</a>';
				showtablerow('', array(), $value);
			}
			showsubmit('ordersubmit');
			showtablefooter();
			showformfooter();
		} else {
			foreach($_GET['displayorder'] as $fieldid => $value) {
				$setarr = array(
					'displayorder' => intval($value),
					'invisible' => intval($_GET['invisible'][$fieldid]) ? 0 : 1,
					'available' => intval($_GET['available'][$fieldid]),
					'showincard' => intval($_GET['showincard'][$fieldid]),
					'showinregister' => intval($_GET['showinregister'][$fieldid]),
				);
				C::t('common_member_profile_setting')->update($fieldid, $setarr);

				if($fieldid == 'birthday') {
					C::t('common_member_profile_setting')->update('birthmonth', $setarr);
					C::t('common_member_profile_setting')->update('birthyear', $setarr);
				} elseif($fieldid == 'birthcity') {
					C::t('common_member_profile_setting')->update('birthprovince', $setarr);
					$setarr['required'] = 0;
					C::t('common_member_profile_setting')->update('birthdist', $setarr);
					C::t('common_member_profile_setting')->update('birthcommunity', $setarr);
				} elseif($fieldid == 'residecity') {
					C::t('common_member_profile_setting')->update('resideprovince', $setarr);
					$setarr['required'] = 0;
					C::t('common_member_profile_setting')->update('residedist', $setarr);
					C::t('common_member_profile_setting')->update('residecommunity', $setarr);
				} elseif($fieldid == 'idcard') {
					C::t('common_member_profile_setting')->update('idcardtype', $setarr);
				}

			}
			require_once libfile('function/cache');
			updatecache(array('profilesetting', 'fields_required', 'fields_optional', 'fields_register', 'setting'));
			include_once libfile('function/block');
			loadcache('profilesetting', true);
			blockclass_cache();
			cpmsg('members_profile_edit_succeed', 'action=members&operation=profile', 'succeed');
		}
	}

} elseif($operation == 'stat') {

	if($_GET['do'] == 'stepstat' && $_GET['t'] > 0 && $_GET['i'] > 0) {
		$t = intval($_GET['t']);
		$i = intval($_GET['i']);
		$o = $i - 1;
		$value = C::t('common_member_stat_field')->fetch_all_by_fieldid($_GET['fieldid'], $o, 1);
		if($value) {
			$optionid = intval($value[0]['optionid']);
			$fieldvalue = $value[0]['fieldvalue'];
		} else {
			$optionid = 0;
			$fieldvalue = '';
		}
		$cnt = ($_GET['fieldid'] === 'groupid') ? C::t('common_member')->count_by_groupid($fieldvalue) : C::t('common_member_profile')->count_by_field($_GET['fieldid'], $fieldvalue);
		C::t('common_member_stat_field')->update($optionid, array('users'=>$cnt, 'updatetime'=>TIMESTAMP));
		if($i < $t) {
			cpmsg('members_stat_do_stepstat', 'action=members&operation=stat&fieldid='.$_GET['fieldid'].'&do=stepstat&t='.$t.'&i='.($i+1), '', array('t'=>$t, 'i'=>$i));
		} else {
			cpmsg('members_stat_update_data_succeed', 'action=members&operation=stat&fieldid='.$_GET['fieldid'], 'succeed');
		}
	}

	$options = array('groupid'=>cplang('usergroup'));
	$fieldids = array('gender', 'birthyear', 'birthmonth', 'constellation', 'zodiac','birthprovince', 'resideprovince');
	loadcache('profilesetting');
	foreach($_G['cache']['profilesetting'] as $fieldid=>$value) {
		if($value['formtype']=='select'||$value['formtype']=='radio'||in_array($fieldid,$fieldids)) {
			$options[$fieldid] = $value['title'];
		}
	}

	if(!empty($_GET['fieldid']) && !isset($options[$_GET['fieldid']])) {
		cpmsg('members_stat_bad_fieldid', 'action=members&operation=stat', 'error');
	}

	if(!empty($_GET['fieldid']) && $_GET['fieldid'] == 'groupid') {
		$usergroups = array();
		foreach(C::t('common_usergroup')->range() as $value) {
			$usergroups[$value['groupid']] = $value['grouptitle'];
		}
	}

	if(!submitcheck('statsubmit')) {

		shownav('user', 'nav_members_stat');
		showsubmenu('nav_members_stat');
		showtips('members_stat_tips');

		showformheader('members&operation=stat&fieldid='.$_GET['fieldid']);
		showtableheader('members_stat_options');
		$option_html = '<ul>';
		foreach($options as $key=>$value) {
			$extra_style = $_GET['fieldid'] == $key ? ' font-weight: 900;' : '';
			$option_html .= ""
				."<li style=\"float: left; width: 160px;$extra_style\">"
				. "<a href=\"".ADMINSCRIPT."?action=members&operation=stat&fieldid=$key\">$value</a>"
				. "</li>";
		}
		$option_html .= '</ul><br style="clear: both;" />';
		showtablerow('', array('colspan="5"'), array($option_html));

		if($_GET['fieldid']) {

			$list = array();
			$total = 0;
			foreach(($list = C::t('common_member_stat_field')->fetch_all_by_fieldid($_GET['fieldid'])) as $value) {
				$total += $value['users'];
			}
			for($i=0, $L=count($list); $i<$L; $i++) {
				if($total) {
					$list[$i]['percent'] = intval(10000 * $list[$i]['users'] / $total) / 100;
				} else {
					$list[$i]['percent'] = 0;
				}
				$list[$i]['width'] = $list[$i]['percent'] ? intval($list[$i]['percent'] * 2) : 1;
			}
			showtablerow('', array('colspan="4"'), array(cplang('members_stat_current_field').$options[$_GET['fieldid']].'; '.cplang('members_stat_members').$total));

			showtablerow('', array('width="200"', '', 'width="160"', 'width="160"'),array(
					cplang('members_stat_option'),
					cplang('members_stat_view'),
					cplang('members_stat_option_members'),
					cplang('members_stat_updatetime')
				));
			foreach($list as $value) {
				if($_GET['fieldid']=='groupid') {
					$value['fieldvalue'] = $usergroups[$value['fieldvalue']];
				} elseif($_GET['fieldid']=='gender') {
					$value['fieldvalue'] = lang('space', 'gender_'.$value['fieldvalue']);
				} elseif(empty($value['fieldvalue'])) {
					$value['fieldvalue'] = cplang('members_stat_null_fieldvalue');
				}
				showtablerow('', array('width="200"', '', 'width="160"', 'width="160"'),array(
					$value['fieldvalue'],
					'<div style="background-color: yellow; width: 200px; height: 20px;"><div style="background-color: red; height: 20px; width: '.$value['width'].'px;"></div></div>',
					$value['users'].' ('.$value['percent'].'%)',
					!empty($value['updatetime']) ? dgmdate($value['updatetime'], 'u') : 'N/A'
				));
			}

			showtablefooter();
			$optype_html = '<input type="radio" class="radio" name="optype" id="optype_option" value="option" /><label for="optype_option">'.cplang('members_stat_update_option').'</label>&nbsp;&nbsp;'
					.'<input type="radio" class="radio" name="optype" id="optype_data" value="data" /><label for="optype_data">'.cplang('members_stat_update_data').'</label>';
			showsubmit('statsubmit', 'submit', $optype_html);
			showformfooter();

		} else {
			showtablefooter();
			showformfooter();
		}

	} else {

		if($_POST['optype'] == 'option') {

			$options = $inserts = $hits = $deletes = array();
			foreach(C::t('common_member_stat_field')->fetch_all_by_fieldid($_GET['fieldid']) as $value) {
				$options[$value['optionid']] = $value['fieldvalue'];
				$hits[$value['optionid']] = false;
			}

			$alldata = $_GET['fieldid'] === 'groupid' ? C::t('common_member')->fetch_all_groupid() : C::t('common_member_profile')->fetch_all_field_value($_GET['fieldid']);
			foreach($alldata as $value) {
				$fieldvalue = $value[$_GET[fieldid]];
				$optionid = array_search($fieldvalue, $options);
				if($optionid) {
					$hits[$optionid] = true;
				} else {
					$inserts[] = array('fieldid'=>$_GET['fieldid'], 'fieldvalue'=>$fieldvalue);
				}
			}
			foreach ($hits as $key=>$value) {
				if($value == false) {
					$deletes[] = $key;
				}
			}
			if($deletes) {
				C::t('common_member_stat_field')->delete($deletes);

			}
			if($inserts) {
				C::t('common_member_stat_field')->insert_batch($inserts);
			}

			cpmsg('members_stat_update_option_succeed', 'action=members&operation=stat&fieldid='.$_GET['fieldid'], 'succeed');

		} elseif($_POST['optype'] == 'data') {

			if(($t = C::t('common_member_stat_field')->count_by_fieldid($_GET['fieldid'])) > 0) {
				cpmsg('members_stat_do_stepstat_prepared', 'action=members&operation=stat&fieldid='.$_GET['fieldid'].'&do=stepstat&t='.$t.'&i=1', '', array('t'=>$t));
			} else {
				cpmsg('members_stat_update_data_succeed', 'action=members&operation=stat&fieldid='.$_GET['fieldid'], 'succeed');
			}

		} else {
			cpmsg('members_stat_null_operation', 'action=members&operation=stat', 'error');
		}
	}
}

function showsearchform($operation = '') {
	global $_G, $lang;

	$groupselect = array();
	$usergroupid = isset($_GET['usergroupid']) && is_array($_GET['usergroupid']) ? $_GET['usergroupid'] : array();
	$medals = isset($_GET['medalid']) && is_array($_GET['medalid']) ? $_GET['medalid'] : array();
	$tagid = isset($_GET['tagid']) && is_array($_GET['tagid']) ? $_GET['tagid'] : array();
	$query = C::t('common_usergroup')->fetch_all_not(array(6, 7), true);
	foreach($query as $group) {
		$group['type'] = $group['type'] == 'special' && $group['radminid'] ? 'specialadmin' : $group['type'];
		$groupselect[$group['type']] .= "<option value=\"$group[groupid]\" ".(in_array($group['groupid'], $usergroupid) ? 'selected' : '').">$group[grouptitle]</option>\n";
	}
	$groupselect = '<optgroup label="'.$lang['usergroups_member'].'">'.$groupselect['member'].'</optgroup>'.
		($groupselect['special'] ? '<optgroup label="'.$lang['usergroups_special'].'">'.$groupselect['special'].'</optgroup>' : '').
		($groupselect['specialadmin'] ? '<optgroup label="'.$lang['usergroups_specialadmin'].'">'.$groupselect['specialadmin'].'</optgroup>' : '').
		'<optgroup label="'.$lang['usergroups_system'].'">'.$groupselect['system'].'</optgroup>';
	$medalselect = $usertagselect = '';
	foreach(C::t('forum_medal')->fetch_all_data(1) as $medal) {
		$medalselect .= "<option value=\"$medal[medalid]\" ".(in_array($medal['medalid'], $medals) ? 'selected' : '').">$medal[name]</option>\n";
	}
	$query = C::t('common_tag')->fetch_all_by_status(3);
	foreach($query as $row) {
		$usertagselect .= "<option value=\"$row[tagid]\" ".(in_array($row['tagid'], $tagid) ? 'selected' : '').">$row[tagname]</option>\n";
	}

	showtagheader('div', 'searchmembers', !$_GET['submit']);
	echo '<script src="static/js/calendar.js" type="text/javascript"></script>';
	echo '<style type="text/css">#residedistrictbox select, #birthdistrictbox select{width: auto;}</style>';
	$formurl = "members&operation=$operation".($_GET['do'] == 'mobile' ? '&do=mobile' : '');
	showformheader($formurl, "onSubmit=\"if($('updatecredittype1') && $('updatecredittype1').checked && !window.confirm('$lang[members_reward_clean_alarm]')){return false;} else {return true;}\"");
	showtableheader();
	if(isset($_G['setting']['membersplit'])) {
		showsetting('members_search_table', '', '', '<select name="tablename" ><option value="master">'.$lang['members_search_table_master'].'</option><option value="archive">'.$lang['members_search_table_archive'].'</option></select>');
	}
	showsetting('members_search_user', 'username', $_GET['username'], 'text');
	showsetting('members_search_uid', 'uid', $_GET['uid'], 'text');
	showsetting('members_search_group', '', '', '<select name="groupid[]" multiple="multiple" size="10">'.$groupselect.'</select>');
	showtablefooter();

	showtableheader();
	showtagheader('tbody', 'advanceoption');
	$_G['showsetting_multirow'] = 1;
	if(empty($medalselect)) {
		$medalselect = '<option value="">'.cplang('members_search_nonemedal').'</option>';
	}
	if(empty($usertagselect)) {
		$usertagselect = '<option value="">'.cplang('members_search_noneusertags').'</option>';
	}
	showsetting('members_search_medal', '', '', '<select name="medalid[]" multiple="multiple" size="10">'.$medalselect.'</select>');
	showsetting('members_search_usertag', '', '', '<select name="tagid[]" multiple="multiple" size="10">'.$usertagselect.'</select>');
	if(!empty($_G['setting']['connect']['allow'])) {
		showsetting('members_search_conisbind', array('conisbind', array(
			array(1, $lang['yes']),
			array(0, $lang['no']),
		), 1), $_GET['conisbind'], 'mradio');
		showsetting('members_search_uinblacklist', array('uin_low', array(
			array(1, $lang['yes']),
			array(0, $lang['no']),
		), 1), $_GET['uin_low'], 'mradio');
	}
	showsetting('members_search_online', array('sid_noempty', array(
		array(1, $lang['yes']),
		array(0, $lang['no']),
	), 1), $_GET['online'], 'mradio');
	showsetting('members_search_lockstatus', array('status', array(
		array(-1, $lang['yes']),
		array(0, $lang['no']),
	), 1), $_GET['status'], 'mradio');
	showsetting('members_search_freezestatus', array('freeze', array(
		array(1, $lang['yes']),
		array(0, $lang['no']),
	), 1), $_GET['freeze'], 'mradio');
	showsetting('members_search_emailstatus', array('emailstatus', array(
		array(1, $lang['yes']),
		array(0, $lang['no']),
	), 1), $_GET['emailstatus'], 'mradio');
	showsetting('members_search_avatarstatus', array('avatarstatus', array(
		array(1, $lang['yes']),
		array(0, $lang['no']),
	), 1), $_GET['avatarstatus'], 'mradio');
	showsetting('members_search_email', 'email', $_GET['email'], 'text');
	showsetting("$lang[credits] $lang[members_search_between]", array("credits_low", "credits_high"), array($_GET['credits_low'], $_GET['credtis_high']), 'range');

	if(!empty($_G['setting']['extcredits'])) {
		foreach($_G['setting']['extcredits'] as $id => $credit) {
			showsetting("$credit[title] $lang[members_search_between]", array("extcredits$id"."_low", "extcredits$id"."_high"), array($_GET['extcredits'.$id.'_low'], $_GET['extcredits'.$id.'_high']), 'range');
		}
	}

	showsetting('members_search_friendsrange', array('friends_low', 'friends_high'), array($_GET['friends_low'], $_GET['friends_high']), 'range');
	showsetting('members_search_postsrange', array('posts_low', 'posts_high'), array($_GET['posts_low'], $_GET['posts_high']), 'range');
	showsetting('members_search_regip', 'regip', $_GET['regip'], 'text');
	showsetting('members_search_lastip', 'lastip', $_GET['lastip'], 'text');
	showsetting('members_search_oltimerange', array('oltime_low', 'oltime_high'), array($_GET['oltime_low'], $_GET['oltime_high']), 'range');
	showsetting('members_search_regdaterange', array('regdate_after', 'regdate_before'), array($_GET['regdate_after'], $_GET['regdate_before']), 'daterange');
	showsetting('members_search_lastvisitrange', array('lastvisit_after', 'lastvisit_before'), array($_GET['lastvisit_after'], $_GET['lastvisit_before']), 'daterange');
	showsetting('members_search_lastpostrange', array('lastpost_after', 'lastpost_before'), array($_GET['lastpost_after'], $_GET['lastpost_before']), 'daterange');
	showsetting('members_search_group_fid', 'fid', $_GET['fid'], 'text');
	if($_G['setting']['verify']) {
		$verifydata = array();
		foreach($_G['setting']['verify'] as $key => $value) {
			if($value['available']) {
				$verifydata[] = array('verify'.$key, $value['title']);
			}
		}
		if(!empty($verifydata)) {
			showsetting('members_search_verify', array('verify', $verifydata), $_GET['verify'], 'mcheckbox');
		}
	}
	$yearselect = $monthselect = $dayselect = "<option value=\"\">".cplang('nolimit')."</option>\n";
	$yy=dgmdate(TIMESTAMP, 'Y');
	for($y=$yy; $y>=$yy-100; $y--) {
		$y = sprintf("%04d", $y);
		$yearselect .= "<option value=\"$y\" ".($_GET['birthyear'] == $y ? 'selected' : '').">$y</option>\n";
	}
	for($m=1; $m<=12; $m++) {
		$m = sprintf("%02d", $m);
		$monthselect .= "<option value=\"$m\" ".($_GET['birthmonth'] == $m ? 'selected' : '').">$m</option>\n";
	}
	for($d=1; $d<=31; $d++) {
		$d = sprintf("%02d", $d);
		$dayselect .= "<option value=\"$d\" ".($_GET['birthday'] == $d ? 'selected' : '').">$d</option>\n";
	}
	showsetting('members_search_birthday', '', '', '<select class="txt" name="birthyear" style="width:75px; margin-right:0">'.$yearselect.'</select> '.$lang['year'].' <select class="txt" name="birthmonth" style="width:75px; margin-right:0">'.$monthselect.'</select> '.$lang['month'].' <select class="txt" name="birthday" style="width:75px; margin-right:0">'.$dayselect.'</select> '.$lang['day']);

	loadcache('profilesetting');
	unset($_G['cache']['profilesetting']['uid']);
	unset($_G['cache']['profilesetting']['birthyear']);
	unset($_G['cache']['profilesetting']['birthmonth']);
	unset($_G['cache']['profilesetting']['birthday']);
	require_once libfile('function/profile');
	foreach($_G['cache']['profilesetting'] as $fieldid=>$value) {
		if(!$value['available'] || in_array($fieldid, array('birthprovince', 'birthdist', 'birthcommunity', 'resideprovince', 'residedist', 'residecommunity'))) {
			continue;
		}
		if($fieldid == 'gender') {
			$select = "<option value=\"\">".cplang('nolimit')."</option>\n";
			$select .= "<option value=\"0\">".cplang('members_edit_gender_secret')."</option>\n";
			$select .= "<option value=\"1\">".cplang('members_edit_gender_male')."</option>\n";
			$select .= "<option value=\"2\">".cplang('members_edit_gender_female')."</option>\n";
			showsetting($value['title'], '', '', '<select class="txt" name="gender">'.$select.'</select>');
		} elseif($fieldid == 'birthcity') {
			$elems = array('birthprovince', 'birthcity', 'birthdist', 'birthcommunity');
			showsetting($value['title'], '', '', '<div id="birthdistrictbox">'.showdistrict(array(0,0,0,0), $elems, 'birthdistrictbox', 1, 'birth').'</div>');
		} elseif($fieldid == 'residecity') {
			$elems = array('resideprovince', 'residecity', 'residedist', 'residecommunity');
			showsetting($value['title'], '', '', '<div id="residedistrictbox">'.showdistrict(array(0,0,0,0), $elems, 'residedistrictbox', 1, 'reside').'</div>');
		} elseif($fieldid == 'constellation') {
			$select = "<option value=\"\">".cplang('nolimit')."</option>\n";
			for($i=1; $i<=12; $i++) {
				$name = lang('space', 'constellation_'.$i);
				$select .= "<option value=\"$name\">$name</option>\n";
			}
			showsetting($value['title'], '', '', '<select class="txt" name="constellation">'.$select.'</select>');
		} elseif($fieldid == 'zodiac') {
			$select = "<option value=\"\">".cplang('nolimit')."</option>\n";
			for($i=1; $i<=12; $i++) {
				$option = lang('space', 'zodiac_'.$i);
				$select .= "<option value=\"$option\">$option</option>\n";
			}
			showsetting($value['title'], '', '', '<select class="txt" name="zodiac">'.$select.'</select>');
		} elseif($value['formtype'] == 'select' || $value['formtype'] == 'list') {
			$select = "<option value=\"\">".cplang('nolimit')."</option>\n";
			$value['choices'] = explode("\n",$value['choices']);
			foreach($value['choices'] as $option) {
				$option = trim($option);
				$select .= "<option value=\"$option\">$option</option>\n";
			}
			showsetting($value['title'], '', '', '<select class="txt" name="'.$fieldid.'">'.$select.'</select>');
		} else {
			showsetting($value['title'], '', '', '<input class="txt" name="'.$fieldid.'" />');
		}
	}
	showtagfooter('tbody');
	$_G['showsetting_multirow'] = 0;
	showsubmit('submit', $operation == 'clean' ? 'members_delete' : 'search', '', 'more_options');
	showtablefooter();
	showformfooter();
	showtagfooter('div');
}

function searchcondition($condition) {
	include_once libfile('class/membersearch');
	$ms = new membersearch();
	return $ms->filtercondition($condition);
}

function searchmembers($condition, $limit=2000, $start=0) {
	include_once libfile('class/membersearch');
	$ms = new membersearch();
	return $ms->search($condition, $limit, $start);
}

function countmembers($condition, &$urladd) {

	$urladd = '';
	foreach($condition as $k => $v) {
		if(in_array($k, array('formhash', 'submit', 'page')) || $v === '') {
			continue;
		}
		if(is_array($v)) {
			foreach($v as $vk => $vv) {
				if($vv === '') {
					continue;
				}
				$urladd .= '&'.$k.'['.$vk.']='.rawurlencode($vv);
			}
		} else {
			$urladd .= '&'.$k.'='.rawurlencode($v);
		}
	}
	include_once libfile('class/membersearch');
	$ms = new membersearch();
	return $ms->getcount($condition);
}

function shownewsletter() {
	global $lang;

	showtableheader();
	showsetting('members_newsletter_subject', 'subject', '', 'text');
	showsetting('members_newsletter_message', 'message', '', 'textarea');
	if($_GET['do'] == 'mobile') {
		showsetting('members_newsletter_system', 'system', 0, 'radio');
		showhiddenfields(array('notifymembers' => 'mobile'));
	} else {
		showsetting('members_newsletter_method', array('notifymembers', array(
			    array('email', $lang['email'], array('pmextra' => 'none', 'posttype' => '')),
			    array('notice', $lang['notice'], array('pmextra' => 'none', 'posttype' => '')),
			    array('pm', $lang['grouppm'], array('pmextra' => '', 'posttype' => 'none'))
			)), 'pm', 'mradio');
		showtagheader('tbody', 'posttype', '', 'sub');
		showsetting('members_newsletter_posttype', array('posttype', array(
				array(0, cplang('members_newsletter_posttype_text')),
				array(1, cplang('members_newsletter_posttype_html')),
			), TRUE), '0', 'mradio');
		showtagfooter('tbody');
		showtagheader('tbody', 'pmextra', true, 'sub');
		showsetting('members_newsletter_system', 'system', 0, 'radio');
		showtagfooter('tbody');
	}
	showsetting('members_newsletter_num', 'pertask', 100, 'text');
	showtablefooter();

}

function notifymembers($operation, $variable) {
	global $_G, $lang, $urladd, $conditions, $search_condition;

	if(!empty($_GET['current'])) {

		$subject = $message = '';
		if($settings = C::t('common_setting')->fetch($variable, true)) {
			$subject = $settings['subject'];
			$message = $settings['message'];
		}

		$setarr = array();
		foreach($_G['setting']['extcredits'] as $id => $value) {
			if(isset($_GET['extcredits'.$id])) {
				if($_GET['updatecredittype'] == 0) {
					$setarr['extcredits'.$id] = $_GET['extcredits'.$id];
				} else {
					$setarr[] = 'extcredits'.$id;
				}
			}
		}

	} else {

		$current = 0;
		$subject = $_GET['subject'];
		$message = $_GET['message'];
		$subject = dhtmlspecialchars(trim($subject));
		$message = trim(str_replace("\t", ' ', $message));
		$addmsg = '';
		if(($_GET['notifymembers'] && $_GET['notifymember']) && !($subject && $message)) {
			cpmsg('members_newsletter_sm_invalid', '', 'error');
		}

		if($operation == 'reward') {

			$serarr = array();
			if($_GET['updatecredittype'] == 0) {
				if(is_array($_GET['addextcredits']) && !empty($_GET['addextcredits'])) {
					foreach($_GET['addextcredits'] as $key => $value) {
						$value = intval($value);
						if(isset($_G['setting']['extcredits'][$key]) && !empty($value)) {
							$setarr['extcredits'.$key] = $value;
							$addmsg .= $_G['setting']['extcredits'][$key]['title'].": ".($value > 0 ? '<em class="xi1">+' : '<em class="xg1">')."$value</em> ".$_G['setting']['extcredits'][$key]['unit'].' &nbsp; ';
						}
					}
				}
			} else {
				if(is_array($_GET['resetextcredits']) && !empty($_GET['resetextcredits'])) {
					foreach($_GET['resetextcredits'] as $key => $value) {
						$value = intval($value);
						if(isset($_G['setting']['extcredits'][$key]) && !empty($value)) {
							$setarr[] = 'extcredits'.$key;
							$addmsg .= $_G['setting']['extcredits'][$key]['title'].': <em class="xg1">'.cplang('members_reward_clean').'</em> &nbsp; ';
						}
					}
				}
			}
			if($addmsg) {
				$addmsg  = ' &nbsp; <br /><br /><b>'.cplang('members_reward_affect').':</b><br \>'.$addmsg;
			}

			if(!empty($setarr)) {
				$limit = 2000;
				set_time_limit(0);
				$i = 0;
				while(true) {
					$uids = searchmembers($search_condition, $limit, $i*$limit);
					$allcount = C::t('common_member_count')->fetch_all($uids);
					$insertmember = array_diff($uids, array_keys($allcount));
					foreach($insertmember as $uid) {
						C::t('common_member_count')->insert(array('uid' => $uid));
					}
					if($_GET['updatecredittype'] == 0) {
						C::t('common_member_count')->increase($uids, $setarr);
					} else {
						C::t('common_member_count')->clear_extcredits($uids, $setarr);
					}
					if(count($uids) < $limit) break;
					$i++;
				}
			} else {
				cpmsg('members_reward_invalid', '', 'error');
			}

			if(!$_GET['notifymembers']) {
				cpmsg('members_reward_succeed', '', 'succeed');
			}

		} elseif ($operation == 'confermedal') {

			$medals = $_GET['medals'];
			if(!empty($medals)) {
				$medalids = array();
				foreach($medals as $key => $medalid) {
					$medalids[] = $key;
				}

				$medalsnew = $comma = '';
				$medalsnewarray = $medalidarray = array();
				foreach(C::t('forum_medal')->fetch_all_by_id($medalids) as $medal) {
					$medal['status'] = empty($medal['expiration']) ? 0 : 1;
					$medal['expiration'] = empty($medal['expiration'])? 0 : TIMESTAMP + $medal['expiration'] * 86400;
					$medal['medal'] = $medal['medalid'].(empty($medal['expiration']) ? '' : '|'.$medal['expiration']);
					$medalsnew .= $comma.$medal['medal'];
					$medalsnewarray[] = $medal;
					$medalidarray[] = $medal['medalid'];
					$comma = "\t";
				}

				$uids = searchmembers($search_condition);
				if($uids) {
					foreach(C::t('common_member_field_forum')->fetch_all($uids) as $uid => $medalnew) {
						$usermedal = array();
						$addmedalnew = '';
						if(empty($medalnew['medals'])) {
							$addmedalnew = $medalsnew;
						} else {
							foreach($medalidarray as $medalid) {
								$usermedal_arr = explode("\t", $medalnew['medals']);
								foreach($usermedal_arr AS $key => $medalval) {
									list($usermedalid,) = explode("|", $medalval);
									$usermedal[] = $usermedalid;
								}
								if(!in_array($medalid, $usermedal)){
									$addmedalnew .= $medalid."\t";
								}
							}
							$addmedalnew .= $medalnew['medals'];
						}
						C::t('common_member_field_forum')->update($medalnew['uid'], array('medals' => $addmedalnew), true);
						foreach($medalsnewarray as $medalnewarray) {
							$data = array(
								'uid' => $medalnew['uid'],
								'medalid' => $medalnewarray['medalid'],
								'type' => 0,
								'dateline' => $_G['timestamp'],
								'expiration' => $medalnewarray['expiration'],
								'status' => $medalnewarray['status'],
							);
							C::t('forum_medallog')->insert($data);
							C::t('common_member_medal')->insert(array('uid' => $medalnew['uid'], 'medalid' => $medalnewarray['medalid']), 0, 1);
						}
					}
				}
			}

			if(!$_GET['notifymember']) {
				cpmsg('members_confermedal_succeed', '', 'succeed');
			}
		} elseif ($operation == 'confermagic') {
			$magics = $_GET['magic'];
			$magicnum = $_GET['magicnum'];
			if($magics) {
				require_once libfile('function/magic');
				$limit = 200;
				set_time_limit(0);
				for($i=0; $i > -1; $i++) {
					$uids = searchmembers($search_condition, $limit, $i*$limit);

					foreach($magics as $magicid) {
						$uparray = $insarray = array();
						if(empty($magicnum[$magicid])) {
							continue;
						}
						$query = C::t('common_member_magic')->fetch_all($uids ? $uids : -1, $magicid);
						foreach($query as $row) {
							$uparray[] = $row['uid'];
						}
						if($uparray) {
							C::t('common_member_magic')->increase($uparray, $magicid, array('num' => $magicnum[$magicid]));
						}
						$insarray = array_diff($uids, $uparray);
						if($insarray) {
							$sqls = array();
							foreach($insarray as $uid) {
								C::t('common_member_magic')->insert(array(
									'uid' => $uid,
									'magicid' => $magicid,
									'num' => $magicnum[$magicid]
								));
							}
						}
						foreach($uids as $uid) {
							updatemagiclog($magicid, '3', $magicnum[$magicid], '', $uid);
						}
					}
					if(count($uids) < $limit) break;
				}
			}
		}

		C::t('common_setting')->update($variable, array('subject' => $subject, 'message' => $message));
	}

	$pertask = intval($_GET['pertask']);
	$current = $_GET['current'] ? intval($_GET['current']) : 0;
	$continue = FALSE;

	if(!function_exists('sendmail')) {
		include libfile('function/mail');
	}
	if($_GET['notifymember'] && in_array($_GET['notifymembers'], array('pm', 'notice', 'email', 'mobile'))) {
		$uids = searchmembers($search_condition, $pertask, $current);

		require_once libfile('function/discuzcode');
		$message = in_array($_GET['notifymembers'], array('email','notice')) && $_GET['posttype'] ? discuzcode($message, 1, 0, 1, '', '' ,'' ,1) : discuzcode($message, 1, 0);
		$pmuids = array();
		if($_GET['notifymembers'] == 'pm') {
			$membernum = countmembers($search_condition, $urladd);
			$gpmid = $_GET['gpmid'];
			if(!$gpmid) {
				$pmdata = array(
						'authorid' => $_G['uid'],
						'author' => !$_GET['system'] ? $_G['member']['username'] : '',
						'dateline' => TIMESTAMP,
						'message' => ($subject ? '<b>'.$subject.'</b><br /> &nbsp; ' : '').$message.$addmsg,
						'numbers' => $membernum
					);
				$gpmid = C::t('common_grouppm')->insert($pmdata, true);
			}
			$urladd .= '&gpmid='.$gpmid;
		}
		$members = C::t('common_member')->fetch_all($uids);
		if($_GET['notifymembers'] == 'mobile') {
			$toUids = array_keys($members);
			if($_G['setting']['cloud_status'] && !empty($toUids)) {
				try {
					$noticeService = Cloud::loadClass('Service_Client_Notification');
					$fromType = $_GET['system'] ? 1 : 2;
					$noticeService->addSiteMasterUserNotify($toUids, $subject, $message, $_G['uid'], $_G['username'], $fromType, TIMESTAMP);
				} catch (Cloud_Service_Client_RestfulException $e) {
					cpmsg('['.$e->getCode().']'.$e->getMessage(), '', 'error');
				}

			}
		} else {
			foreach($members as $member) {
				if($_GET['notifymembers'] == 'pm') {
					C::t('common_member_grouppm')->insert(array(
						'uid' => $member['uid'],
						'gpmid' => $gpmid,
						'status' => 0
					), false, true);
					$newpm = setstatus(2, 1, $member['newpm']);
					C::t('common_member')->update($member['uid'], array('newpm'=>$newpm));
				} elseif($_GET['notifymembers'] == 'notice') {
					notification_add($member['uid'], 'system', 'system_notice', array('subject' => $subject, 'message' => $message.$addmsg, 'from_id' => 0, 'from_idtype' => 'sendnotice'), 1);
				} elseif($_GET['notifymembers'] == 'email') {
					if(!sendmail("$member[username] <$member[email]>", $subject, $message.$addmsg)) {
						runlog('sendmail', "$member[email] sendmail failed.");
					}
				}

				$log = array();
				if($_GET['updatecredittype'] == 0) {
					foreach($setarr as $key => $val) {
						if(empty($val)) continue;
						$val = intval($val);
						$id = intval($key);
						$id = !$id && substr($key, 0, -1) == 'extcredits' ? intval(substr($key, -1, 1)) : $id;
						if(0 < $id && $id < 9) {
								$log['extcredits'.$id] = $val;
						}
					}
					$logtype = 'RPR';
				} else {
					foreach($setarr as $val) {
						if(empty($val)) continue;
						$id = intval($val);
						$id = !$id && substr($val, 0, -1) == 'extcredits' ? intval(substr($val, -1, 1)) : $id;
						if(0 < $id && $id < 9) {
							$log['extcredits'.$id] = '-1';
						}
					}
					$logtype = 'RPZ';
				}
				include_once libfile('function/credit');
				credit_log($member['uid'], $logtype, $member['uid'], $log);

				$continue = TRUE;
			}
		}
	}

	$newsletter_detail = array();
	if($continue) {
		$next = $current + $pertask;
		$newsletter_detail = array(
			'uid' => $_G['uid'],
			'current' => $current,
			'next' => $next,
			'search_condition' => serialize($search_condition),
			'action' => "action=members&operation=$operation&{$operation}submit=yes&current=$next&pertask=$pertask&system={$_GET['system']}&posttype={$_GET['posttype']}&notifymember={$_GET['notifymember']}&notifymembers=".rawurlencode($_GET['notifymembers']).$urladd
		);
		save_newsletter('newsletter_detail', $newsletter_detail);

		$logaddurl = '';
		foreach($setarr as $k => $v) {
			if($_GET['updatecredittype'] == 0) {
				$logaddurl .= '&'.$k.'='.$v;
			} else {
				$logaddurl .= '&'.$v.'=-1';
			}
		}
		$logaddurl .= '&updatecredittype='.$_GET['updatecredittype'];

		cpmsg("$lang[members_newsletter_send]: ".cplang('members_newsletter_processing', array('current' => $current, 'next' => $next, 'search_condition' => serialize($search_condition))), "action=members&operation=$operation&{$operation}submit=yes&current=$next&pertask=$pertask&system={$_GET['system']}&posttype={$_GET['posttype']}&notifymember={$_GET['notifymember']}&notifymembers=".rawurlencode($_GET['notifymembers']).$urladd.$logaddurl, 'loadingform');
	} else {
		del_newsletter('newsletter_detail');

		if($operation == 'reward' && $_GET['notifymembers'] == 'pm') {
			$message = '';
		} else {
			$message = '_notify';
		}
		cpmsg('members'.($operation ? '_'.$operation : '').$message.'_succeed', '', 'succeed');
	}

}

function banlog($username, $origgroupid, $newgroupid, $expiration, $reason, $status = 0) {
	global $_G, $_POST;
	$cloud_apps = dunserialize($_G['setting']['cloud_apps']);
	if (isset($_POST['bannew']) && $_POST['formhash'] && $cloud_apps['security']['status'] == 'normal') {
		$securityService = Cloud::loadClass('Service_Security');
		if ($_POST['bannew']) {
			$securityService->logBannedMember($username, $reason);
		} else {
			$securityService->updateMemberRecover($username);
		}
    }
	writelog('banlog', dhtmlspecialchars("$_G[timestamp]\t{$_G[member][username]}\t$_G[groupid]\t$_G[clientip]\t$username\t$origgroupid\t$newgroupid\t$expiration\t$reason\t$status"));
}

function selectday($varname, $dayarray) {
	global $lang;
	$selectday = '<select name="'.$varname.'">';
	if($dayarray && is_array($dayarray)) {
		foreach($dayarray as $day) {
			$langday = $day.'_day';
			$daydate = $day ? '('.dgmdate(TIMESTAMP + $day * 86400).')' : '';
			$selectday .= '<option value='.$day.'>'.$lang[$langday].'&nbsp;'.$daydate.'</option>';
		}
	}
	$selectday .= '</select>';

	return $selectday;
}

function accessimg($access) {
	return $access == -1 ? '<img src="static/image/common/access_disallow.gif" />' :
		($access == 1 ? '<img src="static/image/common/access_allow.gif" />' : '<img src="static/image/common/access_normal.gif" />');
}

function connectunbind($member) {
	global $_G;
	if(!$member['conopenid']) {
		return;
	}
	$_G['member'] = array_merge($_G['member'], $member);

	C::t('#qqconnect#connect_memberbindlog')->insert(array('uid' => $member['uid'], 'uin' => $member['conopenid'], 'type' => '2', 'dateline' => $_G['timestamp']));
	C::t('common_member')->update($member['uid'], array('conisbind'=>0));
	C::t('#qqconnect#common_member_connect')->delete($member['uid']);
}

function save_newsletter($cachename, $data) {
	C::t('common_cache')->insert(array('cachekey' => $cachename, 'cachevalue' => serialize($data), 'dateline' => TIMESTAMP), false, true);
}

function del_newsletter($cachename) {
	C::t('common_cache')->delete($cachename);
}

function get_newsletter($cachename) {
	foreach(C::t('common_cache')->fetch_all($cachename) as $result) {
		$data = $result['cachevalue'];
	}
	return $data;
}

?>