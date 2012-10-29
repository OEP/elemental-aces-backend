<?PHP
require_once("../inc/functions.php");
$db = getAcesDB();

$result = $db->register("OEP", "foo");

print_r($result);

$result = $db->login("OEP", "foo");

print_r($result);

?>
