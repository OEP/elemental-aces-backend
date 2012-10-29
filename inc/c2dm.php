<?PHP

require_once("../inc/functions.php");
require_config();

function googleAuthenticate($force = false, $source="Company-AppName-Version", $service="ac2dm") {
    global $globalConfig;
    $db = getAcesDB();

    $username = $globalConfig["C2DM_USER"];
    $password = $globalConfig["C2DM_PASSWORD"];

    // Attempt to short-circuit.
    if( !$force ) {
      $authKey = $db->fetchGoogleKey();
      if( $authKey ) {
        return $authKey;
      }
    }


    // get an authorization token
    $ch = curl_init();
    if(!$ch) {
        return false;
    }

    curl_setopt($ch, CURLOPT_URL, "https://www.google.com/accounts/ClientLogin");
    $post_fields = "accountType=" . urlencode('HOSTED_OR_GOOGLE')
                   . "&Email=" . urlencode($username)
                   . "&Passwd=" . urlencode($password)
                   . "&source=" . urlencode($source)
                   . "&service=" . urlencode($service);

    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // for debugging the request
    //curl_setopt($ch, CURLINFO_HEADER_OUT, true); // for debugging the request

    $response = curl_exec($ch);

    //var_dump(curl_getinfo($ch)); //for debugging the request
    //var_dump($response);

    curl_close($ch);

    if (strpos($response, '200 OK') === false) {
        return false;
    }

    // find the auth code
    preg_match("/(Auth=)([\w|-]+)/", $response, $matches);


    if (!$matches[2]) {
        return false;
    }

    $db->setGoogleKey( $matches[2] );
    return $matches[2];
}

function c2dmPushGameId($deviceRegId, $gameId) {
    $db = getAcesDB();

    $authCode = googleAuthenticate();

    if($authCode === false) {
      echo "Couldn't authenticate!\n";
      return false;
    }

    $response = c2dmSendGameId($authCode, $deviceRegId, $gameId);

    ## This means Google wants us to reauthenticate.
    ## We'll try it once. After that we'll have to stop.
    if( $response["httpCode"] == 401 )
    {
      $authCode = googleAuthenticate(true);

      if($authCode) {
        $response = c2dmSendGameId($authCode, $deviceRegId, $gameId);
        return $response;
      }

      return false;
    }

    return $response;
}

function c2dmSendGameId($authCode, $deviceRegId, $gameId) {
    $headers = array('Authorization: GoogleLogin auth=' . $authCode);
    $data = array(
                'registration_id' => $deviceRegId,
                'collapse_key' => $gameId,
                'data.game_id' => $gameId
            );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://android.apis.google.com/c2dm/send");
    if ($headers)
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HEADER, true);


    $response = curl_exec($ch);

    $parsed = c2dmParseResponse($response, $ch);
    curl_close($ch);
    return $parsed;
}

function parseHeaders($headers) {
  $out = array();
  $lines = split("\n", $headers);

  // Ignores the HTTP Response
  array_shift($lines);

  foreach($lines as $line) {
    $splitLine = explode(":", $line, 2);
    $name = trim($splitLine[0]);
    $value = "";

    if(count($splitLine) == 2) {
      $value = trim($splitLine[1]);
    }

    if(!empty($name))
    {
      $out[$name] = $value;
    }
  }
  return $out;
}

function c2dmParseResponse($response, $ch) {
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $out = array();
  $curlInfo = curl_getinfo($ch);


  $headerSize = $curlInfo['header_size'];
  $headerText = substr($response, 0, $headerSize);
  $bodyText = substr($response, $headerSize);
  $headers = parseHeaders($headerText);
  $out["httpCode"] = $httpCode;
  $out["headers"] = $headers;

  switch($httpCode) {
    case 200:
      $splitBody = explode("=", $bodyText, 2);
      $name = trim($splitBody[0]);
      $value = trim($splitBody[1]);
      if( !empty($name) && !empty($value) )
      {
        $out[$name] = $value;
      }
      else
      {
        $out["Error"] = "ParseError";
        $out["Response"] = array($name, $value);
      }
      break;

    default:
      break;
  }

  return $out;
}

?>
