<?php


namespace Loopy\Spartan;


class File extends \SplFileInfo
{

    protected $name;

    /**
     * File constructor.
     */
    public function __construct($file, $field_name)
    {
        parent::__construct($file);
        $this->name = $field_name;
    }

    public function toArray()
    {
        return [
            'name' => $this->name,
            'contents' => fopen($this->getPath(), 'r'),
            'filename' => $this->getFilename(),
        ];

    }
}