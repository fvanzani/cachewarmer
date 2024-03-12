<?php

class cacheWarmer
{
    protected string $startUrl = '';
    protected int $maxLevel = 2;
    protected int $sleep = 0;
    protected array $excludes = [];
    protected array $links=[];

    function __construct()
    {
        $this->checkArguments();
    }
    private function checkArguments()
    {
        if (PHP_SAPI!=='cli') {
            exit("__FILE__ is a command line only script.");
        }

        $opts = getopt('u:l:e:s:h', ['url:', 'level:', 'excludes:', 'help', 'sleep:']);
        $url = $opts['u'] ?? $opts['url'] ?? '';
        $isHelp = isset($opts['h']) || isset($opts['help']);
        $excludes=$opts['e'] ?? $opts['excludes'] ?? null;
        $level=$opts['l'] ?? $opts['level'] ?? null;
        $sleep=$opts['s'] ?? $opts['sleep'] ?? null;
        $argsMultiple = (is_array($sleep)|| is_array($level)||is_array($excludes)||is_array($url)) ;
        $errUrl = parse_url($url)===false || !in_array(parse_url($url,PHP_URL_SCHEME),['http','https']);
        if ($isHelp || $errUrl || $argsMultiple ) {
            echo "Syntax: php ". $_SERVER['PHP_SELF'] ." -u <url> [-l|--level <level>] [-e|--excludes <excludes>] [-s|--sleep <sleep>] [-h|--help]\n";
            echo "Example: php ".$_SERVER['PHP_SELF']." -u https://www.webpagetest.org/ \n";
            echo "Example: php ".$_SERVER['PHP_SELF']." -u https://www.webpagetest.org/ --excludes '/customer,/checkout' \n";
            exit;
        }
        $this->startUrl=$url;
        $this->sleep = (int) ($sleep ??  $this->sleep);
        $this->excludes=$excludes ? explode(',',$excludes): $this->excludes;
        $this->maxLevel = (int) abs( $level ?? $this->maxLevel);
        $excludesStr=implode(',',$this->excludes);
        echo("Url: {$this->startUrl} \nExcludes:{$excludesStr} \nSleep: {$this->sleep} \nLevel: {$this->maxLevel}\n");

    }
    function execute()
    {
        $this->startUrl = rtrim($this->startUrl,'/');
        $this->links[]=$this->startUrl;
        $this->visitUrl($this->startUrl,1);
    }
    private function visitUrl(string $currentUrl, int $currentLevel)
    {
        if ($currentLevel > $this->maxLevel) {
            return;
        }
        $startTime = microtime(true);
        $html=$this->getHtmlFromUrl($currentUrl);
        $endTime = microtime(true);
        $responseTime = round($endTime - $startTime, 2);
        $this->log("url: $currentUrl",['responseTime'=>$responseTime]);
        sleep($this->sleep);
        $links=$this->linksExtractor($html);
        $links = $this->filterLinks($links);
        // remove already visited links
        $links = array_diff($links, $this->links);
        // add new links to property links
        $this->links = array_merge($links, $this->links);
        //print_r($links);
        //$this->log("New links: ".count($links)." All links: ".count($this->links));

        foreach ($links as $link) {
            $this->visitUrl($link, $currentLevel + 1);
        }
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
        if (parse_url($url,PHP_URL_SCHEME)) {
            return $url;
        }
        if (!str_starts_with($url,'/')) {
            $newUrl=$this->startUrl.'/'.$url;
        } else {
            $newUrl=parse_url($this->startUrl,PHP_URL_SCHEME).'://'.parse_url($this->startUrl,PHP_URL_HOST).$url;
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
    private function log($msg,array $context=[])
    {
        $dateStr = date('Y-m-d\TH:i:sP');
        $responseTimeStr=($context['responseTime'] ?? '') ? " [{$context['responseTime']} s]": "";
        $msg = "[$dateStr]{$responseTimeStr} $msg\n";
        echo $msg;
        //$logName = pathinfo(__FILE__, PATHINFO_FILENAME) . '.log';
        // file_put_contents($msg, FILE_APPEND);
    }

    private function getHtmlFromUrl($url)
    {
        return @file_get_contents($url);
    }

    private function removeQueryFragment(string $url)
    {
        $scheme=parse_url($url,PHP_URL_SCHEME)?parse_url($url,PHP_URL_SCHEME).'://':'';
        $host=parse_url($url,PHP_URL_HOST);
        $port=parse_url($url,PHP_URL_PORT)?':'.parse_url($url,PHP_URL_PORT):'';
        $path=parse_url($url,PHP_URL_PATH);
        $link="{$scheme}{$host}{$port}{$path}";
        return $link;
    }
}

(new cacheWarmer())->execute();



