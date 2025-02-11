<?php
declare(strict_types=1);

namespace AuditLog\Test\Event;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Event\AuditUpdateEvent;
use AuditLog\EventFactory;
use Cake\TestSuite\TestCase;

class SerializeTest extends TestCase
{
    /**
     * Tests serializing a create event.
     *
     * @return void
     */
    public function testSerializeCreate()
    {
        $event = new AuditCreateEvent('123', 50, 'articles', ['title' => 'foo'], null, 'foo');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests serializing an update event.
     *
     * @return void
     */
    public function testSerializeUpdate()
    {
        $event = new AuditUpdateEvent('123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], 'foo');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests serializing a delete event.
     *
     * @return void
     */
    public function testSerializeDelete()
    {
        $event = new AuditDeleteEvent('123', 50, 'articles', [], ['title' => 'bar'], 'bar');
        $event->setParentSourceName('authors');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = serialize($event);
        $this->assertEquals($event, unserialize($serialized));
    }

    /**
     * Tests json serializing a create event.
     *
     * @return void
     */
    public function testJsonSerializeCreate()
    {
        $factory = new EventFactory();
        $event = new AuditCreateEvent('123', 50, 'articles', ['title' => 'foo'], null, 'foo');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }

    /**
     * Tests json serializing an update event.
     *
     * @return void
     */
    public function testJsonSerializeUpdate()
    {
        $factory = new EventFactory();
        $event = new AuditUpdateEvent('123', 50, 'articles', ['title' => 'foo'], ['title' => 'bar'], 'foo');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }

    /**
     * Tests json serializing a delete event.
     *
     * @return void
     */
    public function testJsonSerializeDelete()
    {
        $factory = new EventFactory();
        $event = new AuditDeleteEvent('123', 50, 'articles', null, ['title' => 'bar'], 'bar');
        $event->setParentSourceName('authors');
        $event->setMetaInfo(['extra' => 'info']);
        $serialized = json_encode($event);
        $result = $factory->create(json_decode($serialized, true));
        $this->assertEquals($event, $result);
    }
}
