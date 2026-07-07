<?php

namespace App\Services\News;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArmenpressNewsFeed
{
    private const CACHE_TTL_MINUTES = 30;

    /**
     * Armenpress economy-category RSS feed per locale.
     */
    private const FEED_URLS = [
        'en' => 'https://armenpress.am/en/rss/articles/economy',
        'ru' => 'https://armenpress.am/ru/rss/articles/economy',
        'hy' => 'https://armenpress.am/hy/rss/articles/economy',
    ];

    /**
     * Human-readable (non-RSS) category page per locale, used for the
     * "view more" link.
     */
    private const CATEGORY_URLS = [
        'en' => 'https://armenpress.am/en/articles/economy',
        'ru' => 'https://armenpress.am/ru/articles/economy',
        'hy' => 'https://armenpress.am/hy/articles/economy',
    ];

    /**
     * Latest economy articles for the given app locale.
     *
     * @return array<int, array{title: string, url: string, image: ?string, category: ?string, published_at: Carbon}>
     */
    public function latest(string $locale, int $limit = 4): array
    {
        $feedUrl = self::FEED_URLS[$locale] ?? self::FEED_URLS['en'];

        $articles = Cache::remember(
            "news.armenpress.{$feedUrl}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->fetch($feedUrl)
        );

        return array_map(
            fn (array $article) => [...$article, 'published_at' => Carbon::parse($article['published_at'])],
            array_slice($articles, 0, $limit)
        );
    }

    /**
     * The category page to link readers to for more articles.
     */
    public function categoryUrl(string $locale): string
    {
        return self::CATEGORY_URLS[$locale] ?? self::CATEGORY_URLS['en'];
    }

    /**
     * @return array<int, array{title: string, url: string, image: ?string, category: ?string, published_at: string}>
     */
    private function fetch(string $feedUrl): array
    {
        try {
            // Armenpress sits behind Cloudflare and blocks requests without a
            // browser-like User-Agent (returns 403).
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                'Accept' => 'application/rss+xml, application/xml;q=0.9, */*;q=0.8',
            ])->timeout(5)->get($feedUrl);

            if (!$response->successful()) {
                return [];
            }

            $xml = simplexml_load_string($response->body());

            if ($xml === false || !isset($xml->channel->item)) {
                return [];
            }

            $articles = [];

            foreach ($xml->channel->item as $item) {
                $articles[] = [
                    'title' => trim((string) $item->title),
                    'url' => (string) $item->link,
                    'image' => (string) ($item->enclosure['url'] ?? '') ?: null,
                    'category' => (string) ($item->categories->category[0] ?? '') ?: null,
                    'published_at' => Carbon::parse((string) $item->pubDate)->toIso8601String(),
                ];
            }

            return $articles;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Armenpress news feed', [
                'feed_url' => $feedUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
