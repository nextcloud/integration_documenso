<template>
	<div id="documenso_prefs" class="section">
		<h2>
			<DocumensoIcon class="icon" />
			{{ t('integration_documenso', 'Documenso integration') }}
		</h2>
		<div id="documenso-content">
			<NcNoteCard type="info">
				{{ t('integration_documenso', 'To create an access token, go to the "API Token" section of your Documenso User settings.') }}
			</NcNoteCard>
			<NcTextField
				v-model="state.url"
				:label="t('integration_documenso', 'Documenso instance address')"
				placeholder="https://app.documenso.com/"
				:disabled="connected === true"
				:show-trailing-button="!!state.url"
				@trailing-button-click="state.url = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<EarthIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField
				v-model="state.token"
				type="password"
				:label="t('integration_documenso', 'Access token')"
				:placeholder="t('integration_documenso', 'Documenso access token')"
				:disabled="connected === true"
				:show-trailing-button="!!state.token"
				@trailing-button-click="state.token = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<div v-if="connected" class="line">
				<label class="documenso-connected">
					<CheckIcon :size="20" class="icon" />
					{{ t('integration_documenso', 'Connected as {user}', { user: state.user_name }) }}
				</label>
				<NcButton id="documenso-rm-cred"
					@click="onLogoutClick">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
					{{ t('integration_documenso', 'Disconnect from Documenso') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import DocumensoIcon from './icons/DocumensoIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

import { delay } from '../utils.js'

export default {
	name: 'PersonalSettings',

	components: {
		NcButton,
		NcNoteCard,
		NcTextField,
		DocumensoIcon,
		EarthIcon,
		KeyOutlineIcon,
		CloseIcon,
		CheckIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_documenso', 'user-config'),
			initialToken: loadState('integration_documenso', 'user-config').token,
			loading: false,
		}
	},

	computed: {
		connected() {
			return this.state.token && this.state.token !== ''
				&& this.state.url && this.state.url !== ''
				&& this.state.user_name && this.state.user_name !== ''
		},
	},

	mounted() {
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions({ token: this.state.token })
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({
					url: this.state.url,
				})
				if (!'dummyToken'.includes(this.state.token)) {
					this.saveOptions({
						token: this.state.token,
					})
				}
			}, 2000)()
		},
		async saveOptions(values) {
			await confirmPassword()
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_documenso/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_documenso', 'Documenso options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_documenso', 'Failed to save Documenso options')
						+ ': ' + error.response.request.responseText,
					)
				})
				.then(() => {
					this.loading = false
				})
		},
	},
}
</script>

<style scoped lang="scss">
#documenso_prefs {
	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		gap: 8px;
	}
	#documenso-content {
		margin-left: 40px;
		display: flex;
		flex-direction: column;
		gap: 4px;
		max-width: 800px;

		.line {
			display: flex;
			align-items: center;
			gap: 8px;
		}
	}
}
</style>
