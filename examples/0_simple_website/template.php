<?php
include_once("sef/page.php");

abstract class MyTemplate extends SEF\Page {
    function getTemplate(): string {
        return '
<html>
    <head>
        <title>{$$Page\Title$$}</title>
    </head>
    <body>
        {$$Template\Content$$}
    </body>
</html>';
    }
}
