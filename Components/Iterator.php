<?php

namespace Kaa\HttpClient\Components;

class Iterator
{
    private int $currentIndex;
    /** @var mixed $array */
    private $array;

    public function __construct(array &$array)
    {
        $this->array = &$array;
        $this->currentIndex = 0;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return $this->array[$this->currentIndex];
    }

    public function key(): int
    {
        return $this->currentIndex;
    }

    /**
     * @return mixed
     */
    public function next()
    {
        return $this->array[++$this->currentIndex];
    }

    public function hasNext(): bool
    {
        return isset($this->array[$this->currentIndex + 1]);
    }



}