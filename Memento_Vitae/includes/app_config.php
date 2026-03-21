<?php

if (!function_exists("envValue")) {
    function envValue($key, $default = null) {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!defined("APP_URL")) {
    define("APP_URL", envValue("APP_URL", "http://localhost/DWEB/Memento_Vitae"));
}

if (!defined("MAIL_DELIVERY_MODE")) {
    define("MAIL_DELIVERY_MODE", envValue("MAIL_DELIVERY_MODE", "smtp"));
}

if (!defined("MAIL_HOST")) {
    define("MAIL_HOST", envValue("MAIL_HOST", "smtp.gmail.com"));
}

if (!defined("MAIL_PORT")) {
    define("MAIL_PORT", (int)envValue("MAIL_PORT", 587));
}

if (!defined("MAIL_USERNAME")) {
    define("MAIL_USERNAME", envValue("MAIL_USERNAME", "adminmemento@gmail.com"));
}

if (!defined("MAIL_PASSWORD")) {
    define("MAIL_PASSWORD", envValue("MAIL_PASSWORD", "raaw rbwm wsih ekcr"));
}

if (!defined("MAIL_FROM_EMAIL")) {
    define("MAIL_FROM_EMAIL", envValue("MAIL_FROM_EMAIL", "adminmemento@gmail.com"));
}

if (!defined("MAIL_FROM_NAME")) {
    define("MAIL_FROM_NAME", envValue("MAIL_FROM_NAME", "Memento Vitae"));
}

if (!defined("MAIL_ENCRYPTION")) {
    define("MAIL_ENCRYPTION", envValue("MAIL_ENCRYPTION", "tls"));
}

if (!defined("MAIL_ALLOW_INSECURE")) {
    define("MAIL_ALLOW_INSECURE", filter_var(envValue("MAIL_ALLOW_INSECURE", false), FILTER_VALIDATE_BOOLEAN));
}

if (!defined("MAIL_CONFIGURED")) {
    define(
        "MAIL_CONFIGURED",
        trim((string)MAIL_USERNAME) !== ""
        && trim((string)MAIL_PASSWORD) !== ""
    );
}

if (!defined("LCR_REQUEST_EMAIL")) {
    define("LCR_REQUEST_EMAIL", envValue("LCR_REQUEST_EMAIL", "lcrtestingdweb@gmail.com"));
}

if (!defined("LCR_REQUEST_NAME")) {
    define("LCR_REQUEST_NAME", envValue("LCR_REQUEST_NAME", "Local Civil Registry"));
}

if (!defined("CONTACT_FORM_RECIPIENT_EMAIL")) {
    define("CONTACT_FORM_RECIPIENT_EMAIL", envValue("CONTACT_FORM_RECIPIENT_EMAIL", "adminmemento@gmail.com"));
}

if (!defined("CONTACT_FORM_RECIPIENT_NAME")) {
    define("CONTACT_FORM_RECIPIENT_NAME", envValue("CONTACT_FORM_RECIPIENT_NAME", "Memento Vitae"));
}

?>
