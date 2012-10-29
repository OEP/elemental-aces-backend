<?PHP

require_once("../inc/AcesDB.class.php");
require_once("../inc/functions.php");

$db = getAcesDB();

$debugId = $db->resolveUsername("OEP");
$apiResponse = $db->fetchGameList($debugId);

$gameList = (isset($apiResponse->games)) ? $apiResponse->games : array();

if( count($gameList) == 0 )
{
  echo "No valid games for debug user.\n";
  echo "Run testChallenge.php first.\n";
  exit(0);
}

$gameId = $gameList[0]->gameId;

$result = $db->fetchGameView($debugId, $gameId);

print_r($result);

?>
