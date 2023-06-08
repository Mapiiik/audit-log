<?php
declare(strict_types=1);

namespace AuditLog\Test\TestCase\Model\Behavior;

use ArrayObject;
use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditUpdateEvent;
use AuditLog\Model\Behavior\AuditLogBehavior;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use SplObjectStorage;

class AuditLogBehaviorTest extends TestCase
{
    /**
     * Table to use.
     *
     * @var \Cake\ORM\Table
     */
    public Table $table;

    /**
     * Behavior to use.
     *
     * @var \AuditLog\Model\Behavior\AuditLogBehavior
     */
    public AuditLogBehavior $behavior;

    /**
     * Tests setup.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->table = new Table(['table' => 'articles']);
        $this->table->setPrimaryKey('id');
        $this->behavior = new AuditLogBehavior($this->table, [
            'whitelist' => ['id', 'title', 'body', 'author_id'],
        ]);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testOnSaveCreateWithWithelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => [],
        ]));
        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress NoValue
         **/
        $result = $queue[$entity];
        $this->assertEquals(null, $result->getOriginal());
        unset($data['something_extra']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testOnSaveUpdateWithWithelist()
    {
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->title = 'Another Title'; /* @phpstan-ignore-line */

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => [],
        ]));
        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress NoValue
         **/
        $result = $queue[$entity];
        $this->assertEquals(['title' => 'Another Title'], $result->getChanged());
        $this->assertEquals(['title' => 'The Title'], $result->getOriginal());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditUpdateEvent::class, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSaveCreateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => [],
        ]));
        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress NoValue
         **/
        $result = $queue[$entity];
        $this->assertEquals(null, $result->getOriginal());
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSaveUpdateWithBlacklist()
    {
        $this->behavior->setConfig('blacklist', ['author_id']);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
        ];
        $entity = new Entity($data, ['markNew' => false, 'markClean' => true]);
        $entity->author_id = 50; /* @phpstan-ignore-line */

        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => [],
        ]));

        /** @psalm-suppress InvalidArgument **/
        $this->assertFalse(isset($queue[$entity]));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSaveWithFieldsFromSchema()
    {
        $this->table->setSchema([
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'text'],
            'body' => ['type' => 'text'],
        ]);
        $this->behavior->setConfig('whitelist', false);
        $data = [
            'id' => 13,
            'title' => 'The Title',
            'body' => 'The Body',
            'author_id' => 1,
            'something_extra' => true,
        ];
        $entity = new Entity($data, ['markNew' => true]);
        $event = new Event('Model.afterSave');
        $queue = new SplObjectStorage();
        $this->behavior->afterSave($event, $entity, new ArrayObject([
            '_auditQueue' => $queue,
            '_auditTransaction' => 1,
            'associated' => [],
        ]));
        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress NoValue
         **/
        $result = $queue[$entity];
        unset($data['something_extra'], $data['author_id']);
        $this->assertEquals($data, $result->getChanged());
        $this->assertEquals(13, $result->getId());
        $this->assertEquals('articles', $result->getSourceName());
        $this->assertInstanceOf(AuditCreateEvent::class, $result);
    }
}
