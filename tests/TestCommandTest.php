<?php

namespace Sleuren\Tests;

use Sleuren\Sleuren;
use Sleuren\Tests\Mocks\SleurenClient;

class TestCommandTest extends TestCase
{
    /** @test */
    public function it_detects_if_the_sleuren_key_is_set()
    {
        $this->app['config']['sleuren.sleuren_key'] = '';

        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [Sleuren] Could not find your sleuren key, set this in your .env')
            ->assertExitCode(0);

        $this->app['config']['sleuren.sleuren_key'] = 'test';

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [Sleuren] Found sleuren key')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_its_running_in_the_correct_environment()
    {
        $this->app['config']['app.env'] = 'production';
        $this->app['config']['sleuren.environments'] = [];

        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [Sleuren] Environment (production) not allowed to send errors to Sleuren, set this in your config')
            ->assertExitCode(0);

        $this->app['config']['sleuren.environments'] = ['production'];

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [Sleuren] Correct environment found (' . config('app.env') . ')')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_it_fails_to_send_to_sleuren()
    {
        $this->artisan('sleuren:test')
            ->expectsOutput('❌ [Sleuren] Failed to send exception to Sleuren')
            ->assertExitCode(0);

        $this->app['config']['sleuren.environments'] = [
            'testing',
        ];

        $this->app['sleuren'] = new Sleuren($this->client = new SleurenClient(
            'sleuren_key'
        ));

        $this->artisan('sleuren:test')
            ->expectsOutput('✅ [Sleuren] Sent exception to Sleuren with ID: '.SleurenClient::RESPONSE_ID)
            ->assertExitCode(0);

        $this->assertEquals(SleurenClient::RESPONSE_ID, $this->app['sleuren']->getLastExceptionId());
    }
}
