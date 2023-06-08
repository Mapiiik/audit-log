<?php
declare(strict_types=1);

namespace AuditLog\Test\TestCase\Persister;

use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Event\AuditUpdateEvent;
use AuditLog\Persister\ElasticSearchPersister;
use Cake\Datasource\ConnectionManager;
use Cake\ElasticSearch\IndexRegistry;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

class ElasticSearchPersisterTest extends TestCase
{
    /**
     * Fixtures to be loaded.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.AuditLog.ElasticArticles',
        'plugin.AuditLog.ElasticAudits',
        'plugin.AuditLog.ElasticAuthors',
        'plugin.AuditLog.ElasticTags',
    ];

    /**
     * Tests that create events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleCreateEvent()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'articles', 'type' => 'article']);
        $data = [
            'title' => 'A new article',
            'body' => 'article body',
            'author_id' => 1,
            'published' => 'Y',
        ];

        $events[] = new AuditCreateEvent('1234', 50, 'articles', $data, null, 'A new article');
        $persister->logEvents($events);
        $client->getIndex('articles')->refresh();

        $articles = IndexRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'create',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => null,
            'original' => null,
            'changed' => [
                'title' => 'A new article',
                'body' => 'article body',
                'author_id' => 1,
                'published' => 'Y',
            ],
            'meta' => null,
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that update events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleUpdateEvent()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'articles', 'type' => 'article']);
        $original = [
            'title' => 'Old article title',
            'published' => 'N',
        ];
        $changed = [
            'title' => 'A new article',
            'published' => 'Y',
        ];

        $events[] = new AuditUpdateEvent('1234', 50, 'articles', $changed, $original, 'A new article');
        $events[0]->setParentSourceName('authors');
        $persister->logEvents($events);
        $client->getIndex('articles')->refresh();

        $articles = IndexRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );
        $expected = [
            'transaction' => '1234',
            'type' => 'update',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => $original,
            'changed' => $changed,
            'meta' => null,
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that delete events are correctly stored.
     *
     * @return void
     */
    public function testLogSingleDeleteEvent()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'articles', 'type' => 'article']);

        $events[] = new AuditDeleteEvent('1234', 50, 'articles', null, ['test' => 'test'], null);
        $events[0]->setParentSourceName('authors');
        $persister->logEvents($events);
        $client->getIndex('articles')->refresh();

        $articles = IndexRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'delete',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => 'authors',
            'original' => ['test' => 'test'],
            'changed' => null,
            'meta' => null,
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that all events sent to the logger are actually persisted in the same index,
     * althought your source name.
     *
     * @return void
     */
    public function testLogMultipleEvents()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'audits', 'type' => 'audits']);

        $data = [
            'id' => 3,
            'tag' => 'cakephp',
        ];
        $events[] = new AuditCreateEvent('1234', 4, 'tags', $data, $data, null);

        $original = [
            'title' => 'Old article title',
            'published' => 'N',
        ];
        $changed = [
            'title' => 'A new article',
            'published' => 'Y',
        ];
        $events[] = new AuditUpdateEvent('1234', 2, 'authors', $changed, $original, 'A new article');
        $events[] = new AuditDeleteEvent('1234', 50, 'articles', null, $original, 'Old article title');
        $events[] = new AuditDeleteEvent('1234', 51, 'articles', null, $original, 'Old article title');

        $persister->logEvents($events);
        $client->getIndex('audits')->refresh();

        $audits = IndexRegistry::get('Audits')->find()->all();
        $this->assertCount(4, $audits);
        $audit = $audits->first();
        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($audit->get('@timestamp'))
        );
    }

    /**
     * Tests that Time objects are correctly serialized.
     *
     * @return void
     */
    public function testPersistingTimeObjects()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'articles', 'type' => 'articles']);
        $original = [
            'title' => 'Old article title',
            'published_date' => new DateTime('2015-04-12 20:20:21'),
        ];
        $changed = [
            'title' => 'A new article',
            'published_date' => new DateTime('2015-04-13 20:20:21'),
        ];

        $events[] = new AuditUpdateEvent('1234', 50, 'articles', $changed, $original, 'Old article title');
        $persister->logEvents($events);
        $client->getIndex('articles')->refresh();

        $articles = IndexRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);

        $this->assertEquals(
            new DateTime($events[0]->getTimestamp()),
            new DateTime($articles[0]->get('@timestamp'))
        );

        $expected = [
            'transaction' => '1234',
            'type' => 'update',
            'primary_key' => 50,
            'source' => 'articles',
            'parent_source' => null,
            'original' => [
                'title' => 'Old article title',
                'published_date' => '2015-04-12T20:20:21+00:00',
            ],
            'changed' => [
                'title' => 'A new article',
                'published_date' => '2015-04-13T20:20:21+00:00',
            ],
            'meta' => null,
        ];
        unset($articles[0]['id'], $articles[0]['@timestamp']);
        $this->assertEquals($expected, $articles[0]->toArray());
    }

    /**
     * Tests that metadata is correctly stored.
     *
     * @return void
     */
    public function testLogEventWithMetadata()
    {
        /** @var \Cake\ElasticSearch\Datasource\Connection $client */
        $client = ConnectionManager::get('test_elastic');
        $persister = new ElasticSearchPersister(['connection' => $client, 'index' => 'articles', 'type' => 'articles']);
        $original = [
            'title' => 'Old article title',
            'published_date' => new DateTime('2015-04-12 20:20:21'),
        ];

        $events[] = new AuditDeleteEvent('1234', 50, 'articles', null, $original, 'Old article title');
        $events[0]->setParentSourceName('authors');
        $events[0]->setMetaInfo(['a' => 'b', 'c' => 'd']);
        $persister->logEvents($events);
        $client->getIndex('articles')->refresh();

        $articles = IndexRegistry::get('Articles')->find()->toArray();
        $this->assertCount(1, $articles);
        $this->assertEquals(['a' => 'b', 'c' => 'd'], $articles[0]->meta);
    }
}
