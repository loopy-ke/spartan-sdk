<?php


namespace Loopy\Spartan\Http;


use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Loopy\Spartan\Http\Exceptions\ParsingError;

class Response
{
    /**
     * @var $response GuzzleResponse;
     */
    protected $response;

    /**
     * Response constructor.
     * @param GuzzleResponse $response
     */
    public function __construct(GuzzleResponse $response)
    {
        $this->response = $response;
    }


    /**
     * @return GuzzleResponse
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param GuzzleResponse $response
     * @return $this
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    public function parse()
    {
        if ($this->isJson()) {
            $resource = \json_decode($this->response->getBody()->getContents());
            if ($resource !== null) {
                return $resource;
            }
        }
        throw new ParsingError();
    }

    public function isJson()
    {
        $header = $this->response->getHeader('Content-type');
        return count($header) > 0 && $header[0] == 'application/json';
    }
}