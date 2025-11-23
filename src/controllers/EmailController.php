<?php

namespace humaninitiative\graph\mailer\controllers;

use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

class EmailController extends Controller
{
    /**
     * Specify the behaviors for this controller.
     *
     * @return array behaviors
     */
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            'verbFilter' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'send' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Send an email using Microsoft Graph API.
     *
     * This action will send an email using Microsoft Graph API. It will validate the input first,
     * then create the email message using method chaining, and finally send the email.
     *
     * @return array response of the action. If the email is sent successfully, it will return a response
     * with a status of 'success' and a message of 'Email successfully sent via Graph API.'.
     * If there is an error, it will return a response with a status of 'error' and a message of the error.
     *
     * The response will be in JSON format.
     */
    public function actionSend()
    {
        $request = Yii::$app->request;

        $from = $request->post('from');
        $rawTo = $request->post('to');
        $subject = $request->post('subject');
        $body = $request->post('body');
        $rawCc = $request->post('cc');
        $rawReplyTo = $request->post('replyTo');
        $uploadedFiles = UploadedFile::getInstancesByName('attachments');

        $toDecoded = $this->validateJsonEmailArray($rawTo, 'to', true);
        if ($toDecoded === false) return;

        $ccDecoded = $this->validateJsonEmailArray($rawCc, 'cc');
        if ($ccDecoded === false) return;

        $replyToDecoded = $this->validateJsonEmailArray($rawReplyTo, 'replyTo');
        if ($replyToDecoded === false) return;

        if (empty($subject) || empty($body)) {
            Yii::$app->response->statusCode = 400;
            return [
                'status' => 'error',
                'message' => "Parameter 'subject' and 'body' is required."
            ];
        }

        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                if ($file->error !== UPLOAD_ERR_OK) {
                    Yii::$app->response->statusCode = 400;
                    return [
                        'status' => 'error', 
                        'message' => 'Failed to upload file: ' . $file->name
                    ];
                }
                if ($file->size > 5 * 1024 * 1024) {
                    Yii::$app->response->statusCode = 400;
                    return [
                        'status' => 'error', 
                        'message' => "File size '$file->name' is too large (max 5MB)."
                    ];
                }
            }
        }

        try {
            $message = Yii::$app->graphMailer->compose()
                ->setFrom($from)
                ->setTo($toDecoded)
                ->setSubject($subject)
                ->setHtmlBody($body);

            if (!empty($ccDecoded)) {
                $message->setCc($ccDecoded);
            }

            if (!empty($replyToDecoded)) {
                $message->setReplyTo($replyToDecoded);
            }

            if (!empty($uploadedFiles)) {
                foreach ($uploadedFiles as $file) {
                    $message->attach($file->tempName, ['fileName' => $file->name]);
                }
            }

            $isSent = $message->send();

            if ($isSent) {
                return [
                    'status' => 'success',
                    'message' => 'Email successfully sent.'
                ];
            } else {
                Yii::$app->response->statusCode = 500;
                return [
                    'status' => 'error',
                    'message' => 'Failed to send email.'
                ];
            }
        } catch (\Exception $e) {
            Yii::error('Error: ' . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return [
                'status' => 'error', 
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate a JSON string as an array of email addresses.
     *
     * @param ?string $jsonString The JSON string to validate
     * @param string $paramName The name of the parameter being validated
     * @param bool $required Whether the parameter is required
     * @return array|false An array of email addresses if the parameter is valid, false otherwise
     */
    private function validateJsonEmailArray(?string $jsonString, string $paramName, bool $required = false)
    {
        if (empty($jsonString)) {
            if ($required) {
                Yii::$app->response->statusCode = 400;
                $this->asJson([
                    'status' => 'error', 
                    'message' => "Parameter '{$paramName}' is required."
                ]);
                return false;
            }
            return [];
        }

        $decoded = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Yii::$app->response->statusCode = 400;
            $this->asJson([
                'status' => 'error', 
                'message' => "Parameter '{$paramName}' must be a valid json array."
            ]);
            return false;
        }

        foreach ($decoded as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Yii::$app->response->statusCode = 400;
                $this->asJson([
                    'status' => 'error', 
                    'message' => "Invalid email format in parameter '{$paramName}': " . $email
                ]);
                return false;
            }
        }

        return $decoded;
    }
}