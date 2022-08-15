<?php
declare(strict_types=1);

namespace AuditLog\Event;

/**
 * Implements most of the methods of the EventInterface.
 */
trait BaseEventTrait
{
    /**
     * Global transaction id.
     *
     * @var mixed
     */
    protected $transactionId;

    /**
     * Entity primary key.
     *
     * @var mixed
     */
    protected $id;

    /**
     * Repository name.
     *
     * @var string
     */
    protected string $source;

    /**
     * Parent repository name.
     *
     * @var string|null
     */
    protected ?string $parentSource = null;

    /**
     * Time of event.
     *
     * @var string
     */
    protected string $timestamp;

    /**
     * Extra information to describe the event.
     *
     * @var array|null
     */
    protected ?array $meta = null;

    /**
     * @var string|null
     */
    protected ?string $displayValue;

    /**
     * Returns the global transaction id in which this event is contained.
     *
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Returns the id of the entity that was created or altered.
     *
     * @return mixed
     */
    public function getId()
    {
        if (is_array($this->id) && count($this->id) === 1) {
            return current($this->id);
        }

        return $this->id;
    }

    /**
     * Returns the repository name in which the entity is.
     *
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->source;
    }

    /**
     * Returns the repository name that triggered this event.
     *
     * @return string|null
     */
    public function getParentSourceName(): ?string
    {
        return $this->parentSource;
    }

    /**
     * Sets the name of the repository that triggered this event.
     *
     * @param string $source The repository name
     * @return void
     */
    public function setParentSourceName($source)
    {
        $this->parentSource = $source;
    }

    /**
     * Returns the time string in which this change happened.
     *
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * Returns an array with meta information that can describe this event.
     *
     * @return array|null
     */
    public function getMetaInfo(): ?array
    {
        return $this->meta;
    }

    /**
     * Sets the meta information that can describe this event.
     *
     * @param array|null $meta The meta information to attach to the event
     * @return void
     */
    public function setMetaInfo(?array $meta)
    {
        $this->meta = $meta;
    }

    /**
     * @return string|null
     */
    public function getDisplayValue(): ?string
    {
        return $this->displayValue;
    }
}
