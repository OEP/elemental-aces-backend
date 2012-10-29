<?PHP
require_once("config.php");

$restApi = json_decode( file_get_contents( __RESTAPI__ ) );

printClass($restApi);

function printClass($root)
{
  $description = array();
  array_push($description, "RestEasy auto-generated class stub");
  array_push($description, "Class {$root->name}");
  array_push($description, "Author: {$root->author}");
  array_push($description, "Description: {$root->description}");
  echo "<?PHP\n";
  echo "/*\n";
  foreach($description as $line)
  {
    echo " * {$line}\n";
  }
  echo " */\n";

  echo "class " . (empty($root->name) ? "Callbacks" : $root->name) . "\n";
  echo "{\n";
  printSet($root->gets, "GET");
  printSet($root->posts, "POST");
  echo "}\n";
  echo "?>\n";
}

function printSet($root, $method)
{
  if( empty($root) ) return false;

  echo "  // =============================================== //\n";
  echo "  //                   {$method}\n";
  echo "  // =============================================== //\n";
  echo "\n";
  foreach($root as $method)
  {
    printMethod($method);
  }
}

function printMethod($root)
{
  echo "  /*\n";
  echo "   * Callback: {$root->callback}\n";
  echo "   * Path model: {$root->path}\n";
  echo "   * Description: {$root->doc}\n";
  echo "   * Parameters: " . (empty($root->parameters) ? "<None>" : implode(", ", $root->parameters)) . "\n";
  echo "   * Optional parameters: " . (empty($root->optionals) ? "<None>" : implode(", ", $root->optionals)) . "\n";
  echo "   */\n";
  echo "  function {$root->callback}(\$path, \$pathParameters, \$methodParameters)\n";
  echo "  {\n";
  $params = (empty($root->parameters) ? array() : $root->parameters);
  $oparams = (empty($root->optionals) ? array() : $root->optionals);
  $pparams = extract_path_parameters($root->path);
  foreach($pparams as $param)
  {
    echo "    \${$param} = \$pathParameters['{$param}'];\n";
  }
  foreach($params as $param)
  {
    echo "    \${$param} = \$methodParameters['{$param}'];\n";
  }
  foreach($oparams as $param)
  {
    echo "    \${$param} = (array_key_exists('$param', \$methodParameters) ? \$methodParameters['$param'] : null);\n";
  }
  echo "\n\n";
  echo "    echo \"Reached {$root->callback} callback body\";\n";
  echo "  }\n";
  echo "\n";
}

function extract_path_parameters($modelPath)
{
  $out = array();
  $modelParts = preg_split("%/+%", $modelPath);

  for($i = 0; $i < count($modelParts); $i++)
  {
    $mpart = $modelParts[$i];
    $n = strlen($mpart);

    if($n < 3)
    {
      continue;
    }
    else if($mpart{0} == "<" && $mpart{ $n - 1 } == ">")
    {
      $key = substr($mpart, 1, $n-2);
      array_push($out, $key);
    }
  }
  return $out;
}

?>
