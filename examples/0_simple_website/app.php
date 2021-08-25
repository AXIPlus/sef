<?php
/**
 * Simple website example running on the SEF framwork.
 */

include_once("sef/sef.php");
include_once("pages/multiple_pages.php");   //for Page404

function app_run() {
    $site = new SEF\SEF([
        "debugMode" => true,
        "appFolder" => "examples/0_simple_website",
        "enforceHTTPS" => false,
        "notFoundPage" => "Page404"
    ]);

    $site->Router->addRoute("get", "/", "pages/multiple_pages.php", "PageMain", [], []);
    $site->Router->addRoute("get", "/page1", "pages/single_page.php", "Page1", [], []);
    $site->Router->addRoute("get", "/page2", "pages/multiple_pages.php", "Page2", [], []);

    $site->run();
}
