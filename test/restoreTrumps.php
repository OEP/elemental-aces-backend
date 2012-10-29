<?PHP
require_once("../inc/functions.php");
$db = getAcesDB();

function restoreTrump($this, $that, $result)
{
  global $db;
  if($this == $result)
  {
    $db->restoreTrump($this,$that);
  }
}

$db->deleteTrumps();
restoreTrump(1,1, 0);
restoreTrump(1,2, 2);
restoreTrump(1,3, 0);
restoreTrump(1,4, 1);
restoreTrump(1,5, 1);
restoreTrump(1,6, 6);
restoreTrump(2,1, 2);
restoreTrump(2,2, 0);
restoreTrump(2,3, 3);
restoreTrump(2,4, 0);
restoreTrump(2,5, 5);
restoreTrump(2,6, 2);
restoreTrump(3,1, 0);
restoreTrump(3,2, 3);
restoreTrump(3,3, 0);
restoreTrump(3,4, 4);
restoreTrump(3,5, 5);
restoreTrump(3,6, 3);
restoreTrump(4,1, 1);
restoreTrump(4,2, 0);
restoreTrump(4,3, 4);
restoreTrump(4,4, 0);
restoreTrump(4,5, 4);
restoreTrump(4,6, 6);
restoreTrump(5,1, 1);
restoreTrump(5,2, 5);
restoreTrump(5,3, 5);
restoreTrump(5,4, 4);
restoreTrump(5,5, 0);
restoreTrump(5,6, 0);
restoreTrump(6,1, 6);
restoreTrump(6,2, 2);
restoreTrump(6,3, 3);
restoreTrump(6,4, 6);
restoreTrump(6,5, 0);
restoreTrump(6,6, 0);
?>
