<?php
//  AcmlmBoard XD support - Post functions
if (!defined('BLARG')) trigger_error();

function ParseThreadTags($title) {
    $tags='';
	preg_match_all('/\[(.*?)\]/', $title, $matches);
	foreach($matches[1] as $tag) {

	    if(strpos($title,'['.$tag.']')!==FALSE)
		    $title = str_replace('['.$tag.']', '', $title);

		$tag = htmlspecialchars(strtolower($tag));

		//Start at a hue that makes "18" red.
		$hash = -105;
		for($i = 0; $i < strlen($tag); $i++)
			$hash += ord($tag[$i]);

		//That multiplier is only there to make "nsfw" and "18" the same color.
		$color = 'hsl('.(($hash * 57) % 360).', 70%, 40%)';

		$tags .= '<span class=\"threadTag\" style=\"background-color: '.$color.';\">'.$tag.'</span>';
	}

		$tags = ' '.$tags;

	$title = str_replace('<', '&lt;', $title);
	$title = str_replace('>', '&gt;', $title);
	return [trim($title), $tags];
}

function filterPollColors($input) {
	return preg_replace('@[^#0123456789abcdef]@si', '', $input);
}
/*risoluzione 9337:
 loadBlockLayouts( $blocklayouts, $loguserid)
con aggiunta nel controllo isset($blocklayouts) di "|| !isset($loguserid)" e del riturn di $blocklayouts
From Giosh96  */
function loadBlockLayouts( $blocklayouts, $loguserid) {

	if(isset($blocklayouts) || !isset($loguserid))
		return;

	$rBlocks = Query('select * from {blockedlayouts} where blockee = {0}', $loguserid);
	$blocklayouts = [];

	while($block = Fetch($rBlocks))
		$blocklayouts[$block['user']] = 1;

	return $blocklayouts;
}

function getSyndrome($activity, $syndromes) {
	include __DIR__.'/syndromes.php';
	$soFar = '';
	foreach($syndromes as $minAct => $syndrome)
		if($activity >= $minAct)
			$soFar = '<em style=\"color: '.$syndrome[1].';\">'.$syndrome[0].'</em><br/>';
	return $soFar;
}

function applyTags($text, $tags) {
	if(!stristr($text, '&'))
		return $text;
	$s = $text;
	foreach($tags as $tag => $val)

	    if(strpos($s,'&'.$tag.'&')!==FALSE)
		    $s = str_replace('&'.$tag.'&', $val, $s);

	if(is_numeric($tags['postcount']))
		$s = preg_replace_callback('@&(\d+)&@si', [new MaxPosts($tags), 'max_posts_callback'], $s);
	else
		$s = preg_replace("'&(\d+)&'si", 'preview', $s);
	return $s;
}

class MaxPosts {
	var $tags;
	function __construct($tags) {
		$this->tags = $tags;
	}

	function max_posts_callback($results) {
		return max($results[1] - $this->tags['postcount'], 0);
	}
}

/*risoluzione 9337:
  getActivity($id, $activityCache)
From Giosh96  */
function getActivity($id, $activityCache) {

	if(!isset($activityCache[$id]))
		$activityCache[$id] = FetchResult('select count(*) from {posts} where user = {0} and date > {1}', $id, (time() - 86400));

	return $activityCache[$id];
}

function makePostText($post, $poster) {
	$noSmilies = $post['options'] & 2;

	//Do Ampersand Tags
	$tags = [
		'postnum' => $post['num'],
		'postcount' => $poster['posts'],
		'numdays' => floor((time()-$poster['regdate'])/86400),
		'date' => formatdate($post['date']),
		'rank' => GetRank($poster['rankset'], $poster['posts']),
	];
	//$bucket = 'amperTags'; include(__DIR__.'/pluginloader.php');

	if(isset($poster['signature']))
		if(!$poster['signsep'])
			$separator = '<br/>_________________________<br/>';
		else
			$separator = '<br/>';

	$attachblock = '';
	if (isset($post['has_attachments'])){
		if (isset($post['preview_attachs'])) {
			$ispreview = true;
			$fileids = array_keys($post['preview_attachs']);
			$attachs = Query('SELECT id,filename,physicalname,description,downloads 
				FROM {uploadedfiles}
				WHERE id IN ({0c})',
				$fileids);
		} else {
			$ispreview = false;
			$attachs = Query('SELECT id,filename,physicalname,description,downloads 
				FROM {uploadedfiles}
				WHERE parenttype={0} AND parentid={1} AND deldate=0
				ORDER BY filename',
				'post_attachment', $post['id']);
		}

		while ($attach = Fetch($attachs)) {
			$url = URL_ROOT.'get.php?id='.htmlspecialchars($attach['id']);
			$linkurl = $ispreview ? '#' : $url;
			$filesize = filesize(DATA_DIR.'uploads/'.$attach['physicalname']);
			
			$attachblock .= '<br/><div class="post_attachment">';
			
			$fext = strtolower(substr($attach['filename'], -4));
			if ($fext == '.png' || $fext == '.jpg' || $fext == 'jpeg' || $fext == '.gif') {
				$alt = htmlspecialchars($attach['filename']).' &mdash; '.BytesToSize($filesize).', viewed '.Plural($attach['downloads'], 'time');

				$attachblock .= '<a href="'.$linkurl.'"><img src="'.$url.'" alt="'.$alt.'" title="'.$alt.'" style="max-width:300px; max-height:300px;"></a>';
			} else {
				$link = '<a href="'.$linkurl.'">'.htmlspecialchars($attach['filename']).'</a>';

				$desc = htmlspecialchars($attach['description']);
				if (isset($desc)) $desc .= '<br/>';

				$attachblock .= '<strong>'.__('Attachment: ').$link.'</strong><br/>';
				$attachblock .= '<div class="smallFonts">'.$desc;
				$attachblock .= BytesToSize($filesize).__(' &mdash; Downloaded ').Plural($attach['downloads'], 'time').'</div>';
			}

			$attachblock .= '</div>';
		}
	}

	$postText = $poster['postheader'].$post['text'].$attachblock.$separator.$poster['signature'];
	$postText = ApplyTags($postText, $tags);
	$postText = CleanUpPost($postText, $noSmilies, false);

	return $postText;
}

define('POST_NORMAL', 0);			// standard post box
define('POST_PM', 1);				// PM post box
define('POST_DELETED_SNOOP', 2);	// post box with close/undelete (for mods 'view deleted post' feature)
define('POST_SAMPLE', 3);			// sample post box (profile sample post, newreply post preview, etc)
define('POST_PROFILE', 4);		  // profile about box. This is going to replace the bio field

// $post: post data (typically returned by SQL queries or forms)
// $type: one of the POST_XXX constants
// $params: an array of extra parameters, depending on the post box type. Possible parameters:
//		* tid: the ID of the thread the post is in (POST_NORMAL and POST_DELETED_SNOOP only)
//		* fid: the ID of the forum the thread containing the post is in (POST_NORMAL and POST_DELETED_SNOOP only)
// 		* threadlink: if set, a link to the thread is added next to 'Posted on blahblah' (POST_NORMAL and POST_DELETED_SNOOP only)
//		* noreplylinks: if set, no links to newreply.php (Quote/ID) are placed in the metabar (POST_NORMAL only)


/*risoluzione 9337:
da
function makePost($post, $type, $params=[]) {
	global $loguser, $loguserid, $usergroups, $isBot, $blocklayouts;
a
makePost($post, $type, $isBot,  $blocklayouts, $loguser, $loguserid,$usergroups, $params=[])
con controllo
if(!isset($loguser) || !isset($loguserid) || !isset($usergroups))
	    return false;
From Giosh96  */

function makePost($post, $type, $isBot,  $blocklayouts, $loguser, $loguserid,$usergroups, $params=[]) {
	if(!isset($loguser) || !isset($loguserid) || !isset($usergroups))
	    return false;
	$poster = getDataPrefix($post, 'u_');
	$post['userlink'] = UserLink($poster);
	LoadBlockLayouts();
	$pltype = Settings::get('postLayoutType');
	$isBlocked = $poster['globalblock'] || $loguser['blocklayouts'] || $post['options'] & 1 || isset($blocklayouts[$poster['id']]);
	$post['type'] = $type;
	$post['formattedDate'] = formatdate($post['date']);
	if (!HasPermission('admin.viewips')) $post['ip'] = '';
	else $post['ip'] = htmlspecialchars($post['ip']); // TODO IP formatting?

	if($post['deleted'] && $type == POST_NORMAL) {
		$post['deluserlink'] = UserLink(getDataPrefix($post, 'du_'));
		$post['delreason'] = htmlspecialchars($post['reason']);
		$links = [];
		if (HasPermission('mod.deleteposts', $params['fid']))
			$links['undelete'] = actionLinkTag(__('Undelete'), 'editpost', $post['id'], 'delete=2&key='.$loguser['token']);
		if (HasPermission('mod.deleteposts', $params['fid']) || $poster['id'] == $loguserid)
			$links['view'] = '<a href=\"#\" onclick=\"replacePost('.$post['id'].',true); return false;\">'.__('View').'</a>';
		$post['links'] = $links;
		RenderTemplate('postbox_deleted', ['post' => $post]);
		return;
	}
	$links = [];
	if ($type != POST_SAMPLE || $type != POST_PROFILE) {
		$forum = $params['fid'];
		$thread = $params['tid'];
		$notclosed = (!$post['closed'] || HasPermission('mod.closethreads', $forum));
		$extraLinks = [];

		if (!isset($isBot)) {
			if ($type == POST_DELETED_SNOOP) {
				if ($notclosed && HasPermission('mod.deleteposts', $forum))
					$links['undelete'] = actionLinkTag(__('Undelete'), 'editpost', $post['id'], 'delete=2&key='.$loguser['token']);

				$links['close'] = '<a href=\"#\" onclick=\"replacePost('.$post['id'].',false); return false;\">'.__('Close').'</a>';
			} else if ($type == POST_NORMAL) {
				if (isset($notclosed)) {
					if ($loguserid && HasPermission('forum.postreplies', $forum) && !$params['noreplylinks'])
						$links['quote'] = actionLinkTag(__('Quote'), 'newreply', $thread, 'quote='.$post['id']);

					if (($poster['id'] == $loguserid && HasPermission('user.editownposts')) || HasPermission('mod.editposts', $forum))
						$links['edit'] = actionLinkTag(__('Edit'), 'editpost', $post['id']);

					if (($poster['id'] == $loguserid && HasPermission('user.deleteownposts')) || HasPermission('mod.deleteposts', $forum)) {
						if ($post['id'] != $post['firstpostid']) {
							//$link = htmlspecialchars(actionLink('editpost', $post['id'], 'delete=1&key='.$loguser['token']));
							//$onclick = " onclick=\"deletePost(this);return false;\"";
							$links['delete'] = '<a href=\"{$link}\"{$onclick}>'.__('Delete').'</a>';
						}
					}
					if (HasPermission('mod.deleteposts', $forum) && $post['id'] != $post['firstpostid']) {
							//$link = htmlspecialchars(actionLink('editpost', $post['id'], 'delete=3&key='.$loguser['token']));
							//$onclick =
							//	" onclick= if(!confirm(\'Really wipe this post? This action can\'t be undone\'))return false;";
							$links['delete'] = '<a href=\"{$link}\"{$onclick}>'.__('Wipe').'</a>';
					}
					if (HasPermission('user.reportposts'))
						$links['report'] = actionLinkTag(__('Report'), 'reportpost', $post['id']);
				}
               // $bucket = 'topbar'; include(__DIR__.'/pluginloader.php');
			}
			$links['extra'] = $extraLinks;
		}
		//Threadlinks for listpost.php
		if (isset($params['threadlink'])) {
			$thread = [];
			$thread['id'] = $post['thread'];
			$thread['title'] = $post['threadname'];
			$thread['forum'] = $post['fid'];
			$post['threadlink'] = makeThreadLink($thread);
		} else
			$post['threadlink'] = '';

		$post = printRevisions($post, $forum);
	}
	$post['links'] = $links;
    $sidebar = [];
    $poster['title'] = preg_replace("@Affected by \'?.*?Syndrome\'?@si", '', $poster['title']);
	$sidebar['rank'] = GetRank($poster['rankset'], $poster['posts']);
	if(isset($poster['title']))
		$sidebar['title'] = strip_tags(CleanUpPost($poster['title'], '', true), '<b><strong><i><em><span><s><del><img><a><br/><br><small>');
	else
		$sidebar['title'] = htmlspecialchars($usergroups[$poster['primarygroup']]['title']);
	$sidebar['syndrome'] = GetSyndrome(getActivity($poster['id']));
	$array = managePostMood($post, $sidebar, $poster);
    $post = $array[0];
    //$sidebar = $array[1]; $pic = $array[2];
    $array = modificaVars($poster, $post, $loguser);
    //$sidebar= $array[0];
    $post = $array[0];
    //$bucket = $array[1];
	if(!isset($isBlocked)) {
        funIsBlocked($poster, $pltype);
	} else {
		$poster['postheader'] = '';
		$poster['signature'] = '';
	}
	$post['contents'] = makePostText($post, $poster);
    RenderTemplate('postbox', ['post' => $post]);
}

function funIsBlocked($poster, $pltype) {
    $poster['postheader'] = $pltype ? trim($poster['postheader']) : '';
    $poster['signature'] = trim($poster['signature']);
    $post['haslayout'] = $poster['postheader']?1:0;
    $post['fulllayout'] = $poster['fulllayout'] && $post['haslayout'] && ($pltype==2);
    if (!$post['haslayout'] && $poster['signature'])
        $poster['signature'] = '<div class="signature">'.$poster['signature'].'</div>';
    return $post;
}
function printRevisions($post,$forum) {
    if(isset($post['revision'])) {
        $ru_link = UserLink(getDataPrefix($post, 'ru_'));
        $revdetail = ' '.format(__('by {0} on {1}'), $ru_link, formatdate($post['revdate']));
        if (HasPermission('mod.editposts', $forum))
            $post['revdetail'] = '<a href=\"javascript:void(0);\" onclick=\"showRevisions('.$post['id'].')\">'.Format(__('rev. {0}'), $post['revision']).'</a>'.$revdetail;
        else
            $post['revdetail'] = Format(__('rev. {0}'), $post['revision']).$revdetail;
    }

    return $post;
}

function managePostMood($post, $sidebar, $poster) {
    if($post['mood'] > 0) {
        if(file_exists(DATA_DIR.'avatars/'.$poster['id'].'_'.$post['mood']))
            $sidebar['avatar'] = '<img src=\"'.DATA_URL.'avatars/'.$poster['id'].'_'.$post['mood']."\" alt=\"\">";
    } else if (isset($poster['picture'])) {
        $pic = str_replace('$root/', DATA_URL, $poster['picture']);
        $sidebar['avatar'] = "<img src=\"".htmlspecialchars($pic)."\" alt=\"\">";
    }

    return array( $post, $sidebar, $pic);
}

function modificaVars($poster, $post, $loguser){
    $lastpost = ($poster['lastposttime'] ? timeunits(time() - $poster['lastposttime']) : 'none');
	$lastview = timeunits(time() - $poster['lastactivity']);
	if(!$post['num']) $sidebar['posts'] = $poster['posts'];
    else $sidebar['posts'] = $post['num'].'/'.$poster['posts'];
	$sidebar['since'] = cdate($loguser['dateformat'], $poster['regdate']);
	$sidebar['lastpost'] = $lastpost;
	$sidebar['lastview'] = $lastview;
	$sidebar['posterID'] = $poster['id'];
	if($poster['lastactivity'] > time() - 300)
        $sidebar['isonline'] = __('User is <strong>online</strong>');
	$sidebarExtra = [];
	$bucket = 'sidebar'; include __DIR__.'/pluginloader.php';
	$sidebar['extra'] = $sidebarExtra;
	$post['sidebar'] = $sidebar;
	$post['haslayout'] = false;
	$post['fulllayout'] = false;

	return array($sidebar, $post, $bucket);

}