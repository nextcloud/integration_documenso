<?php

declare(strict_types=1);

namespace OCA\Documenso\Service;

use Exception;
use OCA\Documenso\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagManager;

class UtilsService {
	public const WEBHOOK_SECRET_KEY = 'webhook_secret';
	private const WEBHOOK_SECRET_LENGTH = 32;

	/**
	 * Service providing storage, circles and tags tools
	 */
	public function __construct(
		string $appName,
		private IUserManager $userManager,
		private IShareManager $shareManager,
		private IRootFolder $root,
		private ISystemTagManager $tagManager,
		private IConfig $config,
		private ICrypto $crypto,
		private ISecureRandom $secureRandom,
	) {
	}

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
	 * Return the user's webhook secret, creating and encrypting one if needed.
	 */
	public function getOrCreateWebhookSecret(string $userId): string {
		$secret = $this->getEncryptedUserValue($userId, self::WEBHOOK_SECRET_KEY);
		if ($secret !== '') {
			return $secret;
		}

		$secret = $this->secureRandom->generate(self::WEBHOOK_SECRET_LENGTH, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->setEncryptedUserValue($userId, self::WEBHOOK_SECRET_KEY, $secret);
		return $secret;
	}

	/**
	 * Get a file by its ID and user ID
	 *
	 * @param int $fileId
	 * @param string $userId
	 * @return Node|null
	 */
	public function getFile(int $fileId, string $userId): ?Node {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$userFolder = $this->root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			return $found[0] ?? null;
		}
		return null;
	}
}
