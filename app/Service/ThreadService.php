<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ThreadService
{

    function getAuthHeaders(): array
    {
        $url = "https://api.twitter.com/1.1/guest/activate.json";
        $bearer = config('settings.twitter_bearer_token');
        $headers = [
            "Authorization" => "Bearer {$bearer}"
        ];

        try {
            $client = new Client();
            $response = $client->post($url, ['headers' => $headers]);
            $json = json_decode($response->getBody(), true);

            return [
                "Authorization" => "Bearer {$bearer}",
                "X-Guest-Token" => $json['guest_token'],
            ];
        } catch (\Exception $e) {
            $message = "Error in getAuthHeaders: " . $e->getMessage();
            Log::error($message);
            return [];
        }
    }

    /**
     * @throws \Exception|GuzzleException
     */
    function getThreadById($tweet_id, $cursor = null)
    {
        $headers = $this->getAuthHeaders();
        if (empty($headers)){
            throw new \Exception("Twitter authorization failed.", 401);
        }

        $url = "https://api.twitter.com/graphql/BbCrSoXIR7z93lLCVFlQ2Q/TweetDetail";

        $variables = [
            "focalTweetId" => $tweet_id,
            "referrer" => "messages",
            "with_rux_injections" => false,
            "includePromotedContent" => true,
            "withCommunity" => true,
            "withQuickPromoteEligibilityTweetFields" => true,
            "withBirdwatchNotes" => true,
            "withVoice" => true,
            "withV2Timeline" => true,
        ];

        if ($cursor !== null) {
            $variables["cursor"] = $cursor;
        }

        $features = [
            "blue_business_profile_image_shape_enabled" => true,
            "responsive_web_graphql_exclude_directive_enabled" => true,
            "verified_phone_label_enabled" => false,
            "responsive_web_graphql_timeline_navigation_enabled" => true,
            "responsive_web_graphql_skip_user_profile_image_extensions_enabled" => false,
            "tweetypie_unmention_optimization_enabled" => true,
            "vibe_api_enabled" => true,
            "responsive_web_edit_tweet_api_enabled" => true,
            "graphql_is_translatable_rweb_tweet_is_translatable_enabled" => true,
            "view_counts_everywhere_api_enabled" => true,
            "longform_notetweets_consumption_enabled" => true,
            "tweet_awards_web_tipping_enabled" => false,
            "freedom_of_speech_not_reach_fetch_enabled" => true,
            "standardized_nudges_misinfo" => true,
            "tweet_with_visibility_results_prefer_gql_limited_actions_policy_enabled" => false,
            "interactive_text_enabled" => true,
            "responsive_web_text_conversations_enabled" => false,
            "longform_notetweets_rich_text_read_enabled" => true,
            "responsive_web_enhance_cards_enabled" => false,
        ];

        $url = "{$url}?variables=" . urlencode(json_encode($variables)) . "&features=" . urlencode(json_encode($features));

        $client = new Client();

        try {
            $response = $client->get($url, ['headers' => $headers]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = "getThreadById - Error: " . $e->getMessage();
            Log::error($message);
            return null;
        }

    }

    /**
     * @throws \Exception
     */
    function compileThread($tweet_data): array
    {
        if (!isset($tweet_data['data']['threaded_conversation_with_injections_v2']['instructions'][0]['entries'])) {
            throw new \Exception("Unable to read thread.", 1000);
        }

        $entries = $tweet_data['data']['threaded_conversation_with_injections_v2']['instructions'][0]['entries'];

        $thread_tweets = [];
        $main_tweet = $entries[0]['content']['itemContent'];
        $main_tweet_id = $entries[0]['content']['itemContent']['tweet_results']['result']['rest_id'];

        $tweet_type = $entries[0]['content']['itemContent']['tweetDisplayType'];

        if ($tweet_type != 'SelfThread') {
            throw new \Exception("This is not a thread.", 1001);
        }

        $thread_tweets[] = $main_tweet;

        if (!isset($entries[1]['content']['items'])) {
            throw new \Exception("This is not the first tweet of the thread.", 1002);
        }

        $tweet_children = $entries[1]['content']['items'];

        foreach ($tweet_children as $tweet) {
            if (str_starts_with($tweet['entryId'], 'conversationthread-')
                && $tweet['item']['itemContent']['itemType'] !== 'TimelineTimelineCursor') {
                $thread_tweets[] = $tweet['item']['itemContent'];
            }

            if (str_contains($tweet['entryId'], 'cursor')) {
                $cursor = $tweet['item']['itemContent']['value'];
                $new_tweets = $this->traverseThreadCursor($main_tweet_id, $cursor);
                $thread_tweets = array_merge($thread_tweets, $new_tweets);
            }
        }

        return $thread_tweets;
    }

    function traverseThreadCursor($tweet_id, $cursor): array
    {
        $tweet_data = $this->getThreadById($tweet_id, $cursor);
        $thread_tweets = [];

        if (isset($tweet_data['data']['threaded_conversation_with_injections_v2']['instructions'][0]['moduleItems'])) {
            $items = $tweet_data['data']['threaded_conversation_with_injections_v2']['instructions'][0]['moduleItems'];
            foreach ($items as $tweet) {
                if (str_starts_with($tweet['entryId'], 'conversationthread-')
                    && $tweet['item']['itemContent']['itemType'] !== 'TimelineTimelineCursor') {
                    $thread_tweets[] = $tweet['item']['itemContent'];
                }

                if (str_contains($tweet['entryId'], 'cursor')) {
                    $next_cursor = $tweet['item']['itemContent']['value'];
                    $new_tweets = $this->traverseThreadCursor($tweet_id, $next_cursor);
                    $thread_tweets = array_merge($thread_tweets, $new_tweets);
                }
            }
        }

        return $thread_tweets;
    }

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

    /**
     * @throws \Exception|GuzzleException
     */
    function formatThread($thread_id)
    {
        $tweet_data = $this->getThreadById($thread_id);
        $thread_tweets = $this->compileThread($tweet_data);

        $merged_contents = [];
        $hashtags = [];

        foreach ($thread_tweets as $tweet) {
            $tweet_result = $tweet['tweet_results']['result'];

            $tweet_id = $tweet_result['legacy']['id_str'];
            $tweet_text = $tweet_result['legacy']['full_text'];

            $entities = $tweet_result['legacy']['entities'] ?? [];
            $extended_entities = $tweet_result['legacy']['extended_entities'] ?? [];

            $url_entities = $entities['urls'] ?? [];
            $hashtag_entities = $entities['hashtags'] ?? [];

            if (empty($url_entities)) {
                $tweet_text = $this->stripLinks($tweet_text);
            } else {
                $links_in_tweet = [];

                foreach ($url_entities as $entity) {
                    $links_in_tweet[$entity['url']] = $entity['expanded_url'];
                }

                foreach ($links_in_tweet as $short_link => $expanded_link) {
                    if (str_starts_with($expanded_link, "https://twitter.com/") && str_contains($expanded_link, "/status/")) {
                        $tweet_text = str_replace($short_link, "", $tweet_text);
                    } else {
                        $tweet_text = str_replace($short_link, $expanded_link, $tweet_text);
                    }
                }
            }

            $merge_content = (
                "<div class='thread-part' data-tweet-id='{$tweet_id}'>\n" .
                "<p class='tweet-text'>\n" .
                str_replace("\n", "\n",
                    $this->stripShortLinks(str_replace("\n\n\n", "\n<br>", $tweet_text))) .
                "\n</p>"
            );

            foreach ($url_entities as $url) {
                if (str_starts_with($url['expanded_url'], "https://twitter.com/") && (
                        str_contains($url['expanded_url'], "/status/") || str_contains($url['expanded_url'], "/i/"))) {
                    $merge_content .= "\n" . $this->formatTweetEmbed($url['expanded_url']);
                }
            }

            if (!empty($hashtag_entities)) {
                $hashtag_list = array_map(function ($hashtag) {
                    return strtolower($hashtag['text']);
                }, $hashtag_entities);

                $hashtags[] = $hashtag_list;
            }

            if (!empty($extended_entities)) {
                $media_list = $extended_entities['media'] ?? [];

                foreach ($media_list as $media) {
                    $media_type = $media['type'] ?? null;

                    if ($media_type === 'video') {
                        $video_info = $media['video_info'] ?? [];
                        $variants = $video_info['variants'] ?? [];

                        $bitrate_list = [];
                        $video_url = "";

                        foreach ($variants as $variant) {
                            if ($variant['content_type'] === 'video/mp4') {
                                $bitrate = $variant['bitrate'] ?? 0;
                                $bitrate_list[] = $bitrate;
                                $max_bitrate = max($bitrate_list);
                                if ($bitrate === $max_bitrate) {
                                    $video_url = explode('?', $variant['url'])[0];
                                }
                            }
                        }

                        $merge_content .= "\n" . $this->transformVideoLinks($video_url);

                    } elseif ($media_type === 'animated_gif') {
                        $video_info = $media['video_info'] ?? [];
                        $variants = $video_info['variants'] ?? [];

                        $bitrate_list = [];
                        $gif_url = "";

                        foreach ($variants as $variant) {
                            if ($variant['content_type'] === 'video/mp4') {
                                $bitrate = $variant['bitrate'] ?? 0;
                                $bitrate_list[] = $bitrate;
                                $max_bitrate = max($bitrate_list);
                                if ($bitrate === $max_bitrate) {
                                    $gif_url = explode('?', $variant['url'])[0];
                                }
                            }
                        }

                        $merge_content .= "\n" . $this->transformGifLinks($gif_url);

                    } elseif ($media_type === 'photo') {
                        $media_url_https = $media['media_url_https'] ?? '';
                        $merge_content .= "\n" . $this->transformImageLinks($media_url_https);
                    }
                }
            }

            $merge_content .= "\n</div>\n";
            $merged_contents[] = $merge_content;
        }

        $hashtags = array_unique(array_merge(...$hashtags));

        $thread_info = $thread_tweets[0]['tweet_results']['result'];
        $user_info = $thread_info['core']['user_results']['result']['legacy'];

        $user_info_array = [
            'author_id' => $thread_info['legacy']['user_id_str'],
            'author_username' => $user_info['screen_name'],
            'author_name' => $user_info['name'],
            'author_bio' => $user_info['description'],
            'author_photo' => str_replace('_normal', '', $user_info['profile_image_url_https']),
            'verified' => $user_info['verified']
        ];

        $thread_info_array = [
            'thread_id' => $thread_info['legacy']['id_str'],
            'thread_html' => implode("\n", $merged_contents),
            'thread_snippet' => substr($this->stripLinks($thread_info['legacy']['full_text']), 0,280),
            'thread_count' => count($thread_tweets),
            'thread_hashtags' => implode(', ', $hashtags),
            'thread_lang' => $thread_info['legacy']['lang'],
            'thread_date' => strtotime($thread_info['legacy']['created_at'])
        ];

        $merged_info = array_merge($user_info_array, $thread_info_array);

        return $merged_info;
    }

}