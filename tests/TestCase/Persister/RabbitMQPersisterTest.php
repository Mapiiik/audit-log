<?php
declare(strict_types=1);

namespace AuditLog\Test\TestCase\Persister;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Persister\RabbitMQPersister;
use Cake\TestSuite\TestCase;
use ProcessMQ\Connection\RabbitMQConnection;

class RabbitMQPersisterTest extends TestCase
{
    /**
     * Tests that using the defaults calls the right methods.
     *
     * @return void
     */
    public function testLogDefaults()
    {
        $client = $this->getMockBuilder(RabbitMQConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $persister = new RabbitMQPersister();
        $persister->connection($client);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y',
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, null, 'A new article');
        $events[] = new AuditDeleteEvent('1234', 2, 'comments', null, $data, 'A new article');

        $client->expects($this->once())
            ->method('send')
            ->with('audits.persist', 'store', $events, ['delivery_mode' => 2]);

        $persister->logEvents($events);
    }

    /**
     * Tests overriding defaults.
     *
     * @return void
     */
    public function testLogOverrideDefaults()
    {
        $client = $this->getMockBuilder(RabbitMQConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $persister = new RabbitMQPersister(['delivery_mode' => 1, 'routing' => 'foo', 'exchange' => 'bar']);
        $persister->connection($client);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y',
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, null, 'A new article');
        $events[] = new AuditDeleteEvent('1234', 2, 'comments', null, $data, 'A new article');

        $client->expects($this->once())
            ->method('send')
            ->with('bar', 'foo', $events, ['delivery_mode' => 1]);

        $persister->logEvents($events);
    }
}
