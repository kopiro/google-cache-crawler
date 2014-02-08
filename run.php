<?php

define('VERBOSE', 1);

if (!isset($argv[1])) {
	die("Usage: php run.php yoursite.com initpage endpage timeout\n");
}

function cget($url, $headers=null) {
	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Referer: http://www.google.it/'
		]);

	curl_setopt($ch, CURLOPT_TIMEOUT, 20);

	//curl_setopt($ch, CURLOPT_PROXY, "14.49.42.34:80");
	//curl_setopt($ch, CURLOPT_PROXYTYPE, 'HTTP');

	$response = curl_exec($ch);
	if (VERBOSE) {
		echo "\n\n========== RESPONSE ==============\n";
		echo $response;
		echo "\n========== ENDRESPONSE ==============\n\n";
	}

	if (curl_errno($ch)) {
		$error = curl_error($ch);
		curl_close($ch);
		echo "\n\n$response\n\n";
		throw new Exception($error);
	}

	$info = curl_getinfo($ch);
	if ($info['http_code']>=400) {
		curl_close($ch);
		throw new Exception("Status code is {{$info['http_code']}}");
	}

	if (empty($response)) {
		curl_close($ch);
		throw new Exception("Empty response from server", 1);
	}

	curl_close($ch);
	return $response;
}

require 'ganon.php';


define('GOOGLEURL', "http://www.google.it/search?q=site:%s&start=%d&gbv=1");
define('TIMEOUT', isset($argv[4])?$argv[4]:10);
define('INITPAGE', isset($argv[2])?$argv[2]:0);
define('ENDPAGE', isset($argv[3])?$argv[3]:20);

$site = $argv[1];
echo "\nYour site is $site\n";
$dir = __DIR__.'/sites/'.$site;
echo "Creating directory $dir...\n\n";
@mkdir($dir,0777,1);

echo "Your timeout is ".TIMEOUT."sec per page\n";
echo "Your initial page is ".INITPAGE."\n";
echo "Your end page is ".ENDPAGE."\n\n";


for ($i=INITPAGE; $i<=ENDPAGE; $i++) {
	try {
		$uri = sprintf(GOOGLEURL, $site, $i*10);

		echo sprintf("Retrieving list of links from '$uri'\n");
		echo sprintf("Pagination %d of %d... \n", $i, ENDPAGE);
		$html = str_get_dom(cget($uri));
		$pages = $html('#res li.g .s');

		foreach ($pages as $k => $page) {
			try {

				echo sprintf("Parsing page %d of %d\n", $k, count($pages));

				$gclink = $page('.flc>a',0);
				if (!$gclink) $gclink = $page('a.am-dropdown-menu-item-text', 0);
				if (!$gclink) throw new Exception("Empty Google Cache link");

				$gclink = urldecode(str_replace('/url?q=','', $gclink->href));

				$link = $page('cite', 0)->getPlainText();
				if (empty($link)) throw new Exception("Empty real link");

				echo "Page is '$link'.\n";
				echo "GC link is '$gclink'.\n";

				echo "Retrieving GC page...\n";
				$raw = cget($gclink);
				$raw = preg_replace('#.*?\<\!DOCTYPE html\>.*?\<\!#ms', '<!', $raw);

				$folder = rtrim($dir.'/'.str_replace($site.'/','',$link),'/');
				if (!is_file($folder.'/index.htm')) {
					echo "Writing file in '$folder'\n\n";
					@mkdir($folder, 0777, 1);
					file_put_contents($folder.'/index.htm', $raw);
				} else {
					echo "File index.htm already exists in '$folder'\n\n";
				}
			} catch (Exception $e) {
				echo "Error in page: ".$e->getMessage()."\n\n-------- PLEASE ABORT THIS FUCKING SCRIPT -----------\n\n";
			}

			sleep(TIMEOUT);
		}
	} catch (Exception $e) {
		echo "Error in list: ".$e->getMessage()."\n\n-------- PLEASE ABORT THIS FUCKING SCRIPT -----------\n\n";
	}

	sleep(TIMEOUT);
}