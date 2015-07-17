<?php

namespace Tarantool\Tests\Integration;

use Tarantool\Client as TarantoolClient;
use Tarantool\Connection\Connection;
use Tarantool\Connection\SocketConnection;
use Tarantool\Encoder\PeclEncoder;
use Tarantool\Encoder\PeclLiteEncoder;
use Tarantool\Tests\Adapter\Tarantool;

class ClientBuilder
{
    const CLIENT_PURE = 'pure';
    const CLIENT_PECL = 'pecl';

    const CONN_TCP = 'tcp';
    const CONN_UNIX = 'unix';

    const ENCODER_PECL = 'pecl';
    const ENCODER_PECL_LITE = 'pecl_lite';

    private $client;
    private $encoder;
    private $connection;
    private $host;
    private $port;

    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }

    public function setEncoder($encoder)
    {
        $this->encoder = $encoder;

        return $this;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    public function build()
    {
        if (self::CLIENT_PECL === $this->client) {
            return new Tarantool($this->host, $this->port);
        }

        $connection = $this->createConnection();
        $encoder = $this->createEncoder();

        if (self::CLIENT_PURE === $this->client) {
            return new TarantoolClient($connection, $encoder);
        }

        throw new \UnexpectedValueException(sprintf('""%" client is not supported.', $this->client));
    }

    private function createConnection()
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        if (self::CONN_TCP === $this->connection) {
            return new SocketConnection($this->host, $this->port);
        }

        /*
        if (self::CONN_UNIX === $this->connection) {
            return new StreamConnection($this->unixUri, $this->port);
        }
        */

        throw new \UnexpectedValueException(sprintf('""%" connection is not supported.', $this->connection));
    }

    private function createEncoder()
    {
        if (self::ENCODER_PECL === $this->encoder) {
            return new PeclEncoder();
        }

        if (self::ENCODER_PECL_LITE === $this->encoder) {
            return new PeclLiteEncoder();
        }

        throw new \UnexpectedValueException(sprintf('""%" encoder is not supported.', $this->encoder));
    }
}