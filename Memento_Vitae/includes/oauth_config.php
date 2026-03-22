<?php

if (!function_exists("oauthEnvValue")) {
    function oauthEnvValue($key, $default = null) {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!defined("GOOGLE_CLIENT_ID")) {
    define("GOOGLE_CLIENT_ID", oauthEnvValue("GOOGLE_CLIENT_ID", "38699692232-b5f5esi4a679434hg8jqb5ghod2nb1aj.apps.googleusercontent.com"));
}

?>
