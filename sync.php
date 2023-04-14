<?php
// Include the RouterOS API library
// require_once('routeros_api.class.php');

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);

use \RouterOS\Client;
use \RouterOS\Query;



$manager = $argv[1];

// Connect to the MySQL database
$db_host = $argv[2];
$db_user = $argv[3];
$db_pass = $argv[4];
$db_name = $argv[5];




$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Get the list of routers from the database
$sql = "select nasname,apiusername,apipassword from nas where apiusername !='' and apipassword !=''";
$result = mysqli_query($conn, $sql);
$routers = mysqli_fetch_all($result, MYSQLI_ASSOC);



// Loop through the routers and create PPPoE users on each one
foreach ($routers as $router) {
    // Get the list of PPPoE users from the database for this router
    $sql = "select radcheck.username,radcheck.value 
    FROM radcheck JOIN rm_users ON radcheck.username = rm_users.username 
    JOIN rm_services ON rm_users.srvid = rm_services.srvid 
    JOIN rm_allowednases ON rm_services.srvid = rm_allowednases.srvid 
    JOIN nas ON rm_allowednases.nasid = nas.id 
    WHERE nas.nasname = '".$router['nasname']."' and radcheck.attribute = 'Cleartext-Password'";
    
    $result = mysqli_query($conn, $sql);
    $pppoe_users = mysqli_fetch_all($result, MYSQLI_ASSOC);

    //Connect to router

   try{

    $client = new Client([
        'timeout' => 1,
        'host'    => $router['nasname'],
        'user'    => $router['apiusername'],
        'pass'    => $router['apipassword']
    ]);
     throw new Exception('An error occurred');
    } catch (Exception $e) {
        // Code to handle the exception
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }

     foreach ($pppoe_users as $pppoe_user) {
	$pppoe_username=$pppoe_user['username'];
	$pppoe_password=$pppoe_user['value'];

	$query = (['/ppp/secret/add', 
                   "=name=$pppoe_username", 
		   "=password=$pppoe_password", 
                   '=service=pppoe', '=profile=default', 
                   '=comment=RadiusFail',
                   '=disabled=yes']);
	$response = $client->query($query)->read();
        
         if (!isset($response['after']['message'])) {
                echo $router['nasname']." - ".$pppoe_username." - User Added ;";
                } else{
                        echo $router['nasname']." - ".$pppoe_username." - ".$response['after']['message'].";";
                        }
}
	


}

// Close the MySQL database connection
mysqli_close($conn);
?>
