<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentComment;
use App\Models\Content\ContentPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialInboxController extends Controller
{
    public function index()
    {
        return view('content-planner.inbox', [
            'platforms' => config('content-planner.platforms'),
        ]);
    }

    public function fetchComments(Request $request)
    {
        $query = ContentComment::with(['user', 'post', 'replies.user'])
            ->whereNull('parent_id')
            ->orderByDesc('created_at');

        if ($request->filled('platform')) {
            $query->where('external_platform', $request->platform);
        }

        if ($request->filled('source')) {
            if ($request->source === 'external') {
                $query->whereNotNull('external_id');
            } elseif ($request->source === 'internal') {
                $query->whereNull('external_id');
            }
        }

        if ($request->filled('post_id')) {
            $query->where('content_post_id', $request->post_id);
        }

        if ($request->filled('search')) {
            $query->where('body', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }

        $comments = $query->paginate(20);

        return response()->json([
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function syncPlatformComments(Request $request)
    {
        $platform = $request->input('platform', 'all');
        $synced = 0;

        $posts = ContentPost::where('status', 'published')
            ->whereNotNull('platform_post_id')
            ->when($platform !== 'all', fn ($q) => $q->where('platform', $platform))
            ->get();

        foreach ($posts as $post) {
            try {
                $comments = $this->fetchPlatformComments($post);
                foreach ($comments as $comment) {
                    ContentComment::updateOrCreate(
                        ['external_id' => $comment['id']],
                        [
                            'content_post_id' => $post->id,
                            'body' => $comment['text'],
                            'external_platform' => $comment['platform'],
                            'external_author' => $comment['author_name'],
                            'external_author_avatar' => $comment['author_avatar'] ?? null,
                            'is_internal' => false,
                            'created_at' => $comment['created_at'] ?? now(),
                        ]
                    );
                    $synced++;
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to sync comments for post', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['synced' => $synced]);
    }

    public function replyToComment(Request $request, ContentComment $comment)
    {
        $request->validate(['body' => 'required|string|max:2000']);

        // Attempt platform API reply if external comment
        if ($comment->external_id && $comment->external_platform) {
            try {
                $this->sendPlatformReply($comment, $request->body);
            } catch (\Throwable $e) {
                Log::warning('Platform reply failed', ['comment_id' => $comment->id, 'error' => $e->getMessage()]);
            }
        }

        $reply = ContentComment::create([
            'content_post_id' => $comment->content_post_id,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'parent_id' => $comment->id,
            'is_internal' => false,
        ]);

        return response()->json($reply->load('user'));
    }

    protected function fetchPlatformComments(ContentPost $post): array
    {
        $comments = [];

        if ($post->platform === 'facebook' || $post->platform === 'multi') {
            $token = config('services.facebook.page_access_token');
            if ($token && $post->platform_post_id) {
                try {
                    $response = Http::get("https://graph.facebook.com/v21.0/{$post->platform_post_id}/comments", [
                        'access_token' => $token,
                        'fields' => 'id,message,from,created_time',
                    ]);
                    if ($response->ok()) {
                        foreach ($response->json('data', []) as $c) {
                            $comments[] = [
                                'id' => 'fb_' . $c['id'],
                                'text' => $c['message'] ?? '',
                                'platform' => 'facebook',
                                'author_name' => $c['from']['name'] ?? 'Facebook User',
                                'author_avatar' => null,
                                'created_at' => $c['created_time'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('FB comment fetch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        if ($post->platform === 'instagram' || $post->platform === 'multi') {
            $token = config('services.instagram.access_token');
            if ($token && $post->platform_post_id) {
                try {
                    $response = Http::get("https://graph.facebook.com/v21.0/{$post->platform_post_id}/comments", [
                        'access_token' => $token,
                        'fields' => 'id,text,username,timestamp',
                    ]);
                    if ($response->ok()) {
                        foreach ($response->json('data', []) as $c) {
                            $comments[] = [
                                'id' => 'ig_' . $c['id'],
                                'text' => $c['text'] ?? '',
                                'platform' => 'instagram',
                                'author_name' => $c['username'] ?? 'Instagram User',
                                'author_avatar' => null,
                                'created_at' => $c['timestamp'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('IG comment fetch failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $comments;
    }

    protected function sendPlatformReply(ContentComment $comment, string $replyText): void
    {
        if ($comment->external_platform === 'facebook') {
            $externalId = str_replace('fb_', '', $comment->external_id);
            Http::post("https://graph.facebook.com/v21.0/{$externalId}/comments", [
                'access_token' => config('services.facebook.page_access_token'),
                'message' => $replyText,
            ]);
        }

        if ($comment->external_platform === 'instagram') {
            $externalId = str_replace('ig_', '', $comment->external_id);
            Http::post("https://graph.facebook.com/v21.0/{$externalId}/replies", [
                'access_token' => config('services.instagram.access_token'),
                'message' => $replyText,
            ]);
        }
    }
}
