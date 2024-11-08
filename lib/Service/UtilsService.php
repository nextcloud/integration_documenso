<?php

declare(strict_types=1);

namespace OCA\Documenso\Service;

use Exception;
use OCA\Documenso\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\SystemTag\ISystemTagManager;

class UtilsService {
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IShareManager
	 */
	private $shareManager;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var ISystemTagManager
	 */
	private $tagManager;
	/**
	 * @var ICrypto
	 */
	private $crypto;
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * Service providing storage, circles and tags tools
	 */
	public function __construct(string $appName,
		IUserManager $userManager,
		IShareManager $shareManager,
		IRootFolder $root,
		ISystemTagManager $tagManager,
		IConfig $config,
		ICrypto $crypto) {
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->root = $root;
		$this->tagManager = $tagManager;
		$this->crypto = $crypto;
		$this->config = $config;
	}

	/**
	 * Get decrypted user value
	 *
	 * @return string
	 * @throws Exception
	 * TODO change docu
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
	 * Check if a user is in a given circle
	 *
	 * @param string $userId
	 * @param string $circleId
	 * @return bool
	 */
	public function isUserInCircle(string $userId, string $circleId): bool {
		$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($circleId);
		} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
			$circlesManager->stopSession();
			return false;
		}
		// is the circle owner
		$owner = $circle->getOwner();
		// the owner is also a member so this might be useless...
		if ($owner->getUserType() === 1 && $owner->getUserId() === $userId) {
			$circlesManager->stopSession();
			return true;
		} else {
			$members = $circle->getMembers();
			foreach ($members as $m) {
				// is member of this circle
				if ($m->getUserType() === 1 && $m->getUserId() === $userId) {
					$circlesManager->stopSession();
					return true;
				}
			}
		}
		$circlesManager->stopSession();
		return false;
	}

	/**
	 * Check if user has access to a given file
	 *
	 * @param int $fileId
	 * @param string|null $userId
	 * @return bool
	 */
	public function userHasAccessTo(int $fileId, ?string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$userFolder = $this->root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			return count($found) > 0;
		}
		return false;
	}
}
