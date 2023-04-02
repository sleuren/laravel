<?php

namespace Sleuren\Http;

use GuzzleHttp\ClientInterface;

class Client
{
    /** @var ClientInterface|null */
    protected $client;

    /** @var string */
    protected $project;

    /**
     * @param string $project
     * @param ClientInterface|null $client
     */
    public function __construct(string $project, ClientInterface $client = null)
    {
        $this->project = $project;
        $this->client = $client;
    }

    /**
     * @param array $exception
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function report($exception)
    {
        try {
            return $this->getGuzzleHttpClient()->request('POST', 'https://sleuren.com/api/log', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Sleuren-Package'
                ],
                'json' => array_merge([
                    'project' => $this->project,
                    'additional' => [],
                ], $exception),
                'verify' => config('sleuren.verify_ssl'),
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleHttpClient()
    {
        if (! isset($this->client)) {
            $this->client = new \GuzzleHttp\Client([
                'timeout' => 15,
            ]);
        }

        return $this->client;
    }

    /**
     * @param ClientInterface $client
     * @return $this
     */
    public function setGuzzleHttpClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }
}
