<?php

use GuzzleHttp\Client;

try {
    $client = new Client(['base_uri' => $endpoint]);
    $response = $client->post(
        "api/auth",
        [
            'body' => json_encode([
                'Username' => $username,
                'Password' => $password,
            ]),
        ]
    );
    $contents = $response->getBody()->getContents();
    $json = json_decode($contents, true);
    $token = $json["jwt"];
} catch (Exception $e) {
    die("Failed: " . $e->getMessage());
}
