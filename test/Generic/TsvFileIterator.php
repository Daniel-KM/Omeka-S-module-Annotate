<?php declare(strict_types=1);

namespace Generic;

/**
 * @link https://phpunit.readthedocs.io/fr/latest/writing-tests-for-phpunit.html#fournisseur-de-donnees
 */
class TsvFileIterator implements \Iterator
{
    protected $filepath;
    protected $key = 0;
    protected $current;

    public function __construct($filepath)
    {
        $this->filepath = fopen($filepath, 'rb');
    }

    public function __destruct()
    {
        fclose($this->filepath);
    }

    public function rewind(): void
    {
        rewind($this->filepath);
        $this->current = fgetcsv($this->filepath, 100000, "\t", chr(0), chr(0));
        $this->key = 0;
    }

    public function valid(): bool
    {
        return !feof($this->filepath);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->key;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->current;
    }

    public function next(): void
    {
        $this->current = fgetcsv($this->filepath, 100000, "\t", chr(0), chr(0));
        ++$this->key;
    }
}
