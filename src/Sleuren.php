<?php

namespace Sleuren;


use Throwable;
use Sleuren\Http\Client;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Process\Process;
use Composer\InstalledVersions;
use Jean85\PrettyVersions;
use PackageVersions\Versions;
use DB;

class Sleuren
{
    /** @var Client */
    private $client;

    /** @var array */
    private $blacklist = [];

    /** @var null|string */
    private $lastExceptionId;

    /**
     * @var array<string, string> The list of installed vendors
     */
    private  $packages = [];

    private ?string $baseDir = null;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;

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
            $data['fullUrl'] = $customData['url'];
            $data['file'] = $customData['file'];
            $data['file_type'] = $fileType;
            $data['error'] = $customData['message'];
            $data['exception'] = $customData['stack'];
            $data['line'] = $customData['line'];
            $data['class'] = null;

            $count = config('sleuren.lines_count');

            if ($count > 50) {
                $count = 12;
            }

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
    public function getExceptionData(Throwable $exception)
    {
        $data = [];

        $data['environment'] = App::environment();
        $data['host'] = Request::server('SERVER_NAME');
        $data['method'] = Request::method();
        $data['fullUrl'] = Request::fullUrl();
        $data['exception'] = $exception->getMessage() ?? '-';
        $data['error'] = $exception->getTraceAsString();
        $data['line'] = $exception->getLine();
        $data['file'] = $exception->getFile();
        $data['class'] = get_class($exception);
        $data['release'] = config('sleuren.release', null);

        $data['storage'] = [
            'SERVER' => [
                'USER' => Request::server('USER'),
                'HTTP_USER_AGENT' => Request::server('HTTP_USER_AGENT'),
                'SERVER_PROTOCOL' => Request::server('SERVER_PROTOCOL'),
                'SERVER_SOFTWARE' => Request::server('SERVER_SOFTWARE'),
                'SERVER_ADDR' => Request::server('SERVER_ADDR'),
                'SERVER_PORT' => Request::server('SERVER_PORT'),
                'SERVER_NAME' => Request::server('SERVER_NAME'),
                'SERVER_ADMIN' => Request::server('SERVER_ADMIN'),
                'PHP_VERSION' => PHP_VERSION,
                'KERNEL' => php_uname('a'),
                'OS_NAME' => php_uname('s'),
                'OS_VERSION' => php_uname('r'),
                'OS_ARCH' => php_uname('m'),
            ],
            'PACKAGES' => $this->getComposerPackages(),
            'NPM_PACKAGES' => $this->getNpmPackages(),
            'packagesCount' => count($this->getComposerPackages() + $this->getNpmPackages()),
            'RESOURCES' => [
                'MEMORY' => $this->getMemoryUsage(),
                'CPU' => $this->getCpuUsage(),
                'NETWORK' => $this->getNetworkUsage(),
                'DISK' => [
                    'free' => $this->humanConvert(disk_free_space('/')),
                    'total' => $this->humanConvert(disk_total_space('/')),
                ],
                'UPTIME' => [
                    'uptime' => $this->getUptime(),
                ],
                'DATABASE' => [
                    'driver' => config('database.connections.' . config('database.default') . '.driver'),
                    'database' => config('database.connections.' . config('database.default') . '.database'),
                    'databaseSize' => $this->getDatabaseSize(),
                    'databaseVersion' => $this->getDatabaseVersion(),
                    'tablesCount' =>  $this->getTablesCount(),
                    'indexesCount' => $this->getIndexesCount(),
                ],
                'FRAMEWORK' => [
                    'name' => 'Laravel',
                    'version' => app()->version(),
                ],
                'CACHE' => [
                    'driver' => config('cache.default'),
                ],
                'QUEUE' => [
                    'driver' => config('queue.default'),
                ],
                'SESSION' => [
                    'driver' => config('session.driver'),
                    'lifetime' => config('session.lifetime'),
                ],
                'SDK' => $this->getSdkInfo(),
            ],
            'GIT' => $this->getGitInfo(),
            'QUERIES' => $this->getQueries(),
            'OLD' => $this->filterVariables(Request::hasSession() ? Request::old() : []),
            'SESSION' => $this->filterVariables(Request::hasSession() ? Session::all() : []),
            'HEADERS' => $this->filterVariables(Request::header()),
            'PARAMETERS' => $this->filterVariables($this->filterParameterValues(Request::all()))
        ];
        $data['storage'] = array_filter($data['storage']);

        $count = config('sleuren.lines_count');

        if ($count > 50) {
            $count = 12;
        }

        $lines = file($data['file']);
        $data['executor'] = [];

        if (count($lines) < $count) {
            $count = count($lines) - $data['line'];
        }

        for ($i = -1 * abs($count); $i <= abs($count); $i++) {
            $data['executor'][] = $this->getLineInfo($lines, $data['line'], $i);
        }
        $data['executor'] = array_filter($data['executor']);

        // Get project version
        $data['project_version'] = config('sleuren.project_version', null);

        // to make symfony exception more readable
        if ($data['class'] == 'Symfony\Component\Debug\Exception\FatalErrorException') {
            preg_match("~^(.+)' in ~", $data['exception'], $matches);
            if (isset($matches[1])) {
                $data['exception'] = $matches[1];
            }
        }

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
     * Gets information from the sleuren.
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

        return 'sleuren.' . Str::slug($data['host'] . '_' . $data['method'] . '_' . $data['exception'] . '_' . $data['line'] . '_' . $data['file'] . '_' . $data['class']);
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


    /**
     * @return array<string, string>
     */
    private  function getComposerPackages(): array
    {
        if (empty($this->packages)) {
            foreach ($this->getInstalledPackages() as $package) {
                try {
                    $this->packages[$package] = PrettyVersions::getVersion($package)->getPrettyVersion();
                } catch (\Throwable $exception) {
                    continue;
                }
            }
        }

        return $this->packages;
    }

    /**
     * @return string[]
     */
    private  function getInstalledPackages(): array
    {
        if (class_exists(InstalledVersions::class)) {
            return InstalledVersions::getInstalledPackages();
        }

        if (class_exists(Versions::class)) {
            // BC layer for Composer 1, using a transient dependency
            return array_keys(Versions::VERSIONS);
        }

        // this should not happen
        return [];
    }

    private  function getNpmPackages(): array
    {
        $npmPackages = [];
        if (file_exists(base_path('package.json'))) {
            $npmPackages = json_decode(file_get_contents(base_path('package.json')), true)['devDependencies'] ?? [];
        }
        return $npmPackages;
    }

    private  function getNetworkUsage(): array
    {
        $network = [
            'in'  => $this->command('cat /proc/net/dev | grep eth0 | awk \'{print $2}\'') ? $this->humanConvert($this->command('cat /proc/net/dev | grep eth0 | awk \'{print $2}\'')) : '0',
            'out' => $this->command('cat /proc/net/dev | grep eth0 | awk \'{print $10}\'') ? $this->humanConvert($this->command('cat /proc/net/dev | grep eth0 | awk \'{print $10}\'')) : '0',
        ];
        return $network;
    }

    private  function getCpuUsage(): array
    {
        $cpu = [
            'usage' => $this->command('top -bn1 | grep "Cpu(s)" |  sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk \'{print 100 - $1" %"}\''),
            'cores' => $this->command('nproc --all'),
            'model' => $this->command('cat /proc/cpuinfo | grep "model name" | uniq | awk \'{print $4}\''),
            'mhz'   => $this->command('cat /proc/cpuinfo | grep "cpu MHz" | uniq | awk \'{print $4}\''),
            'cache' => $this->command('cat /proc/cpuinfo | grep "cache size" | uniq | awk \'{print $4}\''),
            'load'  => $this->command('uptime | awk \'{print $10 $11 $12}\''),
        ];
        return $cpu;
    }

    private  function getMemoryUsage(): array
    {
        $memory = [
            'total'     => $this->command('free -h | grep Mem | awk \'{print $2}\''),
            'used'      => $this->command('free -h | grep Mem | awk \'{print $3}\''),
            'free'      => $this->command('free -h | grep Mem | awk \'{print $4}\''),
            'shared'    => $this->command('free -h | grep Mem | awk \'{print $5}\''),
            'buffer'    => $this->command('free -h | grep Mem | awk \'{print $6}\''),
            'available' => $this->command('free -h | grep Mem | awk \'{print $7}\''),
        ];
        return $memory;
    }

    private  function getUptime(): string
    {
        return empty($this->command('uptime -p')) ? 'N/A' : $this->command('uptime -p');
    }

    private  function getSdkInfo()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        return ['name' => 'laravel', 'version' => $composer['require']['sleuren/laravel'] ?? 'unknown'];
    }

    private  function getDatabaseSize(){
        try{
            $size = DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.TABLES WHERE table_schema = "' . config('database.connections.' . config('database.default') . '.database') . '"')[0]->size;
            return $size . ' MB';
        }catch (\Exception $e){
            return 'unknown';
        }
    }
    private  function getDatabaseVersion(){
        try{
            return DB::select('SELECT @@version as version')[0]->version;
        }
        catch (\Exception $e){
            return 'unknown';
        }
    }
    private  function getTablesCount(){
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private  function getIndexesCount(){
        try {
            return count(DB::select('SHOW INDEXES'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getGitInfo(){
        try{
            $git = [
                'hash' => $this->hash(),
                'message' => $this->message(),
                'tag' => $this->tag(),
                'remote' => $this->remote(),
                'isDirty' => ! $this->isClean(),
            ];
            return $git;
        }catch (\Exception $e){
            return 'unknown';
        }
    }
    private function getQueries(){
        try{
            return DB::getQueryLog();
        }catch (\Exception $e){
            return 'unknown';
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

    private function isClean(): bool
    {
        return empty($this->command('git status -s'));
    }

    private function command($command)
    {
        $process = Process::fromShellCommandline($command, $this->baseDir)->setTimeout(1);

        $process->run();
        return trim($process->getOutput());
    }

    private function humanConvert($size)
    {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PD');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
}