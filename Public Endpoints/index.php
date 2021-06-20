<?php
    $config = json_decode(file_get_contents(__DIR__."/../bikehelper/config.json"), true);
    $clientID = $config["ClientID"];

    $redirectUrl = "https://bike.chriswald.com/exchange_token.php";
    $url = "https://www.strava.com/oauth/authorize?client_id=" . $clientID . "&response_type=code&redirect_uri=" . urlencode($redirectUrl) . "&approval_prompt=force&scope=read,activity:read_all";
    $metaRefreshContent = "\"0; " . $url . "\"";
?>

<!DOCTYPE html>

<head>
    <meta http-equiv="refresh" content=<?php echo $metaRefreshContent; ?>>
</head>
