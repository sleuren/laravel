<?php

namespace Sleuren\Tests;

class LoggingTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        \Sleuren\Facade::fake();

        $this->app['config']['logging.channels.sleuren'] = ['driver' => 'sleuren'];
        $this->app['config']['logging.default'] = 'sleuren';
        $this->app['config']['sleuren.environments'] = ['testing'];
    }

    /** @test */
    public function it_will_not_send_log_information_to_sleuren()
    {
        $this->app['router']->get('/log-information-via-route/{type}', function (string $type) {
            \Illuminate\Support\Facades\Log::{$type}('log');
        });

        $this->get('/log-information-via-route/debug');
        $this->get('/log-information-via-route/info');
        $this->get('/log-information-via-route/notice');
        $this->get('/log-information-via-route/warning');
        $this->get('/log-information-via-route/error');
        $this->get('/log-information-via-route/critical');
        $this->get('/log-information-via-route/alert');
        $this->get('/log-information-via-route/emergency');

        \Sleuren\Facade::assertRequestsSent(0);
    }

    /** @test */
    public function it_will_only_send_throwables_to_sleuren()
    {
        $this->app['router']->get('/throwables-via-route', function () {
            throw new \Exception('exception-via-route');
        });

        $this->get('/throwables-via-route');

        \Sleuren\Facade::assertRequestsSent(1);
    }
}
