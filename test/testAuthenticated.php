<?PHP
// This file is included from other test cases. It should simulate being
// authenticated to the other parts of the application.

$_TEST_HEADERS = array();

require_once("../inc/functions.php");

if( !function_exists("apache_request_headers") )
{
  function apache_request_headers()
  {
    global $_TEST_HEADERS;
    return $_TEST_HEADERS;
  }
}

function authenticate()
{
  global $_TEST_HEADERS;
  $db = getAcesDB();
  $db->register("OEP", "foo");
  $res = $db->login("OEP", "foo");
  $auth = sprintf("%s:%s", $res->user['authKey'], "OEP");
  $_TEST_HEADERS[__AUTH_HEADER__] = $auth;
}

function makeUniqueUser()
{
  $db = getAcesDB();
  $username = md5( microtime() );
  $password = "foo";
  return $db->register($username, $password);
}

?>
