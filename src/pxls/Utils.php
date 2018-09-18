<?php
namespace pxls;


class Utils {
    public static function MakeUserLoginURL($login, $returnWithData=false) {
        $replacers = [
            "reddit" => "https://reddit.com/u/%%LOGIN",
            "google" => "https://plus.google.com/%%LOGIN",
            "discord" => "javascript:askDiscord('%%LOGIN');",
            "tumblr" => "https://%%LOGIN.tumblr.com/"
        ];
        $toRet = "#";

        $splitPos = strpos($login, ":");
        $loginData = [
            "service" => substr($login, 0, $splitPos),
            "ID" => substr($login, $splitPos+1)
        ];
        if (array_key_exists($loginData["service"], $replacers)) {
           $toRet = str_replace("%%LOGIN", $loginData["ID"], $replacers[$loginData["service"]]);
        }

        if ($returnWithData === true) {
            return [
                "service" => $loginData["service"],
                "ID" => $loginData["ID"],
                "URL" => $toRet
            ];
        }

        return $toRet;
    }
}