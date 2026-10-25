<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Reference;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCA\ElabFtwPicker\Search\ElabFtwSearchProvider;
use OCA\ElabFtwPicker\Service\ElabFtwService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;

class ElabFtwReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	public const RICH_OBJECT_TYPE = 'elabftw_entity';

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ElabFtwService $service,
		private ?string $userId,
	) {
	}

	public function getId(): string {
		return 'elabftw';
	}

	public function getTitle(): string {
		return $this->l10n->t('eLabFTW experiments and resources');
	}

	public function getOrder(): int {
		return 10;
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	public function getSupportedSearchProviderIds(): array {
		// One provider returning experiments and resources together
		return [
			ElabFtwSearchProvider::ID,
		];
	}

	public function matchReference(string $referenceText): bool {
		return $this->service->parseEntityUrl($referenceText) !== null;
	}

	public function resolveReference(string $referenceText): ?IReference {
		if ($this->userId === null || !$this->matchReference($referenceText)) {
			return null;
		}
		$parsed = $this->service->parseEntityUrl($referenceText);
		if ($parsed === null) {
			return null;
		}
		$entity = $this->service->getEntity($this->userId, $parsed['type'], $parsed['id']);
		if ($entity === null) {
			// no API key, no access, or entry not found: fall back to a plain link
			return null;
		}

		$descriptionParts = array_filter([
			$entity['author'] !== '' ? $this->l10n->t('Author: %1$s', [$entity['author']]) : null,
			$entity['status'] !== null ? $this->l10n->t('Status: %1$s', [$entity['status']]) : null,
		]);

		$reference = new Reference($referenceText);
		$reference->setTitle($entity['title'] !== '' ? $entity['title'] : '#' . $entity['id']);
		$reference->setDescription(implode(' · ', $descriptionParts));
		$reference->setUrl($referenceText);
		$reference->setImageUrl($this->getIconUrl());
		$reference->setRichObject(self::RICH_OBJECT_TYPE, $entity);
		return $reference;
	}

	/**
	 * Previews depend on the viewer's eLabFTW permissions, so the cache is per user.
	 */
	public function getCachePrefix(string $referenceId): string {
		return $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}
}
