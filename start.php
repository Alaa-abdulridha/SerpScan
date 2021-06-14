<?php

/*
 * SerpScan Based on SerpApi LLC.
 */

require __DIR__ . '/conf.php';


$search = new SAPI_Serp($outputPath, $engine, $APIKey);
$search->_domainsFile = $domainsFile;
$search->_usePackage = $usePackage;
$search->init();
$search->processConsoleInput();
$search->startCheck();
