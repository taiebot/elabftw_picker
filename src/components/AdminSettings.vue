<template>
	<NcSettingsSection :name="t('elabftw_picker', 'eLabFTW Smart Picker')"
		:description="t('elabftw_picker', 'URL of your eLabFTW server. Each user then enters their own API key in their personal settings.')">
		<div class="elabftw-admin">
			<NcTextField v-model="baseUrl"
				:label="t('elabftw_picker', 'eLabFTW server URL')"
				placeholder="https://elab.example.org"
				:disabled="loading" />
			<NcButton variant="primary"
				:disabled="loading"
				@click="save">
				{{ t('elabftw_picker', 'Save') }}
			</NcButton>
		</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'AdminSettings',

	components: {
		NcButton,
		NcSettingsSection,
		NcTextField,
	},

	data() {
		return {
			baseUrl: loadState('elabftw_picker', 'admin-config').base_url,
			loading: false,
		}
	},

	methods: {
		t,

		async save() {
			this.loading = true
			try {
				const response = await axios.put(generateUrl('/apps/elabftw_picker/admin-config'), {
					baseUrl: this.baseUrl,
				})
				this.baseUrl = response.data.base_url
				showSuccess(t('elabftw_picker', 'Settings saved'))
			} catch (error) {
				showError(t('elabftw_picker', 'Invalid URL, it must start with http:// or https://'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.elabftw-admin {
	display: flex;
	align-items: flex-end;
	gap: 12px;
	max-width: 600px;
}
</style>
