<?php


namespace Loopy\Spartan\Http;


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
            'contents' => fopen($this->getRealPath(), 'r'),
            'filename' => $this->getFilename(),
            'md5' => md5_file($this->getRealPath()),
            'name' => $this->name,
        ];
    }
}