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

		return $this->emailSignRequest(
			$file,
			$signers,
			$ccEmail, $ccName
		);
	}

	/**
	 * Start the Documenso email signature flow
	 *
	 * @param int $fileId
	 * @param string $signerUserId
	 * @param string|null $ccUserId
	 * @return array result or error
	 */
	public function emailSignByApprover(int $fileId, string $signerUserId, ?string $ccUserId = null): array {
		$found = $this->root->getById($fileId);
		if (count($found) > 0) {
			$file = $found[0];
		} else {
			return ['error' => 'File not found'];
		}

		$signerUser = $this->userManager->get($signerUserId);
		if ($signerUser === null) {
			return ['error' => 'Signer user not found'];
		}
		$signer = [
			'email' => $signerUser->getEMailAddress(),
			'name' => $signerUser->getDisplayName(),
		];

		$ccEmail = null;
		$ccName = null;
		if (!is_null($ccUserId)) {
			$ccUser = $this->userManager->get($ccUserId);
			if ($ccUser === null) {
				return ['error' => 'CC user not found'];
			}
			$ccName = $ccUser->getDisplayName();
			$ccEmail = $ccUser->getEMailAddress();
		}

		return $this->emailSignRequest(
			$file,
			[$signer],
			$ccEmail, $ccName
		);
	}

	/**
	 * Build and sent the enveloppe to Documenso
	 *
	 * @param File $file
	 * @param array $signers
	 * @param string|null $ccEmail
	 * @param string|null $ccName
	 * @return array request result
	 */
	public function emailSignRequest(File $file,
		array $signers,
		?string $ccEmail, ?string $ccName): array {
		$accessToken = $this->utilsService->getEncryptedAppValue('token');
		$baseURI = $this->config->getAppValue(Application::APP_ID, 'url');

		$docB64 = base64_encode($file->getContent());
		$enveloppe = [
			'emailSubject' => $this->l10n->t('Signature of %s', $file->getName()),
			'documents' => [
				[
					'documentBase64' => $docB64,
					'name' => $file->getName(),
					'fileExtension' => 'pdf',
					'documentId' => $file->getId(),
				],
			],
			'recipients' => [
				'carbonCopies' => [],
				'signers' => [],
			],
			'status' => 'sent',
		];

		// signers
		foreach ($signers as $k => $signer) {
			$enveloppe['recipients']['signers'][] = [
				'email' => $signer['email'],
				'name' => $signer['name'],
				'recipientId' => intval($k) + 1,
				'routingOrder' => '1',
				'tabs' => [
					'signHereTabs' => [
						[
							'anchorString' => '**signature_1**',
							'anchorUnits' => 'pixels',
							'anchorXOffset' => '20',
							'anchorYOffset' => '10',
						],
						[
							'anchorString' => '/sn1/',
							'anchorUnits' => 'pixels',
							'anchorXOffset' => '20',
							'anchorYOffset' => '10',
						],
					],
				],
			];
		}

		// CC is optional
		if ($ccName && $ccEmail) {
			$enveloppe['recipients']['carbonCopies'][] = [
				'email' => $ccEmail,
				'name' => $ccName,
				'recipientId' => '99',
				'routingOrder' => '99',
			];
		}

		$endPoint = '/restapi/v2.1/accounts/' . $accountId .'/envelopes';
		return $this->apiRequest($baseURI, $accessToken, $refreshToken, $clientID, $clientSecret, $endPoint, $enveloppe, 'POST');
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
	public function apiRequest(?string $baseUrl, string $accessToken, string $refreshToken,
		string $clientId, string $clientSecret,
		string $endPoint = '', array $params = [], string $method = 'GET'): array {

		$accessToken = $this->utilsService->getEncryptedAppValue('documenso_token');
		try {
			$url = $baseUrl . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'User-Agent' => 'Nextcloud Documenso integration',
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
			];
		} catch (ConnectException $e) {
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, string $clientId, string $clientSecret,
		array $params = [], string $method = 'GET'): array {
		try {
			$b64Credentials = base64_encode($clientId . ':' . $clientSecret);
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Documenso integration',
					'Authorization' => 'Basic ' . $b64Credentials,
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
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
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('Documenso OAuth error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
