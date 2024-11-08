<?php

declare(strict_types=1);

namespace OCA\Documenso\Service;

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Documenso\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class DocumensoAPIService {

	private $l10n;
	private $logger;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var IClient
	 */
	private $client;
	/**
	 * @var UtilsService
	 */
	private $utilsService;

	/**
	 * Service to make requests to Documenso
	 */
	public function __construct(IUserManager $userManager,
		string $appName,
		LoggerInterface $logger,
		IL10N $l10n,
		IConfig $config,
		IRootFolder $root,
		IClientService $clientService,
		UtilsService $utilsService) {
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->root = $root;
		$this->client = $clientService->newClient();
		$this->utilsService = $utilsService;
	}

	/**
	 * Start the Documenso email signature flow
	 *
	 * @param int $fileId
	 * @param string $ccUserId
	 * @param array $targetEmails
	 * @param array $targetUserIds
	 * @return array result or error
	 */
	public function emailSignStandalone(int $fileId, string $ccUserId, array $targetEmails = [], array $targetUserIds = []): array {
		$found = $this->root->getById($fileId);
		if (count($found) > 0) {
			$file = $found[0];
		} else {
			return ['error' => 'File not found'];
		}

		$signers = [];

		foreach ($targetEmails as $targetEmail) {
			$signers[] = [
				'name' => $targetEmail,
				'email' => $targetEmail,
			];
		}

		foreach ($targetUserIds as $targetUserId) {
			$targetUser = $this->userManager->get($targetUserId);
			if ($targetUser !== null) {
				$signers[] = [
					'name' => $targetUser->getDisplayName(),
					'email' => $targetUser->getEMailAddress(),
				];
			}
		}

		// cc user is the one who requested the signature
		$ccUser = $this->userManager->get($ccUserId);
		if ($ccUser === null) {
			return ['error' => 'CC user not found'];
		}
		$ccName = $ccUser->getDisplayName();
		$ccEmail = $ccUser->getEMailAddress();

		$uploadEndpoint = $this->requestUploadEndpoint(
			$file,
			$ccUserId,
			$signers, 
			$ccEmail, $ccName
		);

		if (isset($uploadEndpoint['error'])) {
			return $uploadEndpoint;
		};

		return $this->uploadFile($file, $uploadEndpoint, $ccUserId);
	}

	/**
	 * Build and sent the envelope to Documenso
	 *
	 * @param File $file
	 * @param array $signers
	 * @param string|null $ccEmail
	 * @param string|null $ccName
	 * @return array request result
	 */

	public function requestUploadEndpoint(File $file, ?string $ccUserId, array $signers,
		?string $ccEmail, ?string $ccName): array {
		$token = $this->utilsService->getEncryptedUserValue($ccUserId, 'token');
		$baseUrl = $this->config->getUserValue($ccUserId, Application::APP_ID, 'url');

		$envelope = [
			'title' => $file->getName(),
			'externalId' => 'test',
			'recipients' => $signers, 
			'meta' => [
				'subject' => 'string',
				'message' => 'test',
			],
		];

		$endPoint = 'api/v1/documents';
		return $this->apiRequest($baseUrl, $token,$endPoint, $envelope, 'POST');
	}

	public function uploadFile(File $file, array $uploadEndpoint, $ccUserId): array {
		$options = [
			'body' => $file->getContent(),
		];

		$response = $this->client->put($uploadEndpoint['uploadUrl'], $options);
		$body = $response->getBody();
		$respCode = $response->getStatusCode();

		if ($respCode >= 400) {
			return ['error' => $this->l10n->t('Bad credentials')];
		} else {
			$baseUrl = $this->config->getUserValue($ccUserId, Application::APP_ID, 'url');
			return [
				'body' => json_decode($body, true),
				'documentUrl' => $baseUrl . 'documents/' . $uploadEndpoint['documentId'] . '/edit',
			];
		}
	}

	/**
	 * @param string|null $baseUrl
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 * @throws Exception
	 */
	public function apiRequest(?string $baseUrl, string $token,
		string $endPoint = '', array $params = [], string $method = 'GET'): array {

		try {
			$url = $baseUrl . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => $token,
					'Content-Type' => 'application/json',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			// parse response
			$this->logger->warning('Documenso API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return [
				'error' => $e->getMessage(),
				'response' => json_decode($body, true),
				'request' => $options,
			];
		} catch (ConnectException $e) {
			return ['error' => $e->getMessage()];
		}
	}
}
