<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Core\Configure;
use Cake\Http\Client;

/**
 * Resolve available releases and release channels for in-app updates.
 */
class DeploymentReleaseService
{
    /**
     * Supported release channels.
     */
    public const CHANNELS = [
        'nightly' => 'Nightly',
        'dev' => 'Dev',
        'beta' => 'Beta',
        'released' => 'Released',
    ];

    /**
     * Release feed result.
     *
     * @var array<string, array>
     */
    private array $cachedReleases = [];

    /**
     * Get available channels with display label.
     *
     * @return array<string, string>
     */
    public function getChannels(): array
    {
        return self::CHANNELS;
    }

    /**
     * Resolve all releases from configured feed.
     *
     * @param int $limit Maximum number of items.
     * @return array<int, array>
     */
    public function getReleases(int $limit = 20): array
    {
        if ($this->cachedReleases !== []) {
            return array_slice($this->cachedReleases, 0, $limit);
        }

        $releaseFeed = $this->getFeedUrl();
        if (!$releaseFeed) {
            $this->cachedReleases = [];

            return [];
        }

        $response = null;

        if (str_starts_with($releaseFeed, 'file://')) {
            $path = substr($releaseFeed, 7);
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $response = json_decode($contents, true);
                }
            }
        } else {
            $client = new Client();
            $token = Configure::read('KMP.GitHubToken', env('KMP_GITHUB_TOKEN'));
            $headers = [
                'User-Agent' => 'KMP-Deployment-Controller',
                'Accept' => 'application/vnd.github+json',
            ];
            if ($token) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            $result = $client->get($releaseFeed, [], [
                'headers' => $headers,
                'timeout' => 5,
            ]);

            if ($result->isOk()) {
                $response = $result->getJson();
            }
        }

        if (!is_array($response)) {
            $this->cachedReleases = [];

            return [];
        }

        $items = [];
        foreach ($response as $rawRelease) {
            if (!is_array($rawRelease)) {
                continue;
            }
            $name = $rawRelease['name'] ?? $rawRelease['tag_name'] ?? '';
            $tag = $rawRelease['tag_name'] ?? '';
            if (!$name || !$tag) {
                continue;
            }

            $channel = $this->inferChannel($rawRelease);
            $items[] = [
                'name' => (string)$name,
                'version' => (string)$tag,
                'channel' => $channel,
                'published_at' => (string)($rawRelease['published_at'] ?? ''),
                'prerelease' => (bool)($rawRelease['prerelease'] ?? false),
                'url' => (string)($rawRelease['html_url'] ?? ''),
            ];
        }

        // Stable releases should be first by publish date when available.
        usort($items, function ($left, $right): int {
            if (($right['published_at'] ?? '') === '') {
                return 1;
            }
            if (($left['published_at'] ?? '') === '') {
                return -1;
            }
            if ($left['published_at'] === $right['published_at']) {
                return 0;
            }

            return $left['published_at'] < $right['published_at'] ? 1 : -1;
        });

        $this->cachedReleases = $items;

        return array_slice($items, 0, $limit);
    }

    /**
     * Return release candidates filtered by channel.
     *
     * @param string $channel Filter channel (referred from CHANNELS)
     * @param int $limit Maximum number of items.
     * @return array<int, array>
     */
    public function getReleasesByChannel(string $channel, int $limit = 20): array
    {
        $channel = strtolower((string)$channel);
        if (!array_key_exists($channel, self::CHANNELS)) {
            $channel = 'released';
        }

        return array_values(array_filter(
            $this->getReleases($limit),
            function (array $candidate) use ($channel): bool {
                return $candidate['channel'] === $channel;
            },
        ));
    }

    /**
     * Resolve the best release candidate for a channel.
     *
     * @param string $channel Release channel.
     * @return array<string, string>|null
     */
    public function getLatestByChannel(string $channel): ?array
    {
        $releases = $this->getReleasesByChannel($channel, 1);
        if ($releases === []) {
            return null;
        }

        return $releases[0];
    }

    /**
     * Infer channel for release JSON.
     *
     * @param array<string, mixed> $release JSON release payload.
     * @return string
     */
    private function inferChannel(array $release): string
    {
        $tag = strtolower((string)($release['tag_name'] ?? ''));
        $name = strtolower((string)($release['name'] ?? ''));
        $isPre = (bool)($release['prerelease'] ?? false);

        if (str_contains($tag, 'nightly') || str_contains($name, 'nightly')) {
            return 'nightly';
        }

        if ($isPre || str_contains($tag, 'beta') || str_contains($name, 'beta')) {
            return 'beta';
        }

        if (str_contains($tag, 'dev') || str_contains($name, 'dev')) {
            return 'dev';
        }

        return 'released';
    }

    /**
     * Resolve configured release feed URL.
     *
     * @return string|null
     */
    private function getFeedUrl(): ?string
    {
        return Configure::read('KMP.ReleaseFeed.Url')
            ?: env('KMP_RELEASE_FEED_URL')
            ?: 'https://api.github.com/repos/jhandel/KMP/releases';
    }
}
