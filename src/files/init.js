/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerFileAction } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'

import { inlineStatusAction } from './inlineStatusAction.js'

registerDavProperty('nc:documenso-state', { nc: 'http://nextcloud.org/ns' })
registerFileAction(inlineStatusAction)
