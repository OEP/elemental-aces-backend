<?PHP
require_once("../inc/functions.php");
$db = getAcesDB();

$ids = $db->fetchMagicCardIds();
print_r($ids);

?>
