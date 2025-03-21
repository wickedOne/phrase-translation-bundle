<?php

declare(strict_types=1);

/*
 * This file is part of the Phrase Tag Bundle.
 * (c) wicliff <wicliff.wolda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WickedOne\PhraseTagBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author wicliff <wicliff.wolda@gmail.com>
 */
class PhraseTagService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[] $tags
     *
     * @return string[]
     */
    public function list(?string $key, array $tags): array
    {
        $query = array_filter([
            'page' => '1',
            'per_page' => '100',
            'q' => $this->createQuery($key, $tags),
        ], static fn (string $value): bool => '' !== $value);

        $response = $this->httpClient->request('GET', 'keys', [
            'query' => $query,
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            throw new ProviderException(\sprintf('phrase replied with an error (%d): "%s"', $statusCode, $response->getContent(false)), $response);
        }

        /** @var array{name: string} $arr */
        $arr = $response->toArray();

        return array_column($arr, 'name');
    }

    /**
     * @param string[] $tags
     * @param string[] $addTags
     */
    public function tag(?string $key, array $tags, array $addTags): int
    {
        $query = $this->createQuery($key, $tags);

        $body = array_filter([
            'q' => $query,
            'tags' => implode(',', $addTags),
        ], static fn (string $value): bool => '' !== $value);

        $response = $this->httpClient->request('PATCH', 'keys/tag', [
            'query' => [
                'page' => '1',
                'per_page' => '100',
            ],
            'body' => $body,
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            throw new ProviderException(\sprintf('phrase replied with an error (%d): "%s"', $statusCode, $response->getContent(false)), $response);
        }

        /** @var array{records_affected: string} $arr */
        $arr = $response->toArray();
        $records = $arr['records_affected'];

        $this->logger->info(\sprintf('tagged %d keys matching "%s" with tag(s) "%s"', $records, $query, implode(', ', $addTags)));

        return (int) $records;
    }

    /**
     * @param string[] $tags
     * @param string[] $removeTags
     */
    public function untag(?string $key, array $tags, array $removeTags): int
    {
        $query = $this->createQuery($key, $tags);

        $body = array_filter([
            'q' => $query,
            'tags' => implode(',', $removeTags),
        ], static fn (string $value): bool => '' !== $value);

        $response = $this->httpClient->request('PATCH', 'keys/untag', [
            'query' => [
                'page' => '1',
                'per_page' => '100',
            ],
            'body' => $body,
        ]);

        if (200 !== $statusCode = $response->getStatusCode()) {
            throw new ProviderException(\sprintf('phrase replied with an error (%d): "%s"', $statusCode, $response->getContent(false)), $response);
        }

        /** @var array{records_affected: string} $arr */
        $arr = $response->toArray();
        $records = $arr['records_affected'];

        $this->logger->info(\sprintf('untagged %d keys matching "%s" with tag(s) "%s"', $records, $query, implode(', ', $removeTags)));

        return (int) $records;
    }

    /**
     * @param string[] $tags
     */
    private function createQuery(?string $key, array $tags): string
    {
        $query = $key ?? '';
        $query .= [] !== $tags ? ' tags:'.implode(',', $tags) : '';

        return trim($query);
    }
}
