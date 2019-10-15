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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\Mime\NamedAddress;

class MandrillApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function testPayload()
    {
        $email = new Email();
        $email
            ->from(new NamedAddress('test@example.com', 'My Name'))
            ->subject('Subject')
            ->text('Message')
            ->to(
                new NamedAddress('recipient@example.com', 'Recipient Name')
            )
        ;

        new MandrillApiTransport('KEY')
    }

    public function testSend()
    {
        $email = new Email();
        $email
            ->from(new NamedAddress('test@example.com', 'My Name'))
            ->subject('Subject')
            ->text('Message')
            ->to(
                new NamedAddress('recipient@example.com', 'Recipient Name')
            )
            ->bcc('baz@example.com')
        ;

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response
            ->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['x-message-id' => '1']);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'json' => [
                    'personalizations' => [
                        [
                            'to' => [['email' => 'bar@example.com']],
                            'subject' => null,
                            'bcc' => [['email' => 'baz@example.com']],
                        ],
                    ],
                    'from' => ['email' => 'foo@example.com'],
                    'content' => [
                        ['type' => 'text/plain', 'value' => 'content'],
                    ],
                ],
                'auth_bearer' => 'foo',
            ])
            ->willReturn($response);

        $mailer = new SendgridApiTransport('foo', $httpClient);
        $mailer->send($email);
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
}
