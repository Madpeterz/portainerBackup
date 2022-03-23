<?php

namespace App;

include "../../vendor/autoload.php";
include "functions.php";

$endpoint = getenv("ENDPOINT_URL");
$username = getenv("API_USER");
$password = getenv("API_PASSWORD");
$endpoint_id = getenv("ENDPOINT_ID");
$ignoreContainerNames = getenv("IGNORE_CONTAINERS"); // split by |#|

$token = "";

include "header.html";
