<?php
declare(strict_types=1);

namespace AuditLog\Event;

use AuditLog\EventInterface;
use DateTime;

/**
 * Represents a change in the repository where the list of changes can be
 * tracked as a list of properties and their values.
 */
abstract class BaseEvent implements EventInterface
{
    use BaseEventTrait;
    use SerializableEventTrait;

    /**
     * The array of changed properties for the entity.
     *
     * @var array|null
     */
    protected ?array $changed = null;

    /**
     * The array of original properties before they got changed.
     *
     * @var array|null
     */
    protected ?array $original = null;

    /**
     * Construnctor.
     *
     * @param mixed $transactionId The global transaction id
     * @param mixed $id The entities primary key
     * @param string $source The name of the source (table)
     * @param array|null $changed The array of changes that got detected for the entity
     * @param array|null $original The original values the entity had before it got changed
     * @param string|null $displayValue Human friendly text for the record.
     */
    public function __construct(
        mixed $transactionId,
        mixed $id,
        string $source,
        ?array $changed,
        ?array $original,
        ?string $displayValue
    ) {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->changed = $this->getEventType() === 'delete' ? null : $changed;
        $this->original = $this->getEventType() === 'create' ? null : $original;
        $this->displayValue = $displayValue;
        $this->timestamp = (new DateTime())->format(DateTime::ATOM);
    }

    /**
     * Returns an array with the properties and their values before they got changed.
     *
     * @return array|null
     */
    public function getOriginal(): ?array
    {
        return $this->original;
    }

    /**
     * Returns an array with the properties and their values as they were changed.
     *
     * @return array|null
     */
    public function getChanged(): ?array
    {
        return $this->changed;
    }

    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    abstract public function getEventType(): string;

    /**
     * Returns the array to be used for encoding this object as json.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->basicSerialize() + [
                'original' => $this->original,
                'changed' => $this->changed,
            ];
    }
}
