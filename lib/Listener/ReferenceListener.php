<?php

declare(strict_types=1);

namespace OCA\ElabFtwPicker\Listener;

use OCA\ElabFtwPicker\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class ReferenceListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}
		// Loads the script registering the rich preview widget
		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
	}
}
