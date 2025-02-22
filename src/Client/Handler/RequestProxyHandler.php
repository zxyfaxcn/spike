<?php

/*
 * This file is part of the slince/spike package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spike\Client\Handler;

use Slince\EventDispatcher\Event;
use Spike\Client\Event\Events;
use Spike\Common\Exception\InvalidArgumentException;
use Spike\Common\Protocol\SpikeInterface;
use Spike\Common\Tunnel\HttpTunnel;
use Spike\Common\Tunnel\TunnelInterface;
use Spike\Client\Worker;

class RequestProxyHandler extends MessageActionHandler
{
    /**
     * {@inheritdoc}
     */
    public function handle(SpikeInterface $message)
    {
        $tunnelInfo = $message->getBody();
        $tunnel = $this->client->getConfiguration()->getTunnels()->filter(function(TunnelInterface $tunnel) use ($tunnelInfo){
            return $tunnel->match($tunnelInfo);
        })->first();
        if (!$tunnel) {
            throw new InvalidArgumentException('Can not find the matching tunnel');
        }
        if ($tunnel instanceof HttpTunnel) {
            $tunnel->setProxyHost($tunnelInfo['proxyHost']);
        }
        // 创建流链接
        $client = $this->createWorker($tunnel, $message->getHeader('public-connection-id'));
        $this->getEventDispatcher()->dispatch(new Event(Events::REQUEST_PROXY, $this, [
            'tunnel' => $tunnel,
            'client' => $client,
        ]));
    }

    /**
     * Creates worker.
     *
     * @param TunnelInterface $tunnel
     * @param $publicConnectionId
     *
     * @return Worker\WorkerInterface
     */
    protected function createWorker(TunnelInterface $tunnel, $publicConnectionId)
    {
        if ($tunnel instanceof HttpTunnel) {
            $worker = new Worker\HttpWorker($this->client, $tunnel, $publicConnectionId);
        } else {
            $worker = new Worker\TcpWorker($this->client, $tunnel, $publicConnectionId);
        }
        $worker->start();
        $this->client->getWorkers()->add($worker);

        return $worker;
    }
}