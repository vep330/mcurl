<?php

namespace MCurl;

class Result {
    const TYPE_ERROR_NETWORK = 1;
    const TYPE_ERROR_HTTP = 2;

    protected $query = [];
    protected $info_mh = [];

    protected $rawHeaders = '';

    public function __construct(array $query, array $info_mh) {
        $this->query = $query;
        $this->info_mh = $info_mh;
    }

    public function __destruct() {
        if (isset($this->query['opts'][CURLOPT_WRITEHEADER]) && is_resource($this->query['opts'][CURLOPT_WRITEHEADER]))  {
            fclose($this->query['opts'][CURLOPT_WRITEHEADER]);
        }

        if (is_resource($this->query['ch']))  {
            curl_close($this->query['ch']);
        }
    }

    /**
     * @see curl_getinfo();
     * @return mixed
     */
    public function getInfo(): ?array {
        if($info = curl_getinfo($this->query['ch'])) {
            return $info;
        }

        return null;
    }

    /**
     * Result http code
     * @see curl_getinfo($ch, CURLINFO_HTTP_CODE)
     * @return int
     */
    public function getHttpCode(): int {
        return (int) curl_getinfo($this->query['ch'], CURLINFO_HTTP_CODE);
    }

    public function getHeaders(): string {
        if (!strlen($this->rawHeaders) and isset($this->query['opts'][CURLOPT_WRITEHEADER])) {
            rewind($this->query['opts'][CURLOPT_WRITEHEADER]);
            $this->rawHeaders = trim(stream_get_contents($this->query['opts'][CURLOPT_WRITEHEADER]));
        }

        return $this->rawHeaders;
    }

    /**
     * Result in request
     */
    public function getBody(): string {
        return trim(curl_multi_getcontent($this->query['ch']));
    }

    /**
     * return params request
     * @return mixed
     */
    public function getParams() {
        return $this->query['params'];
    }

    /**
     * Has error
     */
    public function hasError(): bool {
        $errorType = $this->getErrorType();
        return !is_null($errorType);
    }

    /**
     * Return network if has curl error or http if http code >=400
     */
    public function getErrorType(): ?int {
        if ($this->info_mh['result']) {
            return self::TYPE_ERROR_NETWORK;
        }

        if ($this->getHttpCode() >= 400) {
            return self::TYPE_ERROR_HTTP;
        }

        return null;
    }

    /**
     * Return message error
     */
    public function getError(): ?string {
        $message = null;

        switch($this->getErrorType()) {
            case self::TYPE_ERROR_NETWORK: {
                $message = curl_strerror($this->info_mh['result']);
                break;
            }
            case self::TYPE_ERROR_HTTP: {
                $message = 'http error ' . $this->getHttpCode();
                break;
            }
        }

        return $message;
    }

    /**
     * Return code error
     */
    public function getErrorCode(): ?int {
        $number = null;

        switch($this->getErrorType()) {
            case self::TYPE_ERROR_NETWORK: {
                $number = $this->info_mh ? (int) $this->info_mh['result'] : -1;
                break;
            }
            case self::TYPE_ERROR_HTTP: {
                $number = $this->getHttpCode();
                break;
            }
        }

        return $number;
    }
}
