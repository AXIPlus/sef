<?php
include_once("examples/0_simple_website/template.php"); //usually this would have been "app/template.php"

class Page1 extends MyTemplate {
    function getContent(): string {
        $this->title = "Page 1";

        return "<a href=''>Main page</a> <a href='page2'>Page2</a>";
    }
}
