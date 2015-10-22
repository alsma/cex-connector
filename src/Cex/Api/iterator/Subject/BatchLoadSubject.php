<?php

namespace Cex\Api\Iterator\Subject;

class BatchLoadSubject implements \SplSubject
{
    /** @var \SplObjectStorage|\SplObserver[] */
    protected $observers;

    /** @var array */
    protected $batch;

    public function __construct()
    {
        $this->observers = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function attach(\SplObserver $observer)
    {
        $this->observers->attach($observer);
    }

    /**
     * {@inheritdoc}
     */
    public function detach(\SplObserver $observer)
    {
        $this->observers->detach($observer);
    }

    /**
     * {@inheritdoc}
     */
    public function notify()
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * @param array $elements
     */
    public function setBatch(array $elements)
    {
        $this->batch = $elements;
    }

    /**
     * @return array
     */
    public function getBatch()
    {
        return $this->batch;
    }
}
