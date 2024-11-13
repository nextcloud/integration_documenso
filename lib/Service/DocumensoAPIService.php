<?php

declare(strict_types=1);

namespace OCA\Documenso\Service;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Documenso\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class DocumensoAPIService {
	private IClient $client; // stays

	/**
	 * Service to make requests to Documenso
	 */
	public function __construct(
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private IRootFolder $root,
		IClientService $clientService,
		//stays
		private UtilsService $utilsService,
	) {
		$this->client = $clientService->newClient(); // stays
	}

	/**
	 * Start the Documenso email signature flow
	 *
	 * @param int $fileId
	 * @param string $ccUserId
	 * @param string[] $targetEmails
	 * @param string[] $targetUserIds
	 * @return array result or error
	 */
	public function emailSignStandalone(int $fileId, string $ccUserId, array $targetEmails = [], array $targetUserIds = []): array {
		$found = $this->root->getById($fileId);
		if (count($found) > 0) {
			/** @var File $file */
			$file = $found[0];
		} else {
			return ['error' => 'File not found'];
		}

		$signers = [];
		$missingMailCount = 0;
		foreach ($targetEmails as $targetEmail) {
			$signers[] = [
				'name' => $targetEmail,
				'email' => $targetEmail,
			];
		}

		foreach ($targetUserIds as $targetUserId) {
			$targetUser = $this->userManager->get($targetUserId);
			if ($targetUser !== null && $targetUser->getEMailAddress() !== null) {
				$signers[] = [
					'name' => $targetUser->getDisplayName(),
					'email' => $targetUser->getEMailAddress(),
				];
			}
			else {
				$missingMailCount ++;
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
		);

		if (isset($uploadEndpoint['error'])) {
			return $uploadEndpoint;
		};

		$response = $this->uploadFile($file, $uploadEndpoint, $ccUserId);
		$response['missingMailCount'] = $missingMailCount;
		return $response;
	}

	/**
	 * Build and send the envelope to Documenso
	 *
	 * @param File $file
	 * @param string $ccUserId 
	 * @param array $signers
	 * @return array request result
	 */

	public function requestUploadEndpoint(File $file, string $ccUserId, 
		array $signers,): array {
		$token = $this->utilsService->getEncryptedUserValue($ccUserId, 'token');
		$baseUrl = $this->config->getUserValue($ccUserId, Application::APP_ID, 'url');

		/** @var array<string, string|string[]> $envelope */
		$envelope = [
			'title' => $file->getName(),
			'recipients' => $signers,
			'meta' => [
				'signingOrder' => 'PARALLEL',
			],
		];

		$endPoint = 'api/v1/documents';
		return $this->apiRequest($baseUrl, $token, $endPoint, $envelope, 'POST');
	}

	/**
	 * Send the document to a provided upload endpoint
	 *
	 * @param File $file
	 * @param array $uploadEndpoint 
	 * @param string $ccUserId 
	 * @return array request result
	 */
	public function uploadFile(File $file, array $uploadEndpoint, string $ccUserId): array {
		$options = [
			'body' => $file->getContent(),
		];

		$response = $this->client->put($uploadEndpoint['uploadUrl'], $options);
		/** @var string $body */
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


	public function getDocumentList($UserId): array {
		$token = $this->utilsService->getEncryptedUserValue($UserId, 'token');
		$baseUrl = $this->config->getUserValue($UserId, Application::APP_ID, 'url');
		$endPoint = 'api/v1/documents';
		$params = [
			'perPage' => 10,
		];
		return $this->apiRequest($baseUrl, $token, $endPoint, $params);
	}

	/**
	 * @param string $baseUrl
	 * @param string $token
	 * @param string $endPoint
	 * @param array<string, string|string[]> $params
	 * @param string $method
	 * @return array request result
	 * @throws Exception
	 */
	public function apiRequest(string $baseUrl, string $token,
		string $endPoint = '', array $params = [], string $method = 'GET'): array {

		$url = $baseUrl . $endPoint;
		$options = [
			'headers' => [
				'Authorization' => $token,
				'Content-Type' => 'application/json',
			]
		];

		try {
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
			/** @var string $body */
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException|ClientException $e) {
			$response = $e->getResponse();
			$body = (string)$response->getBody();
			// parse response
			$this->logger->warning('Documenso API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
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
