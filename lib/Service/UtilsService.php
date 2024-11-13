<?php

declare(strict_types=1);

namespace OCA\Documenso\Service;

use Exception;
use OCA\Documenso\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagManager;

class UtilsService {
	/**
	 * Service providing storage, circles and tags tools
	 */
	public function __construct(string $appName,
		private IUserManager $userManager,
		private IShareManager $shareManager,
		private IRootFolder $root,
		private ISystemTagManager $tagManager,
		private IConfig $config,
		private ICrypto $crypto) {	}

	/**
	 * Get decrypted user value
	 *
	 * @param string $userId 
	 * @param string $key 
	 * @return string
	 * @throws Exception
	 */
	public function getEncryptedUserValue(string $userId, string $key): string {
		$storedValue = $this->config->getUserValue($userId, Application::APP_ID, $key);
		if ($storedValue === '') {
			return '';
		}
		return $this->crypto->decrypt($storedValue);
	}

	/**
	 * Store encrypted user secret
	 *
	 * @param string $userId 
	 * @param string $key 
	 * @param string $value
	 * @return void
	 */
	public function setEncryptedUserValue(string $userId, string $key, string $value): void {
		if ($value === '') {
			$this->config->setUserValue($userId, Application::APP_ID, $key, '');
		} else {
			$encryptedUserSecret = $this->crypto->encrypt($value);
			$this->config->setUserValue($userId, Application::APP_ID, $key, $encryptedUserSecret);
		}
	}

	/**
	 * Check if user has access to a given file
	 *
	 * @param int $fileId
	 * @param string $userId
	 * @return bool
	 */
	public function userHasAccessTo(int $fileId, string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$userFolder = $this->root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			return !empty($found);
		}
		return false;
	}
}
