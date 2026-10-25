<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Controller;

use OCA\ElabFtwPicker\Service\ConfigService;
use OCA\ElabFtwPicker\Service\ElabFtwService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ConfigController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigService $config,
		private ElabFtwService $service,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Saves the current user's API key, then tests it against the server.
	 */
	#[NoAdminRequired]
	public function setUserConfig(string $apiKey): DataResponse {
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$apiKey = trim($apiKey);
		$this->config->setApiKey($this->userId, $apiKey);
		if ($apiKey === '') {
			return new DataResponse(['ok' => true, 'message' => '']);
		}
		return new DataResponse($this->service->ping($this->userId));
	}

	/**
	 * Admin only (no #[NoAdminRequired]): sets the eLabFTW server URL.
	 */
	public function setAdminConfig(string $baseUrl): DataResponse {
		$normalized = $this->config->normalizeBaseUrl($baseUrl);
		if ($normalized === null) {
			return new DataResponse(['message' => 'Invalid URL'], Http::STATUS_BAD_REQUEST);
		}
		$this->config->setBaseUrl($normalized);
		return new DataResponse(['base_url' => $normalized]);
	}
}
