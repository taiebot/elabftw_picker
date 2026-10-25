import { createApp } from 'vue'
import { registerWidget } from '@nextcloud/vue/components/NcRichText'
import { getRequestToken } from '@nextcloud/auth'
import { linkTo } from '@nextcloud/router'

import ElabFtwReferenceWidget from './components/ElabFtwReferenceWidget.vue'

__webpack_nonce__ = btoa(getRequestToken())
__webpack_public_path__ = linkTo('elabftw_picker', 'js/')

// The destroy callback only receives the element: keep the app instances by element
const apps = new WeakMap()

// Same id as ElabFtwReferenceProvider::RICH_OBJECT_TYPE on the PHP side
registerWidget('elabftw_entity', (el, { richObjectType, richObject, accessible }) => {
	apps.get(el)?.unmount()
	const app = createApp(ElabFtwReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	})
	app.mount(el)
	apps.set(el, app)
}, (el) => {
	apps.get(el)?.unmount()
	apps.delete(el)
}, { hasInteractiveView: false })
