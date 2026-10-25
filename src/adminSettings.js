import { createApp } from 'vue'
import { getRequestToken } from '@nextcloud/auth'
import { linkTo } from '@nextcloud/router'

import AdminSettings from './components/AdminSettings.vue'

__webpack_nonce__ = btoa(getRequestToken())
__webpack_public_path__ = linkTo('elabftw_picker', 'js/')

createApp(AdminSettings).mount('#elabftw_picker_admin_settings')
