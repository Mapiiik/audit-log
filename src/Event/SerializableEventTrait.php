<?php
declare(strict_types=1);

namespace AuditLog\Event;

/**
 * Exposes basic functions for serializing event classes.
 */
trait SerializableEventTrait
{
    /**
     * Returns the string representation of this object.
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize(get_object_vars($this));
    }

    /**
     * Magic method for serializing the Event instance.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Takes the string representation of this object so it can be reconstructed.
     *
     * @param string $data serialized string
     * @return void
     */
    public function unserialize(string $data): void
    {
        $vars = unserialize($data);
        foreach ($vars as $var => $value) {
            $this->{$var} = $value;
        }
    }

    /**
     * Magic method for unserializing the Event data.
     *
     * @param array $data The serialized data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $var => $value) {
            $this->{$var} = $value;
        }
    }

    /**
     * Returns an array with the basic variables that should be json serialized.
     *
     * @return array<string, mixed>
     */
    protected function basicSerialize(): array
    {
        return [
            'type' => $this->getEventType(),
            'transaction' => $this->transactionId,
            'primary_key' => $this->id,
            'display_value' => $this->displayValue,
            'source' => $this->source,
            'parent_source' => $this->parentSource,
            '@timestamp' => $this->timestamp,
            'meta' => $this->meta,
        ];
    }
}
