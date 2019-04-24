<?php

namespace Stampie\Tests\Mailer;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Stampie\Mailer\Postmark;
use Stampie\Tests\TestCase;

class PostmarkTest extends TestCase
{
    const SERVER_TOKEN = '5daa75d9-8fad-4211-9b18-49124642732e';

    /**
     * @var Postmark
     */
    private $mailer;

    /**
     * @var HttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    public function setUp()
    {
        $this->httpClient = $this->getMockBuilder(HttpClient::class)->getMock();
        $this->mailer = new Postmark($this->httpClient, self::SERVER_TOKEN);
    }

    public function testSend()
    {
        $response = new Response();

        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!', '<h1>Stampie</h1>', 'Stampie');

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $body = json_decode((string) $request->getBody(), true);

                return
                    $request->getMethod() === 'POST'
                    && (string) $request->getUri() === 'http://api.postmarkapp.com/email'
                    && $request->getHeaderLine('Content-Type') === 'application/json'
                    && $request->getHeaderLine('Accept') === 'application/json'
                    && $request->getHeaderLine('X-Postmark-Server-Token') === self::SERVER_TOKEN
                    && $body == [
                        'From'     => 'bob@example.com',
                        'To'       => 'alice@example.com',
                        'Subject'  => 'Stampie is awesome!',
                        'HtmlBody' => '<h1>Stampie</h1>',
                        'TextBody' => 'Stampie',
                    ];
            }))
            ->willReturn($response);

        $this->mailer->send($message);
    }

    public function testSendTaggable()
    {
        $response = new Response();

        $message = $this->getTaggableMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome', null, null, [], 'tag');

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $body = json_decode((string) $request->getBody(), true);

                return $body == [
                    'From'    => 'bob@example.com',
                    'To'      => 'alice@example.com',
                    'Subject' => 'Stampie is awesome',
                    'Tag'     => 'tag',
                ];
            }))
            ->willReturn($response);

        $this->mailer->send($message);
    }

    public function testSendWithAttachments()
    {
        $response = new Response();

        $message = $this->getAttachmentsMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome', null, null, [], [
            $this->getAttachmentMock('path-1.txt', 'path1.txt', 'text/plain', null),
            $this->getAttachmentMock('path-2.txt', 'path2.txt', 'text/plain', 'id1'),
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $body = json_decode((string) $request->getBody(), true);

                return $body == [
                    'From'        => 'bob@example.com',
                    'To'          => 'alice@example.com',
                    'Subject'     => 'Stampie is awesome',
                    'Attachments' => [
                        [
                            'Name'        => 'path1.txt',
                            'Content'     => base64_encode("Attachment #1\n"),
                            'ContentType' => 'text/plain',
                        ],
                        [
                            'Name'        => 'path2.txt',
                            'Content'     => base64_encode("Attachment #2\n"),
                            'ContentType' => 'text/plain',
                            'ContentID'   => 'id1',
                        ],
                    ],
                ];
            }))
            ->willReturn($response);

        $this->mailer->send($message);
    }

    /**
     * @expectedException \Stampie\Exception\HttpException
     * @expectedExceptionMessage Internal Server Error
     */
    public function testHandleInternalServerError()
    {
        $response = new Response(500);

        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $this->httpClient
            ->method('sendRequest')
            ->willReturn($response);

        $this->mailer->send($message);
    }

    /**
     * @expectedException \Stampie\Exception\HttpException
     * @expectedExceptionMessage Bad Request
     */
    public function testHandlerBadRequest()
    {
        $response = new Response(400);

        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $this->httpClient
            ->method('sendRequest')
            ->willReturn($response);

        $this->mailer->send($message);
    }

    /**
     * @expectedException \Stampie\Exception\ApiException
     * @expectedExceptionMessage Bad Credentials
     */
    public function testHandleBadCredentials()
    {
        $response = new Response(422, [], '{ "Message" : "Bad Credentials" }');

        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $this->httpClient
            ->method('sendRequest')
            ->willReturn($response);

        $this->mailer->send($message);
    }
}
