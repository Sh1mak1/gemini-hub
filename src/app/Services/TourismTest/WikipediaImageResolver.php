<?php

namespace App\Services\TourismTest;

use Illuminate\Support\Facades\Http;

class WikipediaImageResolver
{
    /**
     * @var list<string>
     */
    private const WIKI_HOSTS = [
        'https://ja.wikipedia.org',
        'https://en.wikipedia.org',
    ];

    public function resolve(string $spotName): ?string
    {
        $spotName = trim($spotName);

        if ($spotName === '') {
            return null;
        }

        foreach (self::WIKI_HOSTS as $host) {
            $imageUrl = $this->fetchThumbnail($host, $spotName);

            if ($imageUrl !== null) {
                return $imageUrl;
            }
        }

        return null;
    }

    private function fetchThumbnail(string $host, string $spotName): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'gemini-hub-tourism-test/1.0'])
                ->get("{$host}/api/rest_v1/page/summary/".rawurlencode($spotName));

            if (! $response->successful()) {
                return null;
            }

            $thumbnail = $response->json('thumbnail.source');

            return is_string($thumbnail) && $thumbnail !== '' ? $thumbnail : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
