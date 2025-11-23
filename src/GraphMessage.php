<?php

namespace humaninitiative\graph\mailer;

use yii\base\BaseObject;

class GraphMessage extends BaseObject
{
    public $from;
    public $to = [];
    public $cc = [];
    public $bcc = [];
    public $replyTo = [];
    public $subject;
    public $textBody;
    public $htmlBody;
    public $attachments = [];

    private $_mailer;

    /**
     * Constructor for GraphMessage
     *
     * @param GraphMailer $mailer Instance of GraphMailer
     * @param array $config Configuration for GraphMessage
     */
    public function __construct(GraphMailer $mailer, $config = [])
    {
        $this->_mailer = $mailer;
        parent::__construct($config);
    }

    /**
     * Set the sender of the message
     *
     * @param string $from The sender of the message
     * @return static
     */
    public function setFrom($from)
    {
        $this->from = $from;
        return $this;
    }
    
    /**
     * Set the recipients of the message
     *
     * @param string|array $to The recipient(s) of the message, can be a string or an array of strings
     * @return static
     */
    public function setTo($to)
    {
        $this->to = (array) $to;
        return $this;
    }
    
    /**
     * Set the carbon copy (CC) recipients of the message
     *
     * @param string|array $cc The CC recipient(s) of the message, can be a string or an array of strings
     * @return static
     */
    public function setCc($cc)
    {
        $this->cc = (array) $cc;
        return $this;
    }
    
    /**
     * Set the blind carbon copy (BCC) recipients of the message
     *
     * @param string|array $bcc The BCC recipient(s) of the message, can be a string or an array of strings
     * @return static
     */
    public function setBcc($bcc)
    {
        $this->bcc = (array) $bcc;
        return $this;
    }
    
    /**
     * Set the reply-to addresses of the message
     *
     * @param string|array $replyTo The reply-to address(es) of the message, can be a string or an array of strings
     * @return static
     */
    public function setReplyTo($replyTo)
    {
        $this->replyTo = (array) $replyTo;
        return $this;
    }
    
    /**
     * Set the subject of the message
     *
     * @param string $subject The subject of the message
     * @return static
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }
    
    /**
     * Set the plain text body of the message
     *
     * @param string $text The plain text body of the message
     * @return static
     */
    public function setTextBody($text)
    {
        $this->textBody = $text;
        return $this;
    }
    
    /**
     * Set the HTML body of the message
     *
     * @param string $html The HTML body of the message
     * @return static
     */
    public function setHtmlBody($html)
    {
        $this->htmlBody = $html;
        return $this;
    }

    /**
     * Attach a file to the message
     *
     * @param string $filePath The path of the file to attach
     * @param array $options The options for the attachment
     * @return static
     *
     * Options:
     *   - fileName: The name of the file (default is the basename of the path)
     *   - contentType: The content type of the file (default is the mime type of the file)
     */
    public function attach($filePath, array $options = [])
    {
        $fileName = basename($filePath);
        if (isset($options['fileName'])) {
            $fileName = $options['fileName'];
        }

        $this->attachments[] = [
            'path' => $filePath,
            'name' => $fileName,
            'type' => $options['contentType'] ?? mime_content_type($filePath),
        ];
        return $this;
    }

    
    /**
     * Attach a content to the message
     *
     * @param string $content The content of the attachment
     * @param string $fileName The name of the file
     * @param array $options The options for the attachment
     * @return static
     *
     * Options:
     *   - contentType: The content type of the file (default is 'application/octet-stream')
     */
    public function attachContent($content, $fileName, array $options = [])
    {
        $this->attachments[] = [
            'content' => $content,
            'name' => $fileName,
            'type' => $options['contentType'] ?? 'application/octet-stream',
        ];
        return $this;
    }

    /**
     * Send the message
     *
     * @return mixed The result of the send operation
     */
    public function send()
    {
        return $this->_mailer->send($this);
    }
}