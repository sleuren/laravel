<?php

namespace Sleuren\Commands;

use Exception;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'sleuren:test {exception?}';

    protected $description = 'Generate a test exception and send it to sleuren';

    public function handle()
    {
        try {
            // Test proc_open function availability
            try {
                proc_open("", [], $pipes);
            } catch (\Throwable $exception) {
                $this->warn("❌ proc_open function disabled.");
                return;
            }

            $this->checkKey();
            $this->checkLogger();

            /** @var Sleuren $sleuren */
            $sleuren = app('sleuren');
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

    protected function checkKey(): self
    {
        $message = empty($this->config->get('sleuren.project_key'))
            ? '❌ Sleuren key not specified. Make sure you specify a value in the `key` key of the `sleuren` config file.'
            : '✅ Sleuren key specified';

        $this->info($message);

        return $this;
    }

    public function checkLogger(): self
    {
        $defaultLogChannel = $this->config->get('logging.default');

        $activeStack = $this->config->get("logging.channels.{$defaultLogChannel}");

        if (is_null($activeStack)) {
            $this->info("❌ The default logging channel `{$defaultLogChannel}` is not configured in the `logging` config file");
        }

        if (! isset($activeStack['channels']) || ! in_array('sleuren', $activeStack['channels'])) {
            $this->info("❌ The logging channel `{$defaultLogChannel}` does not contain the 'sleuren' channel");
        }

        if (is_null($this->config->get('logging.channels.sleuren'))) {
            $this->info('❌ There is no logging channel named `sleuren` in the `logging` config file');
        }

        if ($this->config->get('logging.channels.sleuren.driver') !== 'sleuren') {
            $this->info('❌ The `sleuren` logging channel defined in the `logging` config file is not set to `sleuren`.');
        }

        $this->info('✅ The Sleuren logging driver was configured correctly.');

        return $this;
    }

    public function generateException(): ?Exception
    {
        try {
            throw new Exception($this->argument('exception') ?? 'This is an exception to test if the integration with Sleuren works.');
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }
}
