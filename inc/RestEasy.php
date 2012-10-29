<?PHP
  class RestEasy
  {

    /** stdObject containing representation of config file */
    protected $restApi;

    /** Class which contains our callback functions */
    protected $classInstance;

    /** The constructor deserializes the config file and validates it.
      @throws Exception if the config file is invalid */
    function __construct($configFile, $classInstance)
    {
      $this->restApi = json_decode(file_get_contents($configFile));
      $this->classInstance = $classInstance;
      $this->validate_self();
    }

    /** Performs validation of this object and throws Exception if
      there is a problem */
    function validate_self()
    {
      return $this->validate_requests($this->restApi->gets)
        && $this->validate_requests($this->restApi->posts);
    }

    /** Validates a set of requests */
    function validate_requests($requests)
    {
      if( empty($requests) ) return true;

      foreach($requests as $request)
      {
        $this->except_if_empty($request->path, "Paths are required!");
        $this->except_if_empty($request->callback, "Callbacks are required!");
        $this->except_if_false(
          method_exists($this->classInstance, $request->callback),
          "Class instance has no callback named `{$request->callback}`!"
        );
      }
      return true; 
    }

    /** Throws exception if $var is empty */
    function except_if_empty($var, $msg)
    {
      $this->except_if_false( !empty($var), $msg );
    }

    /** Throws exception if $var is False */
    function except_if_false($var, $msg)
    {
      if( ! $var )
      {
        throw new Exception($msg);
      }
    }

    /** Attempts to perform a selected request */
    function perform_request($request, $path, $parameters)
    {
      if($request == NULL)
      {
        $this->not_implemented($path);
        return;
      }

      $required = (!empty($request->parameters) ? $request->parameters : array());
      $optional = (!empty($request->optionals) ? $request->optionals : array());
      $provided = array_keys($parameters);

      $all = array_unique( array_merge($optional, $required));
      $missing = array_diff($required, $provided);
      $excess = array_diff($provided, $all);

      if( count($missing) > 0 || count($excess) > 0 )
      {
        $this->malformed_request($missing, $excess);
        return;
      }

      $pathVars = $this->extract_path_parameters($request->path, $path);
      $callback = $request->callback;
      $this->classInstance->$callback($path, $pathVars, $parameters);
    }

    /* Returns an array with named keys for paths likes /resource/<id>/ */
    function extract_path_parameters($modelPath, $path)
    {
      $pathParameters = array();
      $modelParts = preg_split("%/+%", $modelPath);
      $pathParts = preg_split("%/+%", $path);

      for($i = 0; $i < count($modelParts); $i++)
      {
        $mpart = $modelParts[$i];
        $ppart = $pathParts[$i];
        $n = strlen($mpart);

        if($n < 3)
        {
          continue;
        }
        else if($mpart{0} == "<" && $mpart{ $n - 1 } == ">")
        {
          $key = substr($mpart, 1, $n-2);
          $pathParameters[$key] = $ppart;
        }
      }
      return $pathParameters;
    }

    /** Given a set of requests, find the one that corresponds to $path */
    function select_request($requests, $path)
    {
      $pathParts = preg_split("%/+%", $path);

      $m = count($pathParts);

      foreach($requests as $request)
      {
        $modelParts = preg_split("%/+%", $request->path);

        if($m != count($modelParts))
        {
          continue;
        }

        $match = true;

        for($i = 0; $match && $i < count($modelParts); $i++)
        {
          $mpart = $modelParts[$i];
          $ppart = $pathParts[$i];
          $n = strlen($mpart);

          if(strlen($mpart) == 0 && strlen($ppart) == 0)
          {
            continue;
          }
          else if($n >= 3 && $mpart{0} == "<" && $mpart{ $n-1 } == ">")
          {
            continue;
          }
          else if($mpart == $ppart)
          {
            continue;
          }
          else
          {
            $match = false;
          }
        }

        if($match)
        {
          return $request;
        }
      }
      return NULL;
    }

    function not_implemented($path)
    {
      header("HTTP/1.0 501 Not implemented", true, 501);
      echo "The path {$path} has no function associated with it.\n";
    }

    function malformed_request($missing, $excess)
    {
      header("HTTP/1.0 400 Bad request", true, 400);
      echo "The received request was invalid<br />\n";
      if( count($missing) > 0 )
      {
        echo "The following parameters are required: "
          . implode(", ", $missing) . "<br/>\n";
      }

      if( count($excess) > 0 )
      {
        echo "The following parameters were not understood: "
          . implode(", ", $excess) . "<br/>\n";
      }
    }

    function handle_request()
    {
      global $_SERVER, $_POST, $_GET;
      if( empty($_SERVER) )
      {
        throw new Exception("Cannot execute handle_request() unless PHP is " .
            "running as a module to the HTTP daemon.");
      }

      $method = $_SERVER['REQUEST_METHOD'];
      $uri = $_SERVER['REQUEST_URI'];
      $parameters = array();
      $requests = array();

      if($method == "GET")
      {
        $parameters = $_GET;
        $requests = $this->restApi->gets;
      }
      else if($method == "POST")
      {
        $parameters = $_POST;
        $requests = $this->restApi->posts;
      }
      else
      {
        $this->not_implemented($uri);
        return;
      }

      $request = $this->select_request($requests, $uri);
      $this->perform_request($request, $uri, $parameters);
    }
  }
?>
