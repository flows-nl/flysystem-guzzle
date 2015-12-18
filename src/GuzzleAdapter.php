<?php

namespace Twistor\Flysystem;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;

/**
 * Uses Guzzle as a backend for HTTP URLs.
 */
class GuzzleAdapter implements AdapterInterface
{
    /**
     * The base URL.
     *
     * @var string
     */
    protected $base;

    /**
     * The Guzzle HTTP client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The visibility of this adapter.
     *
     * @var string
     */
    protected $visibility = AdapterInterface::VISIBILITY_PUBLIC;

    /**
     * Constructs an Http object.
     *
     * @param string                      $base   The base URL.
     * @param \GuzzleHttp\ClientInterface $client An optional Guzzle client.
     */
    public function __construct($base, ClientInterface $client = null)
    {
        $this->client = $client ?: new Client();

        $parsed = parse_url($base);
        $this->base = $parsed['scheme'] . '://';

        if (isset($parsed['user'])) {
            $this->visibility = AdapterInterface::VISIBILITY_PRIVATE;
            $this->base .= $parsed['user'];

            if (isset($parsed['pass']) && $parsed['pass'] !== '') {
                $this->base .= ':' . $parsed['pass'];
            }

            $this->base .= '@';
        };

        $this->base .= $parsed['host'] . '/';

        if (isset($parsed['path']) && $parts['path'] !== '/') {
            $this->base .= trim($parsed['path'], '/') . '/';
        }
    }

    /**
     * Returns the base URL.
     *
     * @return string The base URL.
     */
    public function getBaseUrl()
    {
        return $this->base;
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function createDir($path, Config $config)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($path)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        try {
            $response = $this->client->head($this->base . $path);
        } catch (ClientException $e) {
            return false;
        }

        if ($mimetype = $response->getHeader('Content-Type')) {
            list($mimetype) = explode(';', $mimetype, 2);
            $mimetype = trim($mimetype);
        } else {
            // Remove any query strings or fragments.
            list($path) = explode('#', $path, 2);
            list($path) = explode('?', $path, 2);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $mimetype = $extension ? MimeType::detectByFileExtension($extension) : 'text/plain';
        }

        return [
            'type' => 'file',
            'path' => $path,
            'timestamp' => (int) strtotime($response->getHeader('Last-Modified')),
            'size' => (int) $response->getHeader('Content-Length'),
            'visibility' => $this->visibility,
            'mimetype' => $mimetype,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        return [
            'path' => $path,
            'visibility' => $this->visibility,
        ];
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        try {
            $response = $this->client->head($this->base . $path);
        } catch (ClientException $e) {
            return false;
        }

        return $response->getStatusCode() === 200;
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        if (! $result = $this->readStream($path)) {
            return false;
        }

        $result['contents'] = stream_get_contents($result['stream']);

        if ($result['contents'] === false) {
            return false;
        }

        fclose($result['stream']);
        unset($result['stream']);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        try {
            $stream = $this->client->get($this->base . $path)->getBody()->detach();
        } catch (ClientException $e) {
            return false;
        }

        return [
            'path' => $path,
            'stream' => $stream,
        ];
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        if ($visibility === $this->visibility) {
            return $this->getVisibility($path);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $conf)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        return false;
    }
}