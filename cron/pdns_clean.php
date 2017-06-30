<?php
// Script created by Mark Scholten (www.mscholten.eu) for SinnerG BV (www.sinnerg.nl)
// Distribution is allowed if you don't change this copyright notice
// Changing this code is allowed if you don't change this copyright notice (at least for the parts created by Mark Scholten)
// Asking money for this script is allowed, however if you didn't change it don't say you created it (if you want to donate money, please donate it to PowerDNS)
// Mark Scholten and SinnerG BV provide this script "as is" and without any warranties, it is possible that there are errors in this script

// This script requires that the column failed is added to the domains table in the powerdns database, without it it will not work. int(5) should really be enough for this column

// Start config
$mysqlhost = ""; //mysql server
$mysqluser = ""; // mysql user
$mysqlpass = ""; // mysql pass
$mysqldaba = ""; //mysql database
$pdnssec = "/usr/bin/pdnssec"; // pdnssec

$test = 0; // change to 0 to delete records/domains after $checks tests that failed, if set to something else it will not delete anything
$checks = 7; // define the number of checks before deleting records, this has to be a number. Setting it to 0 will clean it immediately after 1 failed check
$verbose = 0; // set to 1 to be verbose (output domainnames that are deleted/are to be deleted (depending on the $test setting)
$sendmail = 0; // Send mail with domains with errors
$mailaddress = 'recipient@example.com'; // Mail address to send the mail to
// End config

$mysqli = mysqli_init();
if(!$mysqli){
        die('FATAL ERROR: mysqli_init failed');
}
if(!$mysqli->real_connect($mysqlhost, $mysqluser, $mysqlpass, $mysqldaba)){
        die('FATAL ERROR: mysqli->real_connect failed');
}

function is_stil_active($domain,$server){
	$axfr = shell_exec("dig AXFR ".$domain." @".$server."");
	$explode = explode("XFR size:",$axfr);
	if(isset($explode['1'])){
		return TRUE;
	}else{
		sleep(1);
		$axfr = shell_exec("dig AXFR ".$domain." @".$server."");
		$explode = explode("XFR size:",$axfr);
		if(isset($explode['1'])){
			return TRUE;
		}else{
			return FALSE;
		}
	}
}
$timestamp = time()-(24*3600); // get the timestamp for 24 hours ago

$mysqli->query("UPDATE domains SET `failed`=0 WHERE `type`='SLAVE' AND `last_check` > ".$timestamp." AND `failed` NOT LIKE '0'");

$sql3 = "SELECT `id`,`name`,`master`,`failed`,`account` FROM domains WHERE `type`='SLAVE' AND `last_check` < ".$timestamp;
$query = $mysqli->query($sql3) or die();
$dump = '';
if($query->num_rows == FALSE){
}else{
	while($record = $query->fetch_array(MYSQLI_ASSOC)){
		if(!is_stil_active($record['name'],$record['master'])){
			if($test === 0){
				$mysqli->query("UPDATE domains SET `failed`=failed+1 WHERE id='".$record['id']."'") or die();
				if($record['failed'] == $checks){
					$mysqli->query("DELETE FROM records WHERE domain_id='".$record['id']."'");
					$mysqli->query("DELETE FROM cryptokeys WHERE domain_id='".$record['id']."'");
					$mysqli->query("DELETE FROM domains WHERE id='".$record['id']."'");
					$mysqli->query("DELETE FROM domainmetadata WHERE domain_id='".$record['id']."'");
					//$mysqli->query("DELETE FROM dnssec WHERE domain_id='".$record['id']."'"); // a table we use internally for the dnssec data (with some scripts it can be requested remote (read only) by our domain management UI)
				}
			}
			if($verbose === 1){
				echo $record['name']."
";
			}
			$dump .= $record['name']." - ".$record['master']." - ".$record['account']." - ".$record['failed']."\r\n";
		}elseif($record->failed != 0){
			$mysqli->query("UPDATE domains SET `failed`=0 WHERE id='".$record['id']."'") or die();
		}

	}
	if($verbose === 1){
		echo "Done
";
	}
	if($dump != '' && $sendmail === 1){
		mail($mailaddress,"AXFR failed for the following domain names",$dump);
	}
}
?>
