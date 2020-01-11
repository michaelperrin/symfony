<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MandrillApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MandrillApiTransport('KEY'),
                'mandrill+api://mandrillapp.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com'),
                'mandrill+api://example.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'mandrill+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->bcc('baz@example.com')
            ->subject('Email subject')
            ->text('content')
            ->html('<div>HTML content</div>')
            ->embed('example image content', 'IMAGECID', 'image/png');

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                [
                    'email' => 'recipient.email@example.com',
                    'status' => 'sent',
                    'reject_reason' => 'hard-bounce',
                    '_id' => 'abc123abc123abc123abc123abc123',
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://mandrillapp.com/api/1.0/messages/send.json', [
                'json' => [
                    'key' => 'foo',
                    'message' => [
                        'text' => 'content',
                        'html' => '<div>HTML content</div>',
                        'subject' => 'Email subject',
                        'from_email' => 'foo@example.com',
                        'from_name' => 'Ms. Foo Bar',
                        'to' => [
                            [
                                'email' => 'bar@example.com',
                                'name' => 'Mr. Recipient',
                                'type' => 'to',
                            ],
                            [
                                'email' => 'baz@example.com',
                                'type' => 'bcc',
                            ],
                        ],
                        'images' => [
                            [
                                'type' => 'image/png',
                                'name' => 'IMAGECID',
                                'content' => base64_encode('example image content'),
                            ],
                        ],
                    ],
                ],
            ])
            ->willReturn($response);

        $mailer = new MandrillApiTransport('foo', $httpClient);
        $mailer->send($email);
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('headers', $payload['message']);
        $this->assertCount(1, $payload['message']['headers']);
        $this->assertEquals('foo: bar', $payload['message']['headers'][0]);
    }
}
