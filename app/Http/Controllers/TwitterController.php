<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Service\ThreadService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwitterController extends Controller
{

    public function addThread(Request $request): JsonResponse|array
    {
        $input = $request->input('url') ?: $request->input('id');

        $refresh = $request->input('refresh');

        $tweet_id = "";
        if (is_numeric($input)) {
            $tweet_id = $input;
        } else {
            $tweet_id = ThreadService::getTweetID($input);
        }

        if (!is_numeric($tweet_id)) {
            return response()->json(['error' => 'Invalid tweet ID or URL'], 400);
        }

        $thread = Thread::with('author')->where('thread_id', $tweet_id)->first();

        if ($thread && !$refresh) {
            // $thread->toArray();
            return response()->json(['thread_id' => $thread['thread_id'], 'success' => true]);

        } else {
            $thread = new ThreadService();

            try {
                $thread = $thread->formatThread($tweet_id, $refresh);

                return response()->json(['thread_id' => $thread['thread_id'], 'success' => true]);
            } catch (\Exception|GuzzleException $e) {
                Log::error("Thread ID: {$tweet_id}::{$e->getMessage()}");
                return response()->json(['error' => substr($e->getMessage(), 0, 300)], 400);
            }
        }
    }

}
