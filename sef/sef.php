<?php

namespace SEF;
include_once "router.php";

class SEF {
    //instances count
    private static $instances = 0;

    //debug related
    private $debugMode = false;
    private $startTime;
    private $renderContent; //for debug purposes only

    //settings
    private $appFolder = "app/";
    private $baseURI = "";
    private $URI = "";
    private $routeParams = array();

    public $Router;

    function __construct(array $settings) {
        SEF::$instances++;
        if(SEF::$instances != 1) {
            SEF::$instances--;
            throw new \Exception("Cannot have more than 1 SEF object!");
        }

        if(isset($settings["debugMode"])) {
            $this->debugMode = $settings["debugMode"];
            if($this->debugMode) {
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
    
                $this->startTime = microtime(true);
            }
        }

        if(isset($settings["enforceHTTPS"])) {
            if($settings["enforceHTTPS"] && (strtolower($_SERVER['REQUEST_SCHEME']) != "https")) {
                throw new \Exception("SEF::__construct(): https is enforced.");
            }    
        }

        if(isset($settings["appFolder"])) {
            $this->appFolder = $settings["appFolder"];
        }

        if(isset($settings["baseURI"])) {
            $this->baseURI = $settings["baseURI"];
        }

        if(!isset($settings["loadMainRoutes"])) {
            $settings["loadMainRoutes"] = "";
        }

        if(isset($settings["forceURI"])) {
            $this->URI = $settings["forceURI"];
        }

        if(isset($settings["routeParams"])) {
            $this->routeParams = $settings["routeParams"];
        }

        if(isset($settings["notFoundPage"])) {
            RouteNotFound::setPage($settings["notFoundPage"]);
        }

        //compute URI
        if($this->URI == "") {
            $this->URI = $_SERVER["REQUEST_URI"];
        }

        //initialize router
        $this->Router = new Router(['appFolder' => $this->appFolder, 'loadRoutes' => $settings["loadMainRoutes"]]);
    }

    function run() {
        if($this->debugMode) {
            $this->renderContent = $this->Router->forward($this->baseURI, $this->URI, $this->routeParams)->render();
            echo $this->renderContent;
        }
        else {
            echo $this->Router->forward($this->baseURI, $this->URI, $this->routeParams)->render();
        }
    }

    function __destruct() {
        if($this->debugMode) {
            $endTime = microtime(true);
            $creationTime = sprintf("%.6f", ($endTime - $this->startTime));
            echo "Page created in $creationTime seconds.<br>\n";
            SEF::$instances--;

            if(strpos($this->renderContent, '{%%') !== false) {
                echo "Leftover values not treated.<br>\n";
            }

            if(strpos($this->renderContent, '{$$') !== false) {
                echo "Leftover variables not treated.<br>\n";
            }

            if(strpos($this->renderContent, '{&&') !== false) {
                echo "Leftover functions not treated.<br>\n";
            }
        }
    }
}
