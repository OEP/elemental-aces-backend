<?PHP
// Pushes all the notifications out to c2dm.
require_once("../inc/functions.php");

$lock = ".c2dmlock";

if( file_exists($lock) )
{
  echo "Lock file found. Exiting...\n";
  exit(1);
}

// Create lock.
$fp = fopen($lock, "w");
if(!$fp)
{
  echo "Couldn't create lock. Exiting...\n";
  exit(1);
}
fclose($fp);



$db = getAcesDB();

$key = googleAuthenticate($db);

if(!$key)
{
  echo "Couldn't get C2DM authentication. Exiting...\n";
  cleanup(1);
}

echo "{$key}";

if( !$db->fetchPushMessages() )
{
  echo "MySQL error: " . mysql_error() . "; exiting...\n";
  cleanup(1);
}

while( $msg = $db->fetchObject() )
{
  
}

$db->purge();

cleanup(0);

function cleanup($status)
{
  global $lock;
  unlink($lock);
  exit($status);
}

?>
