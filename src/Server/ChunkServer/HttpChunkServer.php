<?php

/*
 * This file is part of the slince/spike package.
 *
 * (c) Slince <taosikai@yeah.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spike\Server\ChunkServer;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use function Slince\Common\httpHeaderBuffer;
use Spike\Common\Protocol\HttpHeaderParser;
use Spike\Server\Server;
use Spike\Version;

/**
 * @codeCoverageIgnore
 */
class HttpChunkServer extends TcpChunkServer
{
    /**
     * {@inheritdoc}
     */
    public function handlePublicConnection(PublicConnection $publicConnection)
    {
        $parser = new HttpHeaderParser();
        httpHeaderBuffer($publicConnection->getConnection(), $parser)->then(function($messages) use ($parser, $publicConnection){
            //Ignore empty message
            if (!$messages) {
                return;
            }
            $message = reset($messages);
            $psrRequest = Message::parseRequest($message);
            $host = $psrRequest->getUri()->getHost();
            if ($this->tunnel->supportProxyHost($host)) {
                $this->tunnel->setProxyHost($host);
                $httpMessage = $message.$parser->getRemainingChunk();
                $publicConnection->setInitBuffer($httpMessage);
                parent::handlePublicConnection($publicConnection);
            } else {
                $body = sprintf('The host "%s" was not bound.', $host);
                $response = $this->makeErrorResponse(404, $body);
                $publicConnection->end(Message::toString($response));
            }
        });
    }

    /**
     * Make an error psr7 response.
     *
     * @param int    $code
     * @param string $message
     *
     * @return Response
     */
    protected function makeErrorResponse($code, $message)
    {
        $message = $message ?: 'Proxy error';

        return new Response($code, [
            'Content-Length' => strlen($message),
            'X-Spiked' => Server::NAME .' '. Version::VERSION,
        ], $message);
    }

    /**
     * {@inheritdoc}
     */
    public function closePublicConnection(PublicConnection $publicConnection, $message = null)
    {
        $publicConnection->end(Message::toString($this->makeErrorResponse(500, $message ?: 'Timeout')));
        $this->publicConnections->removeElement($publicConnection);
    }
}