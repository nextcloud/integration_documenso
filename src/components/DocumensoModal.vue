<template>
	<div class="documenso-modal-container">
		<NcModal v-if="show"
			:size="showIframe ? 'full' : 'normal'"
			label-id="documenso-modal-title"
			@close="closeRequestModal">
			<div class="documenso-modal-content">
				<h2 id="documenso-modal-title" class="modal-title">
					<DocumensoIcon />
					<span class="modal-title-text">
						{{ t('integration_documenso', 'Request a signature via Documenso') }}
					</span>
				</h2>
				<template v-if="!showIframe">
					<span class="field-label">
						{{ t('integration_documenso', 'Users or email addresses') }}
					</span>
					<MultiselectWho
						ref="multiselect"
						class="userInput"
						:value="selectedItems"
						:types="[0]"
						:enable-emails="true"
						:placeholder="t('integration_documenso', 'Nextcloud users or email addresses')"
						:label="t('integration_documenso', 'Users or email addresses')"
						@update:value="updateSelectedItems($event)" />
					<NcEmptyContent
						:name="t('integration_documenso', 'Documenso workflow')"
						:description="t('integration_documenso', 'The document and recipients will be sent to Documenso. A new tab will open with your Documenso overview. To place the signature fields and send the document for signing, please open the uploaded document in editing mode.')">
						<template #icon>
							<DocumensoIcon />
						</template>
					</NcEmptyContent>
					<div class="documenso-footer">
						<NcButton
							@click="closeRequestModal">
							{{ t('integration_documenso', 'Cancel') }}
						</NcButton>
						<NcButton variant="primary"
							:disabled="!canValidate"
							@click="onSignClick">
							{{ t('integration_documenso', 'Send document') }}
							<template v-if="loading" #icon>
								<NcLoadingIcon />
							</template>
						</NcButton>
					</div>
				</template>
				<template v-else>
					<EmbedUpdateDocumentV1
						class="documenso-embed-iframe"
						:host="host"
						:presign-token="embeddingToken"
						:document-id="documentId"
						:only-edit-fields="true"
						:on-document-updated="onDocumentUpdated" />
				</template>
			</div>
			<NcDialog
				v-model:open="showDialog"
				name="Warning"
				:message="t('integration_documenso', 'Some users did not have a mail address assigned to their account. They were not added as signers.')"
				:no-close="true">
				<template #actions>
					<NcButton
						@click="missingMailConfirmation">
						{{ t('integration_documenso', 'OK') }}
					</NcButton>
				</template>
			</NcDialog>
			<NcDialog
				v-model:open="showSaveChoiceDialog"
				:name="t('integration_documenso', 'Document saved')"
				:message="t('integration_documenso', 'Document saved. Distribute the document now or open Documenso for more details?')">
				<template #actions>
					<NcButton
						:disabled="distributeLoading"
						@click="onDistributeClick">
						<template v-if="distributeLoading" #icon>
							<NcLoadingIcon />
						</template>
						{{ t('integration_documenso', 'Distribute') }}
					</NcButton>
					<NcButton
						@click="onGoToDocumensoClick">
						{{ t('integration_documenso', 'Open in new tab') }}
					</NcButton>
				</template>
			</NcDialog>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import { EmbedUpdateDocumentV1 } from '@documenso/embed-vue'
import MultiselectWho from './MultiselectWho.vue'
import DocumensoIcon from './icons/DocumensoIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
export default {
	name: 'DocumensoModal',

	components: {
		DocumensoIcon,
		NcModal,
		MultiselectWho,
		NcButton,
		NcLoadingIcon,
		NcEmptyContent,
		NcDialog,
		EmbedUpdateDocumentV1,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			fileId: 0,
			selectedItems: [],
			showDialog: false,
			showSaveChoiceDialog: false,
			distributeLoading: false,
			documensoUrl: '',
			embeddingToken: '',
			documentId: 0,
			host: '',
		}
	},

	computed: {
		canValidate() {
			return this.selectedItems.length > 0
		},
		showIframe() {
			return this.embeddingToken !== ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		showModal() {
			this.show = true
			// once the modal is opened, focus on the multiselect input
			this.$nextTick(() => {
				this.$refs.multiselect.$el.querySelector('input').focus()
			})
		},
		closeRequestModal() {
			this.selectedItems = []
			this.show = false
			this.showSaveChoiceDialog = false
			this.host = ''
			this.documentId = 0
			this.embeddingToken = ''
		},
		onDocumentUpdated() {
			this.showSaveChoiceDialog = true
		},
		onDistributeClick() {
			this.distributeLoading = true
			const url = generateUrl('/apps/integration_documenso/documenso/distribute')
			axios.post(url, { documentId: this.documentId }).then(() => {
				showSuccess(t('integration_documenso', 'Document distributed successfully'))
				this.closeRequestModal()
			}).catch((error) => {
				console.error(error.response)
				showError(
					t('integration_documenso', 'Failed to distribute document')
					+ ': ' + (error.response?.data?.response?.message ?? error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
				)
			}).finally(() => {
				this.distributeLoading = false
			})
		},
		onGoToDocumensoClick() {
			this.openDocumentTab(true)
		},
		setFileId(fileId) {
			this.fileId = fileId
		},
		updateSelectedItems(newValue) {
			this.selectedItems = newValue
			console.debug(this.selectedItems)
		},
		onSignClick() {
			this.loading = true

			const targetUserIds = this.selectedItems.filter((i) => { return i.type === 'user' }).map((i) => { return i.entityId })
			const targetEmails = this.selectedItems.filter((i) => { return i.type === 'email' }).map((i) => { return i.email })
			const req = {
				targetUserIds,
				targetEmails,
			}
			const url = generateUrl('/apps/integration_documenso/documenso/standalone-sign/' + this.fileId)
			axios.put(url, req).then((response) => {
				this.documensoUrl = response.data.documensoUrl
				this.host = response.data.host
				this.documentId = response.data.documentId
				this.embeddingToken = response.data.embeddingToken ?? ''

				if (response.data.missingMailCount === 0) {
					this.openDocumentTab()
				} else {
					this.showDialog = true
				}
			}).catch((error) => {
				console.debug(error.response)
				showError(
					t('integration_documenso', 'Failed to request signature with Documenso')
					+ ': ' + (error.response?.data?.response?.message ?? error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
				)
			}).then(() => {
				this.loading = false
			})
		},
		missingMailConfirmation() {
			this.openDocumentTab()
			this.showDialog = false

		},
		openDocumentTab(forceOpen = false) {
			// Only open in new tab as a fallback unless forceOpen
			if (!forceOpen && this.embeddingToken !== '') {
				return
			}
			try {
				window.open(this.documensoUrl, '_blank').focus()
			} catch (error) {
				showError(t('integration_documenso', 'Please allow pop-up windows.'))
			}
			this.closeRequestModal()
		},
	},
}
</script>

<style scoped lang="scss">
.documenso-embed-iframe {
	height: 100%;
	padding-bottom: 16px;
}

.documenso-modal-content {
	padding: 16px;
	// min-height: 400px;
	display: flex;
	flex-direction: column;
	height: 100%;

	input[type='text'] {
		width: 100%;
	}

	.userInput {
		width: 100%;
		margin: 0 0 28px 0;
		--vs-dropdown-max-height: 300px;
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 52px 0 52px 0;
	}

	.modal-title {
		display: flex;
		justify-content: center;
		.modal-title-text {
			margin-left: 8px;
		}
	}
}

.documenso-footer {
	margin-top: 16px;
	display: flex;
	gap: 8px;
	justify-content: end;
}

.field-label {
	display: flex;
	align-items: center;
	height: 36px;
	margin: 8px 0 0 0;
	.icon {
		width: 32px;
	}
}
</style>
