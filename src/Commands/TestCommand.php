<?php

namespace Sleuren\Commands;

use Exception;
use Sleuren\Sleuren;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'sleuren:test {exception?}';

    protected $description = 'Generate a test exception and send it to sleuren';

    public function handle()
    {
        try {
            /** @var Sleuren $sleuren */
            $sleuren = app('sleuren');

            if (config('sleuren.project_key')) {
                $this->info('✅ [sleuren] Found project key');
            } else {
                $this->error('❌ [sleuren] Could not find your project key, set this in your .env');
                $this->info('More information on setting your project key: https://www.sleuren.com/docs/how-to-use/installation');
            }

            if (in_array(config('app.env'), config('sleuren.environments'))) {
                $this->info('✅ [sleuren] Correct environment found (' . config('app.env') . ')');
            } else {
                $this->error('❌ [sleuren] Environment (' . config('app.env') . ') not allowed to send errors to sleuren, set this in your config');
                $this->info('More information about environment configuration: https://www.sleuren.com/docs/how-to-use/installation');
            }

            $response = $sleuren->handle(
                $this->generateException()
            );

            if (isset($response->id)) {
                $this->info('✅ Sent exception to Sleuren with ID: '.$response->id);
            } elseif (is_null($response)) {
                $this->info('✅ Sent exception to Sleuren!');
            } else {
                $this->error('❌ Failed to send exception to Sleuren');
            }
        } catch (\Exception $ex) {
            $this->error("❌ [Sleuren] {$ex->getMessage()}");
        }
    }
    public function generateException(): ?Exception
    {
        try {
            throw new Exception($this->argument('exception') ?? 'This is an exception to test if the integration with Sleuren works.');
        } catch (Exception $ex) {
            return $ex;
        }
    }
}
