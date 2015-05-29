<?php
//SET PARAMETERS
$chain      = "test chain"; //Name of chain. Displayed at beginning of each block
$blocktime 	= 600; //Min seconds between blocks
$maxmsg 	= 1000;  //Max messages per block
$maxlength 	= 256; 	 //Max characters per message
$genesismsg = "This is the gensis block in xcp.pw's inStone chain of immutable data and timestamps.";
$blockchain = true; //
date_default_timezone_set("UTC");
$dbname     = '';	//DB name
$username   = ''; 	
$dbpassword = '';		
$hostname   = 'localhost';


$height = $_GET["height"];
$msg = $_GET["msg"];

if ($height == NULL)                    $mode = 0; //add msg
elseif ($height == "top") 				$mode = 1; //get data about latest block or row
elseif (is_numeric($height))			$mode = 2; //view specified block or row
else 									$mode = -1; //error


//call the database
$dbhandle=mysql_connect($hostname, $username, $dbpassword);
mysql_select_db($dbname, $dbhandle);
mysql_set_charset('utf8');


if ($mode == 2) { //view specified block
	if ($blockchain) {
		$uri  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
		$path = "blocks/".sprintf('%02d', floor($height/10000))."/".sprintf('%02d', floor($height/100))."/";
		$filename = $height.".txt";
		header("Location: ".$uri."/".$path.$filename);
	} else {
		header('Content-Type: text/html; charset=utf-8');
		$sql = "SELECT * FROM `messages` WHERE `ind` = $height LIMIT 0,1";
		$data = mysql_query($sql);
		$result = mysql_fetch_array($data);
		echo "Success!<br /><br />Ind: $result[0]<br /><br />Message: $result[1]<br /><br />Hash: $result[2]";
	}
} else {
	header('Content-Type: text/html; charset=utf-8');
}


$timenow = date_create();
$timenowstr = date_format($timenow, 'Y-m-d H:i:s');
$sql = "SELECT * FROM `blocks` ORDER BY height DESC LIMIT 0,1";
$data = mysql_query($sql); 

if ($data == NULL) { //table does not exist -> make tables and genesis block
	if ($blockchain) {
		$heighttext = "chain: $chain\nblock: 1\ntime: $timenowstr\nto: 1\nfrom: 1\nprevhash:\n1: $genesismsg";
		if (!file_exists('blocks/00/00/')) mkdir('blocks/00/00/', 0777, true);
		file_put_contents("blocks/00/00/01.txt", $heighttext);
		$hash = hash_file('sha256', 'blocks/00/00/01.txt');
		$sql = "CREATE TABLE IF NOT EXISTS `blocks` (
		  `height` int(11) NOT NULL AUTO_INCREMENT,
		  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `tomsg` bigint(20) NOT NULL,
		  `frommsg` bigint(20) NOT NULL,
		  `hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
		  PRIMARY KEY (`height`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=0 ;";
		mysql_query($sql);
		$sql = "INSERT INTO `blocks` (`time`, `tomsg`, `frommsg`, `hash`) VALUES ('$timenowstr', 1, 1, '$hash');";	
		mysql_query($sql);
	}
	$sql = "CREATE TABLE IF NOT EXISTS `messages` (
		`ind` bigint(20) NOT NULL AUTO_INCREMENT,
		`msg` varchar($maxlength),
		`hash` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
		PRIMARY KEY (`ind`)
	) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=0 ;";
	mysql_query($sql);
	$hash = hash('sha256', mysql_real_escape_string($genesismsg));
	$sql = "INSERT INTO `messages` (`msg`, `hash`) VALUES ('".mysql_real_escape_string($genesismsg)."', '$hash');";
	mysql_query($sql);
	if ($blockchain) {
		$sql = "SELECT * FROM `blocks` ORDER BY height DESC LIMIT 0,1";
		$data = mysql_query($sql);
	}
}

if ($blockchain) {
	$result = mysql_fetch_array($data); 
	$latestheight = $result[0]; //height of latest block
	$latesttime =   $result[1]; //timestamp of latest block
	$latestinc  =   $result[2]; //index of last msg included in latest block
	$firstinc  =    $result[3]; //index of first msg included in latest block
	$latesthash =   $result[4]; //sha256 hash of latest block
}

$sql = "SELECT * FROM `messages` ORDER BY `ind` DESC LIMIT 0,1";
$data = mysql_query($sql);
$result = mysql_fetch_array($data);
$msglatestind =   $result[0];  //index of last msg submitted
$msglatest    =   $result[1]; 
$msglatesthash =   $result[2]; //hash of last msg submitted+prevhash

if ($mode == 0) { //add msg
	$state = 0;
	if ($msg == NULL)               	$state = -1; //error; no msg
	elseif (strlen($msg) >= $maxlength) $state = -2; //error; msg too long
	elseif ($msglatestind - $latestinc >= $maxmsg) $state = -3; //error; block is full
	
	if ($state == 0) {
		$hash = hash('sha256', mysql_real_escape_string($msg).' '.$msglatesthash);
		$sql = "INSERT INTO `messages` (`msg`, `hash`) VALUES ('".mysql_real_escape_string($msg)."', '$hash')";
		$data = mysql_query($sql);
		//$arr = array('status' => 'success', 'description' => "Message will be inserted in the next block", 'msg' => $msg);
		//echo json_encode($arr);
		echo "Success! Message will be inserted in the next block";
	} elseif ($state == -1) {
		//$arr = array('status' => 'error', 'description' => "No message submitted", 'msg' => $msg);
		//echo json_encode($arr);
		echo "Error! No message submitted";
	} elseif ($state == -2) {
		//$arr = array('status' => 'error', 'description' => "Message is too lang. Max length is 256 characters", 'msg' => $msg);
		//echo json_encode($arr);
		echo "Error! Message is too lang. Max length is 256 characters";
	} elseif ($state == -3) {
		//$arr = array('status' => 'error', 'description' => "Block is full. Wait until next block before submitting again", 'msg' => $msg);
		//echo json_encode($arr);
		echo "Error! Block is full. Wait until next block before submitting again";
	}
}	

	
if ($mode == 1) { //get data about latest block
	if ($blockchain) {
		$arr = array('status' => 'success', 'height' => $latestheight, 'time' => $latesttime, 'tomsg' => $latestinc, 'frommsg' => $firstinc, 'hash' => $latesthash, 'type' => 'block');
	} else {
		$arr = array('status' => 'success', 'height' => $msglatestind, 'time' => '', 'tomsg' => '', 'frommsg' => '', 'hash' => $msglatesthash, 'type' => 'row');
	}
	//echo json_encode($arr);
	echo $_GET['callback'] . '('.json_encode($arr).')';
die();
}


if ($blockchain && strtotime($timenowstr) - strtotime($latesttime) >= $blocktime) { //try to make a new block
	$sql = "SELECT `ind` FROM `messages` ORDER BY `ind` DESC LIMIT 0,1";
	$data = mysql_query($sql);
	$result = mysql_fetch_array($data);
	$msglatestind =   $result[0];  //index of last msg submitted (checking again because a msg can have been submitted)
	if ($msglatestind < $latestinc) { //shall never happen
		//$arr = array('status' => 'error', 'description' => "Critical error. Cannot generate block. Check database tables");
		//echo json_encode($arr);
		echo "Critical error! Cannot generate block. Check database tables";
	} elseif ($msglatestind == $latestinc) {
		//$arr = array('status' => 'success', 'description' => "Ready to make new block. Will do so once a message is submitted");
		//echo json_encode($arr);
		echo "Ready to make new block. Will do so once a message is submitted";
	} else { //ready to make a new block
		$thisheight = $latestheight+1;
		$firstmsg = $latestinc+1; //index of first message in block
		$heighttext = "chain: $chain\nblock: $thisheight\ntime: $timenowstr\nto: $msglatestind\nfrom: $firstmsg\nprevhash: $latesthash";
		$sql = "SELECT * FROM `messages` WHERE `ind` >= $firstmsg ORDER BY `ind` DESC";
		$data = mysql_query($sql);
		while ($row = mysql_fetch_array($data)) {
			$heighttext .= "\n".$row[0].": ".$row[1];  
		}
		$path = "blocks/".sprintf('%02d', floor($thisheight/10000))."/".sprintf('%02d', floor($thisheight/100))."/";
		$filename = sprintf('%02d', $thisheight).".txt";
		if (!file_exists($path)) mkdir($path, 0777, true);
		file_put_contents($path.$filename, $heighttext);
		$hash = hash_file('sha256', $path.$filename);
		$sql = "INSERT INTO `blocks` (`time`,  `tomsg`, `frommsg`, `hash`) VALUES ('$timenowstr', $msglatestind, $firstmsg, '$hash');";
		mysql_query($sql);
	}
}
?>
