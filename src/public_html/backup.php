<?php

use YAPF\Bootstrap\Template\Grid;

include "core.php";
include "getToken.php";

$uri = "api/stacks";

$response = $client->request('GET', $uri, [
    'json' => [],
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
]);

if ($response->getStatusCode() != 200) {
    die("Failed to get stacks");
}
$stacks = json_decode($response->getBody(), true);

$stackDetails = [];
foreach ($stacks as $entry) {
    $dat = [
        "stackID" => $entry["Id"],
        "Name" => $entry["Name"],
        "Stack" => "",
    ];
    $stackDetails[$entry["Id"]] = $dat;

    $uri = "api/stacks/" . $dat["stackID"] . "/file";
    $response = $client->request('GET', $uri, [
        'json' => [],
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ],
    ]);

    if ($response->getStatusCode() != 200) {
        die("Unable to get stackfile for: " . $entry["Name"]);
    }
    $stackDetails[$entry["Id"]]["Stack"] = json_decode($response->getBody(), true)["StackFileContent"];
}
$stacks = [];

//echo json_encode($stackDetails);

$uri = "api/endpoints/" . $endpoint_id . "/docker/containers/json?all=true";
$response = $client->request('GET', $uri, [
    'json' => [],
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
]);

if ($response->getStatusCode() != 200) {
    die("Unable to request containers on the endpoint");
}

$containers = [];
$recoverMounts = [];
$raw_containers = json_decode($response->getBody(), true);
$containerNames = [];
foreach ($raw_containers as $entry) {
    $newContainer = [
        "Name" => $entry["Id"],
        "Command" => "",
        "Entrypoint" => "",
        "Image" => $entry["Image"],
        "Ports" => [],
        "Mounts" => [],
        "Environment" => [],
    ];
    if (array_key_exists("Names", $entry) == true) {
        $newContainer["Name"] = $entry["Names"][0];
    }

    if (in_array($newContainer["Name"], $ignoreContainerNames) == true) {
        continue;
    }
    $containerNames[] = $newContainer["Name"];


    if (array_key_exists("Mounts", $entry) == true) {
        $loopMount = 1;
        foreach ($entry["Mounts"] as $mount) {
            $source = $mount["Source"];
            if ($source == "") {
                $source = $mount["Destination"];
            }
            $mdat = [
                "Source" => $source,
                "Destination" => $mount["Destination"],
            ];
            $newContainer["Mounts"][] = $mdat;
            $recoverMounts[$newContainer["Name"] . "#" . $loopMount] = $mdat;
            $loopMount++;
        }
    }
    if (array_key_exists("Ports", $entry) == true) {
        foreach ($entry["Ports"] as $portBind) {
            if (array_key_exists("IP", $portBind) == false) {
                continue;
            }
            if ($portBind["IP"] == "::") {
                continue;
            }
            $newContainer["Ports"][] = [
                "from" => $portBind["PublicPort"],
                "to" => $portBind["PrivatePort"],
                "type" => $portBind["Type"],
            ];
        }
    }

    $uri = "api/endpoints/" . $endpoint_id . "/docker/containers/" . $entry["Id"] . "/json";
    $response = $client->request('GET', $uri, [
        'json' => [],
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ],
    ]);

    if ($response->getStatusCode() != 200) {
        die("Unable to get container config");
    }

    $cfg = json_decode($response->getBody(), true);

    if (array_key_exists("Config", $cfg) == false) {
        die("Config missing?");
    }
    if (array_key_exists("Cmd", $cfg["Config"]) == false) {
        die("Cmd missing?");
    }
    if (array_key_exists("Entrypoint", $cfg["Config"]) == false) {
        die("Entrypoint missing?");
    }
    if (array_key_exists("Env", $cfg["Config"]) == false) {
        die("Env missing?");
    }
    if (is_array($cfg["Config"]["Cmd"]) == true) {
        $newContainer["Command"] = $cfg["Config"]["Cmd"];
    }
    if (is_array($cfg["Config"]["Entrypoint"]) == true) {
        $newContainer["Entrypoint"] = $cfg["Config"]["Entrypoint"][0];
    }
    foreach ($cfg["Config"]["Env"] as $env) {
        $bits = explode("=", $env, 2);
        $newContainer["Environment"][$bits[0]] = $bits[1];
    }

    if (array_key_exists("Labels", $entry) == true) {
        if (array_key_exists("com.docker.compose.project", $entry["Labels"]) == true) {
            if ($entry["Labels"]["com.docker.compose.project"] != "") {
                continue; // skip this is part of a stack
            }
        }
    }
    $containers[] = $newContainer;
}
$raw_containers = [];

$grid = new Grid();
$grid->addContent("<p>Note: for best results please have the containers running!</p>", 8, true);
$grid->closeRow();
foreach ($stackDetails as $stack) {
    $subGrid = new Grid();
    $subGrid->addContent("<h4>Stack / " . $stack["Name"] . " </h4>", 12);
    $subGrid->addContent('<textarea cols="75" rows="15">' . $stack["Stack"] . '</textarea>', 12);
    $grid->addContent($subGrid->getOutput(), 4);
}



if (count($containers) > 0) {
    $subGrid = new Grid();
    $subGrid->addContent("<h4>Stack / Unassigned </h4>", 12);
    $output = '<textarea cols="175" rows="15">';
    $output .= "version: '3'\n";
    $output .= "services:\n";
    $loop = 0;
    foreach ($containers as $container) {
        $output .= tablevel(1) . "AppRecovery" . ($loop + 1) . ":\n";
        $name = $container["Name"];
        if (strlen($name) > 30) {
            $namebits = explode("/", $container["Image"]);
            $name = substr($name, 0, 30);
            if (count($namebits) >= 2) {
                $name = $namebits[1];
            }
        }
        $name = str_replace(":", "", $name);
        $name = str_replace("/", "", $name);
        if (strlen($name) > 30) {
            $name = substr($name, 0, 30);
        }
        $output .= tablevel(2) . "container_name: " . $name . "\n";
        $output .= tablevel(2) . "restart: always\n";
        $output .= tablevel(2) . "image: " . $container["Image"] . "\n";
        if ($container["Entrypoint"] != "") {
            $output .= tablevel(2) . "entrypoint: " . $container["Entrypoint"] . "\n";
        }
        if ($container["Command"] != "") {
            $output .= tablevel(2) . "command: [\"" . implode("\",\"", $container["Command"]) . "\"]\n";
        }
        if (count($container["Environment"]) > 0) {
            $output .= tablevel(2) . "environment:\n";
            foreach ($container["Environment"] as $key => $value) {
                $output .= tablevel(3) . "- " . $key . "=" . $value . "\n";
            }
        }
        if (count($container["Mounts"]) > 0) {
            $output .= tablevel(2) . "volumes:\n";
            foreach ($container["Mounts"] as $mount) {
                $output .= tablevel(3) . "- type: bind\n";
                $output .= tablevel(3) . "source: " . $mount["Source"] . "\n";
                $output .= tablevel(3) . "target: " . $mount["Destination"] . "\n";
            }
        }
        if (count($container["Ports"]) > 0) {
            $output .= tablevel(2) . "ports:\n";
            foreach ($container["Ports"] as $port) {
                $output .= tablevel(3) . "- " . $port["from"] . ":" . $port["to"] . "\n";
            }
            $output .= tablevel(2) . "expose:\n";
            $output .= tablevel(3) . "- '" . $port["from"] . "/" . $port["type"] . "'\n";
        }
        $output .= "\n";
        $loop++;
    }
    $output .= '</textarea>';
    $subGrid->addContent($output, 12);
    $grid->addContent($subGrid->getOutput(), 12);
}

$grid->addContent("<h4>Folders / Files to backup</h4>", 12);
$output = '<textarea cols="175" rows="15">';
foreach ($recoverMounts as $container => $mount) {
    $output .= $container . " => ";
    $output .= "\"" . $mount["Source"] . "\"\n";
}
$output .= '</textarea>';
$grid->addContent($output, 12);

$grid->addContent("<h4>List of all containers</h4>", 12);
$output = '<textarea cols="175" rows="15">';
foreach ($containerNames as $name) {
    $output .= $name . "\n";
}
$output .= '</textarea>';
$grid->addContent($output, 12);



echo $grid->getOutput();
include "footer.html";
