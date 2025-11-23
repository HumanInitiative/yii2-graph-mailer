<?php

namespace humaninitiative\graph\mailer;

use humaninitiative\graph\AccessToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;

class GraphMailer extends Component
{
    public $tenantId;
    public $clientId;
    public $clientSecret;
    public $email;

    private $_provider;
    private $_accessToken;

    /**
     * Initialize the component
     *
     * This method is called after the object is instantiated.
     * It is called once, immediately after the object is instantiated.
     * You should override this method to perform initialization of the component.
     *
     * @throws InvalidConfigException if the required configuration is missing.
     */
    public function init()
    {
        parent::init();
        if (empty($this->tenantId) || empty($this->clientId) || empty($this->clientSecret) || empty($this->email)) {
            throw new InvalidConfigException('Config GraphMailer (tenantId, clientId, clientSecret, email) is required.');
        }
    }

    /**
     * Get access token to Microsoft Graph
     *
     * This function will return access token to Microsoft Graph,
     * if the access token is not available in cache, it will generate
     * a new access token and store it in cache.
     *
     * @throws \Exception if failed to get access token
     *
     * @return string access token to Microsoft Graph
     */
    protected function getAccessToken()
    {
        $cache = Yii::$app->cache;
        $cacheKey = 'graph_access_token';
        $this->_accessToken = $cache->get($cacheKey);

        if ($this->_accessToken === null || $this->_accessToken === false) {
            try {
                $accessToken = new AccessToken($this->tenantId, $this->clientId, $this->clientSecret);
                $this->_accessToken = $accessToken->generate();
                $cache->set($cacheKey, $this->_accessToken, 3599);
            } catch (\Exception $e) {
                Yii::error('Failed to get Access Token Graph: ' . $e->getMessage(), __METHOD__);
                throw new \Exception('Failed to authenticate with Microsoft Graph.');
            }
        }
        return $this->_accessToken;
    }

    /**
     * Compose a new GraphMessage instance with the given configuration.
     *
     * @param array $config Configuration for GraphMessage
     * @return GraphMessage
     */
    public function compose(array $config = [])
    {
        $message = new GraphMessage($this);
        Yii::configure($message, $config);
        return $message;
    }

    /**
     * Send an email using the Microsoft Graph API.
     *
     * @param GraphMessage $message The message to be sent.
     * @return bool True if the email was sent successfully, false otherwise.
     *
     * @throws InvalidConfigException If the message object is not an instance of GraphMessage.
     */
    public function send($message)
    {
        if (!$message instanceof GraphMessage) {
            throw new InvalidConfigException('Message must be an instance of GraphMessage.');
        }

        $accessToken = $this->getAccessToken();

        $formatRecipients = function (array $emails): array {
            return array_map(function ($email) {
                return ['emailAddress' => ['address' => $email]];
            }, $emails);
        };

        $emailPayload = [
            'message' => [
                'subject' => $message->subject,
                'body' => [
                    'contentType' => !empty($message->htmlBody) ? 'HTML' : 'Text',
                    'content' => $message->htmlBody ?? $message->textBody ?? '',
                ],
                'toRecipients' => $formatRecipients($message->to),
                'from' => ['emailAddress' => ['address' => $this->email]],
            ],
        ];

        if (!empty($message->cc)) {
            $emailPayload['message']['ccRecipients'] = $formatRecipients($message->cc);
        }

        if (!empty($message->bcc)) {
            $emailPayload['message']['bccRecipients'] = $formatRecipients($message->bcc);
        }

        if (!empty($message->replyTo)) {
            $emailPayload['message']['replyTo'] = $formatRecipients($message->replyTo);
        }

        if (!empty($message->attachments)) {
            $formattedAttachments = [];
            foreach ($message->attachments as $attachment) {
                $content = '';
                if (isset($attachment['path'])) {
                    $content = file_get_contents($attachment['path']);
                } elseif (isset($attachment['content'])) {
                    $content = $attachment['content'];
                }

                if ($content) {
                    $formattedAttachments[] = [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $attachment['name'],
                        'contentType' => $attachment['type'],
                        'contentBytes' => base64_encode($content),
                    ];
                }
            }
            if (!empty($formattedAttachments)) {
                $emailPayload['message']['attachments'] = $formattedAttachments;
            }
        }

        $client = new Client();
        $graphEndpoint = "https://graph.microsoft.com/v1.0/users/{$this->email}/sendMail";

        try {
            $response = $client->post($graphEndpoint, [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'json' => $emailPayload,
            ]);
            return $response->getStatusCode() === 202;
        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            Yii::error("Failed to send email: " . $responseBody, __METHOD__);
            throw new \Exception('Failed to send email.');
        }
    }
}