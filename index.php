<?php

$starttime = microtime(true);
define('BLARG', 1);

// change this to change your board's default page
define('MAIN_PAGE', 'index');

$ajaxPage = false;
if(isset($_GET['ajax']))
	$ajaxPage = true;

require(__DIR__ . '/lib/common.php');

$layout_crumbs = '';
$layout_actionlinks = '';

if (isset($_GET['forcelayout'])) {
	setcookie('forcelayout', (int)$_GET['forcelayout'], time()+365*24*3600, URL_ROOT, '', false, true);
	die(header('Location: '.$_SERVER['HTTP_REFERER']));
}

$layout_birthdays = getBirthdaysText();

$tpl->assign('logusername', htmlspecialchars($loguser['displayname'] ?: $loguser['name']));
$tpl->assign('loguser', $loguser);
$tpl->assign('loguserlink', UserLink($loguser));

$metaStuff = [
	'description' => Settings::get('metaDescription'),
	'tags' => Settings::get('metaTags')
];


//Use buffering to draw the page.
//Useful to have it disabled when running from the terminal.
$useBuffering = true;
//Support for running pages from the terminal.
if(isset($argv)) {
	$_GET = [];

	$_SERVER = [];
	$_SERVER['REMOTE_ADDR'] = '0.0.0.0';

	$ajaxPage = true;
	$useBuffering = false;
}


//=======================
// Do the page

$match = $router->match();

ob_start();
$layout_crumbs = '';

if($useBuffering == true)
	ob_start();

$fakeerror = false;
if ($loguser['flags'] & 0x2) {
	if (rand(0,100) <= 75) {
		Alert('Could not load requested page: failed to connect to the database. Try again later.', 'Error');
		$fakeerror = true;
	}
}

if ($fakeerror == false) {
	try {
		// Throw the 404 page if we don't have a match already.
		if ($match === false)
			require_once(__DIR__.'/pages/404.php');
		else {
			// Set up the stuff for our page loader.
			$pageName = $match['target'];
			$pageParams = $match['params'];

			// Check first for plugin pages.
			if(array_key_exists($pageName, $pluginpages)) {
				// TODO: Make this cleaner than a hack.
				$plugin = $pluginpages[$pageName];
				$self = $plugins[$plugin];

				$addonWWXD = __DIR__ . '/plugins/' .$self['dir'].'/pages/'.$pageName.'.php';
				$addonABXD = __DIR__ . '/plugins/' .$self['dir'].'/page_'.$pageName.'.php';

				if (file_exists($addonWWXD))
					require_once($addonWWXD);
				elseif (file_exists($addonABXD))
					require_once($addonABXD);
				else
					require_once(__DIR__.'/pages/404.php');
			} else {
				// Check now for core pages.
				$page = __DIR__ . '/pages/' .$pageName.'.php';

				if (file_exists($page))
					require_once($page);
				else
					require_once(__DIR__.'/pages/404.php');
			}
		}
	} catch(KillException $e) {
		// Nothing. Just ignore this exception.
	}
}

if($ajaxPage == true) {
	if($useBuffering == true) {
		header('Content-Type: text/plain');
		ob_end_flush();
	}
	die();
}

$layout_contents = ob_get_contents();
ob_end_clean();

//Do these things only if it's not an ajax page.
include(__DIR__ . '/lib/views.php');
setLastActivity();

//=======================
// Panels and footer

require(__DIR__ . '/layouts/userpanel.php');
require(__DIR__ . '/layouts/menus.php');

$mobileswitch = '';

if (Settings::get('defaultLayout') !== 'mobile') {
	if (isset($mobileLayout)) $mobileswitch .= 'Mobile view <noscript>(Requires JS enabled for it to work.)</noscript> - ';
	if (isset($_COOKIE['forcelayout']) && $_COOKIE['forcelayout']) $mobileswitch .= '<a href="?forcelayout=0" rel="nofollow">Auto view</a>';
	else if (isset($mobileLayout)) $mobileswitch .= '<a href="?forcelayout=-1" rel="nofollow">Force normal view</a>';
	else $mobileswitch .= '<a href="?forcelayout=1" rel="nofollow">Force mobile view [BETA]</a>';
}

//=======================
// Notification bars

$notifications = getNotifications();

ob_start();
$bucket = 'userBar'; include('./lib/pluginloader.php');
/*
if($rssBar)
{
	write("
	<div style=\"float: left; width: {1}px;\">&nbsp;</div>
	<div id=\"rss\">
		{0}
	</div>
", $rssBar, $rssWidth + 4);
}*/
$bucket = 'topBar'; include('./lib/pluginloader.php');
$layout_bars = ob_get_contents();
ob_end_clean();



//=======================
// Misc stuff

$layout_time = formatdatenow();
$layout_onlineusers = getOnlineUsersText();
$layout_birthdays = getBirthdaysText();
$layout_views = '<span id="viewCount">'.number_format($misc['views']).'</span> '.__('views');
$layout_description = htmlspecialchars(Settings::get('metaDescription'));
$layout_boardtitle = htmlspecialchars(Settings::get('boardname'));

$layout_title = htmlspecialchars(Settings::get('boardname'));
if($title != '')
	$layout_title .= ' &raquo; '.$title;


//=======================
// Board logo and theme

if (file_exists(URL_ROOT.'/themes/$theme/logo.png')) {
	$logo = '<img id="theme_banner" src="themes/$theme/logo.png" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/themes/$theme/logo.jpg')) {
	$logo = '<img id="theme_banner" src="themes/$theme/logo.jpg" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/themes/$theme/logo.jpeg')) {
	$logo = '<img id="theme_banner" src="themes/$theme/logo.jpeg" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/themes/$theme/logo.gif')) {
	$logo = '<img id="theme_banner" src="themes/$theme/logo.gif" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/img/logo.png')) {
	$logo = '<img id="theme_banner" src="img/logo.png" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/img/logo.jpg')) {
	$logo = '<img id="theme_banner" src="img/logo.jpg" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/img/logo.jpeg')) {
	$logo = '<img id="theme_banner" src="img/logo.jpeg" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else if (file_exists(URL_ROOT.'/img/logo.gif')) {
	$logo = '<img id="theme_banner" src="img/logo.gif" alt="'.$layout_boardtitle.'" title="'.$layout_boardtitle.'">';
} else {
	$logo = '<h1>'.$layout_boardtitle.'</h1><h3>'.$layout_description.'</h3>';
}

function checkForImage(&$image, $external, $file) {
	global $dataDir, $dataUrl;
	if(isset($image)) return;
	if(isset($external)) {
		if(file_exists($dataDir.$file))
			$image = $dataUrl.$file;
	} else {
		if(file_exists($file))
			$image = resourceLink($file);
	}
}

checkForImage($favicon, true, 'logos/favicon.gif');
checkForImage($favicon, true, 'logos/favicon.ico');
checkForImage($favicon, false, 'img/favicon.ico');

$themefile = "themes/$theme/style.css";
if(!file_exists(__DIR__ . 'index.php/' .$themefile))
	$themefile = "themes/$theme/style.php";


$layout_credits =
'<img src="'.resourceLink('img/poweredbywwxd.png').'" style="float: left; margin-right: 3px;">WorldWeb XD 0.0.1 &middot; by MaorNinja322, Repflez <a href="'.pageLink('credits').'">et al</a><br>
Based off of Blargboard by StapleButter & ABXD by Dirbaio, Kawa & co.<br>';


$layout_contents = "<div id=\"page_contents\">$layout_contents</div>";

//=======================
// Print everything!

$perfdata = 'Page rendered in '.sprintf('%.03f',microtime(true)-$starttime).' seconds (with '.$queries.' SQL queries and '.sprintf('%.03f',memory_get_usage() / 1024).'K of RAM)';

?>
<!doctype html>
<html>
<head>
	<title><?php print $layout_title; ?></title>

	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=10">
	<meta name="description" content="<?php print $metaStuff['description']; ?>">
	<meta name="keywords" content="<?php print $metaStuff['tags']; ?>">
	<?php if (isset($mobileLayout)) { ?>
		<meta name="viewport" content="user-scalable=yes, initial-scale=1.0, width=device-width">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<script src="<?php echo resourceLink('js/mobile.js'); ?>"></script>
		<?php if (isset($oldAndroid)) { ?>
			<style>
				#mobile-sidebar { height: auto!important; max-height: none!important; }
				#realbody { max-height: none!important; max-width: none!important; overflow: scroll!important; }
			</style>
		<?php }
	} ?>

	<base href="<?php echo getServerURLNoSlash($ishttps)?>" /><!--[if IE]></base><![endif]-->
	<link rel="shortcut icon" href="<?php print $favicon;?>">
	<link rel="stylesheet" href="<?php print resourceLink("css/common.css");?>">
	<link rel="stylesheet" id="theme_css" href="<?php print resourceLink($themefile); ?>">
	<link rel="stylesheet" href="<?php print resourceLink('css/font-awesome.min.css'); ?>">
    <link rel="stylesheet" href="https://opensource.keycdn.com/fontawesome/4.6.3/font-awesome.min.css" integrity="sha384-Wrgq82RsEean5tP3NK3zWAemiNEXofJsTwTyHmNb/iL3dP/sZJ4+7sOld1uqYJtE" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php print resourceLink('css/w3.css'); ?>">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.0/jquery.min.js"></script>
	<script src="<?php print resourceLink("js/jquery.js");?>"></script>
	<script src="<?php print resourceLink("js/tricks.js");?>"></script>
	<script src="<?php print resourceLink("js/jquery.tablednd_0_5.js");?>"></script>
	<script src="<?php print resourceLink("js/jquery.scrollTo-1.4.2-min.js");?>"></script>
	<script src="<?php print resourceLink("js/jscolor/jscolor.js");?>"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/highlight.min.js"></script>
	<script>hljs.initHighlightingOnLoad();</script>
	<script>boardroot = <?php print json_encode(URL_ROOT); ?>;</script>
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script src="//twemoji.maxcdn.com/twemoji.min.js"></script>

	<?php $bucket = 'pageHeader'; include(__DIR__ . '/lib/pluginloader.php'); ?>
</head>
<body style="width:100%; font-size: <?php echo $loguser['fontsize']; ?>%;">
<form action="<?php echo htmlentities(pageLink('logout')); ?>" method="post" id="logout" style="display:none;"><input type="hidden" name="action" value="logout"></form>
<?php
	if (Settings::get('maintenance'))
		echo '<div style="font-size:30px; font-weight:bold; color:red; background:black; padding:5px; border:2px solid red; position:absolute; top:30px; left:30px;">MAINTENANCE MODE</div>';

	RenderTemplate('default', [
		'layout_contents' => $layout_contents,
		'layout_crumbs' => $layout_crumbs,
		'layout_actionlinks' => $layout_actionlinks,
		'headerlinks' => $headerlinks,
		'dropdownlinks' => $dropdownlinks,
		'sidelinks' => $sidelinks,
		'layout_userpanel' => $layout_userpanel,
		'notifications' => $notifications,
		'boardname' => Settings::get('boardname'),
		'poratitle' => Settings::get('PoRATitle'),
		'poratext' => parseBBCode(Settings::get('PoRAText')),
		'layout_logopic' => $logo,
		'layout_time' => $layout_time,
		'layout_views' => $layout_views,
		'layout_onlineusers' => $layout_onlineusers,
		'layout_birthdays' => $layout_birthdays,
		'board_credits' => $layout_credits,
		'logo' => $logo,
		'layout_credits' => parseBBCode(Settings::get('layout_credits')),
		'mobileswitch' => $mobileswitch,
		'perfdata' => $perfdata]);
?>
</body>
</html>
<?php

$bucket = 'finish'; include(__DIR__ . '/lib/pluginloader.php');

?>
