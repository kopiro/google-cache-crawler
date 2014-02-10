<?php

if (!isset($argv[1])) {
	die("Usage: php run.php yoursite.com initpage endpage timeout\n");
}

function cget($url, $headers=null) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, true);
	curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/gcc_cookie.txt');

	//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Referer: http://www.google.it/'));

	curl_setopt($ch, CURLOPT_TIMEOUT, 20);

	//curl_setopt($ch, CURLOPT_PROXY, "14.49.42.34:80");
	//curl_setopt($ch, CURLOPT_PROXYTYPE, 'HTTP');

	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		$error = curl_error($ch);
		curl_close($ch);
		echo "\n\n========== ERROR ==============\n";
		echo $response;
		echo "\n===============================\n\n";
		throw new Exception($error);
	}

	$info = curl_getinfo($ch);
	if ($info['http_code']>=400) {
		curl_close($ch);
		echo "\n\n========== RESPONSE ==============\n";
		echo $response;
		echo "\n===============================\n\n";
		throw new Exception("Status code is {{$info['http_code']}}");
	}

	if (empty($response)) {
		curl_close($ch);
		throw new Exception("Empty response");
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

for ($i=INITPAGE; $i<=ENDPAGE; $i++) {
	try {
		$uri = sprintf(GOOGLEURL, $site, $i*10);

		echo sprintf("Retrieving list of links (%d of %d)... ", $i, ENDPAGE);
		$html = str_get_dom(cget($uri));
		$pages = $html('#res li.g .s');
		echo "OK\n";

		foreach ($pages as $k => $page) {
			try {

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
					$raw = cget($gclink);
					$raw = preg_replace('#.*?\<\!DOCTYPE html\>.*?\<\!#ms', '<!', $raw);

					@mkdir($fullfolder, 0777, 1);
					file_put_contents($fullpath, $raw);
					echo "OK\n";

					sleep(TIMEOUT);

				} else {
					echo "file exists, SKIP.\n";
				}
			} catch (Exception $e) {
				echo "\nError: ".$e->getMessage()."\n\n";
				sleep(TIMEOUT);
			}
		}
	} catch (Exception $e) {
		echo "\nError (in-list): ".$e->getMessage()."\n\n";
	}
}