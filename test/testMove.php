<?PHP
require_once("testAuthenticated.php");

function chooseType($hand, $type)
{
  foreach($hand as $card)
  {
    if($card->cardType == $type)
    {
      echo "\tHe chooses " . strtoupper($card->name) . "!\n";
      return $card->id;
    }
  }
  return null;
}

function doPlay($db, $player, $game, $hand)
{
  $eid = chooseType($hand, 'ELEMENT');
  $mid = chooseType($hand, 'MAGIC');
  if($eid === null)
  {
    echo "{$player->username} can't play.\n";
    return;
  }

  return $db->move($player->id, $game->gameId,
      $game->nextMove, $eid, $mid);
}

authenticate();
$p1 = makeUniqueUser();
$p2 = makeUniqueUser();
$db = getAcesDB();

$p1 = $db->fetchByUsername($p1->user['username']);
$p2 = $db->fetchByUsername($p2->user['username']);

printf("Creating game...\n");

$gameView = $db->challenge($p1->id, $p2->username);
$game = $gameView->game;

while(! $db->gameOver($p1->id, $p2->id, $game->gameId))
{
  printf("========== MOVE %d =========\n", $game->nextMove);
  $p1handView = $db->fetchHand($p1->id, $game->gameId);
  $p2handView = $db->fetchHand($p2->id, $game->gameId);
  echo "\tPlayer 1 is moving.\n";
  $res1 = doPlay($db, $p1, $game, $p1handView->playerHand);
  echo "\tPlayer 2 is moving.\n";
  $res2 = doPlay($db, $p2, $game, $p2handView->playerHand);

  if(isError($res1) or isError($res2))
  {
    echo "Stopping due to error in move call.\n";
    print_r($res1);
    print_r($res2);
    exit(0);
  }

  $gameView = $db->fetchGameView($p1->id, $game->gameId);
  $game = $gameView->game;
}

print_r($gameView);

?>
