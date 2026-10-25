<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\AppInfo;

use OCA\ElabFtwPicker\Listener\ReferenceListener;
use OCA\ElabFtwPicker\Reference\ElabFtwReferenceProvider;
use OCA\ElabFtwPicker\Search\ElabFtwSearchProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Reference\RenderReferenceEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'elabftw_picker';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// One provider for experiments and resources (used by the Smart Picker and global search)
		$context->registerSearchProvider(ElabFtwSearchProvider::class);
		$context->registerReferenceProvider(ElabFtwReferenceProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, ReferenceListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
