<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace ElephantIO\Engine\SocketIO;

use InvalidArgumentException;

use ElephantIO\Payload\Encoder;
use ElephantIO\Engine\AbstractSocketIO;
use ElephantIO\Engine\Socket;
use ElephantIO\Engine\SequentialStream;

use ElephantIO\Exception\SocketException;
use ElephantIO\Exception\UnsupportedTransportException;
use ElephantIO\Exception\ServerConnectionFailureException;

/**
 * Implements the dialog with Socket.IO version 1.x
 *
 * Based on the work of Mathieu Lallemand (@lalmat)
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 * @link https://tools.ietf.org/html/rfc6455#section-5.2 Websocket's RFC
 */
class Version1X extends AbstractSocketIO
{
    const PROTO_OPEN    = 0;
    const PROTO_CLOSE   = 1;
    const PROTO_PING    = 2;
    const PROTO_PONG    = 3;
    const PROTO_MESSAGE = 4;
    const PROTO_UPGRADE = 5;
    const PROTO_NOOP    = 6;

    const TRANSPORT_POLLING   = 'polling';
    const TRANSPORT_WEBSOCKET = 'websocket';

    /** {@inheritDoc} */
    public function connect()
    {
        if ($this->isConnected()) {
            return;
        }

        $this->handshake();
        $this->upgradeTransport();
    }

    /** {@inheritDoc} */
    public function close()
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->write(static::PROTO_CLOSE);

        $this->socket->close();
        $this->socket = null;
        $this->session = null;
        $this->cookies = [];
    }

    /** {@inheritDoc} */
    public function emit($event, array $args)
    {
        $this->keepAlive();
        $namespace = $this->namespace;

        if ('' !== $namespace) {
            $namespace .= ',';
        }

        return $this->write(static::PROTO_MESSAGE, static::PACKET_EVENT . $namespace . json_encode([$event, $args]));
    }

    /** {@inheritDoc} */
    public function wait($event)
    {
        while (true) {
            if ($data = $this->read()) {
                $packet = $this->decodePacket($data);
                if ($packet->proto === static::PROTO_MESSAGE && $packet->type === static::PACKET_EVENT &&
                    $packet->nsp === $this->namespace && $packet->event === $event) {
                    return $packet;
                }
            }
        }
    }

    /** {@inheritDoc} */
    public function of($namespace)
    {
        $this->keepAlive();
        parent::of($namespace);

        $this->write(static::PROTO_MESSAGE, static::PACKET_CONNECT . $namespace);

        if ($data = $this->read()) {
            $packet = $this->decodePacket($data);

            return $packet;
        }
    }

    /** {@inheritDoc} */
    public function write($code, $message = null)
    {
        if (!$this->isConnected()) {
            return;
        }

        $payload = $this->getPayload($code, $message);
        $bytes = $this->socket->send((string) $payload);

        // wait a little bit of time after this message was sent
        \usleep((int) $this->options['wait']);

        return $bytes;
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Version 1.X';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults = parent::getDefaultOptions();

        $defaults['version']   = 2;
        $defaults['use_b64']   = false;
        $defaults['transport'] = static::TRANSPORT_POLLING;

        return $defaults;
    }

    /**
     * Create socket.
     *
     * @throws SocketException
     */
    protected function createSocket()
    {
        $this->socket = new Socket($this->url, $this->context, $this->options);
        if ($errors = $this->socket->getErrors()) {
            throw new SocketException($errors[0], $errors[1]);
        }
    }

    /**
     * Create payload.
     *
     * @param int $code
     * @param string $message
     * @throws \InvalidArgumentException
     * @return \ElephantIO\Payload\Encoder
     */
    protected function getPayload($code, $message)
    {
        if (!is_int($code) || static::PROTO_OPEN > $code || static::PROTO_NOOP < $code) {
            throw new \InvalidArgumentException('Wrong message type when trying to write on the socket');
        }

        return new Encoder($code . $message, Encoder::OPCODE_TEXT, true);
    }

    /**
     * Decode payload data.
     *
     * @param string $data
     * @return \stdClass[]
     */
    protected function decodeData($data)
    {
        $result = [];
        $seq = new SequentialStream($data);
        while (!$seq->isEof()) {
            if (null === ($len = $seq->readUntil(':'))) {
                throw new \RuntimeException('Data delimeter not found!');
            }

            $dseq = new SequentialStream($seq->read((int) $len));
            $type = (int) $dseq->read();
            $packet = $dseq->getData();
            switch ($type) {
                case static::PACKET_CONNECT:
                  $packet = json_decode($packet, true);
                  break;
            }
            $item = new \stdClass();
            $item->type = $type;
            $item->data = $packet;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Pick data which has a type.
     *
     * @param array $data
     * @param int $type
     * @return \stdClass
     */
    protected function pickData($data, $type)
    {
        foreach ($data as $item) {
            if (isset($item->type) && $item->type === $type) {
                return $item;
            }
        }
    }

    /**
     * Decode a packet.
     *
     * @param string $data
     * @return \stdClass
     */
    protected function decodePacket($data)
    {
        $seq = new SequentialStream($data);
        $proto = (int) $seq->read();
        if ($proto >= static::PROTO_OPEN && $proto <= static::PROTO_NOOP) {
            $packet = new \stdClass();
            $packet->proto = $proto;
            $packet->type = (int) $seq->read();
            $packet->nsp = $seq->readUntil();

            switch ($packet->proto) {
                case static::PROTO_MESSAGE:
                    $data = json_decode($seq->getData(), true);
                    if ($packet->type === static::PACKET_EVENT && 2 === count($data)) {
                        $packet->event = $data[0];
                        $packet->data = $data[1];
                    }
                    break;
            }

            return $packet;
        }
    }

    /** Does the handshake with the Socket.io server and populates the `session` value object */
    protected function handshake()
    {
        if (null !== $this->session) {
            return;
        }

        // set timeout to default
        $this->options['timeout'] = $this->getDefaultOptions()['timeout'];

        $this->createSocket();

        $url = $this->socket->getParsedUrl();
        $query = [
            'EIO'       => $this->options['version'],
            'transport' => $this->options['transport']
        ];
        if ($this->options['use_b64']) {
            $query['b64'] = 1;
        }
        if (isset($url['query'])) {
            $query = array_replace($query, $url['query']);
        }

        $this->socket->request(sprintf('/%s/?%s', trim($url['path'], '/'), http_build_query($query)), ['Connection: close']);
        if ($this->socket->getStatusCode() != 200) {
            throw new ServerConnectionFailureException('Unable to perform handshake');
        }

        $handshake = null;
        if (count($data = $this->decodeData($this->socket->getBody()))) {
            if ($data = $this->pickData($data, static::PACKET_CONNECT)) {
                $handshake = $data->data;
            }
        }

        if (null === $handshake || !in_array('websocket', $handshake['upgrades'])) {
            throw new UnsupportedTransportException('websocket');
        }

        $cookies = [];
        foreach ($this->socket->getHeaders() as $header) {
            $matches = null;
            if (preg_match('/^Set-Cookie:\s*([^;]*)/i', $header, $matches)) {
                $cookies[] = $matches[1];
            }
        }
        $this->cookies = $cookies;
        $this->session = new Session(
            $handshake['sid'],
            $handshake['pingInterval'] / 1000,
            $handshake['pingTimeout'] / 1000,
            $handshake['upgrades']
        );
    }

    /**
     * Upgrades the transport to WebSocket
     *
     * FYI:
     * Version "2" is used for the EIO param by socket.io v1
     * Version "3" is used by socket.io v2
     */
    protected function upgradeTransport()
    {
        // set timeout based on handshake response
        $this->options['timeout'] = $this->session->getTimeout();

        $this->createSocket();

        $url = $this->socket->getParsedUrl();
        $query = [
            'EIO'       => $this->options['version'],
            'transport' => static::TRANSPORT_WEBSOCKET,
            'sid'       => $this->session->id,
        ];

        if ($this->options['version'] === 2 && $this->options['use_b64']) {
            $query['b64'] = 1;
        }

        $uri = sprintf('/%s/?%s', trim($url['path'], '/'), http_build_query($query));

        $hash = sha1(uniqid(mt_rand(), true), true);

        if ($this->options['version'] !== 2) {
            $hash = substr($hash, 0, 16);
        }

        $key = base64_encode($hash);

        $origin = '*';
        $headers = isset($this->context['headers']) ? (array) $this->context['headers'] : [];

        foreach ($headers as $header) {
            $matches = [];
            if (preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
                break;
            }
        }

        $headers = [
            'Upgrade: WebSocket',
            'Connection: Upgrade',
            sprintf('Sec-WebSocket-Key: %s', $key),
            'Sec-WebSocket-Version: 13',
            sprintf('Origin: %s', $origin),
        ];

        if (!empty($this->cookies)) {
            $headers[] = sprintf('Cookie: %s', implode('; ', $this->cookies));
        }
        $this->socket->request($uri, $headers, ['skip_body' => true]);
        if ($this->socket->getStatusCode() != 101) {
            throw new ServerConnectionFailureException('Unable to upgrade to WebSocket');
        }

        $this->write(static::PROTO_UPGRADE);

        //remove message '40' from buffer, emmiting by socket.io after receiving static::PROTO_UPGRADE
        if ($this->options['version'] === 2) {
            $this->read();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function keepAlive()
    {
        if ($this->session->needsHeartbeat()) {
            $this->write(static::PROTO_PING);
        }
    }
}
