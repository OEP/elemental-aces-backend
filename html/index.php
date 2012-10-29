<?PHP
require_once("config.php");
require_once( __CALLBACKS__ );
require_once("../inc/RestEasy.php");

$fre = new RestEasy(__RESTAPI__, new ElementalAces());

$fre->handle_request();
?>
