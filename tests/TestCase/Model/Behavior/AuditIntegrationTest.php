<?php
declare(strict_types=1);

namespace AuditLog\Test\Model\Behavior;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Event\AuditUpdateEvent;
use AuditLog\Model\Behavior\AuditLogBehavior;
use AuditLog\Persister\FakePersister;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

class AuditIntegrationTest extends TestCase
{
    use LocatorAwareTrait;

    /**
     * Fixtures to use.
     *
     * @var array<string>
     */
    protected $fixtures = [
        'core.Articles',
        'core.Comments',
        'core.Authors',
        'core.Tags',
        'core.ArticlesTags',
    ];

    /**
     * Table to use.
     *
     * @var \Cake\ORM\Table
     */
    public $table;

    /**
     * Persister
     *
     * @var \AuditLog\PersisterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    public $persister;

    /**
     * Tests setup.
     *
     * @return void
     */
    public function setUp(): void
    {
        // load fixtures
        $this->setupFixtures();

        $this->table = $this->fetchTable('Articles');
        $this->table->hasMany('Comments');
        $this->table->belongsToMany('Tags');
        $this->table->belongsTo('Authors');
        $this->table->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);

        /** @var \AuditLog\Persister\FakePersister|\PHPUnit\Framework\MockObject\MockObject $persister */
        $persister = $this->createMock(FakePersister::class);
        $this->persister = $persister;

        /** @var \AuditLog\Model\Behavior\AuditLogBehavior $auditLog */
        $auditLog = $this->table->getBehavior('AuditLog');
        /** @var \AuditLog\Persister\FakePersister $auditLogPersister */
        $auditLogPersister = $auditLog->persister($this->persister);
    }

    /**
     * Tests that creating an article means having one audit log create event.
     *
     * @return void
     */
    public function testCreateArticle()
    {
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'author_id' => 1,
            'body' => 'new article body',
        ]);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) use ($entity) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $this->assertEquals(null, $event->getOriginal());
                $this->assertNotEmpty($event->getTransactionId());

                $data = $entity->toArray();
                $this->assertEquals($data, $event->getChanged());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that updating an article means having one audit log update event.
     *
     * @return void
     */
    public function testUpdateArticle()
    {
        $entity = $this->table->get(1);
        $entity->title = 'Changed title'; /* @phpstan-ignore-line */
        $entity->published = 'Y'; /* @phpstan-ignore-line */

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditUpdateEvent::class, $event);

                $this->assertEquals(1, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $expected = [
                    'title' => 'Changed title',
                    'published' => 'Y',
                ];
                $this->assertEquals($expected, $event->getChanged());
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a belongsTo association means having one update
     * log event for the main entity.
     *
     * @return void
     */
    public function testCreateArticleWithExisitingBelongsTo()
    {
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body',
        ]);
        $entity->author = $this->table->Authors->get(1); /* @phpstan-ignore-line */

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditCreateEvent::class, $event);

                $this->assertEquals(4, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $changed = $event->getChanged();
                $this->assertEquals(1, $changed['author_id']);
                $this->assertFalse(isset($changed['author']));
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a belongsTo association means having one update
     * log event for the main entity.
     *
     * @return void
     */
    public function testUpdateArticleWithExistingBelongsTo()
    {
        $entity = $this->table->get(1, [
            'contain' => ['Authors'],
        ]);
        $entity->title = 'Changed title'; /* @phpstan-ignore-line */
        $entity->author = $this->table->Authors->get(2); /* @phpstan-ignore-line */

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(1, $events);
                $event = $events[0];
                $this->assertInstanceOf(AuditUpdateEvent::class, $event);

                $this->assertEquals(1, $event->getId());
                $this->assertEquals('articles', $event->getSourceName());
                $expected = [
                    'title' => 'Changed title',
                    'author_id' => 2,
                ];
                $this->assertEquals($expected, $event->getChanged());
                $this->assertFalse(isset($event->getChanged()['author']));
                $this->assertNotEmpty($event->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding a new belongsTo entity means having one update
     * log event for the main entity and one of the new belongsto entity.
     *
     * @return void
     */
    public function testCreateArticleWithNewBelongsTo()
    {
        /* @phpstan-ignore-next-line */
        $this->table->Authors->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);
        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body',
            'author' => [
                'name' => 'Jose',
            ],
        ]);
        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(2, $events);
                $this->assertEquals('authors', $events[0]->getSourceName());
                $this->assertEquals('articles', $events[1]->getSourceName());

                $this->assertInstanceOf(AuditCreateEvent::class, $events[0]);
                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());

                $this->assertEquals(['id' => 5, 'name' => 'Jose'], $events[0]->getChanged());
                $this->assertFalse(isset($events[1]->getChanged()['author']));
                $this->assertEquals('new article body', $events[1]->getChanged()['body']);
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding has many entities means one event for each of the updated
     * associated entities.
     *
     * @return void
     */
    public function testUpdateArticleWithHasMany()
    {
        /* @phpstan-ignore-next-line */
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);

        $entity = $this->table->get(1, [
            'contain' => ['Comments'],
        ]);
        /* @phpstan-ignore-next-line */
        $entity->comments[] = $this->table->Comments->newEntity([
            'user_id' => 1,
            'comment' => 'This is a comment',
        ]);
        /* @phpstan-ignore-next-line */
        $entity->comments[] = $this->table->Comments->newEntity([
            'user_id' => 1,
            'comment' => 'This is another comment',
        ]);
        $entity->setDirty('comments', true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(2, $events);
                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());

                $expected = [
                    'id' => 7,
                    'article_id' => 1,
                    'user_id' => 1,
                    'comment' => 'This is a comment',
                ];
                $this->assertEquals($expected, $events[0]->getChanged());

                $expected = [
                    'id' => 8,
                    'article_id' => 1,
                    'user_id' => 1,
                    'comment' => 'This is another comment',
                ];
                $this->assertEquals($expected, $events[1]->getChanged());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding has many entities means one event for each of the updated
     * associated entities and finally and event for the main entity if it is new.
     *
     * @return void
     */
    public function testCreateArticleWithHasMany()
    {
        /* @phpstan-ignore-next-line */
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);

        $entity = $this->table->newEntity([
            'title' => 'New Article',
            'body' => 'new article body',
            'comments' => [
                ['comment' => 'This is a comment', 'user_id' => 1],
                ['comment' => 'This is another comment', 'user_id' => 1],
            ],
        ]);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(3, $events);
                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('articles', $events[0]->getParentSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());
                $this->assertEquals('articles', $events[2]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[2]->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that adding belongsToMany entities means log events for each new
     * entity in the target table and events for as many entities got saved in the
     * junction table.
     *
     * @return void
     */
    public function testUpdateWithBelongsToMany()
    {
        /* @phpstan-ignore-next-line */
        $this->table->Tags->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);
        /* @phpstan-ignore-next-line */
        $this->table->Tags->junction()->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);

        $entity = $this->table->get(1, [
            'contain' => ['Tags'],
        ]);
        /* @phpstan-ignore-next-line */
        $entity->tags[] = $this->table->Tags->newEntity([
            'name' => 'This is a Tag',
        ]);
        /* @phpstan-ignore-next-line */
        $entity->tags[] = $this->table->Tags->get(3);
        $entity->setDirty('tags', true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(3, $events);
                $this->assertEquals('tags', $events[0]->getSourceName());
                $this->assertEquals('articles_tags', $events[1]->getSourceName());
                $this->assertEquals('articles_tags', $events[2]->getSourceName());

                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertSame($events[0]->getTransactionId(), $events[1]->getTransactionId());
            }));

        $this->table->save($entity);
    }

    /**
     * Tests that deleting an entity logs a single event.
     *
     * @return void
     */
    public function testDelete()
    {
        $entity = $this->table->get(1);
        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(1, $events);
                $this->assertinstanceOf(AuditDeleteEvent::class, $events[0]);
                $this->assertEquals(1, $events[0]->getId());
                $this->assertEquals('articles', $events[0]->getSourceName());
                $this->assertNotEmpty($events[0]->getTransactionId());
                $this->assertNotEmpty($events[0]->getOriginal());
            }));

        $this->table->delete($entity);
    }

    /**
     * Tests that deleting an entity with cascading delete.
     *
     * @return void
     */
    public function testDeleteCascade()
    {
        /* @phpstan-ignore-next-line */
        $this->table->Tags->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);
        /* @phpstan-ignore-next-line */
        $this->table->Tags->junction()->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);
        /* @phpstan-ignore-next-line */
        $this->table->Comments->addBehavior('AuditLog', [
            'className' => AuditLogBehavior::class,
        ]);
        $entity = $this->table->get(1, [
            'contain' => ['Comments', 'Tags'],
        ]);

        /* @phpstan-ignore-next-line */
        $this->table->Comments->setDependent(true);
        /* @phpstan-ignore-next-line */
        $this->table->Comments->setCascadeCallbacks(true);

        /* @phpstan-ignore-next-line */
        $this->table->Tags->setDependent(true);
        /* @phpstan-ignore-next-line */
        $this->table->Tags->getCascadeCallbacks(true);

        $this->persister
            ->expects($this->once())
            ->method('logEvents')
            ->will($this->returnCallback(function (array $events) {
                $this->assertCount(5, $events);
                $id = $events[0]->getTransactionId();
                foreach ($events as $event) {
                    $this->assertinstanceOf(AuditDeleteEvent::class, $event);
                    $this->assertNotEmpty($event->getTransactionId());
                    $this->assertEquals($id, $event->getTransactionId());
                }

                $this->assertEquals('comments', $events[0]->getSourceName());
                $this->assertEquals('comments', $events[1]->getSourceName());
                $this->assertEquals('comments', $events[2]->getSourceName());
                $this->assertEquals('comments', $events[3]->getSourceName());
                $this->assertEquals('articles', $events[4]->getSourceName());
            }));

        $this->table->delete($entity);
    }
}
