<?php

$router = $di->getRouter();

// Define your routes here
$router->add("/crawler/", array(
    "controller" => "Crawler",
    "action"     => "Index",
));
$router->handle($_SERVER['REQUEST_URI']);
