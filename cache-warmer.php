<?php

class cacheWarmer
{
    protected string $startUrl = '';
    protected int $maxLevel = 2;
    protected int $sleep = 0;
    protected int $concurrency = 1;
    protected string $userAgent = 'CacheWarmer/1.1 (https://github.com/fabrizioballiano/cache-warmer)';
    protected array $excludes = [];
    protected array $links = [];
    protected array $queue = [];
    protected array $visited = [];
    protected array $stats = [
        'total' => 0,
        'failed' => 0,
        'startTime' => 0
    ];

    function __construct()
    {
        $this->checkArguments();
    }
    private function checkArguments()
    {
        if (PHP_SAPI!=='cli') {
            exit("__FILE__ is a command line only script.");
        }

        $opts = getopt('u:l:e:s:c:h', ['url:', 'level:', 'excludes:', 'help', 'sleep:', 'concurrency:']);
        if ($opts === false) {
            $opts = [];
        }
        $url = $opts['u'] ?? $opts['url'] ?? '';
        if (is_array($url)) {
            $url = end($url);
        }
        $isHelp = isset($opts['h']) || isset($opts['help']);
        $excludes = $opts['e'] ?? $opts['excludes'] ?? null;
        $level = $opts['l'] ?? $opts['level'] ?? null;
        $sleep = $opts['s'] ?? $opts['sleep'] ?? null;
        $concurrency = $opts['c'] ?? $opts['concurrency'] ?? null;
        $argsMultiple = (is_array($sleep) || is_array($level) || is_array($excludes) || is_array($concurrency));

        $parsedUrl = parse_url((string)$url);
        $scheme = $parsedUrl['scheme'] ?? '';
        $errUrl = $parsedUrl === false || !in_array($scheme, ['http', 'https']);

        if ($isHelp || $errUrl || $argsMultiple) {
            echo "Syntax: php " . $_SERVER['PHP_SELF'] . " -u <url> [-l|--level <level>] [-e|--excludes <excludes>] [-s|--sleep <sleep>] [-c|--concurrency <concurrency>] [-h|--help]\n";
            echo "Example: php " . $_SERVER['PHP_SELF'] . " -u https://www.webpagetest.org/ \n";
            echo "Example: php " . $_SERVER['PHP_SELF'] . " -u https://www.webpagetest.org/ --excludes '/customer,/checkout' \n";
            exit;
        }
        $this->startUrl = (string)$url;
        $this->sleep = (int)($sleep ?? $this->sleep);
        $this->excludes = $excludes ? explode(',', (string)$excludes) : $this->excludes;
        $this->maxLevel = (int)abs((int)($level ?? $this->maxLevel));
        $this->concurrency = (int)abs((int)($concurrency ?? $this->concurrency));
        $excludesStr = implode(',', $this->excludes);
        echo("Url: {$this->startUrl} \nExcludes: {$excludesStr} \nSleep: {$this->sleep} \nLevel: {$this->maxLevel} \nConcurrency: {$this->concurrency}\n\n");

    }
    function execute()
    {
        $this->stats['startTime'] = microtime(true);
        $this->startUrl = rtrim($this->startUrl, '/');
        $this->queue[] = ['url' => $this->startUrl, 'level' => 1];
        $this->visited[] = $this->startUrl;

        $mh = curl_multi_init();
        $activeHandles = [];

        while (!empty($this->queue) || !empty($activeHandles)) {
            // Fill up the active handles
            while (count($activeHandles) < $this->concurrency && !empty($this->queue)) {
                $item = array_shift($this->queue);
                $ch = $this->createCurlHandle($item['url']);
                $activeHandles[(int)$ch] = [
                    'handle' => $ch,
                    'url' => $item['url'],
                    'level' => $item['level'],
                    'startTime' => microtime(true)
                ];
                curl_multi_add_handle($mh, $ch);
            }

            // Execute the handles
            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            if ($status !== CURLM_OK) {
                break;
            }

            // Check for completed handles
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $item = $activeHandles[(int)$ch];
                unset($activeHandles[(int)$ch]);
                curl_multi_remove_handle($mh, $ch);

                $responseTime = round(microtime(true) - $item['startTime'], 2);
                $html = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $this->log("url: {$item['url']}", ['responseTime' => $responseTime, 'code' => $httpCode]);
                    $this->stats['total']++;
                    
                    if ($item['level'] < $this->maxLevel) {
                        $links = $this->linksExtractor($html);
                        $links = $this->filterLinks($links);
                        foreach ($links as $link) {
                            if (!in_array($link, $this->visited)) {
                                $this->visited[] = $link;
                                $this->queue[] = ['url' => $link, 'level' => $item['level'] + 1];
                            }
                        }
                    }
                } else {
                    $error = curl_error($ch);
                    $this->log("FAILED: {$item['url']}", ['responseTime' => $responseTime, 'code' => $httpCode, 'error' => $error]);
                    $this->stats['failed']++;
                }

                curl_close($ch);
                if ($this->sleep > 0) {
                    sleep($this->sleep);
                }
            }

            if ($running) {
                curl_multi_select($mh);
            }
        }

        curl_multi_close($mh);
        $this->printSummary();
    }

    private function createCurlHandle(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        return $ch;
    }

    private function printSummary()
    {
        $duration = round(microtime(true) - $this->stats['startTime'], 2);
        echo "\nSummary:\n";
        echo "Total URLs visited: {$this->stats['total']}\n";
        echo "Failed requests: {$this->stats['failed']}\n";
        echo "Total duration: {$duration} s\n";
    }
    private function filterLinks(array $links)
    {
        $links2=[];
        foreach($links as $link) {
            $link=rtrim($link,'/');
            $link = $this->removeQueryFragment($link);
            // exclude not web links
            if (!str_starts_with($link,'/') && !str_starts_with($link,'http')) {
                continue;
            }
            // relative to absolute
            if (!str_starts_with($link,'http')) {
                $link = $this->relativeToAbsolute($link);
            }
            // exclude links not starting with "startUrl"
            if (!str_starts_with($link,$this->startUrl)) {
                continue;
            }
            if ($link==$this->startUrl) {
                continue;
            }
            // remove excluded links
            // todo: distinguish relative path excludes
            foreach($this->excludes as $exclude) {
                if (strpos($link,$exclude)!==false) {
                    continue 2;
                }
            }

            array_push($links2,$link);
        }
        $links2=array_unique($links2);
        //sort($links2);
        return $links2;
    }
    public function relativeToAbsolute($url)
    {
        if (parse_url((string)$url, PHP_URL_SCHEME)) {
            return $url;
        }
        $url = (string)$url;
        if (!str_starts_with($url, '/')) {
            $newUrl = $this->startUrl . '/' . $url;
        } else {
            $parsed = parse_url($this->startUrl);
            $scheme = $parsed['scheme'] ?? 'http';
            $host = $parsed['host'] ?? 'localhost';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $newUrl = $scheme . '://' . $host . $port . $url;
        }
        return $newUrl;
    }
    public function linksExtractor($html)
    {
        $links=[];
        if (preg_match_all('/<a\s+[^>]*href\s*=\s*["\']([^"\']+)["\']/i',$html,$m))
        {
            $links=$m[1];
        }
        return $links;
    }
    private function log($msg, array $context = [])
    {
        $dateStr = date('Y-m-d\TH:i:sP');
        $responseTimeStr = ($context['responseTime'] ?? '') ? " [{$context['responseTime']} s]" : "";
        $codeStr = (isset($context['code']) && $context['code']) ? " [HTTP {$context['code']}]" : "";
        $errorStr = ($context['error'] ?? '') ? " [Error: {$context['error']}]" : "";
        $msg = "[$dateStr]{$responseTimeStr}{$codeStr}{$errorStr} $msg\n";
        echo $msg;
    }


    private function removeQueryFragment(string $url)
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $link = "{$scheme}{$host}{$port}{$path}";
        return $link;
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    (new cacheWarmer())->execute();
}



