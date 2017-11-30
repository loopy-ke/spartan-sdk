<?php

namespace Loopy\Spartan;

use Loopy\Spartan\Http\Client;

class Member
{
    /**
     * @var $client Client
     */
    protected $client;
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;

    /**
     * Member constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
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

    public function save()
    {
        if ($this->exists) {
            $diff = $this->diff();
            if (count($diff) > 0) {
                $this->client->patch("/member/$this->id", $diff);
                $this->original = $this->attributes;
            }
        } else {
            $type = $this->ids[0]->type;
            $number = $this->ids[0]->number;
            $this->attributes = $this->client->postJson("/member/$type/$number", $this->attributes);
            $this->exists = true;
        }
        $this->original = $this->attributes;
        return true;
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

    public function addFile($path)
    {
        $file = $this->client->postFile("/file/", 'file', $path);
        $this->client->postJson("/photo/$this->id", ['id' => $file->id]);
    }
}