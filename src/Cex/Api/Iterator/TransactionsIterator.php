<?php

namespace Cex\Api\Iterator;

use Cex\Api\HttpClient;
use Cex\Api\Iterator\Subject\BatchLoadSubject;

class TransactionsIterator implements \Iterator
{
    const DEFAULT_BATCH_SIZE = 100;

    /** @var int */
    protected $limit = self::DEFAULT_BATCH_SIZE;

    /** @var int */
    protected $position = 0;

    /** @var array|null */
    protected $elements = null;

    /** @var HttpClient */
    protected $client;

    /** @var array */
    protected $filters;

    /** @var bool */
    protected $hasNext;

    /** @var BatchLoadSubject */
    protected $batchLoadSubject;

    /**
     * @param HttpClient $client
     * @param array      $filters
     */
    public function __construct(HttpClient $client, array $filters = [])
    {
        $this->client = $client;
        $this->filters = $this->prepareFilters($filters);
        $this->batchLoadSubject = new BatchLoadSubject();
    }

    /**
     * @param array $filters
     */
    public function setFilters(array $filters)
    {
        $this->filters = $this->prepareFilters($filters);
        $this->rewind();
    }

    /**
     * @param int $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return BatchLoadSubject
     */
    public function getBatchLoadSubject()
    {
        return $this->batchLoadSubject;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $this->ensureRewound();

        return $this->valid() ? $this->elements[$this->position] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->ensureRewound();

        ++$this->position;
        if (!$this->isValidPosition($this->position)) {
            $this->load();
            $this->position = 0;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        $this->ensureRewound();

        $el = $this->current();

        return $el ? $el['id'] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        $this->ensureRewound();

        return isset($this->elements[$this->position]);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->position = 0;
        $this->elements = null;
        $this->hasNext = null;

        $this->load();
    }

    /**
     * Load transactions for current page
     *
     * Note: We use flag 'prev' as marker for next page existence because transactions are returned sorted by 'id' DESC
     */
    protected function load()
    {
        if (null === $this->elements || $this->hasNext === true) {
            $params = $this->filters + $this->getPaginationParams();

            $res = $this->client->getRawTransactions($params);
            if (isset($res['error'])) {
                throw new \Exception($res['error']);
            }

            $this->elements = isset($res['data'], $res['data']['vtx']) ? array_reverse($res['data']['vtx']) : [];
            $this->hasNext = isset($res['data'], $res['data']['prev']) ? $res['data']['prev'] : false;

            $subject = $this->getBatchLoadSubject();
            $subject->setBatch($this->elements);
            $subject->notify();

            // point of extendability
            $this->elements = $subject->getBatch();
        } else {
            $this->elements = [];
        }
    }

    /**
     * Enforce rewind() if not called yet
     */
    protected function ensureRewound()
    {
        if (!$this->isRewound()) {
            $this->rewind();
        }
    }

    /**
     * @return bool
     */
    protected function isRewound()
    {
        return null !== $this->elements;
    }

    /**
     * @param int $position
     *
     * @return bool
     */
    protected function isValidPosition($position)
    {
        return $position < $this->limit && $position < count($this->elements);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    protected function prepareFilters(array $filters)
    {
        if (isset($filters['start'])) {
            $filters['start'] *= 1e3;
        }

        if (isset($filters['end'])) {
            $filters['end'] *= 1e3;
        }

        if (isset($filters['next'])) {
            $filters['next'] = (int)$filters['next'];
        }

        if (isset($filters['prev'])) {
            $filters['prev'] = (int)$filters['prev'];
        }

        return $filters;
    }

    /**
     * @return array
     */
    protected function getPaginationParams()
    {
        $params = ['limit' => $this->limit];
        if ($this->isRewound() && $this->hasNext === true) {
            $el = end($this->elements);

            $params['txid'] = $el['id'];
            $params['time'] = $el['time'];
            $params['prev'] = 1;
        } elseif (!$this->isRewound()) {
            // emulate latest transaction as start point
            $params['txid'] = '1';
            $params['time'] = '1970-01-01T00:00:00.000Z';
            $params['prev'] = 1;
        }

        return $params;
    }
}
