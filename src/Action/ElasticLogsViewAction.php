<?php
declare(strict_types=1);

namespace AuditLog\Action;

use AuditLog\Model\Document\AuditLog;
use Cake\ElasticSearch\IndexRegistry;
use Crud\Action\ViewAction;
use Crud\Event\Subject;

/**
 * A CRUD action class to implement the view of all details of a single audit log event
 * from elastic search.
 */
class ElasticLogsViewAction extends ViewAction
{
    use IndexConfigTrait;

    /**
     * Returns the Repository object to use.
     *
     * @return \AuditLog\Model\Index\AuditLogsIndex|\Cake\ElasticSearch\Index|\Cake\ORM\Table
     */
    protected function _table()
    {
        return IndexRegistry::get('AuditLog.AuditLogs');
    }

    /**
     * Find a audit log by id.
     *
     * @param string|null $id Record id
     * @param \Crud\Event\Subject $subject Event subject
     * @return \AuditLog\Model\Document\AuditLog
     * @throws \Exception
     */
    protected function _findRecord($id, Subject $subject): AuditLog
    {
        $repository = $this->_table();
        $this->_configIndex($repository, $this->_request());

        if ($this->_request()->getQuery('type')) {
            $repository->setName($this->_request()->getQuery('type'));
        }

        $query = $repository->find($this->findMethod());
        /**
         * @psalm-suppress PossiblyInvalidArgument
         * @psalm-suppress InvalidArrayOffset
         */
        $query->where(['_id' => $id]);
        $subject->set([
            'repository' => $repository,
            'query' => $query,
        ]);
        $this->_trigger('beforeFind', $subject);
        $entity = $query->first();
        if (!$entity) {
            $this->_notFound($id, $subject);
        }
        $subject->set(['entity' => $entity, 'success' => true]);
        $this->_trigger('afterFind', $subject);

        /** @var \AuditLog\Model\Document\AuditLog $entity */
        return $entity;
    }
}
