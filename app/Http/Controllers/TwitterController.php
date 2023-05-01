<?php

namespace App\Http\Controllers;

use App\Service\ThreadService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwitterController extends Controller
{


    public function addThread(Request $request)
    {
        $input = $request->input('url') ?: $request->input('id');
        $tweet_id = "";

        if (is_numeric($input)) {
            $tweet_id = $input;
        } else {
            $tweet_id = $this->getTweetID($input);
        }

        if (!is_numeric($tweet_id)) {
            return response()->json(['error' => 'Invalid tweet ID or URL'], 400);
        }

        $thread = new ThreadService();
        // if available on db fetch it.
        // if not query the api
        // save response to db

        try {
            return $thread->formatThread($tweet_id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    function getTweetID($url)
    {
        $twitter_post_regex = '/^https?:\/\/((mobile\.)|(web\.))?twitter\.com\/(\w+)\/status\/(\d+)/i';
        preg_match($twitter_post_regex, $url, $matches);
        return $matches[5];
    }
}
