<?PHP
require_once("testAuthenticated.php");

authenticate();
$id = assertAuthenticated();
$db = getAcesDB();
$authKey = md5( time() . mt_rand() );

print_r( $db->associateC2DM($id, $authKey) );

?>
