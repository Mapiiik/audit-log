<?php
declare(strict_types=1);

namespace AuditLog\Persister;

use AuditLog\PersisterInterface;

/**
 * Implementes audit logs events persisting - fake class for tests
 */
class FakePersister implements PersisterInterface
{
    /**
     * Persists all of the audit log event objects that are provided.
     *
     * @param array<\AuditLog\EventInterface> $auditLogs An array of EventInterface objects
     * @return void
     */
    public function logEvents(array $auditLogs): void
    {
    }
}
