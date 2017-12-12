<?php

namespace Loopy\Spartan\Http;

use Carbon\Carbon;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;

class Client
{
    protected $app_id;
    protected $secret;
    protected $client;

    protected $files = [];

    public function __construct($app_id, $secret, $options = [])
    {
        if (!isset($options['base_uri'])) {
            $options['base_uri'] = 'https://core.spartancash.co.ke';
        }
        $options['http_errors'] = true;

        $this->client = new \GuzzleHttp\Client($options);
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
        $this->files = [];
        $url .= '?' . http_build_query($params);
        $request = new Request('get', $url, ['Accept' => 'application/json']);

        $request = $this->prepareRequest($request);
        return $this->send($request);
    }

    public function postJson($url, $params)
    {
        $this->files = [];
        $body = json_encode($params);
        $request = new Request('post', $url, ['Accept' => 'application/json', 'Content-type' => 'application/json'], $body);
        $request = $this->prepareRequest($request);
        return $this->send($request);
    }

    public function patch($url, $params)
    {
        $this->files = [];
        $body = json_encode($params);
        $request = new Request('patch', $url, ['Accept' => 'application/json', 'Content-type' => 'application/json'], $body);
        $request = $this->prepareRequest($request);
        return $this->send($request);
    }

    public function postFile($url, $field, $file)
    {
        return $this->postFiles($url, [$field => $file]);
    }

    public function postFiles($url, array $files)
    {
        foreach ($files as $field => $file) {
            $this->files[] = (new File($file, $field))->toArray();
        }
        $body = new MultipartStream($this->files);
        $request = new Request('post', $url, ['Accept' => 'application/json', 'Content-type' => 'multipart/form-data;boundary="' . $body->getBoundary() . '"'], $body);
        $request = $request->withBody($body);
        $request = $this->prepareRequest($request);
        return $this->send($request);
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
        if (count($this->files) > 0) {
            $files = $this->files;
            uasort($files, function ($a, $b) {
                if ($a['name'] == $b['name']) {
                    return 0;
                }
                return strcmp($a['name'], $b['name']);
            });

            $string = '';
            foreach ($files as $file) {
                $string .= $file['name'] . "\n" . $file['filename'] . "\n" . $file['md5'] . "\n";
            }
            return md5($string);
        }
        return md5($request->getBody()->getContents());
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getContentType(Request $request)
    {
        $header = $request->getHeader('Content-type');

        if (!in_array($request->getMethod(), ['GET']) && count($header) > 0) {
            return implode(";", $header);
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

    private function send($request)
    {
        $response = new Response($this->client->send($request));
        if ($response->isJson() && $response->getResponse()->getStatusCode() >= 200 && $response->getResponse()->getStatusCode() < 300) {
            return $response->parse();
        }
        return $response;
    }

    public function delete($url, $params)
    {
        $this->files = [];
        $body = json_encode($params);
        $request = new Request('delete', $url, ['Accept' => 'application/json', 'Content-type' => 'application/json'], $body);
        $request = $this->prepareRequest($request);
        return $this->send($request);
    }

}