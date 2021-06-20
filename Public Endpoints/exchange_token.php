<?php

$config = json_decode(file_get_contents(__DIR__."/../bikehelper/config.json"), true);
$clientId = $config["ClientID"];
$clientSecret = $config["ClientSecret"];
$allowedUserId = $config["AllowedUserID"];

function customError($errno, $errstr) {
    echo "<b>Error:</b> [$errno] $errstr";
}

function ExchangeCodeForToken($clientId, $clientSecret, $allowedUserId)
{
    $code = $_GET["code"];

    // exhange code for auth token
    $url = "https://www.strava.com/oauth/token?client_id=" . $clientId . "&client_secret=" . $clientSecret . "&code=" . $code . "&grant_type=authorization_code";
    
    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true);
    $res = curl_exec($req);
    curl_close($req);
    
    $auth = json_decode($res, true);
    
    $athleteId = strval($auth["athlete"]["id"]);
    
    if ($athleteId !== $allowedUserId)
    {
        echo "Unauthorized Athlete $athleteId !== $allowedUserId";
        return false;
    }

    file_put_contents(__DIR__."/../bikehelper/auth.json", $res);
    
    return true;
}

function CreateSubscription($clientId, $clientSecret)
{
    $callbackUrl = "https://bike.chriswald.com/verify_subscription.php";
    $url = "https://www.strava.com/api/v3/push_subscriptions?client_id=" . $clientId . "&client_secret=" . $clientSecret . "&callback_url=" . $callbackUrl . "&verify_token=chris";

    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true);
    $res = curl_exec($req);
    curl_close($req);

    print_r($res);
}

function SubscriptionExists($clientId, $clientSecret)
{
    $url = "https://www.strava.com/api/v3/push_subscriptions?client_id=" . $clientId . "&client_secret=" . $clientSecret;
    
    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($req);
    curl_close($req);

    $subscriptions = json_decode($res, true);
    return count($subscriptions) !== 0;
}

if (ExchangeCodeForToken($clientId, $clientSecret, $allowedUserId))
{
    if (!SubscriptionExists($clientId, $clientSecret))
    {
        CreateSubscription($clientId, $clientSecret);
    }
}

?>
