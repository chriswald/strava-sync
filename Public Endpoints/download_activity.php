<?php

include(__DIR__."/../bikehelper/download_utils.php");

$activityId = $_GET["activity"];

$sessionCookies = Login();
RefreshAccessToken();
DownloadActivity($activityId, $sessionCookies);

?>
