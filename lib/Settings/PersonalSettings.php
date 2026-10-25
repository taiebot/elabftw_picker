<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Settings;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCA\ElabFtwPicker\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class PersonalSettings implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private ConfigService $config,
		private ?string $userId,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('user-config', [
			'base_url' => $this->config->getBaseUrl(),
			// the key itself is never sent back to the browser
			'has_api_key' => $this->userId !== null && $this->config->hasApiKey($this->userId),
		]);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-personalSettings');
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 20;
	}
}
