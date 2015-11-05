<?php
/*
 *   Backpack.tf Database Importer
 *   For use with v4 of the Backpack.tf API
 *   Copyright (C) 2012-2014  Jake "rannmann" Forrester
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

include_once('../config.php'); //include our config file

$time_start = microtime(true); //start timer
$query_count = 0; //have a query count for debugging reasons

$link = mysqli_connect($server, $dbuser, $dbpass, $database); //create our mysql connection

if (!$link) { //if error, show and exit
	echo "Error: Unable to connect to MySQL." . PHP_EOL;
	echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
	echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
	exit;
}

echo "Success: A proper connection to MySQL was made!" . PHP_EOL; //otherwise show success
echo "<br/>Host information: " . mysqli_get_host_info($link) . "<br/>" . PHP_EOL;

//setting up curl, to make contact with the url for the api
function file_get_data($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.94 Safari/537.4');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$raw = file_get_data('http://backpack.tf/api/IGetMarketPrices/v1/?key=' . $bptf_api_key . '&appid=730') or die('Error connecting'); //contact that url for csgo using our api key
$prices = json_decode($raw,true);

if ($prices['response']['success'] == 0) { //if error, show and exit
    die('<br/>Error recieved from backpack.tf: ' . $prices['response']['message']);
}

$prices_clean = $prices['response']['items']; //select the items within the array, thats all we want for now

//Creating fake array just for testing tables.

$fakepricestest = array(
	'response' => 
		array('items' => 
			array(
			'Weapon1' => 
				array('last_updated' => 1336678011, 'quantity' => 50, 'value' => 500),
			'weapon2' => 
				array('last_updated' => 1336678511, 'quantity' => 75, 'value' => 650)
			)
		)
	);
	
$fakepricestest_clean = $fakepricestest['response']['items'];

//print_r ($prices_clean); //testing prices variable
//print_r ($fakepricestest_clean); //compare with created variable that should match.

/* Create the temporary table */

$sql = <<<SQL
DROP TABLE IF EXISTS weapons;
CREATE TABLE weapons (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	weaponname varchar(255),
	lastupdated INT(11),
	quantity INT,
	value INT
);
SQL;

if($result = mysqli_multi_query($link, $sql)){
	echo $result;
	$link->next_result();
} else {
	die('There was an error running the query [' . $link->error . ']');
}

echo " Weapon table created.";

$query_count++;

//fakearray insert
/*
if(is_array($fakepricestest_clean)){
	foreach($fakepricestest_clean as $key => $value){
		$sql = "INSERT INTO weapons (weaponname, lastupdated, quantity, value) VALUES ('" . $key . "', '" . $value['last_updated'] . "', '" . $value['quantity'] . "', '" . $value['value'] . "');";
		if (!$result = mysqli_multi_query($link, $sql)){
			die('There was an error running the query [' . $link->error . ']');
		}
		$query_count++;
	}
}
*/
//currently it only imports around 150 records, so it is getting caught on a variable in the below statement. Probably need to check if it is set first then assume zero.
if(is_array($prices_clean)){
	foreach($prices_clean as $key => $value){
		$sql = "INSERT INTO weapons (weaponname, lastupdated, quantity, value) VALUES ('" . $key . "', '" . $value['last_updated'] . "', '" . $value['quantity'] . "', '" . $value['value'] . "');";
		if (!$result = mysqli_multi_query($link, $sql)){
			die('There was an error running the query [' . $link->error . ']');
		}
		$query_count++;
	}
}

$time_end = microtime(true); //see time at the end
$time = $time_end - $time_start; //measure time

echo "$query_count queries completed successfully in $time seconds.";

mysqli_close($link);
?>
