<?php
/**
 * Created by Pavlo Onysko.
 * Date: 11/10/16
 */

namespace helpers;

class Request
{
    const REDIRECT_CODES = ['300', '301', '302', '303', '304', '305', '306', '307'];

    const ERROR_CODES = ['0', '400', '401', '402', '403', '404', '405', '406', '407', '408', '409', '410', '411', '412', '413', '414', '415', '416', '417', '500', '501', '502', '503', '504', '505'];

    /**
     * Create cUrl descriptor.
     *
     * @param $url string cUrl url parameter
     * @return resource Created descriptor with options
     */
    protected function create($url)
    {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

        return $c;
    }

    /**
     * Create simple cUrl request.
     *
     * @param $url string Request url
     * @return mixed Response body
     */
    public function single($url)
    {
        $c = $this->create($url);
        $r = curl_exec($c);
        curl_close($c);

        return $r;
    }

    /**
     * Create multi cUrl request.
     *
     * @param $root string root url
     * @param $urls array Collection of urls to parse
     * @param callable $callback External callback handler
     */
    public function multi($root, $urls, callable $callback)
    {
        $m = curl_multi_init();

        // Collection of cUrl descriptors
        $descriptors = [];

        foreach ($urls as $url) {
            $c = $this->create($url);
            curl_multi_add_handle($m, $c);

            // Save descriptor
            $descriptors[$url] = $c;
        }

        $active = null;

        do {
            $exec = curl_multi_exec($m, $active);
        } while ($exec == CURLM_CALL_MULTI_PERFORM);

        while ($active && $exec == CURLM_OK) {
            while (curl_multi_exec($m, $active) === CURLM_CALL_MULTI_PERFORM);

            if (curl_multi_select($m) != -1) {
                do {
                    $exec = curl_multi_exec($m, $active);
                } while ($exec == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($descriptors as $url => $descriptor) {
            $code = curl_getinfo($descriptor, CURLINFO_HTTP_CODE);

            $callbackParams = ['root' => $root, 'url' => $url, 'code' => $code];

            $callbackParams['data'] = ($code == '200' && $root === Crawler::DEFAULT_ROOT) ?
                curl_multi_getcontent($descriptor) :
                null;

            // Call external handler
            call_user_func_array($callback, $callbackParams);

            // Remove cUrl handler from multi query
            curl_multi_remove_handle($m, $descriptor);
        }

        curl_multi_close($m);
    }
}
