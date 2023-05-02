<?php

namespace App\Service;

trait UtilityService
{


    function stripShortLinks($text_input): string
    {
        $match_links = "/(https?):(\\/\\/t\\.co\\/([A-Za-z0-9]|[A-Za-z]){10})/";
        return preg_replace($match_links, "", $text_input);
    }

    function stripLinks($text_input): string
    {
        $match_links = "/(https?|ftp|file):\\/\\/[\\-a-zA-Z0-9+&@#\\/%?=~_|!:,.;]*[\\-a-zA-Z0-9+&@#\\/%=~_|]/";
        return preg_replace($match_links, "", $text_input);
    }

    function cleanTweet($text): string
    {
        return $this->stripShortLinks($text);
    }

    function formatTweetEmbed($tweet_link): string
    {
        return "<div class='quote'>{$tweet_link}</div>";
    }

    function transformGifLinks($text): string
    {
        $url_validation_regex = "/(.\\/)*.+\\.([Mm][Pp]4|[3Gg][Pp]|[Gg][Ii][Ff])/";
        preg_match_all($url_validation_regex, $text, $matches);
        $sb = [];

        foreach ($matches[0] as $found) {
            $sb[] = "<video class='gif' src='{$found}' controls></video>";
        }

        return implode('', $sb);
    }

    function transformVideoLinks($text): string
    {
        $url_validation_regex = "/(.\\/)*.+\\.([Mm][Pp]4|[3Gg][Pp]|[Gg][Ii][Ff])/";
        preg_match_all($url_validation_regex, $text, $matches);
        $sb = [];
        foreach ($matches[0] as $found) {
            $sb[] = "<video class='vid' src='{$found}' controls></video>";
        }
        return implode('', $sb);
    }

    function transformImageLinks($text): string
    {
        $url_validation_regex = "/(.\\/)*.+\\.([Pp][Nn][Gg]|[Jj][Pp][Ee]?[Gg])/";
        preg_match_all($url_validation_regex, $text, $matches);
        $sb = [];
        foreach ($matches[0] as $found) {
            $sb[] = "<img src='{$found}'/>";
        }
        return implode('', $sb);
    }

    function getMaxVideoVariant($bitrate_list, $bitrate)
    {
        $bitrate_list[] = $bitrate;
        return max($bitrate_list);
    }

    public static function getTweetID($url)
    {
        $twitter_post_regex = '/^https?:\/\/((mobile\.)|(web\.))?twitter\.com\/(\w+)\/status\/(\d+)/i';
        preg_match($twitter_post_regex, $url, $matches);
        return $matches[5];
    }

}
