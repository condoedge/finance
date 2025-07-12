<?php

trait PayableTrait
{
    public function getPayableId(): int
    {
        return $this->id;
    }
    
    public function getPayableType(): string
    {
        return static::class;
    }
}