<?php
declare(strict_types=1);

namespace AuditLog\Meta;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

/**
 * Event listener that is capable of enriching the audit logs
 * with the current request info.
 */
class RequestMetadata implements EventListenerInterface
{
    /**
     * The current request.
     *
     * @var \Cake\Http\ServerRequest
     */
    protected $request;

    /**
     * The current user name or id.
     *
     * @var mixed
     */
    protected $user;

    /**
     * Constructor.
     *
     * @param \Cake\Http\ServerRequest $request The current request
     * @param string|int $user The current user id or username
     */
    public function __construct($request, $user = null)
    {
        $this->request = $request;
        $this->user = $user;
    }

    /**
     * Returns an array with the events this class listens to.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return ['AuditLog.beforeLog' => 'beforeLog'];
    }

    /**
     * Enriches all of the passed audit logs to add the request
     * info metadata.
     *
     * @param \Cake\Event\Event $event The AuditLog.beforeLog event
     * @param array $logs The audit log event objects
     * @return void
     */
    public function beforeLog(Event $event, array $logs)
    {
        $meta = [
            'ip' => $this->request->clientIp(),
            'url' => $this->request->getRequestTarget(),
            'user' => $this->user,
        ];

        foreach ($logs as $log) {
            $log->setMetaInfo(($log->getMetaInfo() ?? []) + $meta);
        }
    }
}
