<?php

namespace Stampie\Mailer;

use Psr\Http\Message\ResponseInterface;
use Stampie\Attachment;
use Stampie\Exception\ApiException;
use Stampie\Exception\HttpException;
use Stampie\Mailer;
use Stampie\Message\AttachmentsAwareInterface;
use Stampie\Message\TaggableInterface;
use Stampie\MessageInterface;
use Stampie\Util\AttachmentUtils;

/**
 * Sends emails to Postmark server.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
class Postmark extends Mailer
{
    /**
     * {@inheritdoc}
     */
    protected function getEndpoint()
    {
        return 'https://api.postmarkapp.com/email';
    }

    /**
     * {@inheritdoc}
     */
    protected function handle(ResponseInterface $response)
    {
        $httpException = new HttpException($response->getStatusCode(), $response->getReasonPhrase());

        // Not 422 contains information about API Error
        if ($response->getStatusCode() == 422) {
            $error = json_decode((string) $response->getBody());

            throw new ApiException(isset($error->Message) ? $error->Message : 'Unprocessable Entity', $httpException);
        }

        throw $httpException;
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaders()
    {
        return [
            'Content-Type'            => 'application/json',
            'X-Postmark-Server-Token' => $this->getServerToken(),
            'Accept'                  => 'application/json',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function format(MessageInterface $message)
    {
        $headers = [];
        foreach ($message->getHeaders() as $name => $value) {
            $headers[] = ['Name' => $name, 'Value' => $value];
        }

        $parameters = [
            'From'     => $this->buildIdentityString($message->getFrom()),
            'To'       => $this->buildIdentityString($message->getTo()),
            'Subject'  => $message->getSubject(),
            'Headers'  => $headers,
            'HtmlBody' => $message->getHtml(),
            'TextBody' => $message->getText(),
            'ReplyTo'  => $message->getReplyTo(),
        ];

        if ($message instanceof TaggableInterface) {
            $tag = $message->getTag();

            if (is_array($tag)) {
                $tag = reset($tag);
            }

            $parameters['Tag'] = $tag;
        }

        if ($message instanceof AttachmentsAwareInterface) {
            $attachments = $this->processAttachments($message->getAttachments());

            if ($attachments) {
                $parameters['Attachments'] = $attachments;
            }
        }

        return json_encode(array_filter($parameters));
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    protected function getAttachmentContent(Attachment $attachment)
    {
        return file_get_contents($attachment->getPath());
    }

    /**
     * @param Attachment[] $attachments
     *
     * @return array An array containing arrays of the following format:
     *               array(
     *               'Name'                 => name,
     *               'Content'              => base64-encoded content,
     *               'ContentType'          => type,
     *               (optional) 'ContentID' => id,
     *               )
     */
    protected function processAttachments(array $attachments)
    {
        $attachments = AttachmentUtils::processAttachments($attachments);

        $processedAttachments = [];
        foreach ($attachments as $name => $attachment) {
            $item = [
                'Name'        => $name,
                'Content'     => base64_encode($this->getAttachmentContent($attachment)),
                'ContentType' => $attachment->getType(),
            ];

            $id = $attachment->getId();
            if (isset($id)) {
                $item['ContentID'] = $id;
            }

            $processedAttachments[] = $item;
        }

        return $processedAttachments;
    }
}
