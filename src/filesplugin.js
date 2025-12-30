import DocumensoModal from './components/DocumensoModal.vue'

import axios from '@nextcloud/axios'
import { generateUrl, linkTo } from '@nextcloud/router'

import { createApp } from 'vue'
import {
	registerFileAction, Permission, FileAction, FileType,
} from '@nextcloud/files'
import DocumensoIcon from '../img/app-dark.svg'

import { getCSPNonce } from '@nextcloud/auth'

__webpack_nonce__ = getCSPNonce() // eslint-disable-line
__webpack_public_path__ = linkTo('integration_zulip', 'js/') // eslint-disable-line

if (!OCA.Documenso) {
	/**
	 * @namespace
	 */
	OCA.Documenso = {
		requestOnFileChange: false,
		ignoreLists: [
			'trashbin',
			'files.public',
		],
	}
}

const requestSignatureAction = new FileAction({
	id: 'documenso-sign',
	displayName: (nodes) => {
		return t('integration_documenso', 'Request signature with Documenso')
	},
	enabled(nodes, view) {
		return !OCA.Documenso.ignoreLists.includes(view.id)
			&& nodes.length === 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& !nodes.some(({ type }) => type !== FileType.File)
			&& !nodes.some(({ mime }) => mime !== 'application/pdf')
	},
	iconSvgInline: () => DocumensoIcon,
	async exec(node) {
		OCA.Documenso.DocumensoModalVue.$children[0].setFileId(node.fileid)
		OCA.Documenso.DocumensoModalVue.$children[0].showModal()
		return null
	},
})
registerFileAction(requestSignatureAction)

// signature modal
const modalId = 'documensoModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

const app = createApp(DocumensoModal)
app.mixin({ methods: { t, n } })
OCA.Documenso.DocumensoModalVue = app.mount(modalElement)

// is Documenso configured?
const urlDs = generateUrl('/apps/integration_documenso/info')
axios.get(urlDs).then((response) => {
	OCA.Documenso.documensoConnected = response.data.connected
}).catch((error) => {
	console.error(error)
})
