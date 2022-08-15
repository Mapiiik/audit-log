<?php
declare(strict_types=1);

namespace AuditLog\Test\Meta;

use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Meta\ApplicationMetadata;
use Cake\Event\EventDispatcherTrait;
use Cake\TestSuite\TestCase;

class ApplicationMetadataTest extends TestCase
{
    use EventDispatcherTrait;

    /**
     * Tests that metadata is added to the audit log objects.
     *
     * @return void
     */
    public function testDataIsAdded()
    {
        $listener = new ApplicationMetadata('my_app', ['extra' => 'thing']);
        $this->getEventManager()->on($listener);
        $logs[] = new AuditDeleteEvent('1234', 1, 'articles', null, ['title' => 'bar'], 'bar');
        $event = $this->dispatchEvent('AuditLog.beforeLog', ['logs' => $logs]);

        $expected = [
            'app_name' => 'my_app',
            'extra' => 'thing',
        ];
        $this->assertEquals($expected, $logs[0]->getMetaInfo());
    }
}
