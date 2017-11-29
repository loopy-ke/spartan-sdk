<?php

namespace Loopy\Spartan;

use Carbon\Carbon;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;

class Client
{
    protected $app_id;
    protected $secret;
    protected $client;

    public function __construct($app_id, $secret)
    {
        $this->client = new \GuzzleHttp\Client(['http_errors' => false]);
        $this->app_id = $app_id;
        $this->secret = $secret;
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function get($url, $params = [])
    {
        $url .= '?' . http_build_query($params);
        $request = new Request('get', $url, ['Accept' => 'application/json']);

        $request = $this->prepareRequest($request);
        return $this->client->send($request);
    }

    public function postJson($url, $params)
    {
        $body = json_encode($params);
        $request = new Request('post', $url, ['Accept' => 'application/json', 'Content-type' => 'application/json'], $body);
        $request = $this->prepareRequest($request);
        return $this->client->send($request);
    }

    public function postFile($url, $field, $filename)
    {
        $file = new File($filename, $field);
        $body = ['multipart' => [$file->toArray()]];
        $request = new Request('post', $url, ['Accept' => 'application/json', 'Content-type' => 'multipart/form-data'], $body);
        $request = $request->withBody(new MultipartStream($body));
        $request = $this->prepareRequest($request);
        return $this->client->send($request);
    }

    /**
     * @param Request $request
     * @return \GuzzleHttp\Psr7\MessageTrait|Request
     */
    public function prepareRequest(Request $request)
    {
        $request = $request->withHeader('Date', Carbon::now('GMT')->format('D, d M Y H:i:s \G\M\T'));
        $signature = base64_encode(hash_hmac('sha1', $this->utf8(($this->getStringToSign($request))), $this->secret));
        $authorization = 'SGA' . ' ' . $this->app_id . ":" . $signature;
        $request = $request->withHeader('Authorization', $authorization);
        return $request;
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getContentMd5(Request $request)
    {
        return md5($request->getBody());
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getContentType(Request $request)
    {
        $header = $request->getHeader('Content-type');

        if (!in_array($request->getMethod(), ['GET', 'DELETE']) && count($header) > 0) {
            return $header[0];
        }

        return '';
    }

    private function getStringToSign(Request $request)
    {
        return $request->getMethod() . "\n" .
            $this->getContentMd5($request) . "\n" .
            $this->getContentType($request) . "\n" .
            $this->getDate($request) . "\n" .
            $request->getRequestTarget();
    }

    private function getDate(Request $request)
    {
        return $request->getHeader('Date')[0];
    }

    private function utf8($string)
    {
        $encoding = mb_detect_encoding($string, mb_detect_order(), true);
        if (!$encoding) {
            return $string;
        }
        if (strcasecmp($encoding, 'UTF-8') != 0) {
            $encoding = iconv($encoding, 'UTF-8', $string);
            if ($encoding !== false) {
                return $encoding;
            } else {
                return $string;
            }
        }
    }

}