<?PHP
require_config();
require_once("AcesDB.class.php");

define('__AUTH_HEADER__', 'x-elementalaces-session');

abstract class API {
  const GenericError = "GenericError";
  const AuthError = "AuthError";
}

function require_config()
{
  require_once("/home/www/priv/config.php");
}

function isError($response)
{
  return isset($response->error);
}

// Halt due to authentication error.
function authHalt($msg)
{
  halt($msg, API::AuthError);
}

function halt($msg, $type=API::GenericError)
{
  printJSON(mkGenericErrorObject($msg, $type));
  exit(0);
}

// Print a JSON-serializable $thing
// and return proper mimetype.
function printJSON($thing)
{
  if(!headers_sent())
  {
    header("Content-Type: application/json");
  }
  echo json_encode($thing);
}

function assertAuthenticated()
{
  $headers = apache_request_headers();

  if(!isset($headers[__AUTH_HEADER__]))
  {
    authHalt("You must be authenticated to do that.");
  }

  $session = $headers[__AUTH_HEADER__];
  $splitSession = explode(":", $session, 2);

  if( count($splitSession) != 2 )
  {
    authHalt("That is a malformed session ID.");
  }

  $username = $splitSession[1];
  $authKey = $splitSession[0];

  $db = getAcesDB();

  $result = $db->isAuthenticated($username, $authKey);

  if($result === false)
  {
    authHalt("That is not a valid authentication key for that user.");
  }

  return $result;
}

function mkGenericErrorObject($msg, $type=API::GenericError)
{
  return (object) array(
      "error" => array(
          "message" => $msg,
          "type" => $type
      )
  );
}


function mkMysqlErrorObject()
{
  return (object) array(
      "error" => array(
          "message" => mysql_error()
      )
  );
}

/**
  * Given two objects, merges their attributes and
  * returns a copy.
  */
function mergeObjects($A, $B)
{
  return (object) array_merge(((array) $A), ((array) $B));
}

function getAcesDB()
{
  global $globalConfig;
  return new AcesDB($globalConfig["MYSQL_DB"],
      $globalConfig["MYSQL_USER"], 
      $globalConfig["MYSQL_PASSWORD"],
      $globalConfig["MYSQL_HOST"]);
}

?>
