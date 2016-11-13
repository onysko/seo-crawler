<?php
/**
 * Created by Pavlo Onysko.
 * Date: 11/10/16
 */

namespace helpers;

class Crawler
{
    /** int Count of parallel cUrl descriptors */
    const MULTI_CURL_HANDLERS = 10;

    /** array Preg match rules */
    const PREG_RULES = [
        'a'     => '#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is',
        'loc'   => "'<loc>(.*?)</loc>'si"
    ];

    /** string Default analytics root */
    const DEFAULT_ROOT = 'MAP';

    /** @var array Collection of link for crawling */
    protected $links = [];

    /** @var string Url to map for crawling */
    protected $map;

    /** @var Request cUrl helper */
    protected $request;

    /** @var string Domain for parsing internal links */
    protected $domain;

    /** @var Config Configuration data */
    protected $config;

    /** @var resource HTTP error codes log handler */
    protected $e_handler;
    /** @var resource HTTP redirect codes log handler */
    protected $r_handler;

    /**
     * Find links in source data.
     *
     * @param $data string Data for parsing
     * @param $rule string Preg match rule
     * @return array Unique links collection
     */
    protected function grep($data, $rule)
    {
        preg_match_all(self::PREG_RULES[$rule], $data, $urls);

        $urls[1] = $this->clear($urls[1]);

        // Disallow multiple parsing same urls
        $selected = array_diff($urls[1], $this->links);
        $this->links = array_merge($this->links, $selected);

        return $selected;
    }

    /**
     * Protect empty and system links.
     *
     * @param $chunk array Collection of urls for clearing
     * @return array Updated collection
     */
    protected function clear($chunk)
    {
        $clean = [];

        foreach ($chunk as $url) {
            if (empty($url) || $url[0] == '#') {
                continue;
            }

            if ($url[0] == '/') {
                $url = $this->domain.$url;
            }

            if ($this->config->internalOnly && !substr_count($url, $this->domain)) {
                continue;
            }

            $clean[] = $url;
        }

        return $clean;
    }

    /**
     * Chunk data for limited pieces.
     *
     * @param $data array Collection for prepare
     *
     * @return array
     */
    protected function chunk($data)
    {
        return array_chunk($data, $this->config->streamCount);
    }

    /**
     * Trace log message.
     *
     * @param $m string Message
     */
    protected function trace($m)
    {
        echo $m.PHP_EOL;
    }

    /**
     * Parse map domain.
     */
    protected function domain()
    {
        $data = parse_url($this->map);

        $this->domain = $data['scheme'].'://'.$data['host'];
    }

    /**
     * Save crawling result.
     *
     * @param $handler resource CSV file handler
     * @param $data array Data for saving
     */
    protected function log($handler, $data)
    {
        fputcsv($handler, $data);
    }

    /**
     * Crawler constructor.
     *
     * @param $map string Path to root map
     * @param Request|null $request Request helper
     * @param Config|null $config Configuration data
     */
    public function __construct($map, $request = null, $config = null)
    {
        $this->map = $map;
        $this->request = isset($request) ? $request : new Request();
        $this->config = isset($config) ? $config : Config::fromJson();
        $this->domain();


        $this->e_handler = $this->config->errorLog ? fopen('error_log.csv', 'w') : null;
        $this->r_handler = $this->config->redirectLog ? fopen('redirect_log.csv', 'w') : null;
    }

    /**
     * Start crawling process.
     */
    public function start()
    {
        $time = microtime(true);

        $this->process(self::DEFAULT_ROOT, $this->request->single($this->map), 'loc');

        $this->trace('Crawling finished!');
        $this->trace('Total links crawled - '.count($this->links));
        $this->trace('Total time: '.(microtime(true)-$time).'s');
    }

    /**
     * Crawling iteration.
     *
     * @param $root string Source root url
     * @param $data string Url content
     * @param $rule string Preg match rule
     */
    public function process($root, $data, $rule)
    {
        $chunks = $this->chunk($this->grep($data, $rule));

        if (count($chunks) !== 0) {
            foreach ($chunks as $chunk) {
                $chunk = $this->clear($chunk);
                $this->request->multi($root, $chunk, [$this, 'requestCallback']);
            }
        }
    }

    /**
     * cUrl request callback.
     *
     * @param $root string Source root url
     * @param $url string Parsed url
     * @param $code string HTTP header code
     * @param $data string Parsed url content
     *
     * @return bool Callback status
     */
    public function requestCallback($root, $url, $code, $data)
    {
        if ($root === self::DEFAULT_ROOT && $data !== null) {
            $this->process($url, $data, 'a');
        }

        $this->trace($code.' - '.$url);

        if ($this->config->errorLog && in_array($code, Request::ERROR_CODES)) {
            // cUrl errors hook
            $code = $code == '0' ? 'cUrl error' : $code;
            $this->log($this->e_handler, [$url, $root, $code]);
        }

        if ($this->config->redirectLog && in_array($code, Request::REDIRECT_CODES)) {
            $this->log($this->r_handler, [$url, $root, $code]);
        }

        if ($code == '200' && $root === self::DEFAULT_ROOT && $data !== null) {
            $this->process($url, $data, 'a');
            return true;
        }

        return false;
    }
}
