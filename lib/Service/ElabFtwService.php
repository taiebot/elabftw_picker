<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Service;

use DateTimeImmutable;
use OCP\Http\Client\IClientService;
use OCP\IDateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

class ElabFtwService {
	public const TYPE_EXPERIMENTS = 'experiments';
	public const TYPE_ITEMS = 'items';

	/** eLabFTW "state" value of an archived entry */
	public const STATE_ARCHIVED = 2;

	/** Max length of the plain-text excerpt of the entry body */
	private const EXCERPT_LENGTH = 200;

	/** eLabFTW web page used for each entity type */
	private const PAGES = [
		self::TYPE_EXPERIMENTS => 'experiments.php',
		self::TYPE_ITEMS => 'database.php',
	];

	public function __construct(
		private IClientService $clientService,
		private ConfigService $config,
		private LoggerInterface $logger,
		private IDateTimeZone $dateTimeZone,
	) {
	}

	public function isConfigured(string $userId): bool {
		return $this->config->getBaseUrl() !== '' && $this->config->hasApiKey($userId);
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	public function ping(string $userId): array {
		if ($this->config->getBaseUrl() === '') {
			return ['ok' => false, 'message' => 'The administrator has not configured the eLabFTW server URL.'];
		}
		try {
			$this->request($userId, '/' . self::TYPE_EXPERIMENTS, ['limit' => 1]);
			return ['ok' => true, 'message' => ''];
		} catch (Throwable $e) {
			$this->logger->warning('eLabFTW connection test failed: ' . $e->getMessage(), ['app' => 'elabftw_picker']);
			return ['ok' => false, 'message' => $e->getMessage()];
		}
	}

	/**
	 * Search experiments or resources, most recently modified first.
	 *
	 * @param int|null $state eLabFTW state to search in (null: the API default, i.e. normal entries)
	 * @return list<array<string, mixed>> normalized entities
	 */
	public function search(string $userId, string $type, string $term, int $limit, int $offset, ?int $state = null): array {
		if (!isset(self::PAGES[$type])) {
			return [];
		}
		$query = [
			'limit' => $limit,
			'offset' => $offset,
			'order' => 'lastchange',
			'sort' => 'desc',
		];
		if ($state !== null) {
			$query['state'] = $state;
		}
		$term = trim($term);
		if ($term !== '') {
			$query['q'] = $term;
		}
		try {
			$rows = $this->request($userId, '/' . $type, $query);
		} catch (Throwable $e) {
			$this->logger->warning('eLabFTW search failed: ' . $e->getMessage(), ['app' => 'elabftw_picker']);
			return [];
		}
		$entities = [];
		foreach ($rows ?? [] as $row) {
			if (is_array($row) && isset($row['id'])) {
				$entities[] = $this->normalizeEntity($type, $row);
			}
		}
		return $entities;
	}

	/**
	 * @return array<string, mixed>|null normalized entity, null if not found or not accessible
	 */
	public function getEntity(string $userId, string $type, int $id): ?array {
		if (!isset(self::PAGES[$type])) {
			return null;
		}
		// An archived entry is only returned by the API when its state is asked for explicitly,
		// so try the normal entry first, then the archived one.
		$row = null;
		foreach ([[], ['state' => self::STATE_ARCHIVED]] as $query) {
			try {
				$row = $this->request($userId, '/' . $type . '/' . $id, $query);
			} catch (Throwable $e) {
				$this->logger->info('eLabFTW entity could not be fetched: ' . $e->getMessage(), ['app' => 'elabftw_picker']);
				$row = null;
				continue;
			}
			if (is_array($row) && isset($row['id'])) {
				break;
			}
			$row = null;
		}
		if ($row === null) {
			return null;
		}
		return $this->normalizeEntity($type, $row);
	}

	public function buildEntityUrl(string $type, int $id): string {
		return $this->config->getBaseUrl() . '/' . self::PAGES[$type] . '?mode=view&id=' . $id;
	}

	/**
	 * Recognizes experiments.php / database.php links belonging to the configured server.
	 *
	 * @return array{type: string, id: int}|null
	 */
	public function parseEntityUrl(string $url): ?array {
		$baseUrl = $this->config->getBaseUrl();
		if ($baseUrl === '') {
			return null;
		}
		$base = parse_url($baseUrl);
		$target = parse_url(trim($url));
		if ($base === false || $target === false || !isset($base['host'], $target['host'])) {
			return null;
		}
		if (strtolower($target['scheme'] ?? '') !== strtolower($base['scheme'] ?? '')
			|| strtolower($target['host']) !== strtolower($base['host'])
			|| ($target['port'] ?? null) !== ($base['port'] ?? null)
		) {
			return null;
		}
		$basePath = rtrim($base['path'] ?? '', '/');
		$path = $target['path'] ?? '';
		if (!str_starts_with($path, $basePath . '/')) {
			return null;
		}
		$type = array_search(substr($path, strlen($basePath) + 1), self::PAGES, true);
		if ($type === false) {
			return null;
		}
		parse_str($target['query'] ?? '', $params);
		$id = $params['id'] ?? null;
		if (!is_string($id) || !ctype_digit($id)) {
			return null;
		}
		return ['type' => (string)$type, 'id' => (int)$id];
	}

	/**
	 * @param array<string, mixed> $row raw eLabFTW API entity
	 * @return array<string, mixed>
	 */
	private function normalizeEntity(string $type, array $row): array {
		$id = (int)$row['id'];
		$author = trim((string)($row['fullname'] ?? ''));
		if ($author === '') {
			$author = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
		}
		$modifiedAt = $row['modified_at'] ?? $row['lastchange'] ?? null;
		$modifiedAt = is_string($modifiedAt) && $modifiedAt !== '' ? $modifiedAt : null;

		return [
			'id' => $id,
			'type' => $type,
			'title' => (string)($row['title'] ?? ''),
			'author' => $author,
			'modified_at' => $modifiedAt,
			'modified_ts' => $this->toTimestamp($modifiedAt),
			'status' => $this->nullableString($row['status_title'] ?? null),
			'status_color' => $this->nullableString($row['status_color'] ?? null),
			'category' => $this->nullableString($row['category_title'] ?? null),
			'category_color' => $this->nullableString($row['category_color'] ?? null),
			'tags' => $this->parseTags($row['tags'] ?? null),
			'locked' => !empty($row['locked']),
			'timestamped' => !empty($row['timestamped']),
			'signed' => !empty($row['signature_count']) || !empty($row['last_signed_at']),
			'archived' => (int)($row['state'] ?? 0) === self::STATE_ARCHIVED,
			'excerpt' => $this->buildExcerpt($row),
			'url' => $this->buildEntityUrl($type, $id),
		];
	}

	private function nullableString(mixed $value): ?string {
		return is_string($value) && $value !== '' ? $value : null;
	}

	/**
	 * eLabFTW gives the tags as one string separated by "|" ("ethics in research|HeLa").
	 *
	 * @return list<string>
	 */
	private function parseTags(mixed $value): array {
		if (!is_string($value) || $value === '') {
			return [];
		}
		$tags = array_map('trim', explode('|', $value));
		return array_values(array_filter($tags, static fn (string $tag): bool => $tag !== ''));
	}

	/**
	 * Plain-text start of the entry body (the body is HTML, so it is stripped).
	 */
	private function buildExcerpt(array $row): ?string {
		$html = $row['body_html'] ?? $row['body'] ?? null;
		if (!is_string($html) || $html === '') {
			return null;
		}
		// Block tags become spaces so that "<p>a</p><p>b</p>" does not turn into "ab"
		$html = (string)preg_replace('/<\/?(p|br|div|li|ul|ol|h[1-6]|tr|td|th)\b[^>]*>/i', ' ', mb_substr($html, 0, 3000));
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim((string)preg_replace('/\s+/u', ' ', $text));
		if ($text === '') {
			return null;
		}
		if (mb_strlen($text) > self::EXCERPT_LENGTH) {
			return rtrim(mb_substr($text, 0, self::EXCERPT_LENGTH)) . '…';
		}
		return $text;
	}

	/**
	 * eLabFTW dates have no timezone ("2026-10-23 22:30:00"). PHP would read them in the
	 * Nextcloud server timezone (often UTC), shifting them by the offset of the lab.
	 * They are read in the current user's timezone instead (assumed to be the lab's).
	 * A date that carries its own offset keeps it.
	 */
	private function toTimestamp(?string $date): ?int {
		if ($date === null) {
			return null;
		}
		try {
			return (new DateTimeImmutable($date, $this->dateTimeZone->getTimeZone()))->getTimestamp();
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * @param array<string, scalar> $query
	 * @return array<mixed>|null
	 * @throws Throwable on network, HTTP or decoding errors
	 */
	private function request(string $userId, string $path, array $query = []): ?array {
		$baseUrl = $this->config->getBaseUrl();
		$apiKey = $this->config->getApiKey($userId);
		if ($baseUrl === '' || $apiKey === '') {
			return null;
		}
		$url = $baseUrl . '/api/v2' . $path;
		if ($query !== []) {
			$url .= '?' . http_build_query($query);
		}
		$response = $this->clientService->newClient()->get($url, [
			'headers' => [
				'Authorization' => $apiKey,
				'Accept' => 'application/json',
			],
			'timeout' => 10,
		]);
		$body = $response->getBody();
		if (!is_string($body)) {
			$body = (string)stream_get_contents($body);
		}
		$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
		return is_array($data) ? $data : null;
	}
}
