<?php
declare(strict_types=1);

namespace AuditLog;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Event\AuditUpdateEvent;
use ReflectionObject;

/**
 * Can be used to convert an array of data obtained from elastic search
 * to convert it to an EventInterface object.
 */
class EventFactory
{
    /**
     * Converts an array of data as comming from elastic search and
     * converts it into an AuditLog\EventInterface object.
     *
     * @param array $data The array data from elastic search
     * @return \AuditLog\EventInterface
     */
    public function create(array $data): EventInterface
    {
        $map = [
            'create' => AuditCreateEvent::class,
            'update' => AuditUpdateEvent::class,
            'delete' => AuditDeleteEvent::class,
        ];

        $event = new $map[$data['type']](
            $data['transaction'],
            $data['primary_key'],
            $data['source'],
            $data['changed'],
            $data['original'],
            $data['display_value']
        );

        if (isset($data['parent_source'])) {
            $event->setParentSourceName($data['parent_source']);
        }

        $reflection = new ReflectionObject($event);
        $timestamp = $reflection->getProperty('timestamp');
        $timestamp->setAccessible(true);
        $timestamp->setValue($event, $data['@timestamp']);
        $event->setMetaInfo($data['meta']);

        return $event;
    }
}
