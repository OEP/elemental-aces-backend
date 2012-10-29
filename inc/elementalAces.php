<?PHP
/*
 * RestEasy auto-generated class stub
 * Class ElementalAces
 * Author: Paul Kilgo and Nicholas Hamner
 * Description: API for the card-based element trumping game, Elemental Aces
 */
 
require_once("functions.php");

class ElementalAces
{
  // =============================================== //
  //                   GET
  // =============================================== //

  /*
   * Callback: getGameList
   * Path model: /api/game_list
   * Description: Returns a list of all games the user is playing
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function getGameList($path, $pathParameters, $methodParameters)
  {
    $id = assertAuthenticated();
    $db = getAcesDB();
    printJSON( $db->fetchGameList($id) );
  }

  /*
   * Callback: getHand
   * Path model: /api/game/<gameId>/hand
   * Description: Returns a list of cards in a user's hand for given <gameId>
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function getHand($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];
    $id = assertAuthenticated();
    $db = getAcesDB();
    printJSON( $db->fetchHand($id, $gameId) );
  }

  /*
   * Callback: getDiscard
   * Path model: /api/game/<gameId>/discard
   * Description: Returns a list of cards in user's discard pile for <gameId>
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function getDiscard($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];
    $id = assertAuthenticated();
    $db = getAcesDB();
    printJSON( $db->fetchDiscard($id, $gameId) );
  }

  /*
   * Callback: getGameState
   * Path model: /api/game/<gameId>
   * Description: Returns the game state for a given gameId
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function getGameState($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];
    $id = assertAuthenticated();
    $db = getAcesDB();
    printJSON($db->fetchGameView($id, $gameId));
  }

  // =============================================== //
  //                   POST
  // =============================================== //

  /*
   * Callback: login
   * Path model: /api/login
   * Description: Logs in a user by username and password
   * Parameters: username, password
   * Optional parameters: <None>
   */
  function login($path, $pathParameters, $methodParameters)
  {
    $username = $methodParameters['username'];
    $password = $methodParameters['password'];

    $db = getAcesDB();
    $result = $db->login($username, $password);
    printJSON($result);
  }

  /*
   * Callback: register
   * Path model: /api/register
   * Description: Registers a new username and password
   * Parameters: username, password
   * Optional parameters: <None>
   */
  function register($path, $pathParameters, $methodParameters)
  {
    $db = getAcesDB();
    $username = $methodParameters['username'];
    $password = $methodParameters['password'];
    $result = $db->register($username, $password);
    printJSON($result);
  }

  /*
   * Callback: postMove
   * Path model: /api/game/<gameId>/move/<number>
   * Description: Post an arbitrary move number given a game ID and card instance IDs
   * Parameters: elementCardId
   * Optional parameters: magicCardId
   */
  function postMove($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];
    $number = $pathParameters['number'];
    $elementCardId = $methodParameters['elementCardId'];
    $magicCardId = (array_key_exists('magicCardId', $methodParameters) ? $methodParameters['magicCardId'] : null);

    $id = assertAuthenticated();
    $db = getAcesDB();
    $result = $db->move(
      $id, 
      $gameId,
      $number,
      $elementCardId,
      $magicCardId
    );
    printJSON($result);
  }

  /*
   * Callback: challenge
   * Path model: /api/challenge
   * Description: Challenges a user to a game.
   * Parameters: username
   * Optional parameters: <None>
   */
  function challenge($path, $pathParameters, $methodParameters)
  {
    $username = $methodParameters['username'];
    $id = assertAuthenticated();
    
    $db = getAcesDB();
    $result = $db->challenge($id, $username);
    printJSON($result);
  }

  /*
   * Callback: accept
   * Path model: /api/game/<gameId>/accept
   * Description: Accepts a game we've been challenged to.
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function accept($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];
    //echo "Reached postMove callback body";
    $id = assertAuthenticated();
    $db = getAcesDB();
    $result = $db->accept($id, $gameId);
    echo $result;
  }

  /*
   * Callback: reject
   * Path model: /api/game/<gameId>/reject
   * Description: Rejects a game we've been challenged to.
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function reject($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];


    echo "Reached reject callback body";
  }

  /*
   * Callback: concede
   * Path model: /api/game/<gameId>/concede
   * Description: Concedes a game which is in progress.
   * Parameters: <None>
   * Optional parameters: <None>
   */
  function concede($path, $pathParameters, $methodParameters)
  {
    $gameId = $pathParameters['gameId'];


    echo "Reached concede callback body";
  }

  /*
   * Callback: c2dmAssociate
   * Path model: /api/c2dm/associate
   * Description: Associates a C2DM registration ID with the logged-in user.
   * Parameters: regId
   * Optional parameters: <None>
   */
  function c2dmAssociate($path, $pathParameters, $methodParameters)
  {
    $regId = $methodParameters['regId'];
  	$id = assertAuthenticated();
  	$db = getAcesDB();
    $result = $db->associateC2DM($id, $regId);
    printJSON($result);
  }

}
?>
