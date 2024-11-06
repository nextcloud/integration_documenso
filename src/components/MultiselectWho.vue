<template>
	<NcSelect
		class="documenso-multiselect"
		label="displayName"
		track-by="trackKey"
		:value="value"
		:multiple="true"
		:clear-on-select="true"
		:loading="loadingSuggestions"
		:options="formattedSuggestions"
		:placeholder="placeholder"
		:searchable="true"
		:append-to-body="false"
		:aria-label-combobox="label"
		v-bind="$attrs"
		@search="asyncFind"
		@input="$emit('update:value', $event)">
		<template #option="option">
			<div class="multiselect-option">
				<NcAvatar v-if="option.type === 'user'"
					class="documenso-avatar-option"
					:user="option.entityId"
					:show-user-status="false" />
				<NcAvatar v-else-if="['group', 'circle', 'email'].includes(option.type)"
					class="documenso-avatar-option"
					:display-name="option.displayName"
					:is-no-user="true"
					:disable-tooltip="true"
					:show-user-status="false" />
				<NcHighlight
					:text="option.displayName"
					:search="query"
					class="multiselect-name" />
				<span v-if="option.icon"
					:class="{ icon: true, [option.icon]: true, 'multiselect-icon': true }" />
			</div>
		</template>
		<template #no-options>
			{{ t('integration_documenso', 'No recommendations. Start typing.') }}
		</template>
	</NcSelect>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcHighlight from '@nextcloud/vue/dist/Components/NcHighlight.js'

export default {
	name: 'MultiselectWho',

	components: {
		NcAvatar,
		NcSelect,
		NcHighlight,
	},

	props: {
		value: {
			type: Array,
			required: true,
		},
		types: {
			type: Array,
			// users, groups and circles
			default: () => [
				0,
				1,
				// wait until new circle stuff is more stable
				// 7,
			],
		},
		placeholder: {
			type: String,
			default: t('integration_documenso', 'Who?'),
		},
		label: {
			type: String,
			default: t('integration_documenso', 'Users or groups'),
		},
		enableEmails: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loadingSuggestions: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
		}
	},

	computed: {
		queryIsEmail() {
			const cleanQuery = this.query.replace(/\s+/g, '')
			return /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/.test(cleanQuery)
		},
		formattedSuggestions() {
			// users suggestions (avoid selected users)
			const result = this.suggestions.filter((s) => {
				return s.source === 'users' && !this.value.find(u => u.type === 'user' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'user',
					displayName: s.label,
					icon: 'icon-user',
					trackKey: 'user-' + s.id,
				}
			})

			// email suggestion
			const cleanQuery = this.query.replace(/\s+/g, '')
			if (this.enableEmails
				&& /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/.test(cleanQuery)
				&& !this.value.find(i => i.type === 'email' && i.email === cleanQuery)
			) {
				result.push({
					type: 'email',
					displayName: cleanQuery,
					email: cleanQuery,
					icon: 'icon-mail',
					trackKey: 'email-' + cleanQuery,
				})
			}

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (lowerCurrent.match(lowerQuery) && !this.value.find(u => u.type === 'user' && u.entityId === this.currentUser.uid)) {
					result.push({
						entityId: this.currentUser.uid,
						type: 'user',
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + this.currentUser.uid,
					})
				}
			}

			// groups suggestions (avoid selected ones)
			const groups = this.suggestions.filter((s) => {
				return s.source === 'groups' && !this.value.find(u => u.type === 'group' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'group',
					displayName: s.label,
					icon: 'icon-group',
					trackKey: 'group-' + s.id,
				}
			})
			result.push(...groups)

			// circles suggestions (avoid selected ones)
			const circles = this.suggestions.filter((s) => {
				return s.source === 'circles' && !this.value.find(u => u.type === 'circle' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'circle',
					displayName: s.label,
					icon: 'icon-circle',
					trackKey: 'circle-' + s.id,
				}
			})
			result.push(...circles)

			// always add selected users/groups/circles/emails at the end
			result.push(...this.value.map((w) => {
				return w.type === 'user'
					? {
						entityId: w.entityId,
						type: 'user',
						displayName: w.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + w.entityId,
					}
					: w.type === 'group'
						? {
							entityId: w.entityId,
							type: 'group',
							displayName: w.displayName,
							icon: 'icon-group',
							trackKey: 'group-' + w.entityId,
						}
						: w.type === 'circle'
							? {
								entityId: w.entityId,
								type: 'circle',
								displayName: w.displayName,
								icon: 'icon-circle',
								trackKey: 'circle-' + w.entityId,
							}
							: {
								type: 'email',
								displayName: w.displayName,
								email: w.email,
								icon: 'icon-mail',
								trackKey: 'email-' + w.email,
							}
			}))

			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingSuggestions = true
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					shareTypes: this.types,
				},
			}).then((response) => {
				this.suggestions = response.data.ocs.data
			}).catch((error) => {
				showError(t('integration_documenso', 'Impossible to get user/group/circle list'))
				console.error(error)
			}).then(() => {
				this.loadingSuggestions = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
:deep(.multiselect-option) {
	display: flex;
	align-items: center;

	.multiselect-name {
		flex-grow: 1;
		margin-left: 10px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.multiselect-icon {
		opacity: 0.5;
	}
	.icon-circle {
		background-image: var(--icon-contacts-circles-000);
		background-size: 100% 100%;
		background-repeat: no-repeat;
		background-position: center;
	}
}
</style>
