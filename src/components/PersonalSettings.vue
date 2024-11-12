<template>
	<div id="documenso_prefs" class="section">
		<h2>
			<DocumensoIcon class="icon" />
			{{ t('integration_documenso', 'Documenso integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_documenso', 'To create an access token, go to the "API Token" section of your Documenso User settings.') }}
		</p>
		<div id="documenso-content">
			<div class="line">
				<label for="documenso-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_documenso', 'Documenso instance address') }}
				</label>
				<input id="documenso-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_documenso', 'https://app.documenso.com/')"
					@input="onInput">
			</div>
			<div class="line">
				<label for="documenso-token">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_documenso', 'Access token') }}
				</label>
				<input id="documenso-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_documenso', 'Documenso access token')"
					@input="onInput">
			</div>
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
import KeyIcon from 'vue-material-design-icons/Key.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import DocumensoIcon from './icons/DocumensoIcon.vue'
import { NcButton } from '@nextcloud/vue'

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
		DocumensoIcon,
		EarthIcon,
		KeyIcon,
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
	#documenso-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 250px;
		}
	}
}
</style>
