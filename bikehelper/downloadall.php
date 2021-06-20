<?php
include(__DIR__."/download_utils.php");

$activityIds = GetActivityList();
echo count($activityIds) . "\n";

$sessionCookies = Login();
RefreshAccessToken();

foreach ($activityIds as $id)
{
    DownloadActivity($id, $sessionCookies);
    sleep(10); // rate limit to 100 requests per 15 minutes
}

?>
