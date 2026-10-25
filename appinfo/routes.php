<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'config#setUserConfig', 'url' => '/user-config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
	],
];
