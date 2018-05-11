<?php

namespace Loopy\Spartan;

use Loopy\Spartan\Cache\CacheStore;
use Loopy\Spartan\Cache\MemoryStore;
use Loopy\Spartan\Http\Client;

class Member
{
    /**
     * @var $client Client
     * @var $cache CacheStore
     */
    protected $client;
    protected $cache;
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected $query = [];

    /**
     * Member constructor.
     * @param array $attributes
     * @param CacheStore $cache
     */
    public function __construct(array $attributes = [], CacheStore $cache = null)
    {
        $this->attributes = $attributes;
        $this->cache = $cache == null ? new MemoryStore() : $cache;
    }

    /**
     * @return mixed
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param mixed $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }


    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @param $id_type
     * @param $number
     * @return $this
     */
    public function find($id_type, $number)
    {
        $this->attributes = (array)$this->client->get("/member/$id_type/$number");
        $this->original = $this->attributes;
        $this->exists = true;
        return $this;
    }

    /**
     * @param $id
     * @return $this
     */
    public function findWithInternalId($id)
    {
        if ($this->cache && $this->cache->has($id)) {
            $this->attributes = unserialize($this->cache->get("$id"));
        } else {
            $this->attributes = (array)$this->client->get("/member/$id");
        }
        $this->original = $this->attributes;
        $this->exists = true;
        return $this;
    }

    /**
     * @param null $client
     * @return $this
     */
    public static function factory($client = null)
    {
        $static = new static();
        return $static->setClient($client);
    }

    public function __get($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
        return null;
    }

    public function __set($name, $value)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name] = $value;
        }
        return $this;
    }

    /**
     * @param $type
     * @param $number
     * @return $this
     */
    public function addId($type, $number)
    {
        $this->attributes['ids'][] = (object)['type' => $type, 'number' => $number];
        return $this;
    }

    /**
     * @param $type
     * @param $number
     * @return $this
     */
    public function removeId($type)
    {
        foreach ($this->attributes['ids'] as $key => $id) {
            if ($id->type == $type) {
                unset($this->attributes['ids'][$key]);
            }
        }
        $this->attributes['ids'] = array_values($this->attributes['ids']);
        return $this;
    }

    public function save()
    {
        if ($this->exists) {
            $diff = $this->diff();
            if (isset($diff['ids'])) {
                unset($diff['ids']);
                $this->updateIds();
            }
            if (count($diff) > 0) {
                $this->client->patch("/member/$this->id", $diff);
                $this->original = $this->attributes;
            }
        } else {
            $type = $this->ids[0]->type;
            $number = $this->ids[0]->number;
            $this->attributes = (array)$this->client->postJson("/member/$type/$number", $this->attributes);
            $this->exists = true;
        }
        $this->original = $this->attributes;
        $this->updateCache();
        return $this;
    }

    protected function diff()
    {
        $diff = [];
        foreach ($this->attributes as $key => $value) {
            if ($this->original[$key] != $value) {
                $diff[$key] = $value;
            }
        }
        return $diff;
    }

    public function addPhoto($path)
    {
        $file = $this->client->postFile("/file/", 'file', $path);
        $this->client->postJson("/photo/$this->id", ['id' => $file->id]);
    }

    public function where($criteria, $value, $operator = 'like')
    {
        $this->query[$criteria] = $operator . '?' . $value;
        return $this;
    }

    public function get()
    {
        $response = $this->client->get("/search", $this->query);
        $members = [];
        if (count($response->data) > 0) {
            foreach ($response->data as $attributes) {
                $members[] = (new static((array)$attributes))->setClient($this->getClient());
            }
        }
        $response->members = $members;
        unset($response->data);
        return $response;
    }

    private function updateIds()
    {
        $ids = $this->attributes['ids'];
        $ids_ = $this->original['ids'];

        $count = max(count($this->original['ids']), count($this->attributes['ids']));
        $operations = [];
        $foundOriginal = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($ids[$i])) {
                $found = false;
                $updated = false;
                $id = (object)$ids[$i];
                foreach ($ids_ as $id_) {
                    $id_ = (object)$id_;
                    if ($id_ == $id) {
                        $found = true;
                        $foundOriginal[] = $id_;
                        break;
                    } else if ($id->type == $id_->type) {
                        $updated = true;
                        $found = true;
                    }
                }
                if (!$found) {
                    $operations[] = ['type' => 'add', 'identity' => $id];
                } else if ($updated) {
                    $operations[] = ['type' => 'update', 'identity' => $id];
                }
            }
        }

        //find deleted elements
        foreach ($this->original['ids'] as $id) {
            if (!in_array($id, $this->attributes['ids'])) {
                $operations[] = ['type' => 'delete', 'identity' => $id];
            }
        }

        $this->syncIds($operations);
    }

    private function syncIds(array $operations)
    {
        $sets = [];
        foreach ($operations as $operation) {
            $sets[$operation['type']][] = $operation['identity'];
        }
        foreach ($sets as $type => $set) {
            switch ($type) {
                case 'add':
                    $this->client->postJson("/member/identity/$this->id", ['ids' => $set]);
                    break;
                case 'update':
                    $this->client->patch("/member/identity/$this->id", ['ids' => $set]);
                    break;
                case 'delete':
                    $this->client->delete("/member/identity/$this->id", ['ids' => $set]);
            }
        }
    }

    public function __toString()
    {
        return json_encode($this->attributes, JSON_PRETTY_PRINT);
    }

    private function updateCache()
    {
        if ($this->cache != null) {
            $this->cache->put($this->id, serialize($this->getAttributes()));
        }
    }


}