<?php

namespace Stampie\Mailer;

use Psr\Http\Message\ResponseInterface;
use Stampie\Attachment;
use Stampie\Exception\ApiException;
use Stampie\Exception\HttpException;
use Stampie\IdentityInterface;
use Stampie\Mailer;
use Stampie\Message\AttachmentsAwareInterface;
use Stampie\Message\MetadataAwareInterface;
use Stampie\Message\TaggableInterface;
use Stampie\MessageInterface;
use Stampie\Util\AttachmentUtils;

/**
 * Mailer to be used with SendGrid Web API.
 *
 * @author Henrik Bjrnskov <henrik@bjrnskov.dk>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SendGrid extends Mailer
{
    /**
     * {@inheritdoc}
     */
    protected function getEndpoint()
    {
        return 'https://api.sendgrid.com/v3/mail/send';
    }

    /**
     * {@inheritdoc}
     */
    protected function handle(ResponseInterface $response)
    {
        $httpException = new HttpException($response->getStatusCode(), $response->getReasonPhrase());

        // 4xx will content error information in the body encoded as JSON
        if (!in_array($response->getStatusCode(), range(400, 417))) {
            throw $httpException;
        }

        $error = json_decode((string) $response->getBody());
        $message = '';
        foreach ($error->errors as $i => $e) {
            $message .= sprintf(
                "ERROR #%d: \nField: %s \nMessage: %s \nHelp: %s\n\n\n",
                $i,
                isset($e->field) ? $e->field : '-',
                isset($e->message) ? $e->message : '-',
                isset($e->help) ? $e->help : '-'
            );
        }

        throw new ApiException($message, $httpException);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaders()
    {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer '.$this->getServerToken(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function format(MessageInterface $message)
    {
        $personalization = [
            'to'      => $this->formatRecipients($message->getTo()),
            'cc'      => $this->formatRecipients($message->getCc()),
            'bcc'     => $this->formatRecipients($message->getBcc()),
            'subject' => $message->getSubject(),
        ];

        if ($headers = $message->getHeaders()) {
            $personalization['headers'] = $headers;
        }

        if (empty($personalization['cc'])) {
            unset($personalization['cc']);
        }

        if (empty($personalization['bcc'])) {
            unset($personalization['bcc']);
        }

        $content = [
            ['type' => 'text/plain', 'value' => $message->getText()],
            ['type' => 'text/html', 'value' => $message->getHtml()],
        ];

        foreach ($content as $i => $c) {
            if (empty($c['value'])) {
                unset($content[$i]);
            }
        }

        $parameters = [
            'personalizations' => [$personalization],
            'from'             => $this->formatRecipients($message->getFrom())[0],
            'reply_to'         => $this->formatRecipients($message->getReplyTo()),
            'content'          => array_values($content),
        ];

        if (empty($parameters['reply_to'])) {
            unset($parameters['reply_to']);
        } else {
            $parameters['reply_to'] = $parameters['reply_to'][0];
        }

        if ($message instanceof AttachmentsAwareInterface) {
            $attachments = $message->getAttachments();
            if (!empty($attachments)) {
                $parameters['attachments'] = $this->processAttachments($attachments);
            }
        }

        if ($message instanceof TaggableInterface) {
            $tags = (array) $message->getTag();
            if (!empty($tags)) {
                $parameters['categories'] = $tags;
            }
        }

        if ($message instanceof MetadataAwareInterface) {
            $metadata = array_filter($message->getMetadata());
            if (!empty($metadata)) {
                if (isset($parameters['custom_args'])) {
                    $parameters['custom_args'] = array_merge($parameters['custom_args'], $metadata);
                } else {
                    $parameters['custom_args'] = $metadata;
                }
            }
        }

        return json_encode($parameters);
    }

    /**
     * @param Attachment[] $attachments
     *
     * @return array First element: All attachments – array(name => path). Second element: Inline attachments – array(id => name)
     */
    protected function processAttachments(array $attachments)
    {
        $attachments = AttachmentUtils::processAttachments($attachments);
        $processedAttachments = [];

        foreach ($attachments as $name => $attachment) {
            $item = [
                'content'  => base64_encode(file_get_contents($attachment->getPath())),
                'type'     => $attachment->getType(),
                'filename' => $attachment->getName(),
            ];

            $id = $attachment->getId();
            if (!empty($id)) {
                $item['disposition'] = 'inline';
                $item['content_id'] = $id;
            }

            $processedAttachments[] = $item;
        }

        return $processedAttachments;
    }

    /**
     * @param IdentityInterface[]|string $recipients
     *
     * @return array
     */
    private function formatRecipients($recipients)
    {
        $data = [];
        foreach ($this->normalizeIdentities($recipients) as $recipient) {
            $item = [
                'email' => $recipient->getEmail(),
                'name'  => $recipient->getName(),
            ];

            if (empty($item['name'])) {
                unset($item['name']);
            }

            $data[] = $item;
        }

        return $data;
    }
}
