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

    /** @var resource 301 HTTP code log handler */
    protected $e301_handler;
    /** @var resource 302 HTTP code log handler */
    protected $e302_handler;
    /** @var resource 403 HTTP code log handler */
    protected $e403_handler;
    /** @var resource 404 HTTP code log handler */
    protected $e404_handler;

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
     * Protect internal and system links.
     *
     * @param $chunk array Collection of urls for clearing
     * @return array Updated collection
     */
    protected function clear($chunk)
    {
        $clean = [];

        foreach ($chunk as $url) {
            if ($url[0] == '/') {
                $clean[] = $this->domain.$url;
                continue;
            } elseif ($url[0] == '' || $url[0] == '#') {
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
        return array_chunk($data, self::MULTI_CURL_HANDLERS);
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
     * Parse map domain
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
     */
    public function __construct($map, $request = null)
    {
        $this->map = $map;
        $this->request = isset($request) ? $request : new Request();
        $this->domain();

        $this->e301_handler = fopen('log_301.csv', 'w');
        $this->e302_handler = fopen('log_302.csv', 'w');
        $this->e403_handler = fopen('log_403.csv', 'w');
        $this->e404_handler = fopen('log_404.csv', 'w');
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
        if ($root === self::DEFAULT_ROOT) {
            $this->process($url, $data, 'a');
        }

        $this->trace($code);

        switch ($code) {
            case '0':
                $this->trace('NULL url '.$url.' root '.$root);
                break;
            case '301':
                $this->log($this->e301_handler, [$url, $root]);
                break;
            case '302':
                $this->log($this->e302_handler, [$url, $root]);
                break;
            case '403':
                $this->log($this->e403_handler, [$url, $root]);
                break;
            case '404':
                $this->log($this->e404_handler, [$url, $root]);
                break;
            case '200':
                if ($root === self::DEFAULT_ROOT) {
                    $this->process($url, $data, 'a');
                }
                return true;
        }

        return false;
    }
}
