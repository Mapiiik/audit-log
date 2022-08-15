<?php
declare(strict_types=1);

namespace AuditLog\Test\Persister;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Model\Table\AuditLogsTable;
use AuditLog\Persister\TablePersister;
use Cake\Datasource\EntityInterface;
use Cake\Error\Debugger;
use Cake\ORM\Entity;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class TablePersisterTest extends TestCase
{
    use LocatorAwareTrait;

    /**
     * Test subject
     *
     * @var \AuditLog\Persister\TablePersister
     */
    public $TablePersister;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->TablePersister = new TablePersister();

        TableRegistry::getTableLocator()->setConfig('AuditLogs', [
            'className' => AuditLogsTable::class,
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->TablePersister);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testConfigDefaults()
    {
        $expected = [
            'extractMetaFields' => false,
            'logErrors' => true,
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_AUTOMATIC,
            'serializeFields' => true,
            'table' => 'AuditLogs',
            'unsetExtractedMetaFields' => true,
        ];
        $this->assertEquals($expected, $this->TablePersister->getConfig());
    }

    /**
     * @return void
     */
    public function testGetTableDefault()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
    }

    /**
     * @return void
     */
    public function testSetTableAsAlias()
    {
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable('Custom'));
        $this->assertInstanceOf(Table::class, $this->TablePersister->getTable());
        $this->assertEquals('Custom', $this->TablePersister->getTable()->getAlias());
    }

    /**
     * @return void
     */
    public function testSetTableAsObject()
    {
        $customTable = $this->fetchTable('Custom');
        $this->assertInstanceOf(AuditLogsTable::class, $this->TablePersister->getTable());
        $this->assertInstanceOf(TablePersister::class, $this->TablePersister->setTable($customTable));
        $this->assertSame($customTable, $this->TablePersister->getTable());
    }

    /**
     * @return void
     */
    public function testSetInvalidTable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The `$table` argument must be either a table alias, or an instance of `\Cake\ORM\Table`.');
        $this->TablePersister->setTable(null); /* @phpstan-ignore-line */
    }

    /**
     * @return void
     */
    public function testSerializeNull()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', null, null, null);
        $event->setMetaInfo(null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => null,
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testExtractMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], null, null);
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"baz":{"bar":"foo"}}',
            'foo' => 'bar',
            'nested' => 'value',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => [
                'foo',
                'baz.nested' => 'nested',
            ],
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testExtractAllMetaFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], null, null);
        $event->setMetaInfo([
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '[]',
            'foo' => 'bar',
            'baz' => [
                'nested' => 'value',
                'bar' => 'foo',
            ],
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testExtractMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], null, null);
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => [
                'foo',
            ],
            'unsetExtractedMetaFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testExtractAllMetaFieldsDoNotUnset()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], null, null);
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => '{"foo":"bar"}',
            'foo' => 'bar',
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'extractMetaFields' => true,
            'unsetExtractedMetaFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testErrorLogging()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], null, 'Test');

        /** @var \AuditLog\Persister\TablePersister|\PHPUnit\Framework\MockObject\MockObject $TablePersister */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->onlyMethods(['log'])
            ->getMock();

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'source' => 'source',
            'parent_source' => null,
            'display_value' => 'Test',
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => null,
        ]);

        $logged = clone $entity;
        $logged->setError('field', ['error']);
        $logged->setSource('AuditLogs');

        $TablePersister
            ->expects($this->once())
            ->method('log')
            ->with(
                '[AuditLog\Persister\TablePersister] Persisting audit log failed. Data:' . PHP_EOL .
                Debugger::exportVar($logged, 4)
            );

        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                $entity->setError('field', ['error']);

                return false;
            }
        );

        $TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testDisableErrorLogging()
    {
        /** @var \AuditLog\Persister\TablePersister|\PHPUnit\Framework\MockObject\MockObject $TablePersister */
        $TablePersister = $this
            ->getMockBuilder(TablePersister::class)
            ->onlyMethods(['log'])
            ->getMock();

        $TablePersister
            ->expects($this->never())
            ->method('log');

        $TablePersister->setConfig([
            'logErrors' => false,
        ]);
        $TablePersister->getTable()->getEventManager()->on(
            'Model.beforeSave',
            function ($event, EntityInterface $entity) {
                return false;
            }
        );

        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], [], null);
        $TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testCompoundPrimaryKeyExtractDefault()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testCompoundPrimaryKeyExtractRaw()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => [1, 2, 3],
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'json');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_RAW,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testCompoundPrimaryKeyExtractProperties()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key_0' => 1,
            'primary_key_1' => 2,
            'primary_key_2' => 3,
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_PROPERTIES,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 'pk', 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '"pk"',
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testCompoundPrimaryKeyExtractSerialized()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', [1, 2, 3], 'source', [], [], null);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => '[]',
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => '[1,2,3]',
            'meta' => null,
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('primary_key', 'string');

        $this->TablePersister->setConfig([
            'primaryKeyExtractionStrategy' => TablePersister::STRATEGY_SERIALIZED,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return void
     */
    public function testDoNotSerializeFields()
    {
        $event = new AuditCreateEvent('62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96', 1, 'source', [], [], null);
        $event->setMetaInfo([
            'foo' => 'bar',
        ]);

        $entity = new Entity([
            'transaction' => '62ba2e1e-1524-4d4e-bb34-9bf0e03b6a96',
            'type' => 'create',
            'display_value' => null,
            'source' => 'source',
            'parent_source' => null,
            'original' => null,
            'changed' => [],
            'created' => new \DateTime($event->getTimestamp()),
            'primary_key' => 1,
            'meta' => [
                'foo' => 'bar',
            ],
        ]);
        $entity->setSource('AuditLogs');

        $AuditLogsTable = $this->getMockForModel('AuditLogs', ['save']);
        $AuditLogsTable
            ->expects($this->once())
            ->method('save')
            ->with($entity)
            ->willReturn($entity);

        $AuditLogsTable->getSchema()->setColumnType('original', 'json');
        $AuditLogsTable->getSchema()->setColumnType('changed', 'json');
        $AuditLogsTable->getSchema()->setColumnType('meta', 'json');

        $this->TablePersister->setConfig([
            'serializeFields' => false,
        ]);
        $this->TablePersister->setTable($AuditLogsTable);
        $this->TablePersister->logEvents([$event]);
    }

    /**
     * @return \Cake\ORM\Table|\PHPUnit\Framework\MockObject\MockObject
     */
    public function getMockForModel($alias, array $methods = [], array $options = [])
    {
        return parent::getMockForModel($alias, $methods, $options + [
            'className' => AuditLogsTable::class,
        ]);
    }
}
