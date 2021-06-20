<?php

function Verify()
{
    $mode = $_GET["hub_mode"];
    $challenge = $_GET["hub_challenge"];
    $verifyToken = $_GET["hub_verify_token"];

    if ($verifyToken === "chris")
    {
        header("Content-Type: application/json");
        http_response_code(200);
        echo "{ \"hub.challenge\":\"$challenge\" }";
    }
    else
    {
        http_response_code(404);
        die();
    }
}

function ProcessEvent()
{
    $json = file_get_contents("php://input");
    $event = json_decode($json, true);

    $config = json_decode(file_get_contents(__DIR__."/../bikehelper/config.json"), true);
    $clientId = $config["ClientID"];
    $clientSecret = $config["ClientSecret"];
    $allowedUserId = $config["AllowedUserID"];

    $athleteId = strval($event["owner_id"]);
    $objectType = $event["object_type"];
    $aspectType = $event["aspect_type"];

    if ($athleteId !== $allowedUserId ||
        $objectType !== "activity" ||
        $aspectType !== "create")
    {
        http_response_code(200);
        die();
    }

    $activityId = $event["object_id"];
    DownloadRide($activityId);
}

function DownloadRide($activityId)
{
    $url = "http://localhost:59001/download_activity.php?activity=$activityId";

    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_exec($req);
    curl_close($req);
}

if ($_SERVER['REQUEST_METHOD'] === "GET")
{
    Verify();
}
else if ($_SERVER['REQUEST_METHOD'] === "POST")
{
	file_put_contents(__DIR__."/../bikehelper/verify.json",file_get_contents("php://input"));
    ProcessEvent();
}
else
{
    http_response_code(404);
    die();
}

?>
