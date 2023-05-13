<?php

namespace Sleuren;

use Throwable;
use Sleuren\Http\Client;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

use Sleuren\Recorders\JobRecorder\JobRecorder;
use Sleuren\Recorders\LogRecorder\LogRecorder;
use Sleuren\Recorders\DumpRecorder\DumpRecorder;
use Sleuren\Recorders\QueryRecorder\QueryRecorder;

class Sleuren
{
    /** @var Client */
    private $client;
    /** @var array */
    private $blacklist = [];
    /** @var null|string */
    private $lastExceptionId;
    /** @var string */
    const NAME = 'sleuren/laravel';
    /** @var string */
    const VERSION = '1.1.2';
    /** @var null|string */
    private $baseDir;
    /** @var array */
    protected QueryRecorder $queryRecorder;
    /** @var array */
    protected LogRecorder $logRecorder;
    /** @var array */
    protected JobRecorder $jobRecorder;
    /** @var array */
    protected DumpRecorder $dumpRecorder;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->queryRecorder = app(QueryRecorder::class);
        $this->logRecorder = app(LogRecorder::class);
        $this->jobRecorder = app(JobRecorder::class);
        $this->dumpRecorder = app(DumpRecorder::class);

        $this->blacklist = array_map(function ($blacklist) {
            return strtolower($blacklist);
        }, config('sleuren.blacklist', []));

    }

    /**
     * @param Throwable $exception
     * @param string $fileType
     * @return bool|mixed
     */
    public function handle(Throwable $exception, $fileType = 'php', array $customData = [])
    {
        if ($this->isSkipEnvironment()) {
            return false;
        }
        $data = $this->getExceptionData($exception);
        if ($this->isSkipException($data['class'])) {
            return false;
        }

        if ($this->isSleepingException($data)) {
            return false;
        }

        if ($fileType == 'javascript') {
            $data['fullUrl']   = $customData['url'];
            $data['file']      = $customData['file'];
            $data['file_type'] = $fileType;
            $data['error']     = $customData['message'];
            $data['message']   = $customData['stack'];
            $data['line']      = $customData['line'];
            $data['class']     = null;
            $count = 5;
            $lines = file($data['file']);
            $data['executor'] = [];

            for ($i = -1 * abs($count); $i <= abs($count); $i++) {
                $currentLine = $data['line'] + $i;
                $index = $currentLine - 1;
                if (!array_key_exists($index, $lines)) {
                    continue;
                }
                $data['executor'][] = [
                    'line_number' => $currentLine,
                    'line' => $lines[$index],
                ];
            }
            $data['executor'] = array_filter($data['executor']);
        }
        $rawResponse = $this->logError($data);
        if (!$rawResponse) {
            return false;
        }
        $response = json_decode($rawResponse->getBody()->getContents());
        if (isset($response->id)) {
            $this->setLastExceptionId($response->id);
        }
        if (config('sleuren.sleep') !== 0) {
            $this->addExceptionToSleep($data);
        }
        return $response;
    }

    /**
     * @return bool
     */
    public function isSkipEnvironment()
    {
        if (count(config('sleuren.environments')) == 0) {
            return true;
        }
        if (in_array(App::environment(), config('sleuren.environments'))) {
            return false;
        }

        return true;
    }

    /**
     * @param string|null $id
     */
    private function setLastExceptionId(?string $id)
    {
        $this->lastExceptionId = $id;
    }

    /**
     * Get the last exception id given to us by the sleuren API.
     * @return string|null
     */
    public function getLastExceptionId()
    {
        return $this->lastExceptionId;
    }

    /**
     * @param Throwable $exception
     * @return array
     */
    public function getExceptionData(Throwable $exception, $data = [])
    {
        $data['fullUrl']       = Request::fullUrl();
        $data['previousUrl']   = Request::server('HTTP_REFERER');
        if (Request::route()) {
            $data['route'] = [
                'name'         => Request::route()->getName(),
                'uri'          => Request::route()->uri(),
                'method'       => Request::method(),
                'action'       => Request::route()->getAction(),
                'parameters'   => Request::route()->parameters(),
            ];
        }
        $data['message'] = $exception->getMessage() ?? '-';
        $data['file']    = $exception->getFile();
        $data['line']    = $exception->getLine();
        $data['class']   = get_class($exception);
        $data['storage'] = [
            'SERVER' => [
                'REQUEST_METHOD'  => Request::server('REQUEST_METHOD'),
                'HTTP_USER_AGENT' => Request::server('HTTP_USER_AGENT'),
                'SERVER_PROTOCOL' => Request::server('SERVER_PROTOCOL'),
                'SERVER_SOFTWARE' => Request::server('SERVER_SOFTWARE'),
                'SERVER_NAME'     => Request::server('SERVER_NAME'),
                'SERVER_ADMIN'    => Request::server('SERVER_ADMIN'),
                'REQUEST_TIME'    => Request::server('REQUEST_TIME'),
                'USER'            => Request::server('USER'),
                'IP'              => Request::server('REMOTE_ADDR'),
                'PORT'            => Request::server('REMOTE_PORT'),
                'KERNEL'          => php_uname('a'),
                'OS_NAME'         => php_uname('s'),
                'OS_VERSION'      => php_uname('r'),
                'OS_ARCH'         => php_uname('m'),
            ],
            'FRAMEWORK' => [
                'name'    => 'Laravel',
                'version' => app()->version(),
                'locale'  => app()->getLocale(),
                'debug'   => config('app.debug') ? 'true' : 'false',
                'env'     => config('app.env'),
                'cli'     => $this->runningInConsole(),
            ],
            'SDK' => [
                'name'    => Sleuren::NAME,
                'version' => Sleuren::VERSION,
            ],
            'PHP' => [
                'version'      => PHP_VERSION,
                'extensions'   => get_loaded_extensions(),
                'memory_limit' => ini_get('memory_limit'),
                'timezone'     => date_default_timezone_get(),
                'sapi'         => php_sapi_name(),
            ],
            'PROJECT' => [
                'release' => $this->command('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD'),
                'version' => $this->command("git log -1 --pretty=format:'%h' --abbrev-commit"),
                'branch'  => $this->command('git rev-parse --abbrev-ref HEAD'),
                'tag'     => $this->command('git describe --tags --abbrev=0'),
                'path'    => base_path(),
                'name'    => config('app.name'),
            ],
            'OLD'          => $this->filterVariables(Request::hasSession() ? Request::old() : []),
            'GIT'          => $this->getGitInfo(),
            'COOKIE'       => $this->filterVariables(Request::cookie()),
            'SESSION'      => $this->filterVariables(Request::hasSession() ? Session::all() : []),
            'HEADERS'      => $this->filterVariables(Request::header()),
            'PARAMETERS'   => $this->filterVariables($this->filterParameterValues(Request::all())),
            'COMPOSER_PACKAGES' => $this->getComposerPackages(),
            'NPM_PACKAGES' => $this->getNpmPackages(),
            'LOGS'         => $this->logRecorder->getLogMessages(),
            'JOBS'         => $this->jobRecorder->getJob(),
            'QUERIES'      => $this->queryRecorder->getQueries(),
            'DUMP'         => $this->dumpRecorder->getDumps(),
        ];
        $data['storage'] = array_filter($data['storage']);
        $count = 5;
        $lines = file($data['file']);
        $data['executor'] = [];
        if (count($lines) < $count) {
            $count = count($lines) - $data['line'];
        }
        for ($i = -1 * abs($count); $i <= abs($count); $i++) {
            $data['executor'][] = $this->getLineInfo($lines, $data['line'], $i);
        }
        $data['executor'] = array_filter($data['executor']);
        if ($data['class'] == 'Symfony\Component\Debug\Exception\FatalErrorException') {
            preg_match("~^(.+)' in ~", $data['exception'], $matches);
            if (isset($matches[1])) {
                $data['exception'] = $matches[1];
            }
        }
        $data['error'] = $data['error'] = $exception->getTraceAsString();
        return $data;
    }

    /**
     * @param array $parameters
     * @return array
     */
    public function filterParameterValues($parameters)
    {
        return collect($parameters)->map(function ($value) {
            if ($this->shouldParameterValueBeFiltered($value)) {
                return '...';
            }

            return $value;
        })->toArray();
    }

    /**
     * Determines whether the given parameter value should be filtered.
     *
     * @param mixed $value
     * @return bool
     */
    public function shouldParameterValueBeFiltered($value)
    {
        return $value instanceof UploadedFile;
    }

    /**
     * @param $variables
     * @return array
     */
    public function filterVariables($variables)
    {
        if (is_array($variables)) {
            array_walk($variables, function ($val, $key) use (&$variables) {
                if (is_array($val)) {
                    $variables[$key] = $this->filterVariables($val);
                }
                foreach ($this->blacklist as $filter) {
                    if (Str::is($filter, strtolower($key))) {
                        $variables[$key] = '***';
                    }
                }
            });

            return $variables;
        }

        return [];
    }

    /**
     * Gets information from the line.
     *
     * @param $lines
     * @param $line
     * @param $i
     *
     * @return array|void
     */
    private function getLineInfo($lines, $line, $i)
    {
        $currentLine = $line + $i;

        $index = $currentLine - 1;

        if (!array_key_exists($index, $lines)) {
            return;
        }

        return [
            'line_number' => $currentLine,
            'line' => $lines[$index],
        ];
    }

    /**
     * @param $exceptionClass
     * @return bool
     */
    public function isSkipException($exceptionClass)
    {
        return in_array($exceptionClass, config('sleuren.except'));
    }

    /**
     * @param array $data
     * @return bool
     */
    public function isSleepingException(array $data)
    {
        if (config('sleuren.sleep', 0) == 0) {
            return false;
        }

        return Cache::has($this->createExceptionString($data));
    }

    /**
     * @param array $data
     * @return string
     */
    private function createExceptionString(array $data)
    {
        return 'sleuren.' . Str::slug($data['storage']['SERVER']['SERVER_NAME'] . '_' . $data['message'] . '_' . $data['line'] . '_' . $data['file'] . '_' . $data['class']);
    }

    /**
     * @param $exception
     * @return \GuzzleHttp\Promise\PromiseInterface|\Psr\Http\Message\ResponseInterface|null
     */
    private function logError($exception)
    {
        return $this->client->report([
            'exception' => $exception,
            'user' => $this->getUser(),
        ]);
    }

    /**
     * @return array|null
     */
    public function getUser()
    {
        if (function_exists('auth') && (app() instanceof \Illuminate\Foundation\Application && auth()->check())) {
            /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
            $user = auth()->user();

            if ($user instanceof \Sleuren\Concerns\Sleurenable) {
                return $user->toSleuren();
            }

            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                return $user->toArray();
            }
        }

        return null;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function addExceptionToSleep(array $data)
    {
        $exceptionString = $this->createExceptionString($data);

        return Cache::put($exceptionString, $exceptionString, config('sleuren.sleep'));
    }

    private function getGitInfo()
    {
        try {
            $git = [
                'hash' => $this->hash(),
                'message' => $this->message(),
                'tag' => $this->tag(),
                'remote' => $this->remote(),
            ];
            return $git;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function hash(): ?string
    {
        return $this->command("git log --pretty=format:'%H' -n 1") ?: null;
    }

    private function message(): ?string
    {
        return $this->command("git log --pretty=format:'%s' -n 1") ?: null;
    }

    private function tag(): ?string
    {
        return $this->command('git describe --tags --abbrev=0') ?: null;
    }

    private function remote(): ?string
    {
        return $this->command('git config --get remote.origin.url') ?: null;
    }

    private function command($command)
    {
        $process = Process::fromShellCommandline($command, $this->baseDir)->setTimeout(3);
        $process->run();
        return trim($process->getOutput());
    }

    private function getNpmPackages(): array
    {
        $npmPackages = [];
        if (file_exists(base_path('package.json'))) {
            $npmPackages = json_decode(file_get_contents(base_path('package.json')), true)['devDependencies'] ?? [];
            $npmPackages = array_merge($npmPackages, json_decode(file_get_contents(base_path('package.json')), true)['dependencies'] ?? []);
        }
        return $npmPackages;
    }

    private function getComposerPackages(): array
    {
        $composerPackages = [];
        if (file_exists(base_path('composer.json'))) {
            $composerPackages = json_decode(file_get_contents(base_path('composer.json')), true)['require-dev'] ?? [];
            $composerPackages = array_merge($composerPackages, json_decode(file_get_contents(base_path('composer.json')), true)['require'] ?? []);
        }
        return $composerPackages;
    }

    private function runningInConsole(): bool
    {
        if (isset($_ENV['APP_RUNNING_IN_CONSOLE'])) {
            return $_ENV['APP_RUNNING_IN_CONSOLE'] === 'true';
        }
        return in_array(php_sapi_name(), ['cli', 'phpdb']);
    }
}
