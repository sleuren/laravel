<?php

namespace Sleuren\Tests\Mocks;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;

class SleurenClient extends \Sleuren\Http\Client
{
    const RESPONSE_ID = 'test';

    /** @var array */
    protected $requests = [];

    /**
     * @param array $exception
     */
    public function report($exception)
    {
        $this->requests[] = $exception;

        return new Response(200, [], json_encode(['id' => self::RESPONSE_ID]));
    }

    /**
     * @param int $expectedCount
     */
    public function assertRequestsSent(int $expectedCount)
    {
        Assert::assertCount($expectedCount, $this->requests);
    }
}
