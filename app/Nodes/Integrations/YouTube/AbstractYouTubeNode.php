<?php

namespace App\Nodes\Integrations\YouTube;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;

/**
 * Shared request handling and shaping for the YouTube node family (read
 * only: search, videos, channels, playlists and comments).
 */
abstract class AbstractYouTubeNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_youtube';
    }

    protected function baseUrl(): string
    {
        return 'https://www.googleapis.com/youtube/v3';
    }

    protected function appName(): string
    {
        return 'YouTube';
    }

    /**
     * A video's id from a search result, a playlist item or a video resource.
     *
     * @param  array<string, mixed>  $item
     */
    protected function videoId(array $item): ?string
    {
        $id = $item['id'] ?? null;

        return $item['contentDetails']['videoId']
            ?? $item['snippet']['resourceId']['videoId']
            ?? (is_array($id) ? ($id['videoId'] ?? null) : $id);
    }

    /**
     * A video in the flat shape the nodes return.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function video(array $item): array
    {
        $id = $this->videoId($item);
        $snippet = (array) ($item['snippet'] ?? []);

        return array_filter([
            'video_id' => $id,
            'title' => $snippet['title'] ?? null,
            'description' => $snippet['description'] ?? null,
            'channel_id' => $snippet['channelId'] ?? $snippet['videoOwnerChannelId'] ?? null,
            'channel_title' => $snippet['channelTitle'] ?? null,
            'published_at' => $item['contentDetails']['videoPublishedAt'] ?? $snippet['publishedAt'] ?? null,
            'thumbnail' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
            'url' => $id !== null ? "https://www.youtube.com/watch?v={$id}" : null,
            'duration' => $item['contentDetails']['duration'] ?? null,
            'views' => isset($item['statistics']['viewCount']) ? (int) $item['statistics']['viewCount'] : null,
            'likes' => isset($item['statistics']['likeCount']) ? (int) $item['statistics']['likeCount'] : null,
            'comments' => isset($item['statistics']['commentCount']) ? (int) $item['statistics']['commentCount'] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The "uploads" playlist of a channel, which lists every public video in
     * upload order. A channel ID `UCxxxx` has the uploads playlist `UUxxxx`.
     */
    protected function uploadsPlaylistId(string $channelId): string
    {
        return str_starts_with($channelId, 'UC') ? 'UU'.substr($channelId, 2) : $channelId;
    }
}
