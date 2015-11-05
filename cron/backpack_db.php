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

$raw = file_get_data('http://backpack.tf/api/IGetMarketPrices/v1/?key=' . $bptf_api_key . '&appid=730') or die('Error connecting'); //contact that url for csgo market data using our api key
$prices = json_decode($raw,true);

if ($prices['response']['success'] == 0) { //if error, show and exit
    die('<br/>Error recieved from backpack.tf: ' . $prices['response']['message']);
}

$prices_clean = $prices['response']['items']; //select the items within the array, thats all we want for now, makes the code cleaner below.

//Creating fake array just for testing tables. It should be exactly like the api gives us.

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
//new way to put in sql into a variable. I think it has something to do with mysqli being the substitute instead of mysql.
$sql = <<<SQL
DROP TABLE IF EXISTS weapons_temp;
CREATE TABLE weapons_temp (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	weaponname varchar(255),
	lastupdated INT(11),
	quantity INT,
	value INT
);
SQL;

if($result = mysqli_multi_query($link, $sql)){
	echo $result . " Weapon table created.";
	$link->next_result(); //next result if we are going to run another sql command after this. Otherwise we will get "code can not be run at this time".
} else {
	die('There was an error running the query [' . $link->error . ']');
}

$query_count++;

//run through the foreach to insert all weapons into table
if(is_array($prices_clean)){
	foreach($prices_clean as $key => $value){ //for each key run this insert statement with its values within the key to insert.
		$sql = "INSERT INTO weapons_temp (weaponname, lastupdated, quantity, value) VALUES 
		('" . mysqli_real_escape_string($link, $key) . "', '" . mysqli_real_escape_string($link, $value['last_updated']) . "', 
		'" . mysqli_real_escape_string($link, $value['quantity']) . "', '" . mysqli_real_escape_string($link, $value['value']) . "');"; //make sure we escape our variables, or else the single quotes will get us!
		//echo $sql; //Used for syntax error troubleshooting.
		if (!$result = mysqli_multi_query($link, $sql)){
			die('There was an error running the query [' . $link->error . ']');
		}
		$query_count++;
	}
}

//the current exchange rate is in usd. We need to get this into cad somehow. We may be able to use https://openexchangerates.org/ to use their api and get exchange rates, and see how close that compares with steam.
//apparently steam updates their exchange rates every morning with an unknown service. Possibly paypal.

//next would be to format the data in a better way, parse the weapon name and seperate quality, and other values. When we do that, would should make the table name a temp. then rename it to the actual table at the end.

$time_end = microtime(true); //see time at the end
$time = $time_end - $time_start; //measure time

echo "<br/>$query_count queries completed successfully in $time seconds."; //show how many queries we ran and how long it took us.

mysqli_close($link); //Always close our sql connections.
?>
