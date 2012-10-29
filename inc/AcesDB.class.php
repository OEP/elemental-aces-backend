<?PHP

require_once("c2dm.php");
require_once("functions.php");

class AcesDB
{
  private static $tableTrumps = 'trumps';
  private static $tableC2dmQueue = 'c2dmQueue';
  private static $tablePlayers = 'players';
  private static $tableProperties = 'properties';
  private static $nameClientLogin = 'clientLogin';

  ## Points awarded for winning a round.
  const pointsPerRound = 10;

  ## Maximum cards in hand.
  const handSize = 6;

  ## Starting magic cards in deck.
  const magicCardsInDeck = 5;

  ## Starting element cards in deck.
  const elementCardsInDeck = 25;

  function __construct($dbname, $user, $password, $host)
  {
    $this->dblink = mysql_connect($host, $user, $password);
    
    if(!$this->dblink)
    {
      throw new Exception(mysql_error());
    }

    mysql_select_db($dbname);

    $this->resultQueue = array();
  }

  public function userExists($username)
  {
    return $this->resolveUsername($username) !== false;
  }

  // Resolves a username to its ID.
  public function fetchByUsername($username)
  {
    $sql = sprintf("SELECT id, username FROM players WHERE username = '%s';",
        $this->escapeString($username));
    $result = $this->query($sql);
    if(!$result)
    {
      return false;
    }
    $count = $this->countRows();
    if($count == 0)
    {
      $this->purge();
      return false;
    }
    
    $obj = $this->fetchObject();
    $this->purge();
    return $obj;
  }

  // Resolves a username to its ID.
  public function resolveUsername($username)
  {
    $sql = sprintf("SELECT id FROM players WHERE username = '%s';",
        $this->escapeString($username));
    $result = $this->query($sql);
    if(!$result)
    {
      return false;
    }
    $count = $this->countRows();
    if($count == 0)
    {
      $this->purge();
      return false;
    }
    
    $obj = $this->fetchObject();
    $this->purge();
    return $obj->id;
  }

  public function alertUser($playerId, $gameId) {
    $user = $this->fetchUserById($playerId);

    ## Fail if user lookup fails.
    if(! empty($user->error) ) return false;

    ## Fail if c2dm key not set.
    if(empty($user->user->c2dmKey))
    {
      return false;
    }

    ## Pawn off to c2dm helper function.
    return c2dmPushGameId($user->user->c2dmKey, $gameId);
  }

  // Given a username and authkey, return the ID of the username
  // if the authentication key is valid. Otherwise, return FALSE.
  public function isAuthenticated($username, $authKey)
  {
    $sql = sprintf("SELECT id FROM players WHERE (username = '%s'
        AND authKey = '%s');",
        $this->escapeString($username),
        $this->escapeString($authKey)
    );

    $result = $this->query($sql);
    if(!$result) return false;

    $count = $this->countRows();

    if($count == 0)
    {
      $this->purge();
      return false;
    }

    $obj = $this->fetchObject();
    $this->purge();
    return $obj->id;
  }

  public function login($username, $password)
  {
    $authKey = md5( time() . mt_rand() );
    $sql = sprintf("UPDATE players SET authKey = '%s'
      WHERE (username = '%s' AND password = PASSWORD('%s'));",
      $authKey,
      $this->escapeString($username),
      $this->escapeString($password));

    $result = $this->query($sql);
    if(!$result)
    {
      return mkMysqlErrorObject();
    }

    $rows = $this->affectedRows();
    $this->purge();

    if($rows > 0)
    {
      return (object) array(
            "user" => array(
                "username" => $username,
                "authKey" => $authKey
              )
      );
    }
    else
    {
      return mkGenericErrorObject("Incorrect username or password.");
    }
  }

  // Given a username and password attempt to register
  // the user. Returns a generic error object when the user
  // exists, a mysql error object if there was an error inserting
  // and a user object when the insertion was successful.
  public function register($username, $password)
  {
    if($this->userExists($username))
    {
      return mkGenericErrorObject("That username exists already.");
    }

    $authKey = md5( time() . mt_rand() );
    $sql = sprintf("INSERT INTO players (username, password, authKey)
      VALUES ('%s', PASSWORD('%s'), '%s');",
      $this->escapeString($username),
      $this->escapeString($password),
      $authKey);

    $result = $this->query($sql);
    if(!$result)
    {
      return mkMysqlErrorObject();
    }
    else
    {
      return (object) array(
            "user" => array(
                "username" => $username,
                "authKey" => $authKey
              )
      );
    }
  }

  // Sets the Google key in the database.
  public function setGoogleKey($key)
  {
    $this->setProperty(self::$nameClientLogin, $key);
  }

  // Fetches the key needed for C2DM logon.
  public function fetchGoogleKey()
  {
    return $this->fetchProperty(self::$nameClientLogin);
  }

  private function setProperty($name, $value)
  {
    $sql = sprintf("REPLACE INTO properties (name, value) VALUES ('%s','%s');",
        addslashes($name), addslashes($value));
    $result = $this->query($sql);
    return $result;
  }

  public function deleteTrumps()
  {
    $sql = sprintf("DELETE FROM trumps WHERE 1=1;");
    return $this->query($sql);
  }

  public function restoreTrump($th, $that)
  {
    $sql = sprintf("REPLACE INTO trumps (this, that) VALUES (%d,%d);",
        $th,$that);
    return $this->query($sql);
  }

  // Returns value of generic property.
  private function fetchProperty($name)
  {
    $sql = sprintf("SELECT value FROM %s WHERE name = '%s';",
        self::$tableProperties, $name);
    $this->query($sql);

    if($this->countRows() == 0)
    {
      $this->purge();
      return false;
    }

    $obj = $this->fetchObject();
    $this->purge();
    return ($obj) ? $obj->value : false;
  }

  public function isValidPlay($playerId, $gameId, $cardId, $cardType, $nullOk=false)
  {
    if($cardId == null) return $nullOk;
    $card = $this->fetchCard($cardId);
    if(!$card) return false;
    if(!$card->cardType === $cardType) return false;
    if(!$card->playerId === $playerId) return false;
    if(!$card->gameId === $gameId) return false;
    if(!$card->pileType === "hand") return false;
    return $card;
  }

  public function hasMoved($playerId, $gameId, $moveNum)
  {
    $sql = sprintf("SELECT * FROM previousMoves WHERE 
          moveNumber = %d AND
          gameId = %d AND
          playerId = %d LIMIT 1",
          $moveNum, $gameId, $playerId);

    $result = $this->fetchAll($sql);
    return count($result) !== 0;
  }

  private function fetchOpponentId($playerId, $gameId)
  {
    $sql = sprintf("SELECT playerId FROM gamePlayers WHERE
        playerId != %d AND gameId = %d LIMIT 1",
        $playerId, $gameId);

    $opp = $this->fetchAll($sql);
    return $opp[0]->playerId;
  }

  public function move($playerId, $gameId, $moveNum, $elementId, $magicId)
  {
    $gameView = $this->fetchGameView($playerId, $gameId);

    if(isError($gameView))
    {
      ## Error fetching view --> invalid player/game combo.
      return mkGenericErrorObject("You can't play in that game!");
    }

    if($gameView->game->nextMove != $moveNum)
    {
      ## Synchronization error.
      return $gameView;
    }

    if($this->hasMoved($playerId, $gameView->game->gameId, $moveNum))
    {
      return mkGenericErrorObject("You've already played!");
    }
    
    ## Combination of fetching card instances and checking if they're valid.
    $element = $this->isValidPlay($playerId, $gameId, $elementId, "ELEMENT");
    $magic = $this->isValidPlay($playerId, $gameId, $elementId, "MAGIC", true);

    if(!$element || !$magic)
    {
      return mkGenericErrorObject("You can't play those cards!");
    }


    $this->insertMove($playerId, $gameId, $moveNum, $elementId, $magicId);

    $moves = $this->fetchMoves($playerId, $gameId, $moveNum);

    if( isset($moves->opponentCurrentMove) && 
        isset($moves->playerCurrentMove))
    {
      ## Updates scores, game counter, cards and stuff.
      $this->consumeMoves($moves->opponentCurrentMove,
          $moves->playerCurrentMove);

      $opponentId = $this->fetchOpponentId($playerId, $gameId);

      ## Score updates, game-over specific logic.
      if($this->gameOver($playerId, $opponentId, $gameId))
      {
        ## TODO: Game over logic?
      }
      else
      {
        $this->alertUser($opponentId, $gameId);
      }
    }


    return $this->fetchGameView($playerId, $gameId);
  }

  private function consumeMoves($m1, $m2)
  {
    $winner = $this->getWinner($m1->elementId, $m2->elementId);

    if($winner === false) {}
    else if($winner == $m1->elementId)
    {
      $this->advanceScore($m1->playerId, $m1->gameId);
    }
    else if($winner == $m2->elementId)
    {
      $this->advanceScore($m2->playerId, $m2->gameId);
    }

    $this->discard($m1->elementCardId);
    $this->discard($m2->elementCardId);
    $this->discard($m1->magicCardId);
    $this->discard($m2->magicCardId);

    $this->draw($m1->playerId, $m2->gameId);
    $this->draw($m2->playerId, $m2->gameId);

    $this->advanceMoveCounter($m1->gameId);
  }

  private function advanceScore($playerId, $gameId)
  {
    $sql = sprintf("UPDATE gamePlayers SET score = score + %d
        WHERE playerId = %d LIMIT 1",
        AcesDB::pointsPerRound,
        $playerId);
    return $this->query($sql);
  }

  private function discard($cardId)
  {
    if($cardId === null) return;
    $sql = sprintf("UPDATE cardPile SET pileType = 'discard'
      WHERE id = %d", $cardId);
    $this->query($sql);
  }

  ## Draw as many cards as needed to fill up a player's hand.
  ## Returns true if the player is out of cards after drawing.
  private function draw($playerId, $gameId)
  {
    $n = $this->countHand($playerId, $gameId);
    $howMany = max(0, AcesDB::handSize-$n);
    $keepGoing = true;

    while($howMany > 0 && $keepGoing)
    {
      $keepGoing = $keepGoing && $this->drawOne($playerId, $gameId);
      $howMany -= 1;
    }

    ## Ran out of cards while drawing.
    if(!$keepGoing) return true;

    return $this->countDeck($playerId, $gameId) == 0;
  }

  public function gameOver($p1, $p2, $gameId)
  {
    // count Elements in hands
    $p1hand = $this->countCategoryInHand($p1, $gameId, 'ELEMENT');
    $p2hand = $this->countCategoryInHand($p2, $gameId, 'ELEMENT');
    $p1deck = $this->countDeck($p1, $gameId);
    $p2deck = $this->countDeck($p2, $gameId);

    return ($p1hand == 0 && $p1deck == 0) || ($p2hand == 0 && $p2deck == 0);
  }

  private function drawOne($playerId, $gameId)
  {
    $sql = sprintf("UPDATE cardPile SET pileType = 'hand'
      WHERE pileType = 'deck' AND playerId = %d AND gameId = %d LIMIT 1",
      $playerId, $gameId);
    
    $this->query($sql);
    return $this->affectedRows() > 0;
  }

  private function advanceMoveCounter($gameId)
  {
    $sql = sprintf("UPDATE games SET nextMove=nextMove+1 WHERE
        id = %d\n", $gameId);
    $this->query($sql);
  }

  ## Inserts a move.
  ## DOES NOT MOVE VALIDATION. Must be done beforehand!
  private function insertMove($playerId, $gameId, $moveNum, $elementId,
    $magicId=null)
  {
    $sql = sprintf("INSERT INTO previousMoves
      (playerId, gameId, moveNumber, elementCardId, magicCardId)
      VALUES (%d, %d, %d, %d, %s)",
      $playerId, $gameId, $moveNum, $elementId,
      ($magicId != null) ? intval($magicId) : "NULL");

    $this->query($sql);
  }

  // Returns true if there exists a game between
  // playerIds $a and $b.
  public function existsGame($a, $b)
  {
    $a = (int) $a;
    $b = (int) $b;
    if($a === $b) return false;
    $sql = sprintf("SELECT p.gameId FROM gamePlayers p, gamePlayers q
        WHERE p.gameId = q.gameId && (p.playerId = %d AND q.playerId = %d
          OR p.playerId = %d AND q.playerId = %d);",
        $a, $b, $b, $a);
    $result = $this->query($sql);
    $count = $this->countRows();
    $this->purge();
    return $count > 0;
  }

  public function countDeck($playerId, $gameId)
  {
    return $this->countCards($playerId, $gameId, 'deck');
  }

  public function countHand($playerId, $gameId)
  {
    return $this->countCards($playerId, $gameId, 'hand');
  }
  
  public function countCategoryInHand($playerId, $gameId, $category)
  {
    return $this->countCategoryCards($playerId, $gameId, 'hand', $category);
  }

  private function countCards($playerId, $gameId, $pileType)
  {
    $result = $this->fetchCards($playerId, $gameId, $pileType);
    return ($result) ?  count($result) : 0;
  }
  
  private function countCategoryCards($playerId, $gameId, $pileType, $category)
  {
    $result = $this->fetchCategoryCards($playerId, $gameId, $pileType, $category);
    return ($result) ?  count($result) : 0;
  }
  
  // Fetches the hand for a given player in a given game.
  public function fetchHand($playerId, $gameId)
  {
    $result = $this->fetchCards($playerId, $gameId, 'hand');
    if(!$result)
    {
      return mkMysqlErrorObject();
    }

    return (object) array(
        "playerHand" => $result
    );
  }

  // Fetches the hand for a given player in a given game.
  public function fetchDiscard($playerId, $gameId)
  {
    $result = $this->fetchCards($playerId, $gameId, 'discard');
    if(!$result)
    {
      return mkMysqlErrorObject();
    }

    return (object) array(
        "playerDiscard" => $result
    );
  }

  // Fetches the deck for a given player in a given game.
  public function fetchDeck($playerId, $gameId)
  {
    $result = $this->fetchCards($playerId, $gameId, 'deck');
    if(!$result)
    {
      return mkMysqlErrorObject();
    }

    return (object) array(
        "playerDeck" => $result
    );
  }
  
  // Fetches cards out of the card pile for a given player and game
  private function fetchCards($playerId, $gameId, $pile = 'hand')
  {
    $sql = sprintf("SELECT * FROM allCardsView WHERE playerId = %d
        AND gameId = %d AND pileType = '%s'",
        intval($playerId), intval($gameId), $this->escapeString($pile));

    return $this->fetchAll($sql);
  }
  
  // Fetches element cards out of the card pile for a given player and game
  private function fetchCategoryCards($playerId, $gameId, $pile = 'hand', $category)
  {
    $sql = sprintf("SELECT * FROM allCardsView WHERE playerId = %d
        AND gameId = %d AND pileType = '%s' AND cardType = '%s'",
        intval($playerId), intval($gameId), $this->escapeString($pile), $this->escapeString($category));

    return $this->fetchAll($sql);
  }

  // Fetches cards out of the card pile by unique id
  private function fetchCard($cardId)
  {
    $sql = sprintf("SELECT * FROM allCardsView WHERE id = %d", intval($cardId));
    $result = $this->fetchAll($sql);
    return $result[0];
  }

  // Generic form of fetch*CardsIds.
  private function fetchCardIds($cardType)
  {
    $sql = sprintf("SELECT id FROM cards WHERE cardType = '%s';", $cardType);
    $ids = $this->fetchAll($sql);

    $out = array();
    foreach($ids as $idobj)
    {
      array_push($out, $idobj->id);
    }
    $this->purge();
    return $out;
  }

  // Returns an array of numeric magic card Ids
  private function fetchMagicCardIds()
  {
    return $this->fetchCardIds('MAGIC');
  }

  // Returns an array of numeric element card ids.
  private function fetchElementCardIds()
  {
    return $this->fetchCardIds('ELEMENT');
  }

  // Called when a game is first created. Randomly picks
  // 30 cards for agiven player in a given game.
  private function initializeCards($playerId, $gameId)
  {
    $magic = AcesDB::magicCardsInDeck;
    $element = AcesDB::elementCardsInDeck;
    $count = 0;

    $magicIds = $this->fetchMagicCardIds();
    $elementIds = $this->fetchElementCardIds();

    while($magic > 0 || $element > 0)
    {
      $pick = rand(0, $magic+$element);
      $pickFrom = $elementIds;

      // Uniform chance of choosing element or magic card.
      if($pick < $element)
      {
        $element -= 1;
      }
      else
      {
        $pickFrom = $magicIds;
        $magic -= 1;
      }

      $k = array_rand($pickFrom);
      $id = $pickFrom[$k];

      $this->addCard($id, $gameId, $playerId);
      $count += 1;
    }

    $this->draw($playerId, $gameId);
  }

  // Inserts a new game into games table. Returns gameId on OK, false on failure.
  private function insertGame()
  {
    $sql = sprintf("INSERT INTO games (nextMove) VALUES (1);");
    $result = $this->query($sql);
    if(!$result) return false;
    return $this->lastId();
  }

  // Adds a player to a game and initializes his cards too.
  private function addPlayerToGame($playerId, $gameId, $accepted = false)
  {
    $sql = sprintf("INSERT INTO gamePlayers (playerId, gameId, accepted)
        VALUES (%d, %d, %s);",
        $playerId, $gameId, ($accepted) ? "true" : "false");
    $result = $this->query($sql);
    if(!$result) return false;

    $this->initializeCards($playerId, $gameId);

    return true;
  }

  // Fetches gameViews for a given player
  public function fetchGameList($playerId)
  {
    $ids = $this->fetchGameIds($playerId);
    $games = array();

    foreach($ids as $idObj)
    {
      $gameObj = $this->fetchGameView($playerId, $idObj->gameId);
      array_push($games, $gameObj->game);
    }

    return (object) array(
        "games" => $games
    );
  }

  /**
    * Fetches a PHP array of gameIds the player is involved in."
    * 
    */
  public function fetchGameIds($playerId)
  {
    $sql = sprintf("SELECT gameId FROM gamePlayers WHERE playerId = %d", $playerId);
    return $this->fetchAll($sql);
  }


  // Fetches a gameView for a given player and game ID
  public function fetchGameView($playerId, $gameId)
  {
    $sql = sprintf("SELECT * FROM gameViews WHERE gameId = %d;", $gameId);
    $gameViews = $this->fetchAll($sql);
    if(!$gameViews)
    {
      return mkGenericErrorObject("No such game!");
    }

    $playerView = $this->combineViews($playerId, $gameViews[0], $gameViews[1]);
    $moves = $this->fetchMoves($playerId, $gameId, $playerView->nextMove);

    $playerView = mergeObjects($playerView, $moves); 
    
    // Provide information about who won the game.
    if( $this->gameOver($playerId, $playerView->opponent->playerId, $gameId) )
    {
        if($playerView->score == $playerView->opponent->score)
        {
            $playerView->winner = -1;
        }
        else
        {
            $playerView->winner ($playerView->score > $playerView->opponent->score)
                ? $playerId : $playerView->opponent->playerId;
        }
    }
    else
    {
        $playerView->winner = 0;
    }
    
    // call gameover
    // if true
    
    // winner int

    return (object) array(
        "game" => $playerView
    );
  }

  public function fetchMoves($playerId, $gameId, $moveNumber)
  {
    $sql = sprintf("SELECT * FROM previousMoves WHERE (moveNumber = %d OR moveNumber = %d)
        AND gameId = %d",
          $moveNumber,
          $moveNumber-1,
          $gameId);

    $result = $this->fetchAll($sql);
    $moveObj = array();

    foreach($result as $move)
    {
      $elementCard = $this->fetchCard($move->elementCardId);
      $move->elementName = $elementCard->name;
      $move->elementDescription = $elementCard->description;
      $move->elementId = $elementCard->elementId;

      if($move->magicCardId != null)
      {
        $magicCard = $this->fetchCard($move->magicCardId);
        $move->magicName = $magicCard->name;
        $move->magicDescription = $magicCard->description;

    ## TOMEBBEDO: FIX EFFECT IDS NOT COMING FROM THE DATABASE (allCardsView)
        $move->effectId = isset($magicCard->effectId) ?
          $magicCard->effectId : null;
      }

      if($move->playerId == $playerId && $move->moveNumber == $moveNumber - 1)
      {
        $moveObj['playerPreviousMove'] = $move;
      }
      else if($move->playerId == $playerId && $move->moveNumber == $moveNumber)
      {
        $moveObj['playerCurrentMove'] = $move;
      }
      else if($move->playerId != $playerId && $move->moveNumber == $moveNumber)
      {
        $moveObj['opponentCurrentMove'] = $move;
      }
      else if($move->playerId != $playerId && $move->moveNumber == $moveNumber - 1)
      {
        $moveObj['opponentPreviousMove'] = $move;
      }

      // TODO assign pnts
      
      getWinner($p1Throw, $p2Throw);
      
      
    }

    return (object) $moveObj;
  }

  ## Given two views of the same game, return a combined view
  ## of the two with the player's view as the root.
  private function combineViews($playerId, $view1, $view2)
  {
    if($view1->playerId == $playerId)
    {
      $playerView = $view1;
      $opponentView = $view2;
    }
    else
    {
      $playerView = $view2;
      $opponentView = $view1;
    }

    $playerView->opponent = $opponentView;
    return $playerView;
  }

  // Creates a game between two given player Ids.
  // Player one has accepted the game. (He is the challenger)
  private function createGame($playerOne, $playerTwo)
  {
    $gameId = $this->insertGame();
    $r1 = $this->addPlayerToGame($playerOne, $gameId, true);
    $r2 = $this->addPlayerToGame($playerTwo, $gameId);
    return $gameId;
  }

  // Given a numeric user ID and a username, create a game between
  // the two users. If something went wrong, return an error object.
  public function challenge($challenger, $challengeeUsername)
  {
    $challengee = $this->resolveUsername($challengeeUsername);
    if($challengee === false)
    {
      return mkGenericErrorObject(
          sprintf("Username `%s` not found!", addslashes($challengeeUsername))
      );
    }
    if($challengee === $challenger)
    {
      return mkGenericErrorObject("You cannot challenge yourself!");
    }

    if($this->existsGame($challenger, $challengee))
    {
      return mkGenericErrorObject(sprintf("You already challenged %s!",
            addslashes($challengeeUsername))
      );
    }

    $gameId = $this->createGame($challenger, $challengee);
    return $this->fetchGameView($challenger, $gameId);
  }
  
  public function accept($playerId, $gameId)
  {
  $sql = sprintf("UPDATE `elementalAces`.`gamePlayers` 
    SET `accepted`='1' WHERE `playerId`=%d and`gameId`=%d;", $playerId, $gameId);
  $this->query($sql);
  $this->purge();
  return "Accepted the challenge. The Wheel of Fate is turning...";
  }
  
  // Adds a given card id to a player's deck if no fourth positional argument
  // is given. Fourth positional argument may be 'deck', 'hand', or 'discard'.
  private function addCard($cardId, $gameId, $playerId, $where = 'deck')
  {
    $sql = sprintf("INSERT INTO `elementalAces`.`cardPile`
        (`gameId`, `pileType`, `playerId`, `cardId`, `timestamp`) 
        VALUES (%d, '%s', %d, %d, NOW());",
        $gameId, $where, $playerId, $cardId);
    $result = $this->query($sql);
    if(!$result) return false;
    return true;
  }

  // Fetches all the messages we need to push out to the C2DM
  // clients.
  public function fetchPushMessages()
  {
    $sql = sprintf("SELECT c.playerId, p.c2dmKey, c.gameId
        FROM %s p, %s c WHERE c.playerId = p.id;",
        self::$tablePlayers, self::$tableC2dmQueue);

    return $this->query($sql);
  }

  // Removes messages from C2DM queue given playerId and gameId
  public function removeMessage($playerId, $gameId)
  {
    $sql = sprintf("REMOVE FROM %s WHERE playerId = %d AND gameId = %d;",
        $playerId, $gameId);

    return $this->query($sql);
  }

  // Given two element IDs $a, and $b, return $a if $a trumps
  // $b, return $b if $b trumps $a, or return false on a draw.
  public function getWinner($a, $b)
  {
    // Sanitize.
    $a = (int) $a;
    $b = (int) $b;

    $sql = sprintf("SELECT `this` FROM `%s` WHERE (this = %d AND that = %d) OR
        (this = %d AND that = %d) LIMIT 1", self::$tableTrumps, $a, $b, $b, $a);

    if(!$this->query($sql))
    {
      return false;
    }

    $row = $this->fetchObject();

    if($row === false) return false;
    $winner = $row->this;
    $this->purge();
    return $winner;
  }
  
  public function associateC2DM($id, $regId)
  {
    $sql = sprintf("UPDATE `elementalAces`.`players` SET c2dmKey = '%s'
        WHERE id = %d;\n", $regId, $id);
    $result = $this->query($sql);
   
    if ($this->affectedRows() > 0)
    {
      return $this->fetchUserById($id);
    }
    else
    {
      return mkGenericErrorObject("C2DM Association failed");
    }
    
  }

  public function fetchUserById($id)
  {
    $sql = sprintf("SELECT username, authKey, c2dmKey
        FROM players
        WHERE id = '%s'", $id);
	  $result = $this->query($sql);
	    
    if( !$result ) {
      return mkMysqlErrorObject();
    }

    $u = $this->fetchObject();
    return (object) array(
        "user" => $u
    );
  }
  
  public function lastId()
  {
    return mysql_insert_id();
  }

  public function affectedRows()
  {
    return mysql_affected_rows();
  }

  public function countRows()
  {
    return mysql_num_rows($this->peekResource());
  }
  
  public function fetchAll($sql)
  {
    $result = $this->query($sql);
    $output = array();
    if(!$result) { return false; }
    while($obj = $this->fetchObject())
    {
        array_push($output, $obj);
    }
    $this->purge();
    return $output;
  }

  public function fetchObject()
  {
    return mysql_fetch_object($this->peekResource());
  }

  private function query($sql)
  {
#    if( stripos($sql, "previousMoves") !== false || stripos($sql, "INSERT") !== false)
#      echo "{$sql}\n";
    $result = mysql_query($sql);
    $this->pushResource($result);
    return $result;
  }

  // Purge the latest resource.
  public function purge()
  {
    if($res = $this->popResource())
    {
      mysql_free_result($res);
    }
  }

  private function peekResource()
  {
    if(count($this->resultQueue)==0) return false;
    return current($this->resultQueue);
  }

  // Appends $res to the resource queue if it is a resource.
  private function pushResource($res)
  {
    if( is_resource($res) )
    {
      array_push($this->resultQueue, $res);
      end($this->resultQueue);
    }
  }

  private function popResource()
  {
    if( count($this->resultQueue) == 0 ) return false;
    $size = count($this->resultQueue);
    if( key($this->resultQueue) == $size - 1 && $size >= 0)
      prev($this->resultQueue);
    return array_pop($this->resultQueue);
  }

  private function escapeString($string)
  {
    return addslashes($string);
  }

  // Deallocate any open resources and close the link.
  function __destruct()
  {
    while( $res = $this->popResource() )
    {
      mysql_free_result($res);
    }
    mysql_close( $this->dblink );
  }
}
?>
