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

namespace ElephantIO\Engine;

class SequentialStream
{
    /**
     * @var string
     */
    protected $data = null;

    /**
     * Constructor.
     *
     * @param string $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Read a fixed size data.
     *
     * @param int $len
     * @return string
     */
    public function read($len = 1)
    {
        if (!$this->isEof()) {
            $result = substr($this->data, 0, $len);
            $this->data = substr($this->data, $len);

            return $result;
        }
    }

    /**
     * Read data up to delimeter.
     *
     * @param string $delimeter
     * @return string
     */
    public function readUntil($delimeter = ',')
    {
        if (!$this->isEof() && false !== ($p = strpos($this->data, $delimeter))) {
          $result = substr($this->data, 0, $p);
          $this->data = substr($this->data, $p + 1);

          return $result;
        }
    }

    /**
     * Get unprocessed data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Is EOF.
     *
     * @return boolean
     */
    public function isEof()
    {
        return 0 === strlen($this->data) ? true : false;
    }
}
