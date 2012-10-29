<?PHP
require_once("testAuthenticated.php");

authenticate();
$id = assertAuthenticated();

$db = getAcesDB();

$result = $db->fetchGameList( $id );

foreach( $result->games as $game )
{
  print_r($db->fetchHand($id, $game->gameId));
}

?>
