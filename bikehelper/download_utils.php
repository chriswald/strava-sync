<?php

function Login()
{
    $config = json_decode(file_get_contents(__DIR__."/config.json"), true);
    $email = $config["Email"];
    $pass = $config["Password"];

    $result = GetLoginCsrf();
    $csrf = $result["csrf"];
    $loginCookies = $result["cookies"];
    $stravaSessionCookieVal = $loginCookies["_strava4_session"];

    $postInfo = array(
        "email" => $email,
        "password" => $pass,
        "remember_me" => "on",
        $csrf["csrf_param"] => $csrf["csrf_token"],
        "utf8" => "%E2%9C%93"
    );

    $sessionCookies = [];

    $sessionUrl = "https://www.strava.com/session";

    $headers = [];
    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $sessionUrl);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true);
    curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($postInfo));
    curl_setopt($req, CURLOPT_HTTPHEADER, array(
        "Cookie: _strava4_session=$stravaSessionCookieVal"
    ));
    curl_setopt($req, CURLOPT_HEADERFUNCTION, 
        function($curl, $header) use (&$sessionCookies)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $hname = strtolower(trim($header[0]));
            $hvalue = trim($header[1]);

            if ($hname === "set-cookie")
            {
                $cookie = $hvalue;
                $arr = CookieNameAndValue($cookie);
                $sessionCookies[$arr["name"]] = $arr["value"];
            }

            return $len;
        });
    $res = curl_exec($req);
    curl_close($req);

    return array(
        "cookies" => $sessionCookies
    );
}

function GetLoginCsrf()
{
    $loginUrl = "https://www.strava.com/login";
    $cookies = [];

    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $loginUrl);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HEADERFUNCTION, 
        function($curl, $header) use (&$cookies)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $hname = strtolower(trim($header[0]));
            $hvalue = trim($header[1]);

            if ($hname === "set-cookie")
            {
                $cookie = $hvalue;
                $arr = CookieNameAndValue($cookie);
                $cookies[$arr["name"]] = $arr["value"];
            }

            return $len;
        });
    $res = curl_exec($req);
    curl_close($req);

    $dom = new DOMDocument;
    $dom->loadHTML($res);
    $domxpath = new DOMXPath($dom);
    $csrf_param = $domxpath->query("//meta[@name='csrf-param']")->item(0)->attributes->getNamedItem("content")->value;
    $csrf_token = $domxpath->query("//meta[@name='csrf-token']")->item(0)->attributes->getNamedItem("content")->value;

    return array(
        "csrf" => array(
            "csrf_param" => $csrf_param,
            "csrf_token" => $csrf_token
        ),
        "cookies" => $cookies
    );
}

function RefreshAccessToken()
{
    $config = json_decode(file_get_contents(__DIR__."/config.json"), true);
    $clientId = $config["ClientID"];
    $clientSecret = $config["ClientSecret"];
    
    $auth = json_decode(file_get_contents(__DIR__."/auth.json"), true);
    $refreshToken = $auth["refresh_token"];

    // exhange code for auth token
    $url = "https://www.strava.com/oauth/token?client_id=" . $clientId . "&client_secret=" . $clientSecret . "&refresh_token=" . $refreshToken . "&grant_type=refresh_token";
    
    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true);
    $res = curl_exec($req);
    
    $status = curl_getinfo($req, CURLINFO_HTTP_CODE);
	
    curl_close($req);

    if ($status == 200 || $status == "")
    {
        $auth = json_decode($res, true);

        if (!array_key_exists("refresh_token", $auth))
        {
            // The refresh was unnecessary so keep the original
            $auth["refresh_token"] = $refreshToken;
        }

        file_put_contents(__DIR__."/auth.json", json_encode($auth));
        return true;
    }
    else
    {
        print_r($res);
        return false;
    }
}

function DownloadActivity($activityId, $sessionCookies)
{
	$config = json_decode(file_get_contents(__DIR__."/config.json"), true);
	$outDir = $config["OutDir"];
	
    $headers = [];

    $url = "https://www.strava.com/activities/$activityId/export_original";

    $stravaRememberToken = $sessionCookies["cookies"]["strava_remember_token"];
    $stravaRememberID = $sessionCookies["cookies"]["strava_remember_id"];
    $stravaSession = $sessionCookies["cookies"]["_strava4_session"];
    $cookieHeader = "Cookie: strava_remember_token=$stravaRememberToken; strava_remember_id=$stravaRememberID; _strava4_session=$stravaSession;";
    
    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array(
        $cookieHeader
    ));
    curl_setopt($req, CURLOPT_HEADERFUNCTION, 
        function($curl, $header) use (&$headers)
        {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $hname = strtolower(trim($header[0]));
            $hvalue = trim($header[1]);

            $headers[$hname] = $hvalue;

            return $len;
        });

    $res = curl_exec($req);

    $status = curl_getinfo($req, CURLINFO_HTTP_CODE);
    if ($status !== 200)
    {
        echo "no 200 - $status";
        die();
    }

    $activity = GetActivity($activityId);

    if (!VerifyActivityOwner($activity))
    {
        http_response_code(403);
        die();
    }

    $startTime = strtotime(ActivityStartTime($activity));
    $timeStr = date("Y-m-d-His", $startTime);
    $filename = "$activityId-$timeStr.fit";

    if ($filename !== "")
    {
        echo "Saving file $filename<br>\n";
        file_put_contents($outDir."/".$filename, $res);
    }
}

function GetActivity($activityId)
{
    $auth = json_decode(file_get_contents(__DIR__."/auth.json"), true);
    $accessToken = $auth["access_token"];

    $url = "https://www.strava.com/api/v3/activities/$activityId";

    $req = curl_init();
    curl_setopt($req, CURLOPT_URL, $url);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer $accessToken"
    ));
    $res = curl_exec($req);
    curl_close($req);

    return json_decode($res, true);
}

function GetActivityList()
{
    $auth = json_decode(file_get_contents(__DIR__."/auth.json"), true);
    $accessToken = $auth["access_token"];

    $page = 0;
    $numActivitiesOnPage = 0;

    $activityIds = [];

    $req = curl_init();
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer $accessToken"
    ));

    do
    {
        $page ++;
        $numActivitiesOnPage = 0;

        $url = "https://www.strava.com/api/v3/athlete/activities?page=$page&per_page=100";
        curl_setopt($req, CURLOPT_URL, $url);
        $res = curl_exec($req);
        
        $status = curl_getinfo($req, CURLINFO_HTTP_CODE);

        if ($status == 200)
        {
            $activities = json_decode($res, true);
    
            foreach ($activities as $activity)
            {
                $numActivitiesOnPage ++;
                array_push($activityIds, $activity["id"]);
            }
        }
        else
        {
            echo $res;
            curl_close($req);
            return [];
        }
    } while ($numActivitiesOnPage !== 0);

    curl_close($req);
    
    return $activityIds;
}

function ActivityStartTime($activity)
{
    return $activity["start_date"];
}

function VerifyActivityOwner($activity)
{
    $config = json_decode(file_get_contents(__DIR__."/config.json"), true);
    $allowedUserId = $config["AllowedUserID"];

    return strval($activity["athlete"]["id"]) === $allowedUserId;
}

function startsWith($string, $query)
{
    return (substr($string, 0, strlen($query)) === $query);
}

function endsWith($string, $query)
{
    $thislen = strlen($string);
    $thatlen = strlen($query);
    return (substr($string, $thislen - $thatlen) === $query);
}

function CookieNameAndValue($cookie)
{
    $cookie = explode("=", $cookie, 2);
    $cname = trim($cookie[0]);
    $cvalue = trim($cookie[1]);
    $valuePieces = explode(";", $cvalue, 2);
    $value = $valuePieces[0];

    return array(
        "name" => $cname,
        "value" => $value
    );
}

?>
