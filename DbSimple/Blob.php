<?php
namespace ToolsBundle\DbSimple;

/**
 * Database BLOB.
 * Can read blob chunk by chunk, write data to BLOB.
 */
interface Blob
{
    /**
     * string read(int $length)
     * Returns following $length bytes from the blob.
     *
     * @param $len
     * @return
     */
    public function read($len);

    /**
     * string write($data)
     * Appends data to blob.
     *
     * @param $data
     * @return
     */
    public function write($data);

    /**
     * int length()
     * Returns length of the blob.
     */
    public function length();

    /**
     * blobid close()
     * Closes the blob. Return its ID. No other way to obtain this ID!
     */
    public function close();
}