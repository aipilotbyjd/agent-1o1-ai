<?php

use App\Models\Runs\Run;
use App\Nodes\Integrations\YouTube\YouTubeGetChannelNode;
use App\Nodes\Integrations\YouTube\YouTubeGetVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeListCommentsNode;
use App\Nodes\Integrations\YouTube\YouTubeListPlaylistVideosNode;
use App\Nodes\Integrations\YouTube\YouTubeSearchVideosNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const YT_BASE = ['access_token' => 'ya29-test'];

it('registers every youtube node under the google_youtube category', function () {
    foreach (['search_videos', 'get_video', 'get_channel', 'list_playlist_videos', 'list_comments'] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_youtube_{$suffix}")->category())->toBe('google_youtube');
    }
});

it('searches videos and flattens each result', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => ['kind' => 'youtube#video', 'videoId' => 'abc123XYZ_-'],
        'snippet' => ['title' => 'Intro', 'channelId' => 'UC1', 'channelTitle' => 'Ch', 'publishedAt' => '2026-01-01T00:00:00Z', 'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/x.jpg']]],
    ]]])]);

    $output = (new YouTubeSearchVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'query' => 'laravel', 'order' => 'date', 'channel_id' => 'UC1'], []);

    expect($output['videos'][0])->toBe([
        'video_id' => 'abc123XYZ_-',
        'title' => 'Intro',
        'channel_id' => 'UC1',
        'channel_title' => 'Ch',
        'published_at' => '2026-01-01T00:00:00Z',
        'thumbnail' => 'https://i.ytimg.com/x.jpg',
        'url' => 'https://www.youtube.com/watch?v=abc123XYZ_-',
    ]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/youtube/v3/search')
        && str_contains($request->url(), 'type=video') && str_contains($request->url(), 'order=date') && str_contains($request->url(), 'channelId=UC1'));
});

it('reads a video by link with its counts', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'dQw4w9WgXcQ',
        'snippet' => ['title' => 'Song', 'channelId' => 'UC1'],
        'contentDetails' => ['duration' => 'PT3M33S'],
        'statistics' => ['viewCount' => '100', 'likeCount' => '10', 'commentCount' => '2'],
    ]]])]);

    $output = (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5s'], []);

    expect($output)->toMatchArray(['video_id' => 'dQw4w9WgXcQ', 'duration' => 'PT3M33S', 'views' => 100, 'likes' => 10, 'comments' => 2]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=dQw4w9WgXcQ'));
});

it('reads a video from a short link', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [['id' => 'dQw4w9WgXcQ', 'snippet' => []]]])]);

    (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'https://youtu.be/dQw4w9WgXcQ?si=x'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=dQw4w9WgXcQ'));
});

it('fails clearly when a video does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'nope'], []);
})->throws(RuntimeException::class, 'no video found');

it('reads the connected account\'s own channel when none is given', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'UC1', 'snippet' => ['title' => 'Me', 'customUrl' => '@me'],
        'statistics' => ['subscriberCount' => '1200', 'videoCount' => '30', 'viewCount' => '99999'],
        'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1']],
    ]]])]);

    $output = (new YouTubeGetChannelNode)->execute(Run::factory()->create(), YT_BASE, []);

    expect($output)->toMatchArray(['channel_id' => 'UC1', 'handle' => '@me', 'subscribers' => 1200, 'videos' => 30, 'views' => 99999, 'uploads_playlist_id' => 'UU1', 'url' => 'https://www.youtube.com/channel/UC1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'mine=true') && ! str_contains($request->url(), 'forHandle'));
});

it('looks a channel up by handle or by id', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [['id' => 'UC2', 'snippet' => []]]])]);

    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => '@laravel'], []);
    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UC2'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'forHandle=%40laravel') && ! str_contains($request->url(), 'mine='));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=UC2'));
});

it('fails clearly when a channel does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response([])]);

    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UCnone'], []);
})->throws(RuntimeException::class, 'no channel found');

it('lists a playlist\'s videos', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'snippet' => ['title' => 'Ep 1', 'resourceId' => ['videoId' => 'v1'], 'videoOwnerChannelId' => 'UC1', 'channelTitle' => 'Ch'],
        'contentDetails' => ['videoId' => 'v1', 'videoPublishedAt' => '2026-02-02T00:00:00Z'],
    ]]])]);

    $output = (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1'], []);

    expect($output['videos'][0])->toMatchArray(['video_id' => 'v1', 'title' => 'Ep 1', 'published_at' => '2026-02-02T00:00:00Z', 'channel_id' => 'UC1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/playlistItems') && str_contains($request->url(), 'playlistId=PL1'));
});

it('turns a channel id into its uploads playlist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel_id' => 'UCabc123'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'playlistId=UUabc123'));
});

it('needs a playlist or a channel', function () {
    Http::fake();

    (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), YT_BASE, []);
})->throws(InvalidArgumentException::class, 'choose a playlist or enter a channel ID');

it('triggers on new videos, newest first, keyed by video id', function () {
    $node = new YouTubeListPlaylistVideosNode;

    expect($node->triggerItems(['videos' => [['video_id' => 'v1']]], []))->toHaveCount(1)
        ->and($node->triggerItemId(['video_id' => 'v1']))->toBe('v1')
        ->and($node->triggerListsNewestFirst())->toBeTrue()
        ->and($node->triggerName())->toBe('YouTube: New video');
});

it('lists the newest comments and triggers on them', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'c1',
        'snippet' => ['totalReplyCount' => 2, 'topLevelComment' => ['snippet' => [
            'authorDisplayName' => 'Ada', 'authorChannelId' => ['value' => 'UCada'], 'textDisplay' => 'Great', 'likeCount' => 3, 'publishedAt' => '2026-01-01T00:00:00Z', 'videoId' => 'v1',
        ]]],
    ]]])]);

    $node = new YouTubeListCommentsNode;
    $output = $node->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'v1'], []);

    expect($output['comments'][0])->toBe([
        'comment_id' => 'c1', 'author' => 'Ada', 'author_channel_id' => 'UCada', 'text' => 'Great', 'likes' => 3, 'reply_count' => 2, 'published_at' => '2026-01-01T00:00:00Z', 'video_id' => 'v1',
    ]);
    expect($node->triggerItemId($output['comments'][0]))->toBe('c1');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/commentThreads') && str_contains($request->url(), 'order=time'));
});

it('shows the youtube error message', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['error' => ['message' => 'quotaExceeded']], 403)]);

    (new YouTubeSearchVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'query' => 'x'], []);
})->throws(RuntimeException::class, 'YouTube API error [/search]: quotaExceeded');
