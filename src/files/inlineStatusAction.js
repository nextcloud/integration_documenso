/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { Permission } from '@nextcloud/files'

import DraftIconSvg from '../../img/dash_draft.svg'
import PendingIconSvg from '../../img/dash_pending.svg'
import CompleteIconSvg from '../../img/dash_complete.svg'
import RejectedIconSvg from '../../img/dash_rejected.svg'
import CancelledIconSvg from '../../img/dash_cancelled.svg'

const STATUSES = {
	DRAFT: 'DRAFT',
	PENDING: 'PENDING',
	COMPLETED: 'COMPLETED',
	REJECTED: 'REJECTED',
	CANCELLED: 'CANCELLED',
}

const KNOWN_STATUSES = Object.values(STATUSES)

/**
 * @param {import('@nextcloud/files').Node[]} nodes File nodes from the Files app
 * @return {string}
 */
function getStatus(nodes) {
	if (nodes.length !== 1) {
		return ''
	}
	return nodes[0].attributes['documenso-state'] ?? ''
}

export const inlineStatusAction = {
	id: 'documenso-inline',
	title: ({ nodes }) => {
		const status = getStatus(nodes)
		switch (status) {
		case STATUSES.DRAFT:
			return t('integration_documenso', 'Document is a draft in Documenso')
		case STATUSES.PENDING:
			return t('integration_documenso', 'Waiting for signatures in Documenso')
		case STATUSES.COMPLETED:
			return t('integration_documenso', 'Document was signed in Documenso')
		case STATUSES.REJECTED:
			return t('integration_documenso', 'Document was rejected in Documenso')
		case STATUSES.CANCELLED:
			return t('integration_documenso', 'Document was cancelled in Documenso')
		default:
			return ''
		}
	},
	displayName: ({ nodes }) => {
		const status = getStatus(nodes)
		switch (status) {
		case STATUSES.DRAFT:
			return t('integration_documenso', 'Draft')
		case STATUSES.PENDING:
			return t('integration_documenso', 'Waiting for signatures')
		case STATUSES.COMPLETED:
			return t('integration_documenso', 'Completed')
		case STATUSES.REJECTED:
			return t('integration_documenso', 'Rejected')
		case STATUSES.CANCELLED:
			return t('integration_documenso', 'Cancelled')
		default:
			return ''
		}
	},
	inline: () => true,
	exec: async () => null,
	order: -10,
	iconSvgInline({ nodes }) {
		const status = getStatus(nodes)
		switch (status) {
		case STATUSES.DRAFT:
			return DraftIconSvg
		case STATUSES.PENDING:
			return PendingIconSvg
		case STATUSES.COMPLETED:
			return CompleteIconSvg
		case STATUSES.REJECTED:
			return RejectedIconSvg
		case STATUSES.CANCELLED:
			return CancelledIconSvg
		default:
			return PendingIconSvg
		}
	},
	enabled({ nodes }) {
		if (nodes.length !== 1) {
			return false
		}
		const node = nodes[0]
		const status = getStatus(nodes)
		return (node.permissions & Permission.READ) !== 0
			&& KNOWN_STATUSES.includes(status)
	},
}
