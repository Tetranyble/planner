<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\YouTubeVideoStatistics;
use PHPUnit\Framework\TestCase;

final class YouTubeVideoStatisticsTest extends TestCase
{
    public function test_missing_counts_are_unknown_and_identity_is_bound(): void
    {
        $video = 'Xy123AbC_-1';
        $channel = 'UC'.str_repeat('b', 22);
        $reply = ['items' => [['id' => $video, 'snippet' => ['channelId' => $channel],
            'statistics' => ['viewCount' => '901', 'likeCount' => '12']]]];
        $normalized = YouTubeVideoStatistics::normalize($reply, $video, $channel);
        self::assertSame(901, $normalized['views']);
        self::assertSame(12, $normalized['likes']);
        self::assertNull($normalized['comments']);
        $this->expectException(\DomainException::class);
        YouTubeVideoStatistics::normalize($reply, $video, 'UC'.str_repeat('a', 22));
    }
}
