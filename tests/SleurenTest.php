<?php

namespace Sleuren\Tests;

use Exception;
use Carbon\Carbon;
use Sleuren\Sleuren;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Sleuren\Tests\Mocks\SleurenClient;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SleurenTest extends TestCase
{
    /** @var Sleuren */
    protected $sleuren;

    /** @var Mocks\SleurenClient */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->sleuren = new Sleuren($this->client = new SleurenClient(
            'project_key'
        ));
    }

    /** @test */
    public function is_will_not_crash_if_sleuren_returns_error_bad_response_exception()
    {
        $this->sleuren = new Sleuren($this->client = new \Sleuren\Http\Client(
            'project_key'
        ));

        //
        $this->app['config']['sleuren.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new Response(500, [], '{}'),
            ]),
        ]));

        $this->assertInstanceOf(get_class(new \stdClass()), $this->sleuren->handle(new Exception('is_will_not_crash_if_sleuren_returns_error_bad_response_exception')));
    }

    /** @test */
    public function is_will_not_crash_if_sleuren_returns_normal_exception()
    {
        $this->sleuren = new Sleuren($this->client = new \Sleuren\Http\Client(
            'project_key'
        ));

        //
        $this->app['config']['sleuren.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new \Exception(),
            ]),
        ]));

        $this->assertFalse($this->sleuren->handle(new Exception('is_will_not_crash_if_sleuren_returns_normal_exception')));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_class()
    {
        $this->app['config']['sleuren.except'] = [];

        $this->assertFalse($this->sleuren->isSkipException(NotFoundHttpException::class));

        $this->app['config']['sleuren.except'] = [
            NotFoundHttpException::class,
        ];

        $this->assertTrue($this->sleuren->isSkipException(NotFoundHttpException::class));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_environment()
    {
        $this->app['config']['sleuren.environments'] = [];

        $this->assertTrue($this->sleuren->isSkipEnvironment());

        $this->app['config']['sleuren.environments'] = ['production'];

        $this->assertTrue($this->sleuren->isSkipEnvironment());

        $this->app['config']['sleuren.environments'] = ['testing'];

        $this->assertFalse($this->sleuren->isSkipEnvironment());
    }

    /** @test */
    public function it_will_return_false_for_sleeping_cache_exception_if_disabled()
    {
        $this->app['config']['sleuren.sleep'] = 0;

        $this->assertFalse($this->sleuren->isSleepingException([]));
    }

    /** @test */
    public function it_can_check_if_is_a_sleeping_cache_exception()
    {
        $data = ['host' => 'localhost', 'method' => 'GET', 'exception' => 'it_can_check_if_is_a_sleeping_cache_exception', 'line' => 2, 'file' => '/tmp/Sleuren/tests/SleurenTest.php', 'class' => 'Exception'];

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->assertFalse($this->sleuren->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->sleuren->addExceptionToSleep($data);

        $this->assertTrue($this->sleuren->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:00');

        $this->assertTrue($this->sleuren->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:01');

        $this->assertFalse($this->sleuren->isSleepingException($data));
    }

    /** @test */
    public function it_can_get_formatted_exception_data()
    {
        $data = $this->sleuren->getExceptionData(new Exception(
            'it_can_get_formatted_exception_data'
        ));

        $this->assertSame('testing', $data['environment']);
        $this->assertSame('localhost', $data['host']);
        $this->assertSame('GET', $data['method']);
        $this->assertSame('http://localhost', $data['fullUrl']);
        $this->assertSame('it_can_get_formatted_exception_data', $data['exception']);

        $this->assertCount(13, $data);
    }

    /** @test */
    public function it_filters_the_data_based_on_the_configuration()
    {
        $this->assertContains('*password*', $this->app['config']['sleuren.blacklist']);

        $data = [
            'password' => 'testing',
            'not_password' => 'testing',
            'not_password2' => [
                'password' => 'testing',
            ],
            'not_password_3' => [
                'nah' => [
                    'password' => 'testing',
                ],
            ],
            'Password' => 'testing',
        ];


        $this->assertContains('***', $this->sleuren->filterVariables($data));
    }

    /** @test */
    public function it_can_report_an_exception_to_sleuren()
    {
        $this->app['config']['sleuren.environments'] = ['testing'];

        $this->sleuren->handle(new Exception('it_can_report_an_exception_to_sleuren'));

        $this->client->assertRequestsSent(1);
    }
}
