<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Search;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCA\ElabFtwPicker\Service\ElabFtwService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

/**
 * Single search provider returning experiments AND resources in one list,
 * most recently modified first.
 *
 * Normal entries come first. Once they are exhausted, the result stays "paginated",
 * so the "Load more" button of the picker continues with the archived entries.
 * If a search finds no normal entry at all, the archives are searched right away.
 *
 * The cursor is "<phase>:<experiments offset>:<resources offset>",
 * where the phase is "n" (normal entries) or "a" (archived entries).
 */
class ElabFtwSearchProvider implements IProvider {
	public const ID = 'elabftw';

	private const PHASE_NORMAL = 'n';
	private const PHASE_ARCHIVED = 'a';

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ElabFtwService $service,
	) {
	}

	public function getId(): string {
		return self::ID;
	}

	public function getName(): string {
		return $this->l10n->t('eLabFTW');
	}

	public function getOrder(string $route, array $routeParameters): ?int {
		if (str_starts_with($route, Application::APP_ID . '.')) {
			return -1;
		}
		return 20;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$userId = $user->getUID();
		$limit = $query->getLimit();

		if (!$this->service->isConfigured($userId)) {
			return SearchResult::complete($this->getName(), []);
		}

		$cursor = $query->getCursor();
		[$phase, $experimentsOffset, $itemsOffset] = $this->parseCursor($cursor);
		$term = $query->getTerm();

		$result = $this->fetchPage($userId, $term, $limit, $phase, $experimentsOffset, $itemsOffset);

		// First page without any normal entry: look in the archives right away
		if ($phase === self::PHASE_NORMAL && $cursor === null && $result['page'] === []) {
			$phase = self::PHASE_ARCHIVED;
			$experimentsOffset = 0;
			$itemsOffset = 0;
			$result = $this->fetchPage($userId, $term, $limit, $phase, $experimentsOffset, $itemsOffset);
		}

		$isArchivePhase = $phase === self::PHASE_ARCHIVED;
		$entries = array_map(
			fn (array $entity): SearchResultEntry => $this->buildEntry($entity, $isArchivePhase),
			$result['page'],
		);

		if ($result['hasMore']) {
			$nextCursor = $phase . ':' . ($experimentsOffset + $result['takenExperiments']) . ':' . ($itemsOffset + $result['takenItems']);
			return SearchResult::paginated($this->getName(), $entries, $nextCursor);
		}

		if ($isArchivePhase) {
			return SearchResult::complete($this->getName(), $entries);
		}

		// Normal entries are exhausted: "Load more" searches the archives
		return SearchResult::paginated($this->getName(), $entries, self::PHASE_ARCHIVED . ':0:0');
	}

	/**
	 * One page of merged experiments and resources, for the normal or the archived phase.
	 *
	 * @return array{page: list<array<string, mixed>>, takenExperiments: int, takenItems: int, hasMore: bool}
	 */
	private function fetchPage(string $userId, string $term, int $limit, string $phase, int $experimentsOffset, int $itemsOffset): array {
		$state = $phase === self::PHASE_ARCHIVED ? ElabFtwService::STATE_ARCHIVED : null;

		$experiments = $this->service->search($userId, ElabFtwService::TYPE_EXPERIMENTS, $term, $limit, $experimentsOffset, $state);
		$items = $this->service->search($userId, ElabFtwService::TYPE_ITEMS, $term, $limit, $itemsOffset, $state);

		// Both lists are already sorted by last change: merge them (usort is stable)
		$merged = array_merge($experiments, $items);
		usort($merged, static fn (array $a, array $b): int => ($b['modified_ts'] ?? 0) <=> ($a['modified_ts'] ?? 0));
		$page = array_slice($merged, 0, $limit);

		$takenExperiments = count(array_filter(
			$page,
			static fn (array $entity): bool => $entity['type'] === ElabFtwService::TYPE_EXPERIMENTS,
		));

		return [
			'page' => $page,
			'takenExperiments' => $takenExperiments,
			'takenItems' => count($page) - $takenExperiments,
			'hasMore' => count($merged) > $limit
				|| count($experiments) >= $limit
				|| count($items) >= $limit,
		];
	}

	/**
	 * @param array<string, mixed> $entity normalized entity from ElabFtwService
	 */
	private function buildEntry(array $entity, bool $isArchivePhase): SearchResultEntry {
		$isItem = $entity['type'] === ElabFtwService::TYPE_ITEMS;
		// Neutral gray and padded variants (see make_search_icons.py): a thumbnail cannot follow the theme
		$icon = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, $isItem ? 'resource-search.svg' : 'experiment-search.svg')
		);
		$subline = implode(' · ', array_filter([
			$isItem ? $this->l10n->t('Resource') : $this->l10n->t('Experiment'),
			'#' . $entity['id'],
			($isArchivePhase || !empty($entity['archived'])) ? $this->l10n->t('Archived') : null,
			$entity['category'],
			$entity['author'] !== '' ? $entity['author'] : null,
			$entity['status'],
		]));

		// The first argument is the thumbnail URL (the last one is a CSS class, not a URL)
		return new SearchResultEntry(
			$icon,
			$entity['title'] !== '' ? $entity['title'] : '#' . $entity['id'],
			$subline,
			$entity['url'],
		);
	}

	/**
	 * @return array{0: string, 1: int, 2: int} phase, experiments offset, resources offset
	 */
	private function parseCursor(int|string|null $cursor): array {
		if (!is_string($cursor) || preg_match('/^([na]):(\d+):(\d+)$/', $cursor, $matches) !== 1) {
			return [self::PHASE_NORMAL, 0, 0];
		}
		return [$matches[1], (int)$matches[2], (int)$matches[3]];
	}
}
