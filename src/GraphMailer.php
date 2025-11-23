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

    public $viewPath = '@app/mail';

    public $htmlLayout = 'layouts/html';

    public $textLayout = 'layouts/text';

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
     * Compose a new message.
     *
     * This function will return a new message, and if a view is specified,
     * it will render the content of the view and set it to the message.
     *
     * @param string|null $view The name of the view to render and set to the message.
     * @param array $params The parameters to pass to the view.
     *
     * @return GraphMessage The composed message.
     */
    public function compose($view = null, array $params = [])
    {
        $message = new GraphMessage($this);

        if ($view !== null) {
            $this->renderContent($message, $view, $params);
        }

        Yii::configure($message, $params);
        return $message;
    }

    /**
     * Renders the content of a view and sets it to the message.
     *
     * This function will first render the HTML view of the specified view,
     * and then render the text view of the specified view. If the
     * htmlLayout or textLayout properties are set, it will
     * render the content of the view inside the layout and set
     * the rendered content to the message.
     *
     * @param GraphMessage $message The message to render the content for.
     * @param string $view The name of the view to render.
     * @param array $params The parameters to pass to the view.
     *
     * @return void
     */
    protected function renderContent($message, $view, $params = [])
    {
        $params['message'] = $message;
        $viewComponent = Yii::$app->view;

        // Render HTML Body
        $htmlViewFile = $this->findViewFile($view, 'html');
        if ($htmlViewFile !== null) {
            $htmlContent = $viewComponent->renderFile($htmlViewFile, $params);
            if ($this->htmlLayout) {
                $layoutFile = Yii::getAlias($this->viewPath) . '/' . $this->htmlLayout . '.php';
                $htmlContent = $viewComponent->renderFile($layoutFile, ['content' => $htmlContent, 'message' => $message], $this);
            }
            $message->setHtmlBody($htmlContent);
        }

        // Render Text Body
        $textViewFile = $this->findViewFile($view, 'text');
        if ($textViewFile !== null) {
            $textContent = $viewComponent->renderFile($textViewFile, $params);
            if ($this->textLayout) {
                $layoutFile = Yii::getAlias($this->viewPath) . '/' . $this->textLayout . '.php';
                $textContent = $viewComponent->renderFile($layoutFile, ['content' => $textContent, 'message' => $message], $this);
            }
            $message->setTextBody($textContent);
        }
    }

    /**
     * Finds a view file in the specified view path.
     * The view file can be either a specific view file (e.g. 'view-html.php') or a generic view file (e.g. 'view.php').
     * If the view file is found, the path to the view file is returned, otherwise null is returned.
     *
     * @param string $view The name of the view to find.
     * @param string $type The type of the view to find (e.g. 'html' or 'text').
     * @return string|null The path to the view file, or null if not found.
     */
    protected function findViewFile($view, $type)
    {
        $viewPath = Yii::getAlias($this->viewPath);
        
        // search for specific view
        $specificViewFile = $viewPath . '/' . $view . '-' . $type . '.php';
        if (is_file($specificViewFile)) {
            return $specificViewFile;
        }

        // search for generic view
        $genericViewFile = $viewPath . '/' . $view . '.php';
        if (is_file($genericViewFile)) {
            return $genericViewFile;
        }

        return null;
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