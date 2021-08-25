<?php
include_once("examples/0_simple_website/template.php"); //usually this would have been "app/template.php"

class Page404 extends MyTemplate {
    function getContent(): string {
        $this->title = "404 Error - Page not found!";

        return "We are sorry, but the page you requested was not found.";
    }
}

class Page2 extends MyTemplate {
    function getContent(): string {
        $this->title = "Page 2";

        return "<a href=''>Main page</a> <a href='page1'>Page1</a>";
    }
}

class PageMain extends MyTemplate {
    function getContent(): string {
        $this->title = "Main Page";

        return "<a href='page1'>Page 1</a> <a href='page2'>Page 2</a> ";
    }
}
