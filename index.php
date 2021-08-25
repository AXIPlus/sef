<?php
/**
 * This is the SEF framework entry file.
 * This file should only include your app/app.php file and call app_run().
 * app_run() needs to create the SEF object with required parameters, and call its run function.
 * 
 * Since this is an example case, the include does not actually include "app/app.php" but "examples/xxxxx/app.php".
 */

//choose only one of the examples
// include_once("app/app.php");
include_once("examples/0_simple_website/app.php");
// include_once("examples/1_api/app.php");
// include_once("examples/2_mixed/app.php");

app_run();
