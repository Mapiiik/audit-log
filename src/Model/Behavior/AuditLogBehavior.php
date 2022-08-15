<?php
declare(strict_types=1);

namespace AuditLog\Model\Behavior;

use ArrayObject;
use AuditLog\Event\AuditCreateEvent;
use AuditLog\Event\AuditDeleteEvent;
use AuditLog\Event\AuditUpdateEvent;
use AuditLog\Persister\ElasticSearchPersister;
use AuditLog\PersisterInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use SplObjectStorage;

/**
 * This behavior can be used to log all the creations, modifications and deletions
 * done to a particular table.
 */
class AuditLogBehavior extends Behavior
{
    use LocatorAwareTrait;

    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [
        'index' => null,
        'type' => null,
        'blacklist' => ['created', 'modified'],
        'whitelist' => [],
        'foreignKeys' => [],
        'associations_mode' => 'remove',
        'unsetAssociatedEntityFieldsNotDirtyByFieldName' => [],
    ];

    /**
     * The persister object.
     *
     * @var \AuditLog\PersisterInterface
     */
    protected $persister;

    /**
     * Returns the list of implemented events.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Model.beforeSave' => 'injectTracking',
            'Model.beforeDelete' => 'injectTracking',
            'Model.afterSave' => 'afterSave',
            'Model.afterDelete' => 'afterDelete',
            'Model.afterSaveCommit' => 'afterCommit',
            'Model.afterDeleteCommit' => 'afterCommit',
        ];
    }

    /**
     * Conditionally adds the `_auditTransaction` and `_auditQueue` keys to $options. They are
     * used to track all changes done inside the same transaction.
     *
     * @param \Cake\Event\EventInterface $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved
     * @param \ArrayObject $options The options to be passed to the save or delete operation
     * @return void
     */
    public function injectTracking(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        if (!isset($options['_auditTransaction'])) {
            $options['_auditTransaction'] = Text::uuid();
        }

        if (!isset($options['_auditQueue'])) {
            $options['_auditQueue'] = new SplObjectStorage();
        }
    }

    /**
     * Get display value from entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @return string|null
     */
    public function getDisplayValue(EntityInterface $entity): ?string
    {
        /*
         * Please check ModelTable class initialize() method. In that method you can set the display filed,
         * ModelTable::setDisplayField()
         */
        $displayField = $this->_table->getDisplayField();

        // get display value
        if (is_array($displayField)) {
            $displayValue = [];
            foreach ($displayField as $oneDisplayField) {
                $displayValue[] = $entity->get($oneDisplayField);
            }
            $displayValue = implode(';', $displayValue);
        } else {
            $displayValue = $entity->get($displayField);
        }

        unset($displayField);

        // convert not null display values to string
        if (!is_null($displayValue)) {
            $displayValue = strval($displayValue);
        }

        return $displayValue;
    }

    /**
     * Calculates the changes done to the entity and stores the audit log event object into the
     * log queue inside the `_auditQueue` key in $options.
     *
     * @param \Cake\Event\EventInterface $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved
     * @param \ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }

        $this->setCommonConfig();

        $config = $this->_config;
        if (empty($config['whitelist'])) {
            $config['whitelist'] = $this->_table->getSchema()->columns();
            $config['whitelist'] = array_merge(
                $config['whitelist'],
                $this->getAssociationProperties(array_keys($options['associated']))
            );
        }

        $config['whitelist'] = array_diff($config['whitelist'], $config['blacklist']);
        $changed = $entity->extract($config['whitelist'], true);

        if (empty($changed)) {
            return;
        }

        $original = $entity->extractOriginal(array_keys($changed));
        $properties = $this->getAssociationProperties(array_keys($options['associated']));

        if ($config['associations_mode'] === 'remove') {
            foreach ($properties as $property) {
                unset($changed[$property], $original[$property]);
            }
        }

        if ($config['associations_mode'] === 'audit-trail') {
            // get required associated data
            foreach ($properties as $property) {
                // array of entities
                if (
                    in_array($property, array_keys($original))
                    && is_array($original[$property])
                    && count($original[$property]) > 0
                    && $original[$property][0] instanceof \Cake\ORM\Entity
                ) { // i.e. associted properies
                    foreach ($original[$property] as $associatedKey => $associatedRow) {
                        if (!$associatedRow->isDirty()) {
                            $fieldToCompare =
                                $config['unsetAssociatedEntityFieldsNotDirtyByFieldName'][$property] ?? null;

                            if (isset($fieldToCompare)) {
                                foreach ($changed[$property] as $changedAssociatedKey => $changedAssociatedRow) {
                                    if ($associatedRow->{$fieldToCompare} == $changedAssociatedRow->{$fieldToCompare}) {
                                        unset(
                                            $original[$property][$associatedKey],
                                            $changed[$property][$changedAssociatedKey]
                                        );
                                        break;
                                    }
                                }
                            }
                        } else {
                            /*
                            * add original data (currently in CakePHP 4.x - assiciated entities do not get the orignal
                            * values from EntityTrait::extractOriginal())
                            */
                            $associatedDirtyFields = $associatedRow->getDirty();
                            foreach ($associatedDirtyFields as $associatedDirtyField) {
                                $original[$property][$associatedKey]
                                    ->{$associatedDirtyField} = $associatedRow->getOriginal($associatedDirtyField);
                            }
                        }
                    }

                    if (count($original[$property]) > 0) {
                        $original[$property] = array_values($original[$property]);
                    }
                    if (count($changed[$property]) > 0) {
                        $changed[$property] = array_values($changed[$property]);
                    }

                    // todo: remove any blacklist columns from associated data
                    /*$sourceEntity = basename(
                        str_replace('\\', '/', $this->table()->getEntityClass())
                    );

                    $sourceEntity = Inflector::underscore($sourceEntity);
                    foreach ($original[$property] as $associatedKey => $associatedRow) {
                        if (isset($associatedRow[$sourceEntity . '_id'])
                            && in_array($sourceEntity . '_id', $config['blacklist'])) {
                            unset(
                                //$changed[$property][$associatedKey][$sourceEntity . '_id'],
                                $original[$property][$associatedKey][$sourceEntity . '_id']
                            );
                        }
                    }*/
                }

                // single entity
                if (
                    in_array($property, array_keys($original))
                    && $original[$property] instanceof \Cake\ORM\Entity
                ) {
                    if (!$original[$property]->isDirty()) {
                        unset(
                            $original[$property],
                            $changed[$property]
                        );
                    }
                }
            }
        }

        // now check $changed equals to $original, if not new
        if ($original === $changed && !$entity->isNew()) {
            return;
        }

        // find the value of foreign key linked
        foreach ($config['foreignKeys'] as $model => $fieldName) {
            $fieldKey = Inflector::underscore(Inflector::singularize($model));
            if (isset($changed[$fieldKey . '_id'])) {
                $changed[$fieldKey] = $this->getTableLocator()->get($model)
                    ->get($changed[$fieldKey . '_id'])->{$fieldName};
            }

            if (isset($original[$fieldKey . '_id'])) {
                $original[$fieldKey] = $this->getTableLocator()->get($model)
                    ->get($original[$fieldKey . '_id'])->{$fieldName};
            }
        }

        $primary = $entity->extract((array)$this->_table->getPrimaryKey());
        $auditEvent = $entity->isNew() ? AuditCreateEvent::class : AuditUpdateEvent::class;
        $displayValue = $this->getDisplayValue($entity);

        $transaction = $options['_auditTransaction'];
        $auditEvent = new $auditEvent(
            $transaction,
            $primary,
            $this->_table->getTable(),
            $changed,
            $original,
            $displayValue
        );

        if (!empty($options['_sourceTable'])) {
            $auditEvent->setParentSourceName($options['_sourceTable']->getTable());
        }

        $options['_auditQueue']->attach($entity, $auditEvent);
    }

    /**
     * Persists all audit log events stored in the `_eventQueue` key inside $options.
     *
     * @param \Cake\Event\EventInterface $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved or deleted
     * @param \ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterCommit(EventInterface $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue']) || $options['_auditQueue']->count() == 0) {
            return;
        }

        $events = collection($options['_auditQueue'])
            ->map(function ($entity, $pos, $it) {
                return $it->getInfo();
            })
            ->toList();

        if (empty($events)) {
            return;
        }

        $data = $this->_table->dispatchEvent('AuditLog.beforeLog', ['logs' => $events]);
        $this->persister()->logEvents($data->getData('logs'));

        // stop duplicate records adding to audit_logs table, when saveMany() is called
        $options['_auditQueue']->rewind();

        while ($options['_auditQueue']->valid()) {
            $obj = $options['_auditQueue']->current();
            $options['_auditQueue']->next();
            $options['_auditQueue']->detach($obj);
        }
    }

    /**
     * Persists all audit log events stored in the `_eventQueue` key inside $options.
     *
     * @param \Cake\Event\EventInterface $event The Model event that is enclosed inside a transaction
     * @param \Cake\Datasource\EntityInterface $entity The entity that is to be saved or deleted
     * @param \ArrayObject $options Options array containing the `_auditQueue` key
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, $options)
    {
        if (!isset($options['_auditQueue'])) {
            return;
        }
        $transaction = $options['_auditTransaction'];
        $primary = $entity->extract((array)$this->_table->getPrimaryKey());
        $displayValue = $this->getDisplayValue($entity);

        $this->setCommonConfig();

        $config = $this->_config;

        $original = $entity->getOriginalValues();

        foreach ($original as $originalKey => $originalValue) {
            if (in_array($originalKey, $config['blacklist'])) {
                unset($original[$originalKey]);
            }
        }

        if ($config['associations_mode'] === 'remove') {
            foreach ($this->_table->associations() as $association) {
                unset($original[$association->getProperty()]);
            }
        }

        $auditEvent = new AuditDeleteEvent(
            $transaction,
            $primary,
            $this->_table->getTable(),
            null,
            $original,
            $displayValue
        );

        if (!empty($options['_sourceTable'])) {
            $auditEvent->setParentSourceName($options['_sourceTable']->getTable());
        }

        $options['_auditQueue']->attach($entity, $auditEvent);
    }

    /**
     * Sets the persister object to use for logging all audit events.
     * If called with no arguments, it will return the currently configured persister.
     *
     * @param \AuditLog\PersisterInterface $persister The persister object to use
     * @return \AuditLog\PersisterInterface The configured persister
     */
    public function persister(?PersisterInterface $persister = null)
    {
        if ($persister === null && $this->persister === null) {
            $class = Configure::read('AuditLog.persister') ?: ElasticSearchPersister::class;
            $index = $this->getConfig('index') ?: $this->_table->getTable();
            $type = $this->getConfig('type') ?: Inflector::singularize($index);

            $persister = new $class(compact('index', 'type'));
        }

        if ($persister === null) {
            return $this->persister;
        }

        return $this->persister = $persister;
    }

    /**
     * Helper method used to get the property names of associations for a table.
     *
     * @param array $associated Whitelist of associations to look for
     * @return array List of property names
     */
    protected function getAssociationProperties($associated)
    {
        $associations = $this->_table->associations();
        $result = [];

        foreach ($associated as $name) {
            $result[] = $associations->get($name)->getProperty();
        }

        return $result;
    }

    /**
     * @return void
     */
    private function setCommonConfig()
    {
        $commonBlacklist = Configure::read('AuditLog.blacklist') ?? null;
        if (isset($commonBlacklist)) {
            $this->setConfig('blacklist', $commonBlacklist);
        }

        // set other conifgs similar to the above if necessary
    }
}
