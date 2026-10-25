<template>
	<NcSettingsSection :name="t('elabftw_picker', 'eLabFTW')"
		:description="t('elabftw_picker', 'Enter your eLabFTW API key to search eLabFTW from the Smart Picker and to see previews of eLabFTW links. Create a key in eLabFTW, under Settings > API keys.')">
		<NcNoteCard v-if="!serverUrl" type="warning">
			{{ t('elabftw_picker', 'The administrator has not configured the eLabFTW server URL yet.') }}
		</NcNoteCard>
		<div v-else class="elabftw-personal">
			<p class="elabftw-personal__server">
				{{ t('elabftw_picker', 'Server: {url}', { url: serverUrl }) }}
			</p>
			<NcPasswordField v-model="apiKey"
				:label="hasApiKey ? t('elabftw_picker', 'API key (saved, enter a new one to replace it)') : t('elabftw_picker', 'API key')"
				:disabled="loading"
				autocomplete="off" />
			<div class="elabftw-personal__actions">
				<NcButton variant="primary"
					:disabled="loading || apiKey.trim() === ''"
					@click="save">
					{{ t('elabftw_picker', 'Save and test') }}
				</NcButton>
				<NcButton v-if="hasApiKey"
					variant="tertiary"
					:disabled="loading"
					@click="remove">
					{{ t('elabftw_picker', 'Remove key') }}
				</NcButton>
			</div>
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
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

export default {
	name: 'PersonalSettings',

	components: {
		NcButton,
		NcNoteCard,
		NcPasswordField,
		NcSettingsSection,
	},

	data() {
		const state = loadState('elabftw_picker', 'user-config')
		return {
			serverUrl: state.base_url,
			hasApiKey: state.has_api_key,
			apiKey: '',
			loading: false,
		}
	},

	methods: {
		t,

		async save() {
			await this.send(this.apiKey.trim())
		},

		async remove() {
			await this.send('')
		},

		async send(apiKey) {
			this.loading = true
			try {
				const response = await axios.put(generateUrl('/apps/elabftw_picker/user-config'), { apiKey })
				this.hasApiKey = apiKey !== ''
				this.apiKey = ''
				if (apiKey === '') {
					showSuccess(t('elabftw_picker', 'API key removed'))
				} else if (response.data.ok) {
					showSuccess(t('elabftw_picker', 'Connection to eLabFTW successful'))
				} else {
					showError(t('elabftw_picker', 'Key saved, but the connection test failed: {message}', { message: response.data.message }))
				}
			} catch (error) {
				showError(t('elabftw_picker', 'Could not save the API key'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.elabftw-personal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	max-width: 600px;

	&__server {
		color: var(--color-text-maxcontrast);
		margin: 0;
	}

	&__actions {
		display: flex;
		gap: 8px;
	}
}
</style>
