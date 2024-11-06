<template>
	<div id="opensign_prefs" class="section">
		<h2>
			<OpensignIcon class="icon" />
			{{ t('integration_opensign', 'Opensign integration') }}
		</h2>
		<div id="opensign-content">
			<div class="line">
				<label for="opensign-url">
					<EarthIcon :size="20" class="icon" />
					{{ t('integration_opensign', 'OpenSign instance address') }}
				</label>
				<input id="opensign-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_opensign', 'https://app.opensignlabs.com/')"
					@input="onInput">
			</div>
			<div class="line">
				<label for="opensign-token">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_opensign', 'Access token') }}
				</label>
				<input id="opensign-token"
					v-model="state.token"
					type="password"
					:disabled="connected === true"
					:placeholder="t('integration_opensign', 'OpenSign access token')"
					@input="onInput">
			</div>
			<div v-if="connected" class="line">
				<label class="opensign-connected">
					<CheckIcon :size="20" class="icon" />
					{{ t('integration_opensign', 'Connected as {user}', { user: state.user_name }) }}
				</label>
				<NcButton id="opensign-rm-cred"
					@click="onLogoutClick">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
					{{ t('integration_opensign', 'Disconnect from OpenSign') }}
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

import OpensignIcon from './icons/OpensignIcon.vue'
// TODO change icon

import { NcButton } from '@nextcloud/vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
// import { confirmPassword } from '@nextcloud/password-confirmation'

// import { delay } from '../utils.js'

export default {
	name: 'PersonalSettings',

	components: {
		NcButton,
		OpensignIcon,
		EarthIcon,
		KeyIcon,
		CloseIcon,
		CheckIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_opensign', 'user-config'),
			initialToken: loadState('integration_opensign', 'user-config').token,
			loading: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_opensign/oauth-redirect'),
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
			this.saveOptions({ token: this.state.token, token_type: '' })
		},
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' }, false)
		},
		onInput() {
			this.loading = true
			/* delay(() => {
				const values = {
					url: this.state.url,
				}
				if (this.state.token !== 'dummyToken') {
					values.token = this.state.token
					values.token_type = this.showOAuth ? 'oauth' : 'access'
				}
				this.saveOptions(values)
			}, 2000)() */
		},
		async saveOptions(values, sensitive = true) {
			// if (sensitive) {
			//     await confirmPassword()
			// }
			// TODO needed?
			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/integration_opensign/sensitive-config')
				: generateUrl('/apps/integration_opensign/config')
			return axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_opensign', 'OpenSign options saved'))
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.state.token && response.data.user_name === '') {
							showError(t('integration_opensign', 'Incorrect access token'))
						}
					}
				})
				.catch((error) => {
					console.error(error)
					showError(t('integration_opensign', 'Failed to save OpenSign options'))
				})
				.then(() => {
					this.loading = false
				})
		},
	},
}
</script>

<style scoped lang="scss">
#opensign_prefs {
	#opensign-content {
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
