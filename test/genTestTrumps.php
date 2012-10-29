<?PHP
require_once("../inc/functions.php");
$db = getAcesDB();

echo "<?PHP\n";
echo "require_once(\"../functions.php\");\n";
echo "\$db = getDB();\n";

for($i = 1; $i <= 6; $i++) {
  for($j = 1; $j <= 6; $j++) {
    echo sprintf("assert(\$db%sgetWinner(%d,%d) == %d);\n", "->", $i, $j, $db->getWinner($i,$j));
  }
}
echo "?>\n";
?>
