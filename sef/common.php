<?php

namespace SEF;

/**
 * A RoutingEntity must implement forward() and backward() passing functions.
 */
interface RoutingEntity {
    /**
     * RoutingEntity interface constructor
     * @param   array  $settings  Settings to be saved as $this->settings and passed further on the chain.
     * @return  void
     */
    function __construct(array $settings);

    /**
     * Forward route function.
     * @param   string  $baseURI      Base URI for current router; will be substracted from originalURI.
     * @param   string  $originalURI  Full request URI.
     * @param   array   $setParams    Array of pairs of parameters available for the current route.
     * @return  RendableEntity
     */
    function forward(string $baseURI, string $originalURI, array $setParams): RendableEntity;

    /**
     * Backward route function.
     * @param   string  $baseURI      Base URI for current router; will be substracted from originalURI.
     * @param   string  $originalURI  Full request URI.
     * @param   array   $setParams    Array of pairs of parameters available for the current route. Usually is an empty array.
     * @param   array   $newParams    Array of parameters that need to be matched. Routes with NO setParams will also match! If more routes match, the last one in the array will be returned (Note: define default routes first).
     * @return  string                Link of the backward route.
     */
    function backward(string $baseURI = "", string $originalURI = "", array $setParams, array $newParams): string;
}

/**
 * A RendableEntity is an entity which must implement the render() function.
 */
interface RendableEntity {
    /**
     * RendableEntity interface constructor.
     * @param   array  $params  Parameters to be saved as $this->params by the implementing class.
     * @return  void
     */
    function __construct(array $params);


    /**
     * render function interface
     * @return  string  Value will be rendered on the output.
     */
    function render(): string;
}

/**
 * RouteNotFound class which is a RendableEntity that is returned by a RoutingEntity when route is not found.
 * For initialization, user must set SEF\RouteNotFound::notFoundPage to be a valid RendableEntity.
 * Code 404 is outputted automatically by this class.
 */
class RouteNotFound implements RendableEntity {
    static private $notFoundPage = null;

    /**
     * Public access function to set the not found page class name.
     * @param   string  $pageClassName  Name of the not found page class; must be a subclass of SEF\RendableEntity.
     * @return  void
     */
    static public function setPage(string $pageClassName) {
        self::$notFoundPage = $pageClassName;
        if(!is_subclass_of(self::$notFoundPage, 'SEF\RendableEntity')) {
            throw new \Exception("SEF\RouteNotFound::__construct(): ".self::$notFoundPage." is not a rendable entity.");
        }
    }
    
    //implements RendableEntity
    function __construct(array $params) {
        $this->params = $params;
    }

    //implements RendableEntity
    function render(): string {
        http_response_code(404);

        if(self::$notFoundPage == null) {
            return "404 - Route not found!\n";
        }
        else {
            $page = new self::$notFoundPage($this->params);
            return $page->render();
        }
    }
}

/**
 * var_dump() function with arrays made pretty for HTML printing; is does not output anything to the screen, but returns the output.
 * Used in debugging.
 * @param   any     $value value to be dumped
 * @return  string         returned dump string
 */
function prettyVarDump($value): string {
    ob_start();
    var_dump($value);
    return "<pre>".str_replace("=>\n", "", ob_get_clean())."</pre>\n";
}
