<?php

define('BLARG', 1);
include __DIR__ . '/lib/common.php';

$entry = Fetch(Query('select * from {uploadedfiles} where id = {0}', $_GET['id']));
if (!isset($entry))
	trigger_error(__('Unknown file ID.'));

if ($entry['deldate'] != 0)
	trigger_error(__('No such file.'));

$path = DATA_DIR.'uploads/'.$entry['physicalname'];

if(!file_exists($path))
	trigger_error(__('No such file.'));
	
//Count downloads!
if(isset($isBot)){
        Query('update {uploadedfiles} set downloads = downloads+1 where id = {0}', $entry['id']);
}


// TODO detect/store MIME type instead of all that junk?
$fsize = filesize($path);
$parts = pathinfo($entry['filename']);
$ext = strtolower($parts['extension']);
$download = true;

switch ($ext)
{
	case 'gif': $ctype='image/gif'; $download = false; break;
	case 'bmp': $path = 'img/nobmp.png'; $fsize = filesize($path);
	case 'apng':
	case 'png': $ctype='image/png'; $download = false; break;
	case 'jpeg':
	case 'jpg': $ctype='image/jpg'; $download = false; break;
	case 'css': $ctype='text/css'; $download = false; break;
	case 'txt': $ctype='text/plain'; $download = false; break;
	case 'pdf': $ctype='application/pdf'; $download = false; break;
	case 'mp3': $ctype = 'audio/mpeg'; $download = false; break;
	default: $ctype='application/force-download'; break;
} 

$cachetime = 604800; // 1 week. Should be more than okay. Uploaded files aren't supposed to change.
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: private, post-check={$cachetime}, pre-check=999999999, min-fresh={$cachetime}, max-age={$cachetime}');
if(isset($ctype))
    header('Content-Type: '.$ctype);
if(isset($download))
	header("Content-Disposition: attachment; filename=\"".addslashes(rawurlencode($entry['filename']))."\";");
else
	header("Content-Disposition: filename=\"".addslashes(rawurlencode($entry['filename']))."\"");
header('Content-Transfer-Encoding: binary');
header('Content-Length: '.rawurlencode($fsize));

readfile(basename(realpath($path)));

?>
