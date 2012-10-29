<?

class Callbacks
{
  function add($path, $pathVars, $params)
  {
    echo "{$pathVars['one']} + {$pathVars['two']} = " .
      ($pathVars['one'] + $pathVars['two']);
  }

  function mult($path, $pathVars, $params)
  {
    echo "{$pathVars['one']} * {$pathVars['two']} = " .
      ($pathVars['one'] * $pathVars['two']);
  }

  function putResource($path, $pathVars, $params)
  {
    echo "You put a resource!\n";
  }
}
