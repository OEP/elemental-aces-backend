<?PHP
require_once("testAuthenticated.php");

authenticate();
$id = assertAuthenticated();

$db = getAcesDB();

print_r( $db->fetchGameList( $id ) );

?>
