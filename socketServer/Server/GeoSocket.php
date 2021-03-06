<?php

namespace app\socketServer\Server;

use React\Socket\ConnectionInterface;
use app\socketServer\Logger;

class GeoSocket
{
    use EmitSubscriber;

    private $host;
    private $port;
    private $loop;
    private $connections = [];
    private $unresolvedConnections = [];
    private $unresolvedConnectionId = 0;

    public function getLoop()
    {
        return $this->loop;
    }

    public function getUnresolved()
    {
        return $this->unresolvedConnections;
    }

    public function unresolvedsIncrement()
    {
        return $this->unresolvedConnectionId++;
    }

    public function resolve(GeoConnection $geoConnection)
    {
        $imei = $geoConnection->getImei()->getImeiNumber();
        $id = $geoConnection->getUnresolvedId();
        $oldConnection = $this->findByImei($imei);
        if ($oldConnection) {
            $geoConnection->copyState($oldConnection);
        }

        $this->connections[$imei] = $geoConnection;
        unset($this->unresolvedConnections[$id]);
    }


    public function __construct($host, $port, &$loop)
    {
        $this->host = $host;
        $this->port = $port;
        $this->loop = $loop;

        //Creation of new TCP socket
        $socket = new \React\Socket\Server($this->host . ":" . $this->port, $this->loop);
        $socket = new \React\Socket\LimitingServer($socket, 200);

        $socket->on('connection', function(ConnectionInterface $connection) {
            $unresolvedId = $this->unresolvedsIncrement();
            $this->unresolvedConnections[$unresolvedId] = new GeoConnection($this, $connection, $unresolvedId);
        });
        $socket->on('error', function (Exception $e) {
            Logger::note('error: ' . $e->getMessage());
        });

    }

    private function findByImei($imei)
    {
        foreach ($this->connections as $connection) {
            if ($connection->getImei()->getImeiNumber() === (string)$imei) {
                return $connection;
            }
        }
    }

    public function removeConnection($imei)
    {
        unset($this->connections[$imei]);
    }
}