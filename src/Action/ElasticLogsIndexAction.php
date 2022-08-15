<?php
declare(strict_types=1);

namespace AuditLog\Action;

use Cake\ElasticSearch\IndexRegistry;
use Cake\Http\Response;
use Crud\Action\IndexAction;

/**
 * A CRUD action class to implement the listing of all audit logs
 * documents in elastic search.
 */
class ElasticLogsIndexAction extends IndexAction
{
    use IndexConfigTrait;

    /**
     * Renders the index action by searching all documents matching the URL conditions.
     *
     * @return \Cake\Http\Response|null
     */
    protected function _handle(): ?Response
    {
        $request = $this->_request();
        $this->_configIndex($this->_table(), $request);
        $query = $this->_table()->find();
        /** @var \Cake\ElasticSearch\Index $repository */
        $repository = $query->getRepository();

        $query->searchOptions(['ignore_unavailable' => true]);

        if ($request->getQuery('type')) {
            $repository->setName($request->getQuery('type'));
        }
        if ($request->getQuery('primary_key')) {
            $query->where(['primary_key' => $request->getQuery('primary_key')]);
        }

        if ($request->getQuery('transaction')) {
            $query->where(['transaction' => $request->getQuery('transaction')]);
        }

        if ($request->getQuery('user')) {
            $query->where(['meta.user' => $request->getQuery('user')]);
        }

        if ($request->getQuery('changed_fields')) {
            $query->where(function ($builder) use ($request) {
                $fields = explode(',', $request->getQuery('changed_fields'));
                $fields = array_map(
                    function ($f) {
                        return 'changed.' . $f;
                    },
                    array_map('trim', $fields)
                );
                $fields = array_map([$builder, 'exists'], $fields);

                return $builder->and_($fields);
            });
        }

        if ($request->getQuery('query')) {
            $query->where(function ($builder) use ($request) {
                return $builder->query(new \Elastica\Query\QueryString($request->getQuery('query')));
            });
        }

        try {
            $this->addTimeConstraints($request, $query);
        } catch (\Exception $e) {
        }

        $subject = $this->_subject(['success' => true, 'query' => $query]);
        $this->_trigger('beforePaginate', $subject);

        $items = $this->_controller()->paginate($query);
        $subject->set(['entities' => $items]);

        $this->_trigger('afterPaginate', $subject);
        $this->_trigger('beforeRender', $subject);

        return null;
    }

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
     * Alters the query object to add the time constraints as they can be found in
     * the request object.
     *
     * @param \Cake\Http\ServerRequest $request The request where query string params can be found
     * @param \Cake\ElasticSearch\Query $query The Query to add filters to
     * @return void
     */
    protected function addTimeConstraints($request, $query)
    {
        if ($request->getQuery('from')) {
            $from = new \DateTime($request->getQuery('from'));
            $until = new \DateTime();
        }

        if ($request->getQuery('until')) {
            $until = new \DateTime($request->getQuery('until'));
        }

        if (!empty($from) && !empty($until)) {
            $query->where(function ($builder) use ($from, $until) {
                return $builder->between('@timestamp', $from->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s'));
            });

            return;
        }

        if (!empty($until)) {
            $query->where(['@timestamp <=' => $until->format('Y-m-d H:i:s')]);
        }
    }
}
