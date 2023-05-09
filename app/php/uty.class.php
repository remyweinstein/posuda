<?php
class UTY {
    public static function fact($n) {
        if ($n <= 0) return 1;
        return $n * self::fact($n-1);
    }

    public static function urlPrepare($REQUEST_URI) {
        $url = mb_strtolower($REQUEST_URI, 'UTF-8');
        $url = str_replace("'", "", $url);
        $url = strip_tags($url);
        $url = stripslashes($url);
        $url = trim($url);
        $url = substr($url, 1);
        $endchar = substr($url, -1);
        if ($endchar == "/") $url = substr($url, 0, strlen($url) - 1);
        $url = explode("?",$url)[0];

        return $url;
    }

    public static function getRandomChars($count, $chars = "1234567890QWERTYUIOPASDFGHJKLZXCVBNM") {
        $randomChars = "";
        for ($i = 0; $i < $count; $i++) { $randomChars .= substr($chars, rand(1, strlen($chars)) - 1, 1); }

        return $randomChars;
    }

    public static function setCORS() {
        $http_origin = $_SERVER['HTTP_ORIGIN'];
        $origins = [
            "http://localhost",
            "https://localhost",
            "http://localhost:3000",
            "https://stolica-dev.ru",
            "https://bonus.stolica-dv.ru",
            "http://promo.stolica-dv.ru"
        ];
        if (in_array($http_origin, $origins)) header("Access-Control-Allow-Origin: $http_origin");
        header('Access-Control-Allow-Methods: POST');
        header("Access-Control-Allow-Headers: X-Requested-With");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                header("Access-Control-Allow-Methods: POST, OPTIONS");

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            exit(0);
        }
    }
}
?>