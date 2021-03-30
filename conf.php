<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/SAPI.class.php';

ini_set('max_execution_time', 0);
set_time_limit(0);
date_default_timezone_set('Asia/Riyadh');
#Replace with your API key.
$APIKey = 'a89ab7d75ddaab3c0ece0a1253ee64751148adbe221b1b35863879d8f09edeec';

$outputPath = 'result';
$usePackage = true;

$domainsFile = 'domains.txt';

// query need to search , {{DOMAIN}} not change ever
$engine = [
	['google', 'q', [
		'subDomain' 	=> 'site:.{{DOMAIN}}', // not change key
		'SQLErrors'		=> 'site:{{DOMAIN}} intext:"sql syntax near" | intext:"syntax error has occurred" | intext:"incorrect syntax near" | intext:"unexpected end of SQL command" | intext:"Warning: mysql_connect()" | intext:"Warning: mysql_query()" | intext:"Warning: pg_connect()"',
		'PubDocum'		=> 'site:{{DOMAIN}} ext:doc | ext:docx | ext:odt | ext:rtf | ext:sxw | ext:psw | ext:ppt | ext:pptx | ext:pps | ext:csv',
		'PHP_Err_Warn'	=> 'site:{{DOMAIN}} "PHP Parse error" | "PHP Warning" | "PHP Error"',
		'PHP_INFO'		=> 'site:{{DOMAIN}} ext:php intitle:phpinfo "published by the PHP Group"',
		'DirLIstVuln'	=> 'site:{{DOMAIN}} intitle:index.of',
		'ConfigsFiles'	=> 'site:{{DOMAIN}} ext:xml | ext:conf | ext:cnf | ext:reg | ext:inf | ext:rdp | ext:cfg | ext:txt | ext:ora | ext:ini | ext:env',
		'PastingSites'	=> 'site:pastebin.com | site:paste2.org | site:pastehtml.com | site:slexy.org | site:snipplr.com | site:snipt.net | site:textsnip.com | site:bitpaste.app | site:justpaste.it | site:heypasteit.com | site:hastebin.com | site:dpaste.org | site:dpaste.com | site:codepad.org | site:jsitor.com | site:codepen.io | site:jsfiddle.net | site:dotnetfiddle.net | site:phpfiddle.org | site:ide.geeksforgeeks.org | site:repl.it | site:ideone.com | site:paste.debian.net | site:paste.org | site:paste.org.ru | site:codebeautify.org  | site:codeshare.io | site:trello.com "{{DOMAIN}}"',
		'DBFiles'		=> 'site:{{DOMAIN}} ext:sql | ext:dbf | ext:mdb',
		'SearchGit'		=> 'site:github.com | site:gitlab.com "{{DOMAIN}}"',
		'SearchStack'	=> 'site:stackoverflow.com "{{DOMAIN}}"',
		'LogsFiles'		=> 'site:{{DOMAIN}} ext:log',
		'BackupFiles'	=> 'site:{{DOMAIN}} ext:bkf | ext:bkp | ext:bak | ext:old | ext:backup',
		'LoginPages'	=> 'site:{{DOMAIN}} inurl:login | inurl:signin | intitle:Login | intitle:"sign in" | inurl:auth',
		'SignupPages'	=> 'site:{{DOMAIN}} inurl:signup | inurl:register | intitle:Signup',
	]],

	['baidu', 'q', [
		'subDomain' => 'site:.{{DOMAIN}}', // not change key
	]],

	['bing', 'q', [
		'subDomain' => 'site:.{{DOMAIN}}', // not change key
	]],

	['yahoo', 'p', [
		'subDomain' => 'site:.{{DOMAIN}}', // not change key
	]],

	['yandex', 'text', [
		'subDomain' => 'site:{{DOMAIN}}', // not change key
	]]
];

