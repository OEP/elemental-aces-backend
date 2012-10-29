<?PHP
require_once("testAuthenticated.php");

authenticate();
$id = assertAuthenticated();
$unique = makeUniqueUser();
$uniqueName = $unique->user["username"];

print_r($unique);

$db = getAcesDB();

$tests = array("OEP", "probably-doesnt-exist", "raphael", $uniqueName);
foreach($tests as $test)
{
  echo "Challenging: " . $test . "\n";
  print_r( $db->challenge($id, $test) );
}

?>
