<?php


class SAPI_Serp {
	private $_outputResult = __DIR__.'/Output_result/';
	private $_receivedDataPath = __DIR__.'/Received_Data/';
	private $_packagePath = __DIR__.'/package/';
	public $_domainsFile = __dir__.'/domains.txt';
	private $_engineSearch = [
			['google', 'q', ['subDomain' => 'site:.{{DOMAIN}}',]],
			['baidu', 'q', ['subDomain' => 'site:.{{DOMAIN}}',]],
			['bing', 'q', ['subDomain' => 'site:.{{DOMAIN}}',]],
			['yahoo', 'p', ['subDomain' => 'site:.{{DOMAIN}}',]],
			['yandex', 'text', ['subDomain' => 'site:.{{DOMAIN}}',]]
		];
	
	private $_start = false;

	private $_apiKey = '';
	private $_domainsToSearch = [];
	private $_typeOfAddTime = 'minutes';
	public $_typeOfExportData = ['HTML', 'Json'];
	public $_usePackage = true;

	private $_subDoaminFromSearch = [];
	private $_subDoaminFromPackage = [];
	private $_processingData = [];
	
	private $_client;
	private $_parser;
	
	function __construct($outputPath = null, $engine = null, $APIKey = null) {
		$this->disable_ob();
		echo $this->printToConsole("Start SAPI Class .....	", 'green');
		
		// Check received path
		if($this->initReceivedDataPath())
			echo $this->printToConsole("- The creation of the file receive path has been verified.", 'green');
		
		// Check if declare engines
		if($this->initEngine($engine))
			echo $this->printToConsole("- Found ".count($engine)." search engines before start searching.", 'green');
		
		// Check export path
		if($this->initOutputPath($outputPath))
			echo $this->printToConsole("- The creation of the results folder has been verified.", 'green');
		
		// Check API key exist
		if($APIKey == null)
			die($this->printToConsole('Must enter API key!', 'red'));
			
		$this->_apiKey = $APIKey;
		try {
			$this->_client = new GoogleSearch($this->_apiKey);
			$this->_client->get_account();
		} catch(Exception $e) {
			die($this->printToConsole($e->getMessage(), 'red'));
		}

		$pslManager = new Pdp\PublicSuffixListManager();
		$this->_parser = new Pdp\Parser($pslManager->getList());
	}

	public function init() {
		$this->setDomain($this->_domainsFile);
		return $this->_start = true;
	}
	
	private function initReceivedDataPath() {
		if(!is_dir($this->_receivedDataPath))
			if(!mkdir($this->_receivedDataPath, 0777))
				die($this->printToConsole('There has error when create ('.$this->_receivedDataPath.') !!', 'red'));
		return $this->_receivedDataPath;
	}
	
	private function initEngine($engine) {
		if($engine !== null) {
			if(!is_array($engine))
				die($this->printToConsole('Engine must be As array!', 'red'));
			$this->_engineSearch = $engine;
		}
		return $this->_engineSearch;
	}
	
	private function initOutputPath($outputPath = null) {
		if($outputPath == null){
			$outputPath = $this->_outputResult;
		} else {
			$outputPath = __DIR__.'/'.$outputPath.'/';
		}

		if(!is_dir($outputPath))
			if(mkdir($outputPath, 0777))
				$this->_outputResult = $outputPath;
			else
				die($this->printToConsole('There has error when create ('.$outputPath.')', 'red'));
		else
			$this->_outputResult = $outputPath;
#H		
		return $this->_outputResult;
	}
	
	private function setDomain($domains = null) {
		if($domains == null)
			die($this->printToConsole('There is no domain file to open ... Please select the domain file to start the scan!', 'red'));
		
		if(!is_file($domains))
			die($this->printToConsole('The file ('.$domains.') has not existed!', 'red'));
		
		$domains = json_decode(file_get_contents($domains), true);
		
		if(!is_array($domains))
			die($this->printToConsole('Domain file must be as array!', 'red'));
		
		if(count($domains) == 0)
			die($this->printToConsole('Must enter at least one Domain to search!', 'red'));

		for($i=0; $i<count($domains); $i++){
			// $url = parse_url($domains[$i]['name']);
			// $domains[$i]['name'] = trim((explode('.', $url['host'])[0]) != 'www' ? $url['host'] : (explode('.', $url['host']))[1].'.'.(explode('.', $url['host']))[2]);
			$domains[$i]['name'] = $this->_parser->parseUrl($domains[$i]['name'])->getHost()->getHost();
			$domains[$i]['name'] = trim((explode('.', $domains[$i]['name'])[0]) != 'www' ? $domains[$i]['name'] : (explode('.', $domains[$i]['name']))[1].'.'.(explode('.', $domains[$i]['name']))[2]);
			$this->createDirForDomain($domains[$i]['name']);
		}
		echo $this->printToConsole("- ".count($domains)." domains were found to start the searching.", 'green');
		$this->checkIsValidToSearchNow($domains);
	}
	
	private function createDirForDomain($domainName = null) {
		if($domainName == null)
			return false;
		
		// create folder for domain
		if(!is_dir($this->_receivedDataPath.'/'.$domainName))
			if(!mkdir($this->_receivedDataPath.'/'.$domainName, 0777))
				return false;

		// create folder for data inside folder domain
		if(!is_dir($this->_receivedDataPath.'/'.$domainName.'/Data'))
			if(!mkdir($this->_receivedDataPath.'/'.$domainName.'/Data', 0777))
				return false;
		
		// create jsonfile to get and set last search data
		if(!is_file($this->_receivedDataPath.'/'.$domainName.'/q.json'))
			fopen($this->_receivedDataPath.'/'.$domainName.'/q.json', 'w');
		
		return true;
	}
	
	private function checkIsValidToSearchNow($domains) {
		for($i=0; $i<count($domains); $i++){
			$getDomainData = json_decode(file_get_contents($this->_receivedDataPath.'/'.$domains[$i]['name'].'/q.json'));

			if($getDomainData == null or !is_array($getDomainData)) {
				file_put_contents($this->_receivedDataPath.'/'.$domains[$i]['name'].'/q.json', json_encode([['lastSearch' => strtotime(date('Y-m-d H:i:s')), 'count' => 0]]));
				$domains[$i]['check'] = true;
			} else {
				if(time() >= strtotime('+'.$domains[$i]['time'].' '.$this->_typeOfAddTime, $getDomainData[0]->lastSearch))
					$domains[$i]['check'] = true;
				else
					$domains[$i]['check'] = false;
			}

			$domains[$i]['exp'] = strtotime('+'.$domains[$i]['time'].' '.time());

			if(isset($getDomainData[0]->lastSearch))
				$domains[$i]['exp'] = strtotime('+'.$domains[$i]['time'].' '.$this->_typeOfAddTime, $getDomainData[0]->lastSearch);
		}
		
		$this->_domainsToSearch = $domains;
	}
	
	public function startCheck() {
		if(!$this->_start)
			exit();
		
		echo $this->printToConsole("Starting, scanning ...", 'blue');

		foreach($this->_domainsToSearch as $domainData) {
			if($domainData['check'] === true) {
				echo $this->printToConsole("- Starting checking of the (".$domainData['name'].") domain", 'blue');

				$resultSearching = [];
				$countReslt = 0;
				
				$newNameFile = $this->_receivedDataPath.$domainData['name'].'/Data/'.date('Y-m-d Hi').'_'.substr(md5(rand(1111, 999999) * rand(111, 888)), 0, 8).'.json';

				// Pass a query to engine one by one
				foreach($this->_engineSearch as $engine) {

					if(!isset($engine[2]) or !is_array($engine[2]) or count($engine[2]) == 0){
						echo $this->printToConsole("  - There is no any query for search at (".$engine[0].")", 'red');
						continue;
					}
					
					echo $this->printToConsole(" - Searching in (".$engine[0].") search engine ... ", 'yellow');


#A					// Start search with query one by one
					foreach($engine[2] as $nameQuery => $queryValue){
						$fullQuery = str_replace('{{DOMAIN}}', $domainData['name'], $queryValue);
						
						echo $this->printToConsole("  - Start search with ($fullQuery) ... ", 'blue', false);

						$query = [
							$engine[1]	=> $fullQuery,
							"engine"	=> $engine[0]
						];
	
						$response = $this->_client->get_json($query);
						
						$afterProccess = $this->getAllNextURL($response);
						
						$countReslt += $afterProccess['countData'];
						
						$resultSearching[$nameQuery][] = [$engine[0] => $afterProccess['data']];
						
						$res = $afterProccess['data'];
						
						$dataFiles = is_file($newNameFile) ? json_decode(file_get_contents($newNameFile), true) : [];
						if(is_array($dataFiles) and count($dataFiles) > 0) {
							$dataFiles['searchingData'][$nameQuery][$engine[0]][] = $res;
						} else {
							$dataFiles['searchingData'][$nameQuery][$engine[0]] = $res;
						}

						file_put_contents($newNameFile, json_encode($dataFiles));

						if(count($afterProccess['error']) > 0) {
							foreach($afterProccess['error'] as $error) {
								file_put_contents($this->_receivedDataPath.$domainData['name'].'/erros.txt', date('Y-m-d H:i:s')."\t  error (".$error->error.") for search id = ".$error->search_metadata->id.", to show JSON ".$error->search_metadata->json_endpoint." ". PHP_EOL, FILE_APPEND);
							}
						}
	
						echo $this->printToConsole(" Done", 'blue', false);
						echo $this->printToConsole("  = Found ".$afterProccess['countData']." data", 'yellow');
					}
					
					
				}
				
				echo $this->printToConsole("+ Data was received has been completed", 'green');

				// Process the data received from search engines
				$this->processData($domainData['name'], json_decode(json_encode($resultSearching)));

				// Get data for each subdomain by packaging
				$dataFromPackage = $this->startPackages($domainData['name']);

				$dataFiles = json_decode(file_get_contents($newNameFile), true);
				$dataFiles['packagingData'] = $dataFromPackage;
				file_put_contents($newNameFile, json_encode($dataFiles));

				// Save all raw results
				// file_put_contents($newNameFile, json_encode(['searchingData' => $resultSearching, 'packagingData' => $dataFromPackage]));

				// ## EXPORT ##
				$export = $this->export($domainData['name'], $newNameFile, $domainData['expt']);
				if($export != false or $export != null)
					echo $this->printToConsole("+ Processing has been completed successfully for (".$domainData['name'].") and the report path is $export", "green");
				
				//
				$getDomainData = json_decode(file_get_contents($this->_receivedDataPath.$domainData['name'].'/q.json'));
				$getDomainData[0]->count += 1;
				$getDomainData[0]->lastSearch = time();
				
				// save new data to q.json
				file_put_contents($this->_receivedDataPath.$domainData['name'].'/q.json', json_encode($getDomainData));
				
				file_put_contents(__DIR__.'/logs/logs.txt', date('Y-m-d H:i:s')."\t Domain (".$domainData['name'].") output count ".$countReslt. PHP_EOL, FILE_APPEND);
			} else {
				$time = "";
				$rem = $domainData['exp'] - time();
				$hr  = floor(($rem % 86400) / 3600);
				$min = floor(($rem % 3600) / 60);
				$sec = ($rem % 60);
				if($hr) $time.= "$hr Hours ";
				if($min) $time.= "$min Minutes ";
				if($sec) $time.= "$sec Seconds ";
				echo $this->printToConsole("$time remain for the domain (".$domainData['name'].")", 'yellow');
			}
		}
	}
	
	private function getAllNextURL($response) {
		$output = [];
		$error = [];
		$q = ['google' => 'start', 'baidu' => 'pn', 'bing' => 'first', 'yahoo' => 'b', 'yandex' => 'b'];
		$currentIndex = null; 
		$api = new RestClient([
			'base_url' => 'https://serpapi.com',
			'user_agent' => 'google-search-results-php/1.3.0',
		]);
		
		do {
			if(isset($response->organic_results)){
				$output = array_merge($output, $response->organic_results);
				
#M
				if(isset($response->serpapi_pagination->next)) {
					if( isset($response->search_parameters->{$q[$response->search_parameters->engine]}) ) {
						if($currentIndex == $response->search_parameters->{$q[$response->search_parameters->engine]}) 
							break;
						echo $this->printToConsole($response->search_parameters->{$q[$response->search_parameters->engine]}.' ', 'blue', false);
						$currentIndex = $response->search_parameters->{$q[$response->search_parameters->engine]};
					}
					$nextUrl = parse_url($response->serpapi_pagination->next);
			
					parse_str($nextUrl['query'], $nextQuery);
					$nextQuery = array_merge($nextQuery, ['api_key' => $this->_apiKey]);
					
					$resultOfNextUrl = $api->get($nextUrl['path'], $nextQuery);
					
					if($resultOfNextUrl->info->http_code == 200) {
						$response = json_decode($resultOfNextUrl->response);
					}
				}
			}

			if(isset($response->error)){
				$error[] = $response;
			}
		} while(isset($response->serpapi_pagination) and isset($response->serpapi_pagination->next));

		return ['countData' => count($output), 'data' => $output, 'error' => $error];
	}
	
	// not use yet
	// public function getSavedData($domain = null){
	// 	$returnData = [];
		
	// 	$pathToFind = $this->_receivedDataPath.'*';
		
	// 	foreach(glob($pathToFind) as $domainDir) {
	// 		$domainInDir['name'] = basename($domainDir);
	// 		if($domain == null) {
	// 			$domainInDir['files'] = glob($this->_receivedDataPath.basename($domainDir).'/Data/*.json');
	// 		}
	// 		$returnData[] = $domainInDir;
	// 	}
	// 	return $returnData;
	// }

	private function startPackages($domainName) {
		$dataDomainFromPackage = [];
		if($this->_usePackage) {
			echo $this->printToConsole(" - Fetching data from packaging ... ", 'yellow', false);
			$dataDomainFromPackage = $this->getDataByPackage($domainName);
			$this->_subDoaminFromPackage = $dataDomainFromPackage;
			echo $this->printToConsole("Done", 'yellow');
		}
		return $dataDomainFromPackage;
	}

	private function processData($domainName, $resultSearching) {
		echo $this->printToConsole("- Processing data ...", 'magenta',);
		
		// $pslManager = new Pdp\PublicSuffixListManager();
		// $parser = new Pdp\Parser($pslManager->getList());
		
		$proccessData = [];
		foreach($resultSearching as $nameQuery => $queryData){
			$dataOutput = [];
			
			foreach($queryData as $k => $dataInsideEngine) {
				$engineName = key($dataInsideEngine);

				if(count($dataInsideEngine->$engineName) == 0)
					continue;
				
				$dataOutput['engine'] = $engineName;

				foreach($dataInsideEngine->$engineName as $data){
					$title = isset($data->title) ? $data->title : null;
					$link = isset($data->link) ? $data->link : null;
					$dispLink = isset($data->displayed_link) ? $data->displayed_link : null;
					$snippet = isset($data->snippet) ? htmlspecialchars($data->snippet) : null;

					if($link == null)
						continue;

					$parseLink = parse_url($link);
					$urlToGetSub = $this->_parser->parseUrl($link);
					$domain = $urlToGetSub->getHost()->getSubdomain() != '' ? str_replace($urlToGetSub->getHost()->getSubdomain().'.', '', $urlToGetSub->getHost()->getHost()) : $urlToGetSub->getHost()->getHost();
					
					if($domain != $domainName)
						continue;

					$linkData = [];
					foreach($parseLink as $k => $pl){
						if($pl == '/')
							continue;
						
						$linkData[$k] = $pl;
						$linkData['sub'] = $urlToGetSub->getHost()->getSubdomain();
						$linkData['domain'] = $urlToGetSub->getHost()->getSubdomain() != '' ? str_replace($urlToGetSub->getHost()->getSubdomain().'.', '', $urlToGetSub->getHost()->getHost()) : $urlToGetSub->getHost()->getHost();
					}

#9
					$dataOutput['data'][] = [
						'title'		=> $title,
						'snippet'	=> $snippet,
						'link'		=> $link,
						'dispLink'	=> $dispLink,
						'linkData'	=> $linkData,
					];
				}

				if(isset($dataOutput['data']))
					$proccessData[$nameQuery][] = $dataOutput;
			}
		}

		// This part for convert all paths to tree
		$allLinkData = [];
		$this->_subDoaminFromSearch = [];
		foreach($proccessData as $nameQuery => $queryData){
			foreach($queryData as $k => $engineData) {
				foreach($engineData['data'] as $data){

					$allLinkData[] = $data['linkData'];

					if(!in_array($data['linkData']['host'], $this->_subDoaminFromSearch)) {
						$this->_subDoaminFromSearch[] = $data['linkData']['host'];
					}
				}
			}
		}

		echo $this->printToConsole(" - Creating directory structure ... ", 'blue', false);
		$trees = [];

		foreach($allLinkData as $path){

			$pathSplit = isset($path['path']) ? explode('/', substr(rtrim($path['path'], '/'), 1)) : [];
			$current = &$trees[$path['host']];
			foreach($pathSplit as $k => $singlePath){

				$arKe = array_keys($pathSplit);
				$lsIn = end($arKe);

				$current = &$current['child'][$singlePath];

				if(isset($path['query']) and $lsIn == $k)
					$current['data'][] = $path['query'];
			}
		}
		echo $this->printToConsole("Done", 'blue');

		echo $this->printToConsole("- Processing data ... Done", 'magenta');

		return $this->_processingData = ['trees' => $trees, 'all' => $proccessData];
	}

	public function export($domain, $jsonFile, $outputExport) {
		$data = false;
		if(!is_file($this->_receivedDataPath.$domain.'/Data/'.basename($jsonFile)))
			die($this->printToConsole("File ($jsonFile) at $domain not exist!", 'red'));
		
		if(!isset($this->_typeOfExportData[$outputExport]))
			die($this->printToConsole("Type ($this->_typeOfExportData[$outputExport]) not exist!", 'red'));
		
		$fileData = json_decode(file_get_contents($this->_receivedDataPath.$domain.'/Data/'.basename($jsonFile)), true);

		if(is_array($fileData) and count($fileData) > 0) {
			switch($this->_typeOfExportData[$outputExport]) {
				case "HTML":
					$data = $this->exportToHTML($this->_receivedDataPath.$domain.'/Data/'.basename($jsonFile));				
					break;
				
				case "Json":
					$data = $this->exportToJSON($this->_receivedDataPath.$domain.'/Data/'.basename($jsonFile));
					break;
				
				default:
					$data = null;
				break;
			}
		} else {
			echo($this->printToConsole("There is no data to export it!", 'red'));
		}
		
		return $data;
	}

	private function exportToHTML($file) {
		$domainName = basename(dirname(dirname($file)));

		$proccessData = $this->_processingData;

		$dirStrcu = '<ul class="tree">'.$domainName;

		// Get structure of all directory 
		foreach($proccessData['trees'] as $idTree => $tree) {
			$href = 'https://'.$idTree.'';
			
			$hasChild = function($child, $hrefLink) use(&$hasChild) {
				$childHTML = '';
#9				
				foreach($child as $idTree => $tree) {
					$newHref = $hrefLink.'/'.$idTree;
					$additionDetails = '';

					// Get query Data
					if(isset($tree['data']) ){

						$outText = '';
						$querys = [];
						foreach($tree['data'] as $k => $v){
							$arKe = array_keys($tree['data']);
							$lsIn = end($arKe);

							if(in_array(trim($v), $querys))
								continue;

							$querys[] = trim($v);
							parse_str($v, $query);
							
							$outText .= "<a href='$newHref?$v'>";

							foreach($query as $queryK => $queryV){
								$arKe = array_keys($query);
								$outText .= "<font style='color: #f92672'>$queryK</font>=<font style='color: ".(is_numeric($queryV) ? '#ae81ff' : '#a6e22e')."'>$queryV</font>";
								if(end($arKe) != $queryK)
									$outText .= "&";
							}
							
							$outText .= "</a>";
							
							if($lsIn != $k)
								$outText .= '<br><br>';
						}
						$additionDetails = "data-toggle='popover' data-html='true' data-content=\"$outText\" role='button' style='color: #5de054'";
					}
					if(is_array($tree) and isset($tree['child'])){
						$childHTML .= '<li><a href="'.$newHref.'">'.$idTree.'</a>';
						$childHTML .= '<ul>'.$hasChild($tree['child'], $newHref).'</ul>';
						$childHTML .= '</li>';						
					} else {
						$childHTML .= '<li><a href="'.$newHref.'" '.$additionDetails.'>'.$idTree.'</a></li>';
					}
					
				}
				return $childHTML;
			};

			if(is_array($tree) and isset($tree['child'])){
				$dirStrcu .= "<li><font style='color: #d4af29'>".$idTree."</font>";
				$dirStrcu .= '<ul>';
				$dirStrcu .= $hasChild($tree['child'], $href);
				$dirStrcu .= '</ul>';
				$dirStrcu .= '</li>';
			} else {
				$dirStrcu .= "<li><font style='color: #d4af29'>".$idTree."</font></li>";
			}
		}
		$dirStrcu .= '</ul>';

		// Sub-Domains
		$subDomainsHTML = '';
		$countSub = 1;
		foreach($this->_subDoaminFromPackage as $subDomainName => $subDomainData){
			$subDomainsDataDetailsHTML = "<div class='row'>";
			if(isset($subDomainData['dataDomain'])) {
				$title = $subDomainData['dataDomain']['title'];
				$ips = isset($subDomainData['dataDomain']['ips']) ? implode(', ', $subDomainData['dataDomain']['ips']) : '';
				$cnames = isset($subDomainData['dataDomain']['cnames']) ? implode(', ', $subDomainData['dataDomain']['cnames']) : '';
				$webserver = $subDomainData['dataDomain']['webserver'];
				$contentLength = $subDomainData['dataDomain']['content-length'];
				$statusCode = $subDomainData['dataDomain']['status-code'];
				$contentType = isset($subDomainData['dataDomain']['content-type']) ? $subDomainData['dataDomain']['content-type'] : '';
				$location = $subDomainData['dataDomain']['location'];

				$subDomainsDataDetailsHTML .= "
					<div class='col-12'><h4>Data for Domain</h4>
						<table class='table table-hover table-bordered table-sm'>
							<thead>
								<tr>
									<th scope='col'>#</th>
									<th scope='col'>Name</th>
									<th scope='col'>Value</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<th scope='row'>1</th>
									<td>Title</td>
									<td>$title</td>
								</tr>
								<tr>
									<th scope='row'>2</th>
									<td>ips</td>
									<td>$ips</td>
								</tr>
								<tr>
									<th scope='row'>3</th>
									<td>Cnames</td>
									<td>$cnames</td>
								</tr>
								<tr>
									<th scope='row'>4</th>
									<td>Status Code</td>
									<td>$statusCode</td>
								</tr>
								<tr>
									<th scope='row'>5</th>
									<td>Web Server</td>
									<td>$webserver</td>
								</tr>
								<tr>
									<th scope='row'>6</th>
									<td>Content Type</td>
									<td>$contentType</td>
								</tr>
								<tr>
									<th scope='row'>7</th>
									<td>Content Length</td>
									<td>$contentLength</td>
								</tr>
								<tr>
									<th scope='row'>8</th>
									<td>Location</td>
									<td>$location</td>
								</tr>
						  </tbody>
						</table>
					</div>
				";
			}

			if(isset($subDomainData['endpointsData'])) {
				$contentEndpointsData = '';
				$i=1;
				
				foreach($subDomainData['endpointsData'] as $key => $val){
					$contentEndpointsData .= "
						<tr>
							<th scope='row'>".$i++."</th>
							<td>$key</td>
							<td>".implode('<br>', $val)."</td>
						</tr>";
				}

				$subDomainsDataDetailsHTML .= "
					<div class='col-12'>
						<h4>End-points Domain</h4>
						<table class='table table-hover table-bordered table-sm'>
							<thead>
								<tr>
									<th scope='col'>#</th>
									<th scope='col'>Name</th>
									<th scope='col'>Value</th>
								</tr>
							</thead>
							<tbody>$contentEndpointsData</tbody>
						</table>
					</div>
				";
			}


			$subDomainsDataDetailsHTML .= '</div>';

			$subDomainsHTML .= '
				<tr>
					<td>'.$countSub++.'</td>
					<td>'.$subDomainName.'</td>
					<td data-toggle="popover" data-html="true" data-content="'.$subDomainsDataDetailsHTML.'" role="button" style="color: #5de054">More Details</td>
				</tr>
			';
		}
		
		// Show additional query 
		$otherDataHTML = '';
		$otherDataTableHTML = '';

		$countOther = 1;
		foreach($proccessData['all'] as $queryName => $queryValue) {

			if($queryName == 'subDomain')
				continue;
			
			$otherDataDetailsHTML = "<div class='row'>";

			$contentOther = '';
			$i=1;
			
			// data inside query (engine and its result)
			foreach($queryValue as $searchResult){
				$engineName = $searchResult['engine'];

				$otherDataDetailsEngineDataHTML = "<div class='row'>";
				
				$contentOther2 = '';
				$countOtherEngineData = 1;
				
				// search result data inside engine
				foreach($searchResult['data'] as $res) {
#9
					$contentOther2 .= '
						<tr>
							<th scope=\'row\'>'.$countOtherEngineData++.'</th>
							<td>'.$res['link'].'</td>
							<td>'.$res['title'].'</td>
							<td>'.$res['snippet'].'</td>
						</tr>
					';

				}

				$otherDataDetailsEngineDataHTML .= "
					<div class='col-12'>
						<table class='table table-hover table-bordered table-sm'>
							<thead>
								<tr>
									<th scope='col'>#</th>
									<th scope='col'>Link</th>
									<th scope='col'>Title</th>
									<th scope='col'>Snippet</th>
								</tr>
							</thead>
							<tbody>
								$contentOther2
							</tbody>
						</table>
					</div>
				";

				$otherDataDetailsEngineDataHTML .= '</div>';
							
				$contentOther .= '
					<tr>
						<th scope=row>'.$i++.'</th>
						<td>'.$engineName.'</td>
						<td data-toggle=\'popover\' data-html=\'true\' data-content=\''.str_replace("'", '"', $otherDataDetailsEngineDataHTML).'\' role=\'button\' style=\'color: #5de054\'>More Details</td>
					</tr>
				';
			}

			$otherDataDetailsHTML .= "
				<div class='col-12'>
					<table class='table table-hover table-bordered table-sm'>
						<thead>
							<tr>
								<th scope='col'>#</th>
								<th scope='col'>Engine</th>
								<th scope='col'>Value</th>
							</tr>
						</thead>
						<tbody>
							$contentOther
						</tbody>
					</table>
				</div>
			";
			$otherDataDetailsHTML .= '</div>';
			$otherDataTableHTML .= '
				<tr>
					<td>'.$countOther++.'</td>
					<td>'.$queryName.'</td>
					<td style="width: 80%;">'.$otherDataDetailsHTML.'</td>
				</tr>';
		}

		if($otherDataTableHTML != '') {
			$otherDataHTML = '
				<div class="col-6">
					<div class="p-3 border rounded">
						<h2>Additional query</h2>
						<table class="table table-bordered">
							<thead>
								<tr>
									<th scope="col">#</th>
									<th>Query Name</th>
									<th>Result</th>
								</tr>
							</thead>
							<tbody>
								'.$otherDataTableHTML.'
							</tbody>
						</table>
					</div>
				</div>
			';
		}

		$html = '
			<!doctype html>
			<html lang="en">
				<head>
					<meta charset="utf-8">
					<title>Result for ('.$domainName.')</title>
					<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
					<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
					<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>

					<style>
						.popover-body  {
							background-color: #212529;
							color: #ecf0f1;
							max-height: 500px;
    						overflow-x: hidden;
						}

						ul.tree a {
							color: #faedff;
						}

						ul.tree a:hover {
							color: #0056b3;
						}

						.popover  {
							max-width: 700px
						}

						.popover-body a:hover , ul.tree a:hover {
							text-decoration: none;
						}

						.popover-body [role=button]:hover, ul.tree [role=button]:hover {
							color: #0056b3;
						}

						.popover-body .table {
							color: #faedff;
						}
						
						.popover-body .table td {
							max-width: 450px;
						}
						
						.popover-body .table-hover tbody tr:hover {
							color: #75ddfd;
						}

						ul.tree, ul.tree ul {
							list-style: none;
							padding: 0;
						} 
						
						ul.tree ul {
							margin-left: 21px;
						}
						
						ul.tree li {
							margin: 0;
							padding: 0 7px;
							line-height: 20px;
							/*color: #369;
							font-weight: bold;*/
							border-left:2px solid rgb(255,255,255);
						}
						
						ul.tree li:last-child {
							border-left:none;
						}
						
						ul.tree li:before {
							position:relative;
							/*top: -0.3em;*/
							height: 1em;
							width: 20px;
							color: white;
							border-bottom:2px solid rgb(255,255,255);
							content: "";
							display: inline-block;
							left: -7px;
						}
						
						ul.tree li:last-child:before {
							border-left: 2px solid rgb(255,255,255);   
						}

						
					</style>
					
				</head>

				<body>
					<div class="container-fluid">
						<p style="text-align: center;margin: 30px;font-size: 25px;">Result for ('.$domainName.')</p>

						<div class="row m-3 border rounded">
							<div class="col-12 m-1" style="background-color: #272822;color: #ecf0f1;"><h2>Directory structure</h2><br>
								'.$dirStrcu.'
							</div>
						</div>

						<div class="row m-1 justify-content-md-center">
							
							<div class="col-6">
								<div class="p-3 border rounded">
									<h2>Sub-Domains</h2>
									<table class="table table-bordered">
										<thead>
											<tr>
												<th scope="col">#</th>
												<th>SubDomain</th>
												<th>Tool</th>
											</tr>
										</thead>
										<tbody>
											'.$subDomainsHTML.'
										</tbody>
									</table>
								</div>
							</div>

							'.$otherDataHTML.'
							
							
						</div>

						<script>
							$(\'[data-toggle="popover"]\').popover({
								html: true,
								sanitize: false,
							})

							$("body").on("click", ".popover-body a, .tree a", function (e) {
								e.preventDefault();
								var copyText = $(this).attr("href");

								/*
								document.addEventListener("copy", function(e) {
									e.clipboardData.setData("text/plain", copyText);
									e.preventDefault();
								}, true);

								document.execCommand("copy");
								alert("copied link: " + copyText); 
								*/
							});

							$(\'[data-toggle="popover"]\').click(function (e) {
								e.stopPropagation();
								e.preventDefault();
								$(\'[data-toggle="popover"]\').not(this).popover("hide");
								$(this).popover("toggle");
							});
							
							$(document).click(function (e) {
								if (($(".popover").has(e.target).length == 0) || $(e.target).is(".close")) {
									$(\'[data-toggle="popover"]\').popover("hide");
								}
							});
					
						</script>

					</div>
				</body>
			</html>
		';
		
		$fileNameOutput = $this->_outputResult.$domainName.'_'.date('Y-m-d_H_i_s').'_'.substr(md5(rand(1111, 999999) * rand(111, 888)), 0, 5).'.html';
		file_put_contents($fileNameOutput, $html);
		return $fileNameOutput;
	}
	
	private function exportToJSON($file) {

		$domainName = basename(dirname(dirname($file)));

		$proccessData = $this->_processingData;

		$fileNameOutput = $this->_outputResult.$domainName.'_'.date('Y-m-d_H_i_s').'_'.substr(md5(rand(1111, 999999) * rand(111, 888)), 0, 5).'.json';
		file_put_contents($fileNameOutput, json_encode($proccessData));
		return $fileNameOutput;
	}
	
	private function getDataByPackage($domain) {
		
		$subDomains = [];
		// Get All Sub-domain for Domain
		$cmd_subfinder = 'cd "'.$this->_packagePath.'subfinder/" && go run main.go -d '.$domain.' -all -silent 2>&1';
		$output_subfinder = shell_exec($cmd_subfinder);
		
		foreach(explode("\n", $output_subfinder) as $line){
			if($line != null)
				$subDomains[] = $line;
		}

		foreach($this->_subDoaminFromSearch as $subDomain){
			if(!in_array($subDomain, $subDomains))
				$subDomains[] = $subDomain;
		}
		$counter = 1;
		$outPutData = [];
		foreach($subDomains as $subDomain){
			echo $this->printToConsole($counter++.' ', 'yellow', false);
			$outPutData[$subDomain] = [];

			// Get Data for each sub-domain
			$cmd_httpx = 'cd "'.$this->_packagePath.'httpx/" && echo '.$subDomain.' | go run main.go -json -silent 2>&1';
			$output_httpx = shell_exec($cmd_httpx);
			
			foreach(explode("\n", $output_httpx) as $line){
#D
				if($line != null)
					$outPutData[$subDomain]['dataDomain'] = json_decode($line, true);
			}

			// Get End-point
			$cmd_hakrawler = 'cd "'.$this->_packagePath.'hakrawler/" && go run main.go -url '.$subDomain.' -nocolor -all 2>&1';
			$output_hakrawler = shell_exec($cmd_hakrawler);
			
			foreach(explode("\n", $output_hakrawler) as $line){
				if($line != null)
					$outPutData[$subDomain]['endpointsData'][preg_replace('/[[](.*?)[]]/', '$1', explode(" ", $line)[0])][] = explode(" ", $line)[1];
			}

		}
		return $outPutData;
	}

	public function printToConsole($msg, $color, $newLine = true) {
		$arrColor = [
			'red'		=> "[31m",
			'green'		=> "[92m",
			'yellow'	=> "[33m",
			'blue'		=> "[94m",
			'magenta'	=> "[95m",
		];
		$reset = "[0m";

		$msg = str_replace(["\n", "\r"], ' ', $msg);

		if(defined('STDIN')) {
			
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$msg = "\033".$arrColor[$color].$msg."\033".$reset;
			} else{
				$msg = "\e".$arrColor[$color].$msg."\e".$reset;
			}
		
			if($newLine)
				$msg .= PHP_EOL;

		} else {
			$msg = "<style>body{background-color: black; color: white;}</style><font color=$color>".str_replace(' ', '&nbsp;', $msg)."</font>";
			if($newLine)
				$msg .= '<br>';
		}
		
		return $msg;
	}

	public function processConsoleInput() {
		global $argv;
		
		if(!$this->_start)
			exit();
		
		if(defined('STDIN') and isset($argv[1])) {

			switch ($argv[1]) {
				case "-d":
					if(!isset($argv[2]))
						die($this->printToConsole('Must enter Domain to add!', 'red'));
					
					if(checkdnsrr($argv[2], 'A') !== true)
						die($this->printToConsole('The ('.$argv[2].') domain is not valid domain', 'red'));
					
					if(!isset($argv[3]) or $argv[3] !== '-t')
						die($this->printToConsole('Must enter correct command: -d [domain] -t [type to export (html, json)]', 'red'));
					
					if(!isset($argv[4]) or ($argv[4] != 'html' and $argv[4] != 'json') )
						die($this->printToConsole('Must enter correct [type]: -t [type to export (html, json)]', 'red'));
	
					$hostName = $this->_parser->parseUrl($argv[2])->getHost()->getHost();
	
					if(in_array($hostName, array_column($this->_domainsToSearch, 'name')))
						die($this->printToConsole('The ('.$argv[2].') domain is already exist!', 'red'));
					
					$domainData = ['name' => $hostName, 'time' => 1*60, 'expt' => ($argv[4] == 'html' ? '0' : '1')];
					
					$data = json_decode(file_get_contents($this->_domainsFile), true);
					$data[] = $domainData;
					
					$output = file_put_contents($this->_domainsFile, json_encode($data));
					if($output !== false) {
						$this->setDomain($this->_domainsFile);
						echo $this->printToConsole(" ++ ", 'yellow', false);
						echo $this->printToConsole("($hostName) Added secsessfuly", 'green', false);
						echo $this->printToConsole(" ++", 'yellow');
					}
				break;
					
				case "-w":
					if(!isset($argv[2]))
						die($this->printToConsole('Must enter file domains!: -w [domain json file path]', 'red'));
	
					$this->setDomain($argv[2]);
					$this->_domainsFile = $argv[2];
	
					preg_match('/[$]domainsFile =(.*?)[;]/', file_get_contents(__DIR__.'/conf.php'), $replcaDomain);
					$newConfig = str_replace($replcaDomain[0], '$domainsFile = \''.$this->_domainsFile.'\';', file_get_contents(__DIR__.'/conf.php'));
# Y
					if(file_put_contents(__DIR__.'/conf.php', $newConfig)) {
						echo $this->printToConsole(" ++ ", 'yellow', false);
						echo $this->printToConsole("New file domain ($this->_domainsFile) was change seccessfuly", 'green', false);
						echo $this->printToConsole(" ++", 'yellow');
					}
				break;
			}
		}
	}

	private function disable_ob() {
		// Turn off output buffering
		ini_set('output_buffering', 'off');
		// Turn off PHP output compression
		ini_set('zlib.output_compression', false);
		// Implicitly flush the buffer(s)
		ini_set('implicit_flush', true);
		ob_implicit_flush(true);
		// Clear, and turn off output buffering
		while (ob_get_level() > 0) {
			// Get the curent level
			$level = ob_get_level();
			// End the buffering
			ob_end_clean();
			// If the current level has not changed, abort
			if (ob_get_level() == $level) break;
		}
		// Disable apache output buffering/compression
		if (function_exists('apache_setenv')) {
			apache_setenv('no-gzip', '1');
			apache_setenv('dont-vary', '1');
		}
	}
	
}