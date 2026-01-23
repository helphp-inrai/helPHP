<?php

namespace RestService;

/**
 * This client handles API responses for a given endpoint.
 *
 * It can format the response as JSON, XML, plain text, of a custom format.
 */
class Client {
    /**
     * Current output format.
     *
     * @var string
     */
    private $outputFormat = 'json';

    /**
     * Custom formatting function
     * Arguments: (message)
     *
     * @var callable
     */
    protected $customFormat;

    /**
     * List of possible output formats.
     *
     * @var array
     */
    private $outputFormats = array(
        'json' => 'asJSON',
        'xml' => 'asXML',
        'text' => 'asText',
        'custom' => 'customFormat'
    );

    /**
     * List of possible methods.
     * @var array
     */
    public $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

    /**
     * Current URL.
     *
     * @var string
     */
    private $url;

    /**
     * @var Server
     *
     */
    private $controller;

    /**
     * Custom set http method.
     *
     * @var string
     */
    private $method;


    private static $statusCodes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',  // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );

    /**
     * Create a new client.
     * 
     * @param Server $pServerController The server controller.
     * @return void
     */
    public function __construct($pServerController)
    {
        $this->controller = $pServerController;
        if (isset($_SERVER['PATH_INFO']))
            $this->setURL($_SERVER['PATH_INFO']);

        $this->setupFormats();
    }

    /**
     * Attach client to different controller.
     * 
     * @param Server $pServerController Server controller.
     * @return void
     */
    public function setController($pServerController)
    {
        $this->controller = $pServerController;
    }

    /**
     * Return the currently attached controller.
     * 
     * @return Server $pServerController Server controller.
     */
    public function getController()
    {
        return $this->controller;
    }


    /**
     * Set the custom formatting function/method.
     * Called with arguments: (message)
     *
     * @param  callable $pFn The custom formatting function/method.
     * @return Client   $this The client instance.
     */
    public function setCustomFormat($pFn) {
        $this->customFormat = $pFn;

        return $this;
    }

    /**
     * Returns the current formatting function/method.
     * @return callable $customFormat The check access function/method.
     */
    public function getCustomFormat() {
        return $this->customFormat;
    }

    /**
     * Sends the actual response.
     *
     * @param string $pHttpCode The HTTP code to return.
     * @param $pMessage The data to return.
     * @return void
     */
    public function sendResponse($pMessage, $pHttpCode = '200', $unescape = 0)
    {
        $suppressStatusCode = isset($_GET['_suppress_status_code']) ? $_GET['_suppress_status_code'] : false;
        if ($this->controller->getHttpStatusCodes() &&
            !$suppressStatusCode &&
            php_sapi_name() !== 'cli') {

            $status = self::$statusCodes[intval($pHttpCode)];
            header('HTTP/1.0 ' . ($status ? $pHttpCode . ' ' . $status : $pHttpCode), true, $pHttpCode);
        } elseif (php_sapi_name() !== 'cli') {
            header('HTTP/1.0 200 OK');
        }

        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = intval($pHttpCode);
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatMethod($this->getOutputFormat());
        if ($method == 'customFormat' && $this->customFormat == null) {
            echo $this->asJSON($pMessage, $unescape);
        } 
        else if ($method == 'customFormat' && $this->customFormat != null) {
            $args = array($pMessage);
            $result = call_user_func_array($this->getCustomFormat(), $args);
            $this->setContentLength($result);

            echo $result;
        }
        else {
            if ($method == "asJSON" || $method == "json") {
                echo $this->asJSON($pMessage, $unescape);
            }
            else {
                echo $this->$method($pMessage);
            }
        }
        exit;
    }

    /**
     * Returns the current output format method
     * 
     * @param  string $pFormat The output format. 'json', 'xml', 'text', or 'custom'.
     * @return string 'asJSON', 'asXML', 'asText', or 'customFormat'.
     */
    public function getOutputFormatMethod($pFormat)
    {
        return $this->outputFormats[$pFormat];
    }

    /**
     * Returns the current output format
     * 
     * @return string 'json', 'xml', 'text', or 'custom'
     */
    public function getOutputFormat()
    {
        return $this->outputFormat;
    }

    /**
     * Detect the method.
     *
     * @return string 'get', 'post', 'put', 'delete', 'head', 'options', or 'patch'
     */
    public function getMethod() {
        if ($this->method) {
            return $this->method;
        }

        $method = @$_SERVER['REQUEST_METHOD'];
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']))
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];

        if (isset($_GET['_method']))
            $method = $_GET['_method'];
        else if (isset($_POST['_method']))
            $method = $_POST['_method'];

        $method = strtolower($method);

        if (!in_array($method, $this->methods))
            $method = 'get';

        return $method;

    }

    /**
     * Sets a custom http method.
     *
     * @param  string $pMethod 'get', 'post', 'put', 'delete', 'head', 'options', or 'patch'
     * @return Client $this Client instance.
     */
    public function setMethod($pMethod) {
        $this->method = $pMethod;

        return $this;
    }

    /**
     * Set header "Content-Length" from data.
     *
     * @param mixed $pMessage The data to set the header from.
     * @return void
     */
    public function setContentLength($pMessage) {
        if (php_sapi_name() !== 'cli' )
            header('Content-Length: '.strlen($pMessage));
    }

    /**
     * Converts data to pretty JSON.
     *
     * @param mixed $pMessage The data to convert.
     * @return string JSON version of the original data.
     */
    public function asJSON($pMessage, $unescape = 0) {
        if (php_sapi_name() !== 'cli' )
            header('Content-Type: application/json; charset=utf-8');
        

        if ($unescape == 1) {
            if (!is_string($pMessage)) $json = json_encode($pMessage, JSON_UNESCAPED_SLASHES);
            else $json = $pMessage;
        }
        else {
            if (!is_string($pMessage)) $json = json_encode($pMessage);
            else $json = $pMessage;
        }

        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '    ';
        $newLine     = "\n";
        $inEscapeMode = false; //if the last char is a valid \ char.
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && !$inEscapeMode) {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } elseif (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            } elseif ($char == ':' && $outOfQuotes) {
                $char .= ' ';
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            if ($char == '\\' && !$inEscapeMode)
                $inEscapeMode = true;
            else
                $inEscapeMode = false;
        }

        $this->setContentLength($result);

        return $result;
    }

    /**
     * Converts data to XML.
     *
     * @param mixed $pMessage The data to convert.
     * @param string $pParentTagName The name of the parent tag. Default is ''.
     * @param int $pDepth The depth of the current tag. Default is 1.
     * @param bool $pHeader Whether to wrap the xml in a header. Default is true.
     * @return string XML version of the original data.
     */
    public function asXML($pMessage, $pParentTagName = '', $pDepth = 1, $pHeader = true) {
        if (is_array($pMessage)) {
            $content = '';

            foreach ($pMessage as $key => $data) {
                $key = is_numeric($key) ? $pParentTagName.'-item' : $key;
                $content .= str_repeat('  ', $pDepth)
                    .'<'.htmlspecialchars($key).'>'.
                    $this->asXml($data, $key, $pDepth+1, false)
                    .'</'.htmlspecialchars($key).">\n";
            }

            $xml = $content;
        } else {
            $xml = htmlspecialchars($pMessage);
        }

        if ($pHeader) {
            $xml = "<?xml version=\"1.0\"?>\n<response>\n$xml</response>\n";
            $this->setContentLength($xml);
        }

        return $xml;
    }

    /**
     * Converts data to text.
     *
     * @param mixed $pMessage The data to convert.
     * @return string JSON version of the original data.
     */
    public function asText($pMessage) {
        if (php_sapi_name() !== 'cli' )
            header('Content-Type: text/plain; charset=utf-8');

        $text = '';
        foreach ($pMessage as $key => $data) {
            $key = is_numeric($key) ? '' : $key.': ';
            $text .= $key.$data."\n";
        }
        $this->setContentLength($text);

        return $text;
    }

    /**
     * Set the current output format.
     *
     * @param  string $pFormat Name of the format.
     * @return Client $this Client instance.
     */
    public function setFormat($pFormat) {
        $this->outputFormat = $pFormat;

        return $this;
    }

    /**
     * Returns the current endpoint URL.
     *
     * @return string The current endpoint URL.
     */
    public function getURL() {
        return $this->url;
    }

    /**
     * Set the current endpoint URL.
     *
     * @param  string $pUrl The new endpoint URL.
     * @return Client $this Client instance.
     */
    public function setURL($pUrl) {
        $this->url = $pUrl;

        return $this;
    }

    /**
     * Setup formats.
     *
     * @return Client $this Client instance.
     */
    public function setupFormats() {
        //through HTTP_ACCEPT
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], '*/*') === false) {
            foreach ($this->outputFormats as $formatCode => $formatMethod) {
                if (strpos($_SERVER['HTTP_ACCEPT'], $formatCode) !== false) {
                    $this->outputFormat = $formatCode;
                    break;
                }
            }
        }

        // If URL is null, set to ''
        $urlFormat = $this->getURL();
        if ($urlFormat == null) {
            $urlFormat = '';
        }

        //through uri suffix
        if (preg_match('/\.(\w+)$/i', $urlFormat, $matches)) {
            if (isset($this->outputFormats[$matches[1]])) {
                $this->outputFormat = $matches[1];
                $url = $this->getURL();
                $this->setURL(substr($url, 0, (strlen($this->outputFormat)*-1)-1));
            }
        }

        return $this;
    }

}

/**
 * This client does not send any HTTP data, instead it just returns the value.
 *
 * Good for testing purposes.
 */
class InternalClient extends Client {
    /**
     * Sends the actual response.
     *
     * @param mixed $pMessage The data to process.
     * @param string $pHttpCode The HTTP code to process.
     * @return string HTTP method of current request.
     */
    public function sendResponse($pMessage, $pHttpCode = '200', $unescape = 0) {
        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = $pHttpCode+0;
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatMethod($this->getOutputFormat());

        return $this->$method($pMessage);
    }

}

/**
 * A REST server class for RESTful APIs.
 */
class Server
{
    /**
     * Current routes.
     *
     * structure:
     *  array(
     *    '<uri>' => <callable>
     *  )
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Blacklisted http get arguments.
     *
     * @var array
     */
    protected $blacklistedGetParameters = array('_method', '_suppress_status_code');

    /**
     * Current URL that triggers the controller.
     *
     * @var string
     */
    protected $triggerUrl = '';

    /**
     * Contains the controller object.
     *
     * @var string|object
     */
    protected $controller = '';

    /**
     * List of sub controllers.
     *
     * @var array
     */
    protected $controllers = array();

    /**
     * Parent controller.
     *
     * @var Server
     */
    protected $parentController;

    /**
     * The client
     *
     * @var Client
     */
    protected $client;

    /**
     * OpenAPI Specification.
     *
     * @var array
     */
    protected $apiSpec;

    /**
     * List of excluded methods.
     *
     * @var array|string array('methodOne', 'methodTwo') or * for all methods
     */
    protected $collectRoutesExclude = array('__construct');

    /**
     * List of possible methods.
     * @var array
     */
    public $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

    /**
     * Check access function/method. Will be fired after the route has been found.
     * Arguments: (url, route, method, arguments)
     *
     * @var callable
     */
    protected $checkAccessFn;

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     *
     * @var callable
     */
    protected $sendExceptionFn;

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     *
     * @var boolean
     */
    protected $debugMode = false;

    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     *
     * @var boolean
     */
    protected $describeRoutes = true;

    /**
     * If this controller can not find a route,
     * we fire this method and send the result.
     *
     * @var string
     */
    protected $fallbackMethod = '';

    /**
     * If the lib should send HTTP status codes.
     * Some Client libs does not support this, you can deactivate it via
     * ->setHttpStatusCodes(false);
     *
     * @var boolean
     */
    protected $withStatusCode = true;

    /**
     * @var callable
     */
    protected $controllerFactory;

    /**
     * @var callable
     */
    private function declaresArray(\ReflectionParameter $reflectionParameter): bool {
        $reflectionType = $reflectionParameter->getType();
    
        if (!$reflectionType) return false;
    
        $types = $reflectionType instanceof \ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];
    
        return in_array('array', array_map(fn(\ReflectionNamedType $t) => $t->getName(), $types));
    }


    /**
     * Converts PHP types to OpenAPI types.
     * 
     * @param string $input The input to parse.
     * @return string $type The converted type
     *
     */
    private function convertType($input) {
        switch ($input) {
            case "int":
                $type = array("type" =>  "integer");
                break;
            case "integer":
                $type = array("type" =>  "integer");
                break;
            case "bool":
                $type = array("type" =>  "boolean");
                break;
            case "boolean":
                $type = array("type" =>  "boolean");
                break;
            case "string":
                $type = array("type" =>  "string");
                break;
            case "number":
                $type = array("type" =>  "number");
                break;
            case "array":
                $type = array("type" =>  "array", "items" => (object) null);
                break;
            case "object":
                $type = array("type" =>  "object");
                break;
            default:
                $type = array("\$ref" => "#/components/schemas/AnyValue");
        }
        return $type;
    }

    /**
     * Create a new server.
     *
     * @param string              $pTriggerUrl The URL that triggers the controller.
     * @param string|object       $pControllerClass The default endpoint function class.
     * @param Server $pParentController The parent controller.
     * @return void
     */
    public function __construct($pTriggerUrl, $pControllerClass = null, $pParentController = null) {
        $this->normalizeUrl($pTriggerUrl);

        if ($pParentController) {
            $this->parentController = $pParentController;
            $this->setClient($pParentController->getClient());

            if ($pParentController->getCheckAccess())
                $this->setCheckAccess($pParentController->getCheckAccess());

            if (isset($pParentController->getApiSpec()[0]))
                $this->setApiSpec($pParentController->getApiSpec()[0], $pParentController->getApiSpec()[1]);

            if ($pParentController->getExceptionHandler())
                $this->setExceptionHandler($pParentController->getExceptionHandler());

            if ($pParentController->getDebugMode())
                $this->setDebugMode($pParentController->getDebugMode());

            if ($pParentController->getDescribeRoutes())
                $this->setDescribeRoutes($pParentController->getDescribeRoutes());

            if ($pParentController->getControllerFactory())
                $this->setControllerFactory($pParentController->getControllerFactory());

            $this->setHttpStatusCodes($pParentController->getHttpStatusCodes());

        } else {
            $this->setClient(new Client($this));
        }

        $this->setClass($pControllerClass);
        $this->setTriggerUrl($pTriggerUrl);
    }

    /**
     * Creates controller factory. User for internal testing.
     *
     * @param string $pTriggerUrl The URL that triggers the controller.
     * @param string $pControllerClass The default endpoint function class.
     *
     * @return Server $this The server instance.
     */
    public static function create($pTriggerUrl, $pControllerClass = '') {
        $clazz = get_called_class();

        return new $clazz($pTriggerUrl, $pControllerClass);
    }

    /**
     * Change the current controller factory.
     * 
     * @param callable $controllerFactory
     *
     * @return Server $this The server instance.
     */
    public function setControllerFactory(callable $controllerFactory) {
        $this->controllerFactory = $controllerFactory;

        return $this;
    }

    /**
     * Return the current controller factory.
     * 
     * @return callable $controllerFactory The controller factory.
     */
    public function getControllerFactory() {
        return $this->controllerFactory;
    }

    /**
     * Enable and set parameters for OpenAPI specification generateion.
     * 
     * @param string $title The name of the controller.
     * @param string $version The version of the controller.
     * @param string $desciption The description of the server. Default is null.
     * @param string $server The address of the server. Default is null.
     * @param bool $recurse Whether or not to recurse into the child controllers. Default is true.
     * @return Server $this The server instance.
     */
    public function setApiSpec($title, $version, $description = null, $server = null, $recurse = true) {
        $this->apiSpec = [
            'title' => $title,
            'version' => $version,
            'description' => $description,
            'server' => $server, 
            'recurse' => $recurse
        ];

        return $this;
    }

    /**
     * Return the current controller factory.
     * 
     * @return array $apiSpec The API specification.
     */
    public function getApiSpec() {
        return $this->apiSpec;
    }

    /**
     * Enable / Disable sending of HTTP status codes.
     *
     * @param  boolean $pWithStatusCode If true, send HTTP status codes.
     * @return Server  $this The server instance.
     */
    public function setHttpStatusCodes($pWithStatusCode) {
        $this->withStatusCode = $pWithStatusCode;

        return $this;
    }

    /**
     * Return if HTTP status codes are sent.
     * 
     * @return boolean $withStatusCode If true, send HTTP status codes.
     */
    public function getHttpStatusCodes() {
        return $this->withStatusCode;
    }

    /**
     * Set the check access function/method.
     * Called with arguments: (url, route, method, arguments)
     *
     * @param  callable $pFn The check access function/method.
     * @return Server   $this The server instance.
     */
    public function setCheckAccess($pFn) {
        $this->checkAccessFn = $pFn;

        return $this;
    }

    /**
     * Returns the current check access function/method.
     * @return callable $checkAccessFn The check access function/method.
     */
    public function getCheckAccess() {
        return $this->checkAccessFn;
    }

    /**
     * Set fallback method if no route is found.
     *
     * @param  string $pFn The fallback method.
     * @return Server $this The server instance.
     */
    public function setFallbackMethod($pFn) {
        $this->fallbackMethod = $pFn;

        return $this;
    }

    /**
     * Returns the fallback method.
     * 
     * @return string $fallbackMethod The fallback method.
     */
    public function getFallbackMethod() {
        return $this->fallbackMethod;
    }

    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     * 
     * @param  boolean $pDescribeRoutes If true, serve route descriptions.
     * @return Server  $this The server instance.
     */
    public function setDescribeRoutes($pDescribeRoutes) {
        $this->describeRoutes = $pDescribeRoutes;

        return $this;
    }

    /**
     * Returns whether the service should serve route descriptions
     *
     * @return boolean $describeRoutes If true, serve route descriptions.
     */
    public function getDescribeRoutes() {
        return $this->describeRoutes;
    }

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     *
     * @param  callable $pFn The exception function/method.
     * @return Server   $this The server instance.
     */
    public function setExceptionHandler($pFn) {
        $this->sendExceptionFn = $pFn;

        return $this;
    }

    /**
     * Returns the current exception handler function/method.
     * 
     * @return callable $sendExceptionFn The exception handler function/method.
     */
    public function getExceptionHandler() {
        return $this->sendExceptionFn;
    }

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     *
     * @param  boolean $pDebugMode If true, send debug info.
     * @return Server  $this The server instance.
     */
    public function setDebugMode($pDebugMode) {
        $this->debugMode = $pDebugMode;

        return $this;
    }

    /**
     * Returns if debug mode is enabled.
     * 
     * @return boolean $debugMode If true, send debug info.
     */
    public function getDebugMode() {
        return $this->debugMode;
    }

    /**
     * Alias for getParentController()
     *
     * @return Server $this The server instance.
     */
    public function done() {
        return $this->getParentController();
    }

    /**
     * Returns the parent controller
     *
     * @return Server $this The server instance.
     */
    public function getParentController() {
        return $this->parentController;
    }

    /**
     * Set the URL that triggers the controller.
     *
     * @param $pTriggerUrl The URL that triggers the controller.
     * @return Server $this The server instance.
     */
    public function setTriggerUrl($pTriggerUrl) {
        $this->triggerUrl = $pTriggerUrl;

        return $this;
    }

    /**
     * Gets the current trigger url.
     *
     * @return string $triggerUrl The trigger url.
     */
    public function getTriggerUrl() {
        return $this->triggerUrl;
    }

    /**
     * Sets the client.
     *
     * @param  Client|string $pClient The endpoint client.
     * @return Server        $this The server instance.
     */
    public function setClient($pClient) {
        if (is_string($pClient)) {
            $pClient = new $pClient($this);
        }

        $this->client = $pClient;
        $this->client->setupFormats();

        return $this;
    }

    /**
     * Get the current client.
     *
     * @return Client $client The client.
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Sends a 'Bad Request' response to the client.
     *
     * @param $pCode The HTTP status code.
     * @param $pMessage The message to send.
     * @throws \Exception If the client is not set.
     * @return string The response.
     */
    public function sendBadRequest($pCode, $pMessage) {
        if (is_object($pMessage) && $pMessage->xdebug_message) $pMessage = $pMessage->xdebug_message;
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->getClient()) throw new \Exception('client_not_found_in_ServerController');
        return $this->getClient()->sendResponse($msg, '400');
    }

    /**
     * Sends a 'Internal Server Error' response to the client.
     * 
     * @param $pCode The HTTP status code.
     * @param $pMessage The message to send.
     * @throws \Exception If the client is not set.
     * @return string The response.
     */
    public function sendError($pCode, $pMessage) {
        if (is_object($pMessage) && $pMessage->xdebug_message) $pMessage = $pMessage->xdebug_message;
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->getClient()) throw new \Exception('client_not_found_in_ServerController');
        return $this->getClient()->sendResponse($msg, '500');
    }

    /**
     * Sends a exception response to the client.
     * 
     * @param $pException The exception to send.
     * @throws \Exception If the client is not set.
     * @return void
     */
    public function sendException($pException) {
        if ($this->sendExceptionFn) {
            call_user_func_array($this->sendExceptionFn, array($pException));
        }

        $message = $pException->getMessage();
        if (is_object($message) && $message->xdebug_message) $message = $message->xdebug_message;

        $msg = array('error' => get_class($pException), 'message' => $message);

        $code = '500';
        if ($pException->getCode() != 0) $code = $pException->getCode();

        if ($this->debugMode) {
            $msg['file'] = $pException->getFile();
            $msg['line'] = $pException->getLine();
            $msg['trace'] = $pException->getTraceAsString();
        }

        if (!$this->getClient()) throw new \Exception('Client not found in ServerController');
        return $this->getClient()->sendResponse($msg, $code);
    }

    /**
     * Adds a new route for all http methods (get, post, put, delete, options, head, patch).
     *
     * @param  string          $pUri        The uri to match.
     * @param  callable|string $pCb         The method name of the passed controller or a php callable.
     * @param  string          $pHttpMethod If you want to limit to a HTTP method.
     * @return Server          $this        The server instance.
     */
    public function addRoute($pUri, $pCb, $pHttpMethod = '_all_') {
        $this->routes[$pUri][ $pHttpMethod ] = $pCb;

        return $this;
    }

    /**
     * Same as addRoute, but limits to GET.
     *
     * @param  string          $pUri        The uri to match.
     * @param  callable|string $pCb         The method name of the passed controller or a php callable.
     * @return Server          $this        The server instance.
     */
    public function addGetRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'get');

        return $this;
    }

    /**
     * Same as addRoute, but limits to POST.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addPostRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'post');

        return $this;
    }

    /**
     * Same as addRoute, but limits to PUT.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addPutRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'put');

        return $this;
    }

    /**
     * Same as addRoute, but limits to PATCH.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addPatchRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'patch');

        return $this;
    }

    /**
     * Same as addRoute, but limits to HEAD.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addHeadRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'head');

        return $this;
    }

    /**
     * Same as addRoute, but limits to OPTIONS.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addOptionsRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'options');

        return $this;
    }

    /**
     * Same as addRoute, but limits to DELETE.
     *
     * @param  string          $pUri       The uri to match.
     * @param  callable|string $pCb        The method name of the passed controller or a php callable.
     * @return Server          $this       The server instance.
     */
    public function addDeleteRoute($pUri, $pCb) {
        $this->addRoute($pUri, $pCb, 'delete');

        return $this;
    }

    /**
     * Removes a route.
     *
     * @param  string           $pUri       The uri to match.
     * @return Server           $this       The server instance.
     */
    public function removeRoute($pUri) {
        unset($this->routes[$pUri]);

        return $this;
    }

    /**
     * Sets the controller class to use for function endpoints.
     *
     * @param string|object     $pClass     The class name or object.
     * @return void
     */
    public function setClass($pClass) {
        if (is_string($pClass)) {
            $this->createControllerClass($pClass);
        } elseif (is_object($pClass)) {
            $this->controller = $pClass;
        } else {
            $this->controller = $this;
        }
    }

    /**
     * Setup the controller class.
     *
     * @param  string           $pClassName
     * @throws \Exception                       If the class does not exist.
     */
    protected function createControllerClass($pClassName) {
        if ($pClassName != '') {
            try {
                if ($this->controllerFactory) {
                    $this->controller = call_user_func_array($this->controllerFactory, array(
                        $pClassName,
                        $this
                    ));
                } else {
                    $this->controller = new $pClassName($this);
                }
                if (get_parent_class($this->controller) == '\RestService\Server') {
                    $this->controller->setClient($this->getClient());
                }
            } catch (\Exception $e) {
                throw new \Exception('Error during initialisation of '.$pClassName.': '.$e, 0, $e);
            }
        } else {
            $this->controller = $this;
        }
    }

    /**
     * Attach a sub controller to this controller.
     *
     * @param string    $pTriggerUrl        The url to trigger the sub controller.
     * @param mixed     $pControllerClass   A class name (autoloader required) or a instance of a class.
     *
     * @return Server   $controller         new created Server. Use done() to switch the context back to the parent.
     */
    public function addSubController($pTriggerUrl, $pControllerClass = '') {
        $this->normalizeUrl($pTriggerUrl);

        $base = $this->triggerUrl;
        if ($base == '/') $base = '';

        $controller = new Server($base . $pTriggerUrl, $pControllerClass, $this);

        $this->controllers[] = $controller;

        return $controller;
    }

    /**
     * Normalize $pUrl. Cuts of the trailing slash.
     *
     * @param string $pUrl The url to normalize.
     * @return void
     */
    public function normalizeUrl(&$pUrl) {
        if ('/' === $pUrl) return;
        if ($pUrl != null) {
            if (substr($pUrl, -1) == '/') $pUrl = substr($pUrl, 0, -1);
            if (substr($pUrl, 0, 1) != '/') $pUrl = '/' . $pUrl;
        }
    }

    /**
     * Sends data to the client with 200 http code.
     *
     * @param $pData The data to send.
     * @return void
     */
    public function send($pData, $unescape = 0) {
        return $this->getClient()->sendResponse(array('data' => $pData), 200, $unescape);
    }

    /**
     * Convert string from camel case to dashes.
     * 
     * @param  string $pValue The string to convert.
     * @return string $pValue The converted string.
     */
    public function camelCase2Dashes($pValue) {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $pValue));
    }

    /**
     * Automatically collect routes from class.
     *
     * @return Server $this The server instance.
     */
    public function collectRoutes() {
        if ($this->collectRoutesExclude == '*') return $this;

        $methods = get_class_methods($this->controller);
        foreach ($methods as $method) {
            if (in_array($method, $this->collectRoutesExclude)) continue;

            $info = explode('/', preg_replace('/([a-z]*)(([A-Z]+)([a-zA-Z0-9_]*))/', '$1/$2', $method));
            $uri  = $this->camelCase2Dashes((empty($info[1]) ? '' : $info[1]));

            $httpMethod  = $info[0];
            if ($httpMethod == 'all') {
                $httpMethod = '_all_';
            }
            $reflectionMethod = new \ReflectionMethod($this->controller, $method);
            if ($reflectionMethod->isPrivate()) continue;

            $phpDocs = $this->getMethodMetaData($reflectionMethod);
            if (isset($phpDocs['url'])) {
                if (isset($phpDocs['url']['url'])) {
                    //only one route
                    $this->routes[$phpDocs['url']['url']][$httpMethod] = $method;
                } else {
                    foreach($phpDocs['url'] as $urlAnnotation) {
                        $this->routes[$urlAnnotation['url']][$httpMethod] = $method;
                    }
                }
            } else {
                $this->routes[$uri][$httpMethod] = $method;
            }

            if (isset($phpDocs['unescape'])) {
                $this->unescape = $phpDocs['unescape'];
            }
        }

        return $this;
    }

    /**
     * Simulates a HTTP Call.
     *
     * @param  string $pUri     The uri to call.
     * @param  string $pMethod  The HTTP Method
     * @return string           The response.
     */
    public function simulateCall($pUri, $pMethod = 'get') {
        if (($idx = strpos($pUri, '?')) !== false) {
            parse_str(substr($pUri, $idx+1), $_GET);
            $pUri = substr($pUri, 0, $idx);
        }
        $this->getClient()->setURL($pUri);
        $this->getClient()->setMethod($pMethod);

        return $this->run();
    }


    /**
     * Generate route array for OpenAPI specification from current controller and URL.
     * 
     * @param  string $url  The trigger URL for the current contorller.
     * @return array        The generated route array.
     */
    public function generateOpenApiRoutes($url) {
        $pUri = null;
        $format = $this->getClient()->getOutputFormat();
        $routes = array();

        // Loop through routes and add to specification
        foreach ($this->routes as $routeUri => $routeMethods) {

            $matches = array();

            if (!$pUri || ($pUri && preg_match('|^'.$routeUri.'$|', $pUri, $matches))) {                 

                if ($matches) {
                    array_shift($matches);
                }
                $path = $url . '/'.$routeUri;
                $originalPath = $path;
                $overridePath = null;
                $def = array();

                foreach ($routeMethods as $method => $phpMethod) {
                    if (is_string($phpMethod)) {
                        $ref = new \ReflectionClass($this->controller);
                        $refMethod = $ref->getMethod($phpMethod);
                    } else {
                        $refMethod = new \ReflectionFunction($phpMethod);
                    }
                    $metadata = $this->getMethodMetaData($refMethod);

                    if (isset($metadata['openapiurl']) && $overridePath == null) {
                        $finalPath = $metadata['openapiurl'];
                    }
                    else {
                        preg_match_all('~ \( (?: [^()]+ | (?R) )*+ \) ~x', $originalPath, $paramMatches);
                        $place = 0;

                        if (isset($paramMatches[0]) && count($paramMatches[0]) > 0) {
                            foreach ($paramMatches[0] as $match) {
                                $param = array_keys($metadata['parameters'])[$place];
                                $replace = "{".$param."}";
                                if (($pos = strpos($originalPath, $match)) !== false) {
                                    if(substr($match, 0, 2) !== "(!" && substr($match, 0, 3) !== "(?!"  && substr($match, 0, 3) !== "(?:") {
                                        $path = substr_replace($originalPath, $replace, $pos, strlen($match));
                                        $metadata['parameters'][$param] = array_merge($metadata['parameters'][$param], array('in' => 'path'));
                                        $place++;
                                    }
                                }
                            }
                        }

                        $finalPath = $path;

                        preg_match_all('~ \( (?: [^()]+ | (?R) )*+ \) ~x', $finalPath, $paramMatches);
                        $place = 0;

                        if (isset($paramMatches[0]) && count($paramMatches[0]) > 0) {
                            foreach ($paramMatches[0] as $match) {
                                $param = array_keys($metadata['parameters'])[$place];
                                if (($pos = strpos($finalPath, $match)) !== false) {
                                    if(substr($match, 0, 2) === "(!" || substr($match, 0, 3) === "(?!" || substr($match, 0, 3) === "(?:") {
                                        $finalPath = substr_replace($finalPath, '', $pos, strlen($match));
                                    }
                                }
                            }
                        }

                    }

                    $type = $this->convertType($metadata['return']['type']);
                    $parameters = array();
                    $body = array();
                    foreach ($metadata['parameters'] as $name => $parameter) {
                        $paramType = $this->convertType($parameter['type']);
                        
                        if (isset($parameter['in']) && $parameter['in'] == 'path') {
                            $parameters[] = array(
                                "in" => "path",
                                "name" => $name, 
                                "required" => true,
                                "schema" => $paramType
                            );
                            if (isset($parameter['description']) && $parameter['description'] != null) {
                                $parameters['description'] = $parameter['description'];
                            }
                        } else if ($method == 'get' || $method == 'delete') {
                            $parameters[] = array(
                                "in" => "query",
                                "name" => $name, 
                                "required" => (isset($parameter['required'])) ? $parameter['required'] : false,
                                "schema" => $paramType
                            );
                            if (isset($parameter['description']) && $parameter['description'] != null) {
                                $parameters['description'] = $parameter['description'];
                            }
                        } else {
                            $body[] = array(
                                "name" => $name,
                                "in" => "body",
                                "required" => (isset($parameter['required'])) ? $parameter['required'] : false,
                                "schema" => $paramType
                            );
                            if (isset($parameter['description']) && $parameter['description'] != null) {
                                $body['description'] = $parameter['description'];
                            }
                        }
                    }
                    switch ($format) {
                        case "json":
                            $outputFormat = "application/json";
                            break;
                        case "xml":
                            $outputFormat = "application/xml";
                            break;
                        case "text":
                            $outputFormat = "text/plain";
                            break;
                        default:
                            $outputFormat = "application/json";
                    }
                    $def[$method] = array(
                        "parameters" => $parameters,
                        "responses" => array(
                            "200" => array(
                                "description" => "Successful operation",
                                "content" => array(
                                    $outputFormat => array(
                                        "schema" => array(
                                            "type" => "object",
                                            "properties" => array(
                                                "status" => array(
                                                    "type" => "integer",
                                                ),
                                                "data" => $type,
                                            )
                                        )
                                        )
                                    )
                                ),
                            "500" => array(
                                "description" => "Internal Server Error",
                                "content" => array(
                                    $outputFormat => array(
                                        "schema" => array(
                                            "\$ref" => "#/components/schemas/500"
                                        )
                                    )
                                )
                            )
                        )
                    );
                    if ($method != 'get' && $method != 'delete') {
                        $requiredParams = array();
                        $properties = array();
                        $bodyRequired = false;
                        foreach ($body as $b) {
                            if ($b['required']) {
                                $bodyRequired = true;
                                $requiredParams[] = $b['name'];
                            }
                            $properties[$b['name']] =  $b['schema'];
                        }
                        if ($bodyRequired) {
                            $def[$method]['requestBody']['required'] = true;
                        }
                        $def[$method]['requestBody']['content'] = array(
                            "application/json" => array("schema" => array("type" => "object", "properties" => $properties)),
                            "application/x-www-form-urlencoded" => array("schema" => array("type" => "object", "properties" => $properties))
                        );
                    }
                }

                // If URL provided in @openapiurl comment, use it instead of the one generated above
                if ($overridePath != null)
                    $finalPath = $overridePath;
                    
                // Add route to spec
                $routes[$finalPath] = $def;
            }
        }
        return $routes;
    }

    /**
     * Fire the magic!
     *
     * Searches the method and sends the data to the client.
     *
     * @return mixed The response.
     */
    public function run() {
        //check sub controller
        foreach ($this->controllers as $controller) {
            if ($result = $controller->run()) {
                return $result;
            }
        }

        $requestedUrl = $this->getClient()->getURL();
        $this->normalizeUrl($requestedUrl);
        //check if its in our area

        if ($requestedUrl != null) {
            if (strpos($requestedUrl, $this->triggerUrl) !== 0) return;
        }

        $endPos = $this->triggerUrl === '/' ? 1 : strlen($this->triggerUrl) + 1;

        if ($requestedUrl != null) {
            $uri = substr($requestedUrl, $endPos);
        }
        else {
            $uri = '';
        }

        if (!$uri) $uri = '';

        $arguments = array();
        $requiredMethod = $this->getClient()->getMethod();

        //does the requested uri exist?
        list($callableMethod, $regexArguments, $method, $routeUri) = $this->findRoute($uri, $requiredMethod);

        // If request for options, send route description
        if ((!$callableMethod || $method != 'options') && $requiredMethod == 'options') {
            $description = $this->describe($uri);
            $this->send($description);
        }

        // If request for specification, generate OpenAPI specification
        if (!$callableMethod && $uri == 'spec' && $requiredMethod == 'get' && $this->getApiSpec() != null) {
            // Set JSON headers
            header('HTTP/1.0 200 OK');
            header('Content-Type: application/json; charset=utf-8');

            // Define server details
            $spec['openapi'] = '3.0.0';
            $spec['info'] = array(
                'title' => $this->getApiSpec()['title'],
                'version' => $this->getApiSpec()['version'],
            );
            if (isset($this->getApiSpec()['description']) && $this->getApiSpec()['description'] != null) {
                $spec['info']['description'] = $this->getApiSpec()['description'];
            }
            if (isset($this->getApiSpec()['server']) && $this->getApiSpec()['server'] != null) {
                $spec['servers'] = array(array("url" => $this->getApiSpec()['server']));
            }
            $routes = $this->generateOpenApiRoutes($this->getTriggerUrl());
    
            // Add the spec endpoint to the spec
            $specUrl = $this->getTriggerUrl() . '/spec';
            $routes[$specUrl] = array("get" => array("summary" => "Get the API Specification", "responses" => array("200" => array("description" => "Successful operation"))));
            
            if ($this->getApiSpec()['recurse']) {
            
                foreach ($this->controllers as $controller) {
                    $routes = array_merge($routes, $controller->generateOpenApiRoutes($controller->getTriggerUrl()));
                    if ($controller->getApiSpec() != null) {
                        $specUrl = $controller->getTriggerUrl() . '/spec';
                        $routes[$specUrl] = array("get" => array("summary" => "Get the API Specification", "responses" => array("200" => array("description" => "Successful operation"))));
                    }
                }

            }

            // Add the routes to the spec
            $spec['paths'] = $routes;

            // Add components section
            $spec['components'] = array("schemas" => array(
                "500" => array("type" => "object", "properties" => array("status" => array("type" => "integer"), "error" => array("type" => "string"), "message" => array("type" => "object"))),
                "AnyValue" => (object) null
            ));

            // Return spec
            echo json_encode($spec);
            exit;
        }

        // If route is not found, return fallback method or error
        if (!$callableMethod) {
            if (!$this->getParentController()) {
                if ($this->fallbackMethod) {
                    $m = $this->fallbackMethod;
                    $this->send($this->controller->$m());
                } else {
                    return $this->sendBadRequest('RouteNotFoundException', "There is no route for '$uri'.");
                }
            } else {
                return false;
            }
        }

        if ($method == '_all_')
            $arguments[] = $method;

        if (is_array($regexArguments)) {
            $arguments = array_merge($arguments, $regexArguments);
        }

        //open class and scan method
        if ($this->controller && is_string($callableMethod)) {
            $ref = new \ReflectionClass($this->controller);

            if (!method_exists($this->controller, $callableMethod)) {
                $this->sendBadRequest('MethodNotFoundException', "There is no method '$callableMethod' in ".
                    get_class($this->controller).".");
            }

            $reflectionMethod = $ref->getMethod($callableMethod);
        } else if (is_callable($callableMethod)) {
            $reflectionMethod = new \ReflectionFunction($callableMethod);
        }

        $params = $reflectionMethod->getParameters();

        if ($method == '_all_') {
            //first parameter is $pMethod
            array_shift($params);
        }

        //remove regex arguments
        for ($i=0; $i<count($regexArguments); $i++) {
            array_shift($params);
        }

        //collect arguments
        foreach ($params as $param) {
            $name = $this->argumentName($param->getName());

            // If argument is _ (underscore), pass all arguments
            if ($name == '_') {
                $thisArgs = array();
                foreach ($_GET as $k => $v) {
                    if (substr($k, 0, 1) == '_' && $k != '_suppress_status_code')
                        $thisArgs[$k] = $v;
                }
                $arguments[] = $thisArgs;
            } 
            // Else, pass the named argument
            else {

                // Get PUT data (also supports JSON encoded POST data)
                $_PUT = null;

                if (isset($_SERVER['REQUEST_METHOD'])) {
                    $method = $_SERVER['REQUEST_METHOD'];
                    if ('PUT' === $method || 'POST' === $method) {
                        try {  
                            $_PUT = json_decode(file_get_contents("php://input"), true, 512, JSON_THROW_ON_ERROR);
                        }  
                        catch (\JsonException $exception) {  
                            parse_str(file_get_contents('php://input'), $_PUT);
                        }
                    }
                }

                if (!$param->isOptional() && !isset($_GET[$name]) && !isset($_POST[$name]) && !isset($_PUT[$name])) {
                    return $this->sendBadRequest('MissingRequiredArgumentException', sprintf("Argument '%s' is missing.", $name));
                }

                $arguments[] = isset($_GET[$name]) ? ($_GET[$name]) : (isset($_POST[$name]) ? $_POST[$name] : (isset($_PUT[$name]) ? $_PUT[$name] : $param->getDefaultValue()));
            }
        }

        if ($this->checkAccessFn) {
            $args[] = $this->getClient()->getURL();
            $args[] = $routeUri;
            $args[] = $method;
            $args[] = $arguments;
            try {
                call_user_func_array($this->checkAccessFn, $args);
            } catch (\Exception $e) {
                $this->sendException($e);
            }
        }

        //fire method
        $object = $this->controller;

        return $this->fireMethod($callableMethod, $object, $arguments);

    }

    /**
     * Fire a method.
     *
     * @param  string $pMethod  The method to fire.
     * @param  object $pObject  The object to fire the method on.
     * @param  array  $pArgs    The arguments to pass to the method.
     * @return mixed            The response.
     */
    public function fireMethod($pMethod, $pController, $pArguments) {
        $unescape = 0;
        
        if (is_string($pMethod)) {
            $ref = new \ReflectionClass($this->controller);
            $refMethod = $ref->getMethod($pMethod);
        } else {
            $refMethod = new \ReflectionFunction($pMethod);
        }
        $metadata = $this->getMethodMetaData($refMethod);

        if (isset($metadata['unescape'])) {
            $unescape = $metadata['unescape'];
        }

        $callable = false;

        if ($pController && is_string($pMethod)) {
            if (!method_exists($pController, $pMethod)) {
                return $this->sendError('MethodNotFoundException', sprintf('Method %s in class %s not found.', $pMethod, get_class($pController)));
            } else {
                $callable = array($pController, $pMethod);
            }
        } elseif (is_callable($pMethod)) {
            $callable = $pMethod;
        }

        if ($callable) {
            try {
                return $this->send(call_user_func_array($callable, $pArguments), $unescape);
            } catch (\Exception $e) {
                return $this->sendException($e);
            }
        }
    }

    /**
     * Describe a route or the whole controller with all routes.
     *
     * @param  string  $pUri            The uri to describe.
     * @param  boolean $pOnlyRoutes     Whether to only describe the routes.
     * @return array                    The description.
     */
    public function describe($pUri = null, $pOnlyRoutes = false) {
        $definition = array();

        if (!$pOnlyRoutes) {
            $definition['parameters'] = array(
                '_method' => array( 'description' => 'Can be used as HTTP METHOD if the client does not support HTTP methods.', 'type' => 'string',
                                    'values' => 'GET, POST, PUT, DELETE, HEAD, OPTIONS, PATCH'),
                '_suppress_status_code' => array('description' => 'Suppress the HTTP status code.', 'type' => 'boolean', 'values' => '1, 0'),
                '_format' => array('description' => 'Format of generated data. Can be added as suffix .json .xml', 'type' => 'string', 'values' => 'json, xml'),
            );
        }

        $definition['controller'] = array(
            'entryPoint' => $this->getTriggerUrl()
        );

        foreach ($this->routes as $routeUri => $routeMethods) {

            $matches = array();
            if (!$pUri || ($pUri && preg_match('|^'.$routeUri.'$|', $pUri, $matches))) {

                if ($matches) {
                    array_shift($matches);
                }
                $def = array();
                $def['uri'] = $this->getTriggerUrl().'/'.$routeUri;

                foreach ($routeMethods as $method => $phpMethod) {

                    if (is_string($phpMethod)) {
                        $ref = new \ReflectionClass($this->controller);
                        $refMethod = $ref->getMethod($phpMethod);
                    } else {
                        $refMethod = new \ReflectionFunction($phpMethod);
                    }

                    $def['methods'][strtoupper($method)] = $this->getMethodMetaData($refMethod, $matches);

                }
                $definition['controller']['routes'][$routeUri] = $def;
            }
        }

        if (!$pUri) {
            foreach ($this->controllers as $controller) {
                $definition['subController'][$controller->getTriggerUrl()] = $controller->describe(false, true);
            }
        }

        return $definition;
    }

    /**
     * Fetches all meta data informations as params, return type etc.
     *
     * @param  \ReflectionMethod    $pMethod
     * @param  array                $pRegMatches
     * @return array                The meta data.
     */
    public function getMethodMetaData(\ReflectionFunctionAbstract $pMethod, $pRegMatches = null) {
        $file = $pMethod->getFileName();
        $startLine = $pMethod->getStartLine();

        $fh = fopen($file, 'r');
        if (!$fh) return false;

        $lineNr = 1;
        $lines = array();
        while (($buffer = fgets($fh)) !== false) {
            if ($lineNr == $startLine) break;
            $lines[$lineNr] = $buffer;
            $lineNr++;
        }
        fclose($fh);

        $phpDoc = '';
        $blockStarted = false;
        while ($line = array_pop($lines)) {

            if ($blockStarted) {
                $phpDoc = $line.$phpDoc;

                //if start comment block: /*
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
                continue;
            } else {
                //we are not in a comment block.
                //if class def, array def or close bracked from fn comes above
                //then we dont have phpdoc
                if (preg_match('/^\s*\t*[a-zA-Z_&\s]*(\$|{|})/', $line)) {
                    break;
                }
            }

            $trimmed = trim($line);
            if ($trimmed == '') continue;

            //if end comment block: */
            if (preg_match('/\*\//', $line)) {
                $phpDoc = $line.$phpDoc;
                $blockStarted = true;
                //one line php doc?
                if (preg_match('/\s*\t*\/\*/', $line)) {
                    break;
                }
            }
        }

        $phpDoc = $this->parsePhpDoc($phpDoc);

        $refParams = $pMethod->getParameters();
        $params = array();

        $fillPhpDocParam = !isset($phpDoc['param']);

        foreach ($refParams as $param) {
            $params[$param->getName()] = $param;
            if ($fillPhpDocParam) {
                $phpDoc['param'][] = array(
                    'name' => $param->getName(),
                    'type' => $this->declaresArray($param)?'array':'mixed'
                );
            }
        }

        $parameters = array();

        if (isset($phpDoc['param'])) {
            if (is_array($phpDoc['param']) && is_string(key($phpDoc['param'])))
                $phpDoc['param'] = array($phpDoc['param']);

            $c = 0;
            foreach ($phpDoc['param'] as $phpDocParam) {
                if (isset($params[$phpDocParam['name']]))
                    $param = $params[$phpDocParam['name']];
                if (!$param) continue;
                $parameter = array(
                    'type' => $phpDocParam['type']
                );

                if ($pRegMatches && is_array($pRegMatches) && $pRegMatches[$c]) {
                    $parameter['fromRegex'] = '$'.($c+1);
                }

                $parameter['required'] = !$param->isOptional();

                if ($param->isDefaultValueAvailable()) {
                    $parameter['default'] = str_replace(array("\n", ' '), '', var_export($param->getDefaultValue(), true));
                }
                $parameters[$this->argumentName($phpDocParam['name'])] = $parameter;
                $c++;
            }
        }

        if (!isset($phpDoc['return']))
            $phpDoc['return'] = array('type' => 'mixed');

        $result = array(
            'parameters' => $parameters,
            'return' => $phpDoc['return']
        );

        if (isset($phpDoc['description']))
            $result['description'] = $phpDoc['description'];

        if (isset($phpDoc['url']))
            $result['url'] = $phpDoc['url'];

        if (isset($phpDoc['openapiurl']))
            $result['openapiurl'] = ltrim($phpDoc['openapiurl'], '@openapiurl ');

        if (isset($phpDoc['unescape'])) {
            if ($phpDoc['unescape']['unescape'] == "true") {
                $result['unescape'] = 1;
            }
            else {
                $result['unescape'] = 0;
            }
        }
        
        return $result;
    }


    /**
     * Parse phpDoc string and returns an array of attributes.
     *
     * @param  string $pString The phpDoc string.
     * @return array           The attribute array.
     */
    public function parsePhpDoc($pString) {
        preg_match('#^/\*\*(.*)\*/#s', trim($pString), $comment);

        if (0 === count($comment)) return array();

        $comment = trim($comment[1]);

        preg_match_all('/^\s*\*(.*)/m', $comment, $lines);
        $lines = $lines[1];

        $tags = array();
        $currentTag = '';
        $currentData = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (substr($line, 0, 1) == '@') {

                if ($currentTag)
                    $tags[$currentTag][] = $currentData;
                else
                    $tags['description'] = $currentData;

                $currentData = '';
                preg_match('/@([a-zA-Z_]*)/', $line, $match);
                $currentTag = $match[1];
            }

            $currentData = trim($currentData.' '.$line);

        }
        if ($currentTag)
            $tags[$currentTag][] = $currentData;
        else
            $tags['description'] = $currentData;

        //parse tags
        $regex = array(
            'param' => array('/^@param\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*\$([a-zA-Z_]*)\s*\t*(.*)/', array('type', 'name', 'description')),
            'url' => array('/^@url\s*\t*(.+)/', array('url')),
            'return' => array('/^@return\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*(.*)/', array('type', 'description')),
            'unescape' => array('/^@unescape\s*\t*(.+)/', array('unescape')),
        );
        foreach ($tags as $tag => &$data) {
            if ($tag == 'description') continue;
            foreach ($data as &$item) {
                if (isset($regex[$tag])) {
                    preg_match($regex[$tag][0], $item, $match);
                    $item = array();
                    $c = count($match);
                    for ($i =1; $i < $c; $i++) {
                        if (isset($regex[$tag][1][$i-1])) {
                            $item[$regex[$tag][1][$i-1]] = $match[$i];
                        }
                    }
                }
            }
            if (count($data) == 1)
                $data = $data[0];
        }

        return $tags;
    }

    /**
     * Set first char to lower case.
     * 
     * @param  string $pName The name.
     * @return string       The name.
     */
    public function argumentName($pName) {
        if (ctype_lower(substr($pName, 0, 1)) && ctype_upper(substr($pName, 1, 1))) {
            return strtolower(substr($pName, 1, 1)).substr($pName, 2);
        } return $pName;
    }

    /**
     * Find and return the route for the URL.
     *
     * @param  string        $pUri      The URL.
     * @param  string        $pMethod   limit to method.
     * @return array|boolean            The route or false if not found.
     */
    public function findRoute($pUri, $pMethod = '_all_') {
        if (isset($this->routes[$pUri][$pMethod]) && $method = $this->routes[$pUri][$pMethod]) {
            return array($method, array(), $pMethod, $pUri);
        } elseif ($pMethod != '_all_' && isset($this->routes[$pUri]['_all_']) && $method = $this->routes[$pUri]['_all_']) {
            return array($method, array(), $pMethod, $pUri);
        } else {
            //maybe we have a regex uri
            foreach ($this->routes as $routeUri => $routeMethods) {

                if (preg_match('|^'.$routeUri.'$|', $pUri, $matches)) {

                    if (!isset($routeMethods[$pMethod])) {
                        if (isset($routeMethods['_all_']))
                            $pMethod = '_all_';
                        else
                            continue;
                    }

                    array_shift($matches);
                    foreach ($matches as $match) {
                        $arguments[] = $match;
                    }

                    return array($routeMethods[$pMethod], $arguments, $pMethod, $routeUri);
                }

            }
        }

        return false;
    }
}
