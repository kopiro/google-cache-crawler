<?php

if (!isset($argv[1])) {
	die("Usage: php run.php yoursite.com [timeout] [initpage] [endpage]\n");
}

function scanf($msg){
	echo $msg;
	return trim(fgets(STDIN));
}

function cget($url, $headers=null) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true );
	curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/gcc_cookies");
	curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/gcc_cookies");

	//curl_setopt($ch, CURLOPT_PROXY, "14.49.42.34:80");
	//curl_setopt($ch, CURLOPT_PROXYTYPE, 'HTTP');

	$response = curl_exec($ch);

	$info = curl_getinfo($ch);
	if ($info['http_code']>=400) {
		curl_close($ch);
		throw new Exception($response, $info['http_code']);
	}

	if (empty($response)) {
		curl_close($ch);
		throw new Exception(null, 600);
	}

	curl_close($ch);
	return $response;
}

function gget($uri) {
	try {
		$raw = cget($uri);
		return preg_replace('#.*?\<\!DOCTYPE html\>.*?\<\!#ms', '<!', $raw);
	} catch (Exception $e) {
		if ($e->getCode()==503) {
			preg_match("/src\=\"\/sorry\/image\?id\=([0-9]+)/", $e->getMessage(), $cid); $cid = end($cid);
			$img = "http://ipv4.google.com/sorry/image?id={$cid}";
			$captcha = scanf("Abort this script or solve the captcha ($img): ");
			return gget("http://ipv4.google.com/sorry/CaptchaRedirect?continue=".urlencode($uri)."&id=".$cid."&captcha=".$captcha."&submit=Submit");
		} else {
			return null;
		}
	}
}

require 'ganon.php';

define('GOOGLEURL', "http://www.google.it/search?q=site:%s&start=%d&gbv=1");
define('TIMEOUT', isset($argv[2])?$argv[2]:0);
define('INITPAGE', isset($argv[3])?$argv[3]:0);
define('ENDPAGE', isset($argv[4])?$argv[4]:20);

$site = $argv[1];
echo "\nYour site is $site\n";
$dir = __DIR__.'/sites/'.$site;
echo "Creating directory $dir...\n\n";
@mkdir($dir,0777,1);

for ($i=INITPAGE; $i<=ENDPAGE; $i++) {

	echo sprintf("Retrieving list of links (%d of %d)... ", $i, ENDPAGE);
	$uri = sprintf(GOOGLEURL, $site, $i*10);
	$links = gget($uri);
	if (!$links) {
		echo "ERROR\n";
	} else {
		$html = str_get_dom($links);
		$pages = $html('#res li.g .s');
		echo "OK\n";

		foreach ($pages as $k => $page) {
			$gclink = $page('.flc>a', 0);
			$gclink = urldecode(str_replace('/url?q=', '', $gclink->href));

			$_uri = explode('?', $gclink); array_shift($_uri);
			parse_str(implode('',$_uri), $data);
			preg_match("/cache:[^:]+:([^\+]+)\+/", $data['q'], $matches);
			$link = end($matches);
			if (empty($link)) throw new Exception("Empty real link");

			echo "Processing '{$link}'... ";

			$folder = preg_replace("/https?:\/\/(www\.)?$site\/?/", '', $link);
			$basefolder = basename($folder);
			if (strpos($basefolder,'.')!==false) {
				$t = explode('.', $basefolder);
				$ext = end($t);
				if (!in_array($ext, array('html','htm','php','asp','aspx'))) {
					echo "non-HTML file, SKIP.\n";
					continue;
				}
				$filename = $basefolder;
				$folder = str_replace($basefolder,'',$folder);
			} else {
				$filename = 'index.html';
			}

			$fullfolder = str_replace('//', '/', $dir.'/'.$folder);
			$fullpath = str_replace('//', '/', $fullfolder.'/'.$filename);

			if (!is_file($fullpath)) {
				$raw = gget($gclink);
				if (!$raw) {
					echo "ERROR\n";
				} else {
					echo "OK\n";
					@mkdir($fullfolder, 0777, 1);
					file_put_contents($fullpath, $raw);
					sleep(TIMEOUT);
				}
			} else {
				echo "file exists, SKIP.\n";
			}
		}
	}
}