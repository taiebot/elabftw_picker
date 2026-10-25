<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Service;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Throwable;

class ConfigService {
	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	public function getBaseUrl(): string {
		return rtrim($this->appConfig->getValueString(Application::APP_ID, 'base_url', ''), '/');
	}

	/**
	 * Validates and normalizes the eLabFTW server URL.
	 * Returns '' to unset the URL and null when the value is invalid.
	 */
	public function normalizeBaseUrl(string $url): ?string {
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		$url = (string)preg_replace('#/api/v2/?$#', '', $url);
		$url = rtrim($url, '/');
		$parts = parse_url($url);
		if ($parts === false
			|| !isset($parts['scheme'], $parts['host'])
			|| !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
			|| isset($parts['query'])
			|| isset($parts['fragment'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
		) {
			return null;
		}
		return $url;
	}

	public function setBaseUrl(string $url): void {
		$this->appConfig->setValueString(Application::APP_ID, 'base_url', $url);
	}

	public function getApiKey(string $userId): string {
		$stored = $this->config->getUserValue($userId, Application::APP_ID, 'api_key', '');
		if ($stored === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($stored);
		} catch (Throwable) {
			return '';
		}
	}

	public function hasApiKey(string $userId): bool {
		return $this->getApiKey($userId) !== '';
	}

	public function setApiKey(string $userId, string $apiKey): void {
		if ($apiKey === '') {
			$this->config->deleteUserValue($userId, Application::APP_ID, 'api_key');
			return;
		}
		$this->config->setUserValue($userId, Application::APP_ID, 'api_key', $this->crypto->encrypt($apiKey));
	}
}
