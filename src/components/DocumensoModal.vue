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
					<NcCheckboxRadioSwitch
						v-model="overwriteOriginal"
						:disabled="!canOverwriteOriginal"
						class="overwrite-switch">
						{{ t('integration_documenso', 'Overwrite original file when signed') }}
					</NcCheckboxRadioSwitch>
					<template v-if="!overwriteOriginal">
						<span class="field-label">
							{{ t('integration_documenso', 'Save signed file as') }}
						</span>
						<div class="line destination-folder">
							<label for="documenso-output-folder">
								<FolderOutlineIcon />
								{{ t('integration_documenso', 'Destination folder') }}
							</label>
							<input id="documenso-output-folder"
								:readonly="true"
								:value="targetFolder">
							<NcButton class="edit-output-dir"
								:aria-label="t('integration_documenso', 'Select a destination folder')"
								@click="pickFolder">
								<template #icon>
									<PencilOutlineIcon />
								</template>
							</NcButton>
						</div>
						<NcTextField
							v-model="targetName"
							class="destination-name"
							:label="t('integration_documenso', 'File name')"
							:placeholder="t('integration_documenso', 'document_signed.pdf')" />
					</template>
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import { EmbedUpdateDocumentV1 } from '@documenso/embed-vue'
import MultiselectWho from './MultiselectWho.vue'
import DocumensoIcon from './icons/DocumensoIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
export default {
	name: 'DocumensoModal',

	components: {
		DocumensoIcon,
		NcModal,
		MultiselectWho,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcEmptyContent,
		NcDialog,
		NcTextField,
		FolderOutlineIcon,
		PencilOutlineIcon,
		EmbedUpdateDocumentV1,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			fileId: 0,
			selectedItems: [],
			overwriteOriginal: false,
			canOverwriteOriginal: true,
			targetFolder: '',
			targetName: '',
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
			if (this.selectedItems.length === 0) {
				return false
			}
			if (this.overwriteOriginal) {
				return true
			}
			return this.targetFolder !== '' && this.targetName.trim() !== ''
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
			this.overwriteOriginal = false
			this.canOverwriteOriginal = true
			this.targetFolder = ''
			this.targetName = ''
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
		setSourceFile(fileId, targetFolder, targetName, canOverwriteOriginal = true) {
			this.fileId = fileId
			this.targetFolder = targetFolder
			this.targetName = targetName
			this.canOverwriteOriginal = canOverwriteOriginal
			if (!canOverwriteOriginal) {
				this.overwriteOriginal = false
			}
		},
		async pickFolder() {
			const picker = getFilePickerBuilder(t('integration_documenso', 'Select a destination folder'))
				.startAt(this.targetFolder)
				.setMultiSelect(false)
				.addMimeTypeFilter('httpd/unix-directory')
				.allowDirectories(true)
				.addButton({
					label: t('integration_documenso', 'Select'),
					callback: (nodes) => {
						const node = nodes[0]
						if (node) {
							console.debug(node.path)
							this.targetFolder = node.path
						}
					},
				})
				.build()

			try {
				await picker.pick()
			} catch {
				// picker closed without selecting
			}
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
				overwriteOriginal: this.overwriteOriginal,
				targetFolder: this.targetFolder,
				targetName: this.targetName.trim(),
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

	.line {
		display: flex;

		label {
			display: flex;
			align-items: center;
			margin-right: 8px;
			white-space: nowrap;

			.material-design-icon {
				margin-right: 8px;
			}
		}

		input {
			flex: 1;
			min-width: 0;
			width: auto;
		}
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
