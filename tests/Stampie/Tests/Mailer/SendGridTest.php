<?php

namespace Stampie\Tests\Mailer;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpClient;
use Stampie\Mailer\SendGrid;
use Stampie\Tests\TestCase;

class SendGridTest extends TestCase
{
    const SERVER_TOKEN = 'rudolph:rednose';

    /**
     * @var SendGrid
     */
    private $mailer;

    /**
     * @var HttpClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    public function setUp()
    {
        $this->httpClient = $this->getMockBuilder(HttpClient::class)->getMock();
        $this->mailer = new SendGrid($this->httpClient, self::SERVER_TOKEN);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidServerToken()
    {
        $this->mailer->setServerToken('');
    }

    public function testSend()
    {
        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!', 'Trying out Stampie', null, [
            'X-Custom-Header' => 'My Custom Header Value',
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                return
                    $request->getMethod() === 'POST'
                    && (string) $request->getUri() === 'https://api.sendgrid.com/v3/mail/send'
                    && $request->getHeaderLine('Content-Type') === 'application/json'
                    && $request->getHeaderLine('Authorization') === 'Bearer '.self::SERVER_TOKEN
                    && (string) $request->getBody() === json_encode([
                        'personalizations' => [[
                                'to' => [[
                                        'email' => 'alice@example.com',
                                    ]],
                                'subject' => 'Stampie is awesome!',
                                'headers' => ['X-Custom-Header' => 'My Custom Header Value'],
                            ]],
                        'from' => [
                               'email' => 'bob@example.com',
                           ],
                        'content' => [
                                ['type' => 'text/html', 'value' => 'Trying out Stampie'],
                            ],
                    ]);
            }))
            ->willReturn(new Response());

        $this->mailer->send($message);
    }

    public function testSendWithAttachments()
    {
        $message = $this->getAttachmentsMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!', null, null, [], [
            $this->getAttachmentMock('path-1.txt', 'path1.txt', 'text/plain', null),
            $this->getAttachmentMock('path-2.txt', 'path2.txt', 'text/plain', 'id1'),
            $this->getAttachmentMock('path-3.txt', 'path3.txt', 'text/plain', null),
        ]);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                $body = (string) $request->getBody();

                return
                    false !== strpos($body, base64_encode("Attachment #1\n"))
                    && false !== strpos($body, base64_encode("Attachment #2\n"))
                    && false !== strpos($body, base64_encode("Attachment #3\n"));
            }))
            ->willReturn(new Response());

        $this->mailer->send($message);
    }

    public function testSendTaggable()
    {
        $message = $this->getTaggableMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!', null, null, [], ['tag']);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                return (string) $request->getBody() === json_encode([
                    'personalizations' => [[
                            'to' => [[
                                'email' => 'alice@example.com',
                            ]],
                            'subject' => 'Stampie is awesome!',
                        ]],
                        'from' => [
                            'email' => 'bob@example.com',
                        ],
                        'content' => [],
                    'categories'  => ['tag'],
                ]);
            }))
            ->willReturn(new Response());

        $this->mailer->send($message);
    }

    public function testSendMetadataAware()
    {
        $message = $this->getMetadataAwareMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!', null, null, [], ['client_name' => 'Stampie']);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                return (string) $request->getBody() === json_encode([
                        'personalizations' => [[
                            'to' => [[
                                'email' => 'alice@example.com',
                            ]],
                            'subject' => 'Stampie is awesome!',
                        ]],
                        'from' => [
                            'email' => 'bob@example.com',
                        ],
                        'content'    => [],
                        'custom_args'=> ['client_name' => 'Stampie'],
                    ]);
            }))
            ->willReturn(new Response());

        $this->mailer->send($message);
    }

    public function testSendEmptyMetadata()
    {
        $message = $this->getMetadataAwareMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Request $request) {
                return (string) $request->getBody() === json_encode([
                        'personalizations' => [[
                            'to' => [[
                                'email' => 'alice@example.com',
                            ]],
                            'subject' => 'Stampie is awesome!',
                        ]],
                        'from' => [
                            'email' => 'bob@example.com',
                        ],
                        'content' => [],
                    ]);
            }))
            ->willReturn(new Response());

        $this->mailer->send($message);
    }

    /**
     * @expectedException \Stampie\Exception\ApiException
     */
    public function testHandleBadRequest()
    {
        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $response = new Response(400, [], '{ "errors" : ["Error In an Array"] }');

        $this->httpClient
            ->method('sendRequest')
            ->willReturn($response);

        $this->mailer->send($message);
    }

    /**
     * @expectedException \Stampie\Exception\HttpException
     */
    public function testHandleInternalServerError()
    {
        $message = $this->getMessageMock('bob@example.com', 'alice@example.com', 'Stampie is awesome!');

        $response = new Response(500);

        $this->httpClient
            ->method('sendRequest')
            ->willReturn($response);

        $this->mailer->send($message);
    }
}
