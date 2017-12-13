<?php
//  AcmlmBoard XD support - Handy snippets
// TODO organize better
if (!defined('BLARG')) die();

function endsWith($a, $b){
	return substr($a, strlen($a) - strlen($b)) == $b;
}

function endsWithIns($a, $b){
	return endsWith(strtolower($a), strtolower($b));
}

function startsWith($a, $b){
	return substr($a, 0, strlen($b)) == $b;
}

function startsWithIns($a, $b){
	return startsWith(strtolower($a), strtolower($b));
}


//	Not really much different to kill()
function Alert($s, $t='') {
	if($t=='')
		$t = __('Notice');

	RenderTemplate('messagebox', 
		[	'msgtitle' => $t,
				'message' => $s]);
}

function Kill($s, $t='') {
	if($t=='')
		$t = __('Error');
	Alert($s, $t);
	throw new KillException();
}

function dieAjax($what) {
	global $ajaxPage;

	echo $what;
	$ajaxPage = true;
	throw new KillException();
}

// returns FALSE if it fails.
function QueryURL($url) {
	if (function_exists('curl_init')) {
		$page = curl_init($url);
		if ($page === FALSE)
			return FALSE;

		curl_setopt($page, CURLOPT_TIMEOUT, 10);
		curl_setopt($page, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($page, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($page, CURLOPT_USERAGENT, 'Blargboard/'.BLARG_VERSION);

		$result = curl_exec($page);
		curl_close($page);
		return $result;
	} else if (ini_get('allow_url_fopen')) {
		return file_get_contents($url);
	} else
		return FALSE;
}


function format() {
	$argc = func_num_args();
	if($argc == 1)
		return func_get_arg(0);
	$args = func_get_args();
	$output = $args[0];
	for($i = 1; $i < $argc; $i++) {
		// TODO kill that hack
		$splicethis = preg_replace("'\{([0-9]+)\}'", "&#x7B;\\1&#x7D;", $args[$i]);

		if(strpos($output,'{'.($i-1).'}')!==FALSE)
		    $output = str_replace('{'.($i-1).'}', $splicethis, $output);
	}
	return $output;
}

// TODO NUKE
function write() {
	$argc = func_num_args();
	if($argc == 1) {
		echo func_get_arg(0);
		return;
	}
	$args = func_get_args();
	$output = $args[0];
	for($i = 1; $i < $argc; $i++) {
		// TODO kill that hack
		$splicethis = preg_replace("'\{([0-9]+)\}'", "&#x7B;\\1&#x7D;", $args[$i]);

		if(strpos($output,'{'.($i-1).'}')!==FALSE)
		    $output = str_replace('{'.($i-1).'}', $splicethis, $output);
	}
	echo $output;
}

function OptimizeLayouts($text) {
	$bucket = [];

	// Save the tags in the temp array and remove them from where they were originally
	$regexps = ["@<style(.*?)</style(.*?)>(\r?\n?)@si", "@<link(.*?)>(\r?\n?)@si", "@<script(.*?)</script(.*?)>(\r?\n?)@si"];
	foreach ($regexps as $regexp) {
		preg_match_all($regexp, $text, $temp, PREG_PATTERN_ORDER);
		$text = preg_replace($regexp, '', $text);
		$bucket = array_merge($bucket, $temp[0]);
	}

	// Remove duplicates
	$bucket = array_unique($bucket);

	// Put the tags back
	$newStyles = '<!-- head tags -->'.implode('', $bucket).'<!-- /head tags -->';
	if(strpos($text,'</head>')!==FALSE)
	    $text = str_replace('</head>', $newStyles.'</head>', $text);

	if(strpos($text,'<recaptcha')!==FALSE)
	    $text = str_replace('<recaptcha', '<script', $text);

	return $text;
}


function LoadPostToolbar() {
	echo "<script>window.addEventListener(\"load\", hookUpControls, false);</script>";
}

function TimeUnits($sec) {
	if($sec <	60) return '$sec sec.';
	if($sec <  3600) return floor($sec/60).' min.';
	if($sec < 86400) return floor($sec/3600).' hour'.($sec >= 7200 ? 's' : '');
	return floor($sec/86400).' day'.($sec >= 172800 ? 's' : '');
}

function cdate($format, $date = 0) {
	global $loguser;
	if($date == 0) $date = time();
	return gmdate($format, $date+$loguser['timezone']);
}

function Report($stuff, $hidden = 0, $severity = 0) {
	$full = GetFullURL();
	$here = substr($full, 0, strrpos($full, '/')).'/';

	$req = 'NULL';

	Query('insert into {reports} (ip,user,time,text,hidden,severity,request)
		values ({0}, {1}, {2}, {3}, {4}, {5}, {6})', $_SERVER['REMOTE_ADDR'], (int)$loguserid, time(), str_replace('#HERE#', $here, $stuff), $hidden, $severity, $req);
}

function Shake() {
	$cset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRQSTUVWXYZ0123456789';
	$salt = '';
	$chct = strlen($cset) - 1;
	while (strlen($salt) < 16)
		$salt .= $cset[mt_rand(0, $chct)];
	return $salt;
}

function IniValToBytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	switch($last) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function BytesToSize($size, $retstring = '%01.2f&nbsp;%s') {
	$sizes = ['B', 'KiB', 'MiB'];
	$lastsizestring = end($sizes);
	foreach($sizes as $sizestring) {
		if($size < 1024)
			break;
		if($sizestring != $lastsizestring)
			$size /= 1024;
	}
	if($sizestring == $sizes[0])
		$retstring = '%01d %s'; // Bytes aren't normally fractional
	return sprintf($retstring, $size, $sizestring);
}

function makeThemeArrays() {
	global $themes, $themefiles;
	$themes = [];
	$themefiles = [];
	$dir = @opendir('themes');
	while ($file = readdir($dir)) {
		if ($file != '.' && $file != '..') {
			$themefiles[] = $file;
			$name = explode("\n", @file_get_contents('./themes/'.$file.'/themeinfo.txt'));
			$themes[] = trim($name[0]);
		}
	}
	closedir($dir);
}

function getdateformat() {
	global $loguserid, $loguser;

	if(isset($loguserid))
		return $loguser['dateformat'].', '.$loguser['timeformat'];
	else
		return Settings::get('dateformat');
}

function formatdate($date) {
	return cdate(getdateformat(), $date);
}
function formatdatenow() {
	return cdate(getdateformat());
}
function relativedate($date) {
	$diff = time() - $date;
	if ($diff < 1) return 'right now';
	if ($diff >= 3*86400) return formatdate($date);

	switch($diff){
	    case ($diff<60):
	        $num = $diff; $unit = 'second';
	        break;
        case ($diff<3600):
            $num = intval($diff/60); $unit = 'minute';
            break;
        case ($diff < 86400):
            $num = intval($diff/3600); $unit = 'hour';
            break;
        default:
            $num = intval($diff/86400); $unit = 'day';
            break;
    }

	return $num.' '.$unit.($num>1?'s':'').' ago';
}

function formatBirthday($b) {
	return format('{0} ({1} old)', date('F j, Y', $b), Plural(floor((time() - $b) / 86400 / 365.2425), 'year'));
}

function getSexName($sex) {
	$sexes = [
		0 => __('Male'),
		1 => __('Female'),
		2 => __('N/A'),
	];

	return $sexes[$sex];
}

function formatIP($ip) {
	global $loguser;

	$res = $ip;
	$res .=  ' ' . IP2C($ip);
	$res = "<nobr>$res</nobr>";
	$ip = ip2long_better($ip);
	if (HasPermission('admin.ipsearch'))
		return actionLinkTag($res, 'ipquery', $ip);
	else
		return $res;
}

function ip2long_better($ip) {
	$v = explode('.', $ip); 
	return ($v[0]*16777216)+($v[1]*65536)+($v[2]*256)+$v[3];
}

function long2ip_better($ip) {
   return long2ip((float)$ip);
}

//TODO: Optimize it so that it can be made with a join in online.php and other places.
function IP2C($ip) {
	global $dblink;
	//This nonsense is because ips can be greater than 2^31, which will be interpreted as negative numbers by PHP.
	$ipl = ip2long($ip);
	$r = Fetch(Query('SELECT * 
				 FROM {ip2c}
				 WHERE ip_from <= {0s} 
				 ORDER BY ip_from DESC
				 LIMIT 1',
				 sprintf('%u', $ipl)));

	if($r && $r['ip_to'] >= ip2long_better($ip))
		return ' <img src=\"'.resourceLink('img/flags/'.strtolower($r['cc']).'.png')."\" alt=\"".$r['cc']."\" title=\"".$r['cc']."\" />";
	else
		return '';
}

function getBirthdaysText($ret = true) {
	global $luckybastards, $loguser;

	$luckybastards = [];
	$today = gmdate('m-d', time()+$loguser['timezone']);

	$rBirthdays = Query('select u.birthday, u.(_userfields) from {users} u where u.birthday > 0 and u.primarygroup!={0} order by u.name', Settings::get('bannedGroup'));
	$birthdays = [];
	while($user = Fetch($rBirthdays)) {
		$b = $user['birthday'];
		if(gmdate('m-d', $b) == $today) {
			$luckybastards[] = $user['u_id'];
			if ($ret) {
				$y = gmdate('Y') - gmdate('Y', $b);
				$birthdays[] = UserLink(getDataPrefix($user, 'u_')).' ('.$y.')';
			}
		}
	}
	if (!$ret) return '';
	if(count($birthdays))
		$birthdaysToday = implode(', ', $birthdays);
	if(isset($birthdaysToday))
		return __('Birthdays today:').' '.$birthdaysToday;
	else
		return '';
}

function getKeywords($stuff) {
	$common = ['the', 'and', 'that', 'have', 'for', 'not', 'this'];

	$stuff = strtolower($stuff);
	$stuff = str_replace("\'s", '', $stuff);
	$stuff = preg_replace('@[^\w\s]+@', '', $stuff);
	$stuff = preg_replace('@\s+@', ' ', $stuff);

	$stuff = explode(' ', $stuff);
	$stuff = array_unique($stuff);
	$finalstuff = '';
	foreach ($stuff as $word) {
		if (strlen($word) < 3 && !is_numeric($word)) continue;
		if (in_array($word, $common)) continue;
		
		$finalstuff .= $word.' ';
	}

	return substr($finalstuff,0,-1);
}

function forumRedirectURL($redir) {
	if ($redir[0] == ':') {
		$redir = explode(':', $redir);
		return actionLink($redir[1], $redir[2], $redir[3], $redir[4]);
	} else
		return $redir;
}


function smarty_function_plural($params, $template) {
	return Plural($params['num'], $params['what']);
}

function entity_fix__callback($matches) {
	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// we don't allow control characters, characters out of range, byte markers, etc
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
		return '';
	else
		return '&#' . $num . ';';
}

function utfmb4_fix($string) {
	$i = 0;
	$len = strlen($string);
	$new_string = '';
	while ($i < $len) {
		$ord = ord($string[$i]);

		switch ($ord){
            case 128:
                $new_string .= $string[$i];
                $i++;
                break;
            case 224:
                $new_string .= $string[$i] . $string[$i+1];
                $i += 2;
                break;
            case 240:
                $new_string .= $string[$i] . $string[$i+1] . $string[$i+2];
                $i += 3;
                break;
            case 248:
                //Magic happens.
                $val = (ord($string[$i]) & 0x07) << 18;
                $val += (ord($string[$i+1]) & 0x3F) << 12;
                $val += (ord($string[$i+2]) & 0x3F) << 6;
                $val += (ord($string[$i+3]) & 0x3F);
                $new_string .= '&#' . $val . ';';
                $i += 4;
                break;
        }

	}
	return $new_string;
}

function utfmb4String($string) {
	return utfmb4_fix(preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string));
}
