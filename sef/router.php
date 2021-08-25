<?php

namespace SEF;
include_once "common.php";

/**
 * Standard Router implementation
 * When extending for sub-routers, just reimplement __construct($settings) and after calling parent::__construct($settings) add wanted routes.
 */
class Router implements RoutingEntity {
    //input
    protected $method = "";

    //settings
    protected $settings;    //unparsed
    protected $appFolder = "app/";

    protected $forwardRoutes = array();
    protected $backwardRoutes = array();

    /**
     * Router constructor. Implements RoutingEntity and adds functionality.
     * @param   array  $settings  Router settings known: "appFolder", "loadRoutes".
     * @return  void
     */
    function __construct(array $settings) {
        //settings
        $this->settings = $settings;

        //method
        $this->method = strtolower($_SERVER["REQUEST_METHOD"]);

        //parse settings
        if(isset($settings["appFolder"])){
            $this->appFolder = $settings["appFolder"];
            if(substr($this->appFolder, -1) != "/") {
                $this->appFolder .= "/";
            }
        }

        if(isset($settings["loadRoutes"])){
            unset($this->settings["loadRoutes"]); //don't pass it forward

            $this->loadRoutes($settings["loadRoutes"]);
        }
    }

    /**
     * Load routes from a serialized string. Note that any routes previously added will be cleared.
     * @param   string  $serializedRoutes  Serialized string that contains the routes.
     */
    public function loadRoutes(string $serializedRoutes) {
        if($serializedRoutes == "") {
            return;
        }

        $checkThere = substr($serializedRoutes, -8);
        $serializedRoutes = substr($serializedRoutes, 0, -8);
        $checkHere = hash("crc32b", $serializedRoutes);
        if($checkHere != $checkThere) {
            throw new \Exception("Router::loadRoutes(): routes are invalid.");
        }
        $loadRoutes = unserialize($serializedRoutes);
        $this->forwardRoutes = $loadRoutes[0];
        $this->backwardRoutes = $loadRoutes[1];
        
    }

    /**
     * Save routes for later load (optimization for big routers).
     * @return  string  serializedRoutes
     */
    public function saveRoutes(): string {
        $ser = serialize(array($this->forwardRoutes, $this->backwardRoutes));
        return $ser.hash("crc32b", $ser);
    }
    
    /**
     * Function to add a new Route.
     * @param   string  $method       Method for which route responds.
     * @param   string  $route        The actual route.
     * @param   string  $filePath     Path of file (relative to appPath) where the route will find either a RendableEntity or a RoutingEntity.
     * @param   string  $className    Name of class derived from RendableEntity or RoutingEntity that will be used by router either to route or render.
     * @param   array/null  $paramsMatch  Route will be available only if it contains ALL $paramsMatch on forward pass.
     * @param   array/null  $paramsSet    Router will further pass these parameteres (as well as other previously chained $paramsSet). Note that this will also contain $paramsMatch automatically, but if both have the same parameter, paramSet will prevail
     * @return  void
     */
    public function addRoute(string $method, string $route, string $filePath, string $className, $paramsMatch, $paramsSet) {
        //small route checkups
        if($route == "") {
            throw new \Exception("Router::addRoute(): route can't be blank.");
        }

        if($route[0] != "/") {
            throw new \Exception("Router::addRoute(): must start with a backslash (/).");
        }

        $wcpos = strpos($route, "*");
        if(($wcpos !== false) && ($wcpos != strlen($route) - 1)) {
            throw new \Exception("Router::addRoute(): wildcard (*) must only be at the end of the route.");
        }

        //other checkups
        $method = strtolower($method);
        if($method == "") {
            throw new \Exception("Router::addRoute(): method can't be blank.");
        }

        if($filePath == "") {
            throw new \Exception("Router::addRoute(): filePath can't be blank.");
        }

        if($className == "") {
            throw new \Exception("Router::addRoute(): className can't be blank.");
        }

        //check if file and class exists
        if(!file_exists($this->appFolder.$filePath)) {
            throw new \Exception("Router::addRoute(): could not find filePath: ".$this->appFolder.$filePath.".");
        }

        include_once $this->appFolder.$filePath;
        if(!class_exists($className, false)) {
            throw new \Exception("Router::addRoute(): did not find class $className in ".$this->appFolder.$filePath.".");
        }

        //make method part of the route
        $route = $method.$route;

        //extract trailing '/'
        if(substr($route, -1) == '/') {
            $route = substr($route, 0, -1);
        }

        //check and adjust params
        if(!is_array($paramsSet)) {
            $paramsSet = array();
        }

        if(!is_array($paramsMatch)) {
            $paramsMatch = array();
        }

        //add paramsMatch to paramsSet
        $paramsSet = array_merge($paramsSet, $paramsMatch);

        //explode route
        $explodeRoute = explode("/", $route);
        $explodeCount = count($explodeRoute);
        $lastNode = $explodeRoute[$explodeCount - 1];

        //check if class subtype is ok
        if(($lastNode == '*') && !is_subclass_of($className, "SEF\RoutingEntity")) {
            throw new \Exception("Router::addRoute(): class $className on route $route is not a SEF\RoutingEntity based type.");
        }
        else if(($lastNode != '*') && !is_subclass_of($className, "SEF\RendableEntity")) {
            throw new \Exception("Router::addRoute(): class $className on route $route is not a SEF\RendableEntity based type.");
        }
        

        //try to add to forwardRoutes
        $forwardRoute = &$this->forwardRoutes;
        for($i = 0; $i < $explodeCount; $i++) {
            //decode url
            $lastNode = urldecode($explodeRoute[$i]);;
            if($i != $explodeCount - 1) {
                //ignore last
                $forwardRoute = &$forwardRoute[$lastNode];
            }
        }

        //if lastNode is not wildcard
        if($lastNode != "*") {
            $forwardRoute = &$forwardRoute[$lastNode];
            $lastNode = "";

            if(isset($forwardRoute["*"])) {
                throw new \Exception("Router::addRoute(): route already contains wildcard route can't have other neighbours ($route/*).");
            }
        }
        //otherwise, check that there's no thing else on this new wildcard route
        else {
            if(isset($forwardRoute)) {
                throw new \Exception("Router::addRoute(): wildcard routes can't have other neighbours ($route).");
            }
        }

        //check if route already exists
        if(isset($forwardRoute[""])) {
            throw new \Exception("Router::addRoute(): route already added ($route).");
        }

        //add forwardRoute
        $forwardRoute[$lastNode]["filePath"] = $filePath;
        $forwardRoute[$lastNode]["className"] = $className;
        $forwardRoute[$lastNode]["paramsSet"] = $paramsSet;
        $forwardRoute[$lastNode]["paramsMatch"] = $paramsMatch;


        //add backwardRoutes
        $filePathClass = "$filePath/$className";
        if(!isset($this->backwardRoutes[$filePathClass])) {
            $this->backwardRoutes[$filePathClass] = array();
        }

        $backwardRouteEntry = array();
        $backwardRouteEntry["link"] = $route;
        $backwardRouteEntry["filePath"] = $filePath;
        $backwardRouteEntry["className"] = $className;
        $backwardRouteEntry["paramsSet"] = $paramsSet;
        array_push($this->backwardRoutes[$filePathClass], $backwardRouteEntry);
    }

    /**
     * Debug function which outputs forwardRoutes
     */
    public function dumpForwardRoutes() {
        echo "forwardRoutes: ".prettyVarDump($this->forwardRoutes);
    }

    /**
     * Debug function which outputs backwardRoutes
     */
    public function dumpBackwardRoutes() {
        echo "backwardRoutes: ".prettyVarDump($this->backwardRoutes);
    }

    /**
     * Router forward pass.
     * @param   string  $baseURI      Base URI for current router; will be substracted from originalURI.
     * @param   string  $originalURI  Full request URI.
     * @param   array   $setParams    Array of pairs of parameters available for the current route.
     * @return  RendableEntity
     */
    public function forward(string $baseURI = "", string $originalURI = "", array $setParams): RendableEntity {
        //baseURI
        if($baseURI != "") {
            //remove any baseURI trailing slashes
            if(substr($baseURI, -1) == "/") {
                $baseURI = substr($baseURI, 0, -1);
            }
        }
        
        //calculate requestURI
        $requestURI = $originalURI;

        //remove any trailing variables
        if(($cut = strpos($requestURI, "?")) !== false) {
            $requestURI = substr($requestURI, 0, $cut);
        }

        //always add trailing slash to current URI
        if(substr($requestURI, -1) != "/") {
            $requestURI .= "/";
        }

        //check that originalURI starts with baseURI
        if(($baseURI != "") && (strpos($originalURI, $baseURI) !== 0)) {
            throw new \Exception("Router::forward(): baseURI not found in requested URI's begining.");
        }

        //remove baseURI
        $requestURI = $this->method.substr($requestURI, strlen($baseURI));

        //calculate URL-decoded explodeURI
        $explodeURI = explode("/", substr($requestURI, 0, -1)); //ignore last '/' on purpose
        foreach($explodeURI as $key => $url) {
            $explodeURI[$key] = urldecode($url);
        }
        
        //perform forward pass
        $route = &$this->forwardRoutes;
        $explodeI = 0;
        $newBase = $baseURI;
        for($explodeI = 0; $explodeI < count($explodeURI); $explodeI++) {
            $uri = $explodeURI[$explodeI];

            if(isset($route[$uri])) {
                $route = &$route[$uri];
                if($explodeI > 0) { //skip method
                    $newBase .= "/".urlencode($uri);
                }
            }
            else {
                break;
            }
        }

        $rendableEntity = null;
        if(isset($route["*"]) && (self::matchesParams($setParams, $route["*"]["paramsMatch"]))) {
            //if there's a wildcard for this, proceed further through next router        
            $setParams = array_merge($setParams, $route["*"]["paramsSet"]);

            $filePath = $route["*"]["filePath"];
            $className = $route["*"]["className"];
            include_once $this->appFolder.$filePath;
            
            $nestedRouter = new $className($this->settings);
            $rendableEntity = $nestedRouter->forward($newBase, $originalURI, $setParams);
        }
        else if($explodeI == count($explodeURI)) {
            if(isset($route[""]) && (self::matchesParams($setParams, $route[""]["paramsMatch"]))) {
                //route found
                $setParams = array_merge($setParams, $route[""]["paramsSet"]);

                $filePath = $route[""]["filePath"];
                $className = $route[""]["className"];
                include_once $this->appFolder.$filePath;
                $rendableEntity = new $className($setParams);
            }
            else {
                //route only partially found
                $rendableEntity = new RouteNotFound($setParams);
            }
        }
        else {
            //route not found
            $rendableEntity = new RouteNotFound($setParams);
        }

        return $rendableEntity;
    }

    /**
     * Backward route function.
     * @param   string  $baseURI      Base URI for current router; will be substracted from originalURI.
     * @param   string  $originalURI  Full request URI.
     * @param   array   $setParams    Array of pairs of parameters available for the current route. Usually is an empty array.
     * @param   array   $newParams    Array of parameters that need to be matched. Routes with NO setParams will also match! If more routes match, the last one in the array will be returned (Note: define default routes first).
     * @return  string                Link of the backward route.
     */
    public function backward(string $baseURI = "", string $originalURI = "", array $setParams, array $newParams): string {
        //baseURI
        if($baseURI != "") {
            //remove any baseURI trailing slashes
            if(substr($baseURI, -1) == "/") {
                $baseURI = substr($baseURI, 0, -1);
            }
        }
        
        //calculate requestURI
        $requestURI = $originalURI;

        //remove any trailing variables
        if(($cut = strpos($requestURI, "?")) !== false) {
            $requestURI = substr($requestURI, 0, $cut);
        }

        //always add trailing slash to current URI
        if(substr($requestURI, -1) != "/") {
            $requestURI .= "/";
        }

        //check that originalURI starts with baseURI
        if(($baseURI != "") && (strpos($originalURI, $baseURI) !== 0)) {
            throw new \Exception("Router::backward(): baseURI not found in requested URI's begining.");
        }

        //remove baseURI
        $requestURI = $this->method.substr($requestURI, strlen($baseURI));

        //calculate URL-decoded explodeURI
        $explodeURI = explode("/", substr($requestURI, 0, -1)); //ignore last '/' on purpose
        foreach($explodeURI as $key => $url) {
            $explodeURI[$key] = urldecode($url);
        }
        
        //perform forward pass
        $route = &$this->forwardRoutes;
        $explodeI = 0;
        $newBase = $baseURI;
        for($explodeI = 0; $explodeI < count($explodeURI); $explodeI++) {
            $uri = $explodeURI[$explodeI];

            if(isset($route[$uri])) {
                $route = &$route[$uri];
                if($explodeI > 0) { //skip method
                    $newBase .= "/".urlencode($uri);
                }
            }
            else {
                break;
            }
        }

        $newURI = "";
        if(isset($route["*"]) && (self::matchesParams($setParams, $route["*"]["paramsMatch"]))) {
            //if there's a wildcard for this, proceed further through next router        
            $setParams = array_merge($setParams, $route["*"]["paramsSet"]);

            $filePath = $route["*"]["filePath"];
            $className = $route["*"]["className"];
            $filePathClass = "$filePath/$className";

            $routes = &$this->backwardRoutes[$filePathClass];
            for($i = 0; $i < count($routes); $i++) {
                if(self::matchesNewParams($routes[$i]["paramsSet"], $newParams)) {
                    $newURI = substr($routes[$i]["link"], 0, -2);   //extract '/*'
                }
            }

            include_once $this->appFolder.$filePath;
            $nestedRouter = new $className($this->settings);

            //get backward route
            $nestedURI = $nestedRouter->backward($newBase, $originalURI, $setParams, $newParams);

            //remove baseURI
            $nestedURI = substr($nestedURI, strlen($newBase));

            $newURI .= $nestedURI;
            
        }
        else if($explodeI == count($explodeURI)) {
            if(isset($route[""]) && (self::matchesParams($setParams, $route[""]["paramsMatch"]))) {
                //route found
                $setParams = array_merge($setParams, $route[""]["paramsSet"]);

                $filePath = $route[""]["filePath"];
                $className = $route[""]["className"];
                $filePathClass = "$filePath/$className";
                $routes = &$this->backwardRoutes[$filePathClass];
                for($i = 0; $i < count($routes); $i++) {
                    if(self::matchesNewParams($routes[$i]["paramsSet"], $newParams)) {
                        $newURI = $routes[$i]["link"];
                    }
                }
            }
            else {
                //forward route only partially found
                $newURI = "";
            }
        }
        else {
            //forward route not found
            $newURI = "";
        }

        //extract method (of course, it may not exist if route is /)
        if(strpos($newURI, "/") !== false) {
            $newURI = substr($newURI, strpos($newURI, '/'));
        }
        else {
            $newURI = "";
        }

        //add baseURI
        $newURI = $baseURI.$newURI;

        return $newURI;
    }

    /**
     * Internal function that checks whether all needels are in the haystack, with the same value
     * @param   array  $haystack  where to look for parameters
     * @param   array  $needles   parameters to be matched
     * @return  bool              match or not
     */
    private static function matchesParams(array $haystack, array $needles): bool {
        $result = true;
        foreach($needles as $key => $value) {
            if(!isset($haystack[$key]) || ($haystack[$key] != $value)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    
    private static function matchesNewParams(array $haystack, array $needles): bool {
        if(count($haystack) == 0) {
            return true;
        }

        $result = false;
        foreach($needles as $key => $value) {
            if(isset($haystack[$key]) && ($haystack[$key] == $value)) {
                $result = true;
                break;
            }
        }

        return $result;
    }
}

/*
    function loadRoutes($json) {
        if(!is_array($json)) {
            $json = json_decode(file_get_contents($json), true);
            if($json == null) {
                throw new \Exception("Router::loadRoutes(): JSON parse failed.");
            }
        }

        foreach($json as $route => $rs) {
            //route must not have a trailing /
            if($route != "/") {
                if(substr($route, -1) == '/') {
                    throw new \Exception("Router::loadRoutes(): routes must not end in '/' on route $route.");
                }
            }

            if(!in_array($route[0], array("/", "?"))) {
                throw new \Exception("Router::loadRoutes(): routes must start with '/' on route $route.");
            }

            $lang = isset($rs["lang"]) ? $rs["lang"] : null;
            $method = isset($rs["method"]) ? $rs["method"] : null;
            $path = isset($rs["path"]) ? $rs["path"] : null;
            $page = isset($rs["page"]) ? $rs["page"] : null;
            $router = isset($rs["router"]) ? $rs["router"] : null;

            if($lang != null) {
                if(is_string($lang)) {
                    if($lang == "*") {
                        $lang = $this->languageList;
                    }
                    else {
                        $lang = array($lang);
                    }
                }
                else {
                    $lang2 = $lang;
                    $lang = array();
                    foreach($lang2 as $l) {
                        if($l == "*") {
                            $lang = array_merge($lang, $this->languageList);
                        }
                        else {
                            array_push($lang, $l);
                        }
                    }
                }
            }
            else {
                $lang = array("");
            }

            if($method != null) {
                if(is_string($method)) {
                    $method = array($method);
                }
                for($i = 0; $i < count($method); $i++) {
                    $method[$i] = strtolower($method[$i]);
                }
            }
            
            if($path == null) {
                throw new \Exception("Router::loadRoutes(): 'path' property must be set.");
            }

            if($path[0] == '/') {
                $path = substr($path, 1);
            }

            $class = null;
            $className = null;
            if(substr($route, -1) == "*") {
                if($router == null) {
                    throw new \Exception("Router::loadRoutes(): 'router' property must be set for wildcard route: $route.");
                }
                if($page != null) {
                    throw new \Exception("Router::loadRoutes(): 'page' property isn't used for wildcard route: $route.");
                }
                $class = "router";
                $className = $router;
                $page = null;
            }
            else {
                if($page == null) {
                    throw new \Exception("Router::loadRoutes(): 'page' property must be set for route: $route.");
                }
                if($router != null) {
                    throw new \Exception("Router::loadRoutes(): 'router' property isn't used for route: $route.");
                }
                $class = "page";
                $className = $page;
                $router = null;
            }

            if($this->disableFileCheck == false) {
                if(!file_exists($this->pagesFolder.$path)) {
                    throw new \Exception("Router::loadRoutes(): could not find path: ".$this->pagesFolder.$path.".");
                }

                include_once $this->pagesFolder.$path;
                if(!class_exists($className, false)) {
                    throw new \Exception("Router::loadRoutes(): did not find class $className in ".$this->pagesFolder.$path.".");
                }
            }

            //add route
            foreach($lang as $language) {
                $finalRoute = $route;

                if(($finalRoute == "/") && ($language != "")) {
                    $finalRoute = "";
                }

                if($language != "") {
                    $finalRoute = "/".$language.$finalRoute;
                }

                if($route[0] != "?") {
                    $finalRoute = $this->routePrefix.$finalRoute;
                    if($finalRoute != "/") {
                        if(substr($finalRoute, -1) == '/') {
                            $finalRoute = substr($finalRoute, 0, -1);
                        }
                    }
                }

                $routeExp = explode("/", $finalRoute);
                if($routeExp[0] == "") {
                    array_splice($routeExp, 0, 1);
                }
                
                $route_data = array('?method' => $method, '?path' => $path, '?'.$class => $className);
                $routesVar = '$this->routes';
                for($i = 0; $i < count($routeExp); $i++) {
                    $routesVar .= "['".$routeExp[$i]."']";
                }

                eval("if(!isset($routesVar)) $routesVar = array();");
                eval("$routesVar = array_merge($routesVar, \$route_data);");
            }
        }

        var_dump($this->routes);
        die();
    }

    function loadConfigFile($file_json) {
        if(!file_exists($file_json)) {
            throw new \Exception("Router::loadConfigFile(): $file_json not found.");
        }

        $json = json_decode(file_get_contents($file_json), true);
        if($json == null) {
            throw new \Exception("Router::loadConfigFile(): $file_json parse failed.");
        }

        $this->loadConfig($json);
    }

    function loadConfig($json) {
        if(!is_array($json)) {
            $json = json_decode($json, true);
            if($json == null) {
                throw new \Exception("Router::loadConfig(): JSON parse failed.");
            }
        }

    function getRoute() {
        if($this->enforceHTTPS && ($this->scheme != "https")) {
            throw new \Exception("Router::getRoute(): https is required.");
        }
        
        if($this->disableFileCheck == false) {
            //check ?404 route

            $path = $this->routes["?404"]["?path"];
            $className = $this->routes["?404"]["?page"];
            if(!file_exists($this->pagesFolder.$path)) {
                throw new \Exception("Router::getRoute(): could not find path: ".$this->pagesFolder.$path.".");
            }

            include_once $this->pagesFolder.$this->routes["?404"]["?path"];
            if(!class_exists($className, false)) {
                throw new \Exception("Router::getRoute(): did not find class $className in ".$this->pagesFolder.$path.".");
            }
        }

        echo "finding route for: {$this->requestRoute}<br>\n";
        $routeExp = explode("/", $this->requestRoute);
        if($routeExp[0] == "") {
            array_splice($routeExp, 0, 1);
        }

        if(count($routeExp) > 1) {
            if($routeExp[count($routeExp) - 1] == "") {
                array_splice($routeExp, count($routeExp) - 1, 1);
            }
        }

        $route = $this->routes;
        $routePath = "";
        
        $found = true;
        for($i = 0; $i < count($routeExp); $i++) {
            if(isset($route[$routeExp[$i]])) {
                $route = $route[$routeExp[$i]];
                $routePath .= "/".$routeExp[$i];
            }
            else if(isset($route["*"])) {
                //if requestRoute still continues, but we have new router
                $route = $route["*"];
                $routePath .= "/";
                break;
            }
            else {
                $found = false;
                $routePath .= "/".$routeExp[$i];
                break;
            }
        }

        if($found && !isset($route["?path"])) {
            $found = false;
        }

        if($found) {
            if(isset($route['?router'])) {
                $path = $route['?path'];
                $className = $route["?router"];
                include_once $this->pagesFolder.$path;
                $next = new $className;
                $foundRoute = $className->getRoute();
            }
        }

        if(!$found) {
            if(!isset($this->routes["?404"])) {
                $route = null;
            } else {
                $route = $this->routes["?404"];
                $route["?route"] = $routePath;
            }
        }
        
        if($route != null) {
            $foundRoute["route"] = $route["?route"];
            $foundRoute["path"] = $route["?path"];
            $foundRoute["page"] = $route["?page"];
        }

        return $foundRoute;
    }
}
*/
