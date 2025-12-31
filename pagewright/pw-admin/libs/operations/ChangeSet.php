<?php

namespace Pagewright\Operations;

/**
 * Represents a change set with multiple operations
 */
class ChangeSet
{
    private string $id;
    private string $actor;
    private string $timestamp;
    private string $summary;
    private array $operations;
    private array $notes;
    
    public function __construct(
        string $summary,
        array $operations = [],
        string $actor = 'system',
        array $notes = []
    ) {
        $this->id = $this->generateId();
        $this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $this->actor = $actor;
        $this->summary = $summary;
        $this->operations = [];
        $this->notes = $notes;
        
        foreach ($operations as $op) {
            $this->addOperation($op);
        }
    }
    
    /**
     * Generate unique change ID
     * 
     * @return string
     */
    private function generateId(): string
    {
        return 'chg_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    
    /**
     * Add an operation to the change set
     * 
     * @param Operation $operation
     * @return self
     */
    public function addOperation(Operation $operation): self
    {
        $operation->validate();
        $this->operations[] = $operation;
        return $this;
    }
    
    /**
     * Create from array representation
     * 
     * @param array $array Change set data
     * @return self
     */
    public static function fromArray(array $array): self
    {
        $changeSet = new self(
            $array['summary'] ?? 'Untitled change',
            [],
            $array['actor'] ?? 'system',
            $array['notes'] ?? []
        );
        
        if (isset($array['id'])) {
            $changeSet->id = $array['id'];
        }
        
        if (isset($array['timestamp'])) {
            $changeSet->timestamp = $array['timestamp'];
        }
        
        if (isset($array['ops']) && is_array($array['ops'])) {
            foreach ($array['ops'] as $opData) {
                $changeSet->addOperation(Operation::fromArray($opData));
            }
        }
        
        return $changeSet;
    }
    
    /**
     * Convert to array representation
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor,
            'timestamp' => $this->timestamp,
            'summary' => $this->summary,
            'ops' => array_map(fn($op) => $op->toArray(), $this->operations),
            'notes' => $this->notes
        ];
    }
    
    /**
     * Convert to JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Validate all operations
     * 
     * @return bool True if all valid
     * @throws \InvalidArgumentException
     */
    public function validate(): bool
    {
        if (empty($this->summary)) {
            throw new \InvalidArgumentException('Change set must have a summary');
        }
        
        if (empty($this->operations)) {
            throw new \InvalidArgumentException('Change set must have at least one operation');
        }
        
        foreach ($this->operations as $operation) {
            $operation->validate();
        }
        
        return true;
    }
    
    // Getters
    public function getId(): string { return $this->id; }
    public function getActor(): string { return $this->actor; }
    public function getTimestamp(): string { return $this->timestamp; }
    public function getSummary(): string { return $this->summary; }
    public function getOperations(): array { return $this->operations; }
    public function getNotes(): array { return $this->notes; }
}
