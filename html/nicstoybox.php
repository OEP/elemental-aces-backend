<html>
<head>
<title>Where Nicholas Does Various Stuff?</title>
</head>
<body>

<h1>Elemental ACES API</h1>

<?PHP

require_once("config.php");

$api = json_decode( file_get_contents( __RESTAPI__ ));

echo "<h2>HTTP GET</h2>\n";
printFunctions("GET", $api->gets);
echo "<h2>HTTP POST</h2>\n";
printFunctions("POST", $api->posts);

function printFunctions($word, $functions)
{
  foreach($functions as $f)
  {
    printFunction($word, $f);
  }
}

function printFunction($word, $f)
{
  echo "<p>\n";
  echo "Request: <b>{$word} ". htmlspecialchars($f->path) ."</b><br/>\n";
  echo "Parameters: <b>". 
    ((empty($f->parameters)) ? "<i>None</i>" :
    implode(", ", $f->parameters)) ."</b><br/>\n";
  echo "Optional parameters: <b>". 
    ((empty($f->optionals)) ? "<i>None</i>" :
    implode(", ", $f->optionals)) ."</b><br/>\n";
  echo "Documentation: <b>". 
    ((empty($f->doc)) ? "<i>None</i>" :
    htmlspecialchars($f->doc)) ."</b><br/>\n";
  echo "</p>\n";

}

?>

</body>
</html>
