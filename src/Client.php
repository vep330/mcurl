<?php

namespace MCurl;

/**
 * Summary of Client
 */
class Client {

    /**
     * Result write in temporary files. Dir @see sys_get_temp_dir()
     */
    const STREAM_FILE = 'php://temp/maxmemory:0';

    /**
     * Not exec request
     * @var array
     */
    protected $queries = [];

    /**
     * Not exec count request
     * @var int
     */
    protected $queriesCount = 0;

    /**
     * Exec request
     * @var array
     */
    protected $queriesQueue = [];

    /**
     * Exec count request
     * @var int
     */
    protected $queriesQueueCount = 0;

    /**
     * Max asynchron request
     * @var int
     */
    protected $maxRequest = 10;

    /**
     * Save results
     * @var array
     */
    protected $results = [];

    /**
     * @see curl_multi_init()
     * @var null
     */
    protected $mh;

    /**
     * has Request
     * @var bool
     */
    protected $isRunMh = false;

    protected $enableHeaders = false;

    public function __construct() {
        $this->mh = \curl_multi_init();
    }

    public function __destruct() {
        \curl_multi_close($this->mh);
    }

    /**
     * Add request
     * @param array $opts Options curl. Example: array( CURLOPT_URL => 'http://example.com' );
     * @param array|string $params All data, require binding to the request or if string: identity request
     * @return bool
     */
    public function add(array $opts = [], array $params = []): bool {
        if (!isset($opts[CURLOPT_WRITEHEADER]) and $this->enableHeaders) {
            $opts[CURLOPT_WRITEHEADER] = fopen(self::STREAM_FILE, 'r+');
            if (!$opts[CURLOPT_WRITEHEADER]) {
                return false;
            }
        }

        $query = [
            'opts' => $opts,
            'params' => $params
        ];

        $this->queries[] = $query;
        $this->queriesCount++;

        return true;
    }

    /**
     * Enable headers in result. Default false
     * @param bool $enable
     */
    public function enableHeaders(bool $enable = true) {
        $this->enableHeaders = $enable;
    }

    /**
     * Max request in Asynchron query
     * @param $max int default:10
     * @return void
     */
    public function setMaxRequest(int $max) {
        $this->maxRequest = $max;
        // PHP 5 >= 5.5.0
        if (function_exists('curl_multi_setopt')) {
            \curl_multi_setopt($this->mh, CURLMOPT_MAXCONNECTS, $max);
        }
    }

    /**
     * Return count query
     * @return int
     */
    public function getCountQuery(): int {
        return $this->queriesCount;
    }

    /**
     * Return count query
     * @return int
     */
    public function getQueueCountQuery(): int {
        return $this->queriesQueueCount;
    }

    /**
     * Exec cURL resource
     * @return bool
     */
    public function run(): bool {
        if ($this->isRunMh) {
            $info = false;

            do {
                \curl_multi_exec($this->mh, $active);

                if(($info = \curl_multi_info_read($this->mh, $active)) === false) {
                    \curl_multi_select($this->mh, 1);
                }

            } while ($active == $this->queriesQueueCount and $info === false);

            if ($info['msg'] === CURLMSG_DONE) {
                $id = intval($info['handle']);

                $this->queriesQueueCount--;
                $query = $this->queriesQueue[$id];

                $this->results[] = new Result($query, $info);

                \curl_multi_remove_handle($this->mh, $query['ch']);
                unset($this->queriesQueue[$id]);
            }

            return ($this->processedQuery() OR $this->queriesQueueCount > 0) ? true : ($this->isRunMh = false);
        }

        return $this->processedQuery();
    }

    /**
     * Return one next result, wait first exec request
     * @return Result|null
     */
    public function next(): ?Result {
        while(empty($this->results) AND $this->run()) {}
        return array_pop($this->results);
    }

    protected function processedQuery(): bool {
        if ($this->queriesCount == 0) {
            return false;
        }

        $count = $this->maxRequest - $this->queriesQueueCount;

        if ($count > 0) {
            $limit = $this->queriesCount < $count ? $this->queriesCount : $count;

            $this->queriesCount -= $limit;
            $this->queriesQueueCount += $limit;

            while($limit--) {
                $key = key($this->queries);
                $query = $this->queries[$key];
                unset($this->queries[$key]);

                $query['ch'] = curl_init();
                \curl_setopt_array($query['ch'], $query['opts']);

                \curl_multi_add_handle($this->mh, $query['ch']);
                $id = intval($query['ch']);
                $this->queriesQueue[$id] = $query;
            }
        }

        return $this->isRunMh = true;
    }
}
