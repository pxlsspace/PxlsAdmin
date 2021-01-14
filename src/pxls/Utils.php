<?php
namespace pxls;


class Utils {
    public static function MakeUserLoginURL($login) {
        $replacers = [
            "reddit" => "https://reddit.com/u/%%LOGIN",
            "google" => "https://plus.google.com/%%LOGIN",
            "discord" => "javascript:askDiscord('%%LOGIN');",
            "tumblr" => "https://%%LOGIN.tumblr.com/",
            "vk" => "https://vk.com/id%%LOGIN"
        ];
        $toRet = "#";

        if (array_key_exists($login["service"], $replacers)) {
           $toRet = str_replace("%%LOGIN", $login["service_uid"], $replacers[$login["service"]]);
        }

        return $toRet;
    }
}
