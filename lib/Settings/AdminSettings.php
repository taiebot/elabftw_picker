<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Settings;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCA\ElabFtwPicker\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private ConfigService $config,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('admin-config', [
			'base_url' => $this->config->getBaseUrl(),
		]);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-adminSettings');
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'server';
	}

	public function getPriority(): int {
		return 20;
	}
}
