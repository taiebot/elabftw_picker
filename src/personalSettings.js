import { createApp } from 'vue'
import { getRequestToken } from '@nextcloud/auth'
import { linkTo } from '@nextcloud/router'

import PersonalSettings from './components/PersonalSettings.vue'

__webpack_nonce__ = btoa(getRequestToken())
__webpack_public_path__ = linkTo('elabftw_picker', 'js/')

createApp(PersonalSettings).mount('#elabftw_picker_personal_settings')
