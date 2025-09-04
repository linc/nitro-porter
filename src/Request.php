<?php

/**
 *
 */

namespace Porter;

class Request
{
    private const VALID_DATA_TYPES = [
        'all',
        'users',
        'roles',
        'categories',
        'discussions',
        'comments',
        'attachments',
        'privateMessages',
        'badges',
    ];

    private string $sourcePackage;
    private string $targetPackage;
    private string $inputConnection;
    private string $outputConnection;
    private ?string $inputTablePrefix;
    private ?string $outputTablePrefix;
    private ?string $cdnPrefix;
    private ?string $dataTypes;

    /**
     * Build a valid Porter request.
     *
     * @param string $sourcePackage Source package alias (or 'port')
     * @param string $targetPackage Target package alias (or 'file', 'sql')
     * @param string $inputConnection Connection alias in config.php
     * @param string $outputConnection Connection alias in config.php
     * @param ?string $inputTablePrefix If the input is a database, override source package with this table prefix.
     * @param ?string $outputTablePrefix If the output is a database, override target package with this table prefix.
     * @param ?string $cdnPrefix Text to prepend to attachment URIs.
     * @param ?string $dataTypes CSV of types or 'all' (ex: `users,categories,discussions`)
     * @throws \Exception
     */
    public function __construct(
        string $sourcePackage,
        string $targetPackage,
        string $inputConnection,
        string $outputConnection,
        ?string $inputTablePrefix = null,
        ?string $outputTablePrefix = null,
        ?string $cdnPrefix = null,
        ?string $dataTypes = null,
    ) {
        $this->sourcePackage = $sourcePackage;
        $this->targetPackage = $targetPackage;

        $this->inputConnection = $inputConnection;
        $this->outputConnection = $outputConnection;

        $this->inputTablePrefix = $inputTablePrefix;
        $this->outputTablePrefix = $outputTablePrefix;
        $this->cdnPrefix = $cdnPrefix;

        if (!empty($dataTypes) && !count(array_diff(explode(',', $dataTypes), self::VALID_DATA_TYPES))) {
            $this->dataTypes = $dataTypes;
        } elseif (!empty($dataTypes)) {
            throw new \Exception('Invalid data types in request.');
        } else {
            $this->dataTypes = null;
        }
    }

    public function getSource(): string
    {
        return $this->sourcePackage;
    }

    public function getTarget(): string
    {
        return $this->targetPackage;
    }

    public function getInput(): string
    {
        return $this->inputConnection;
    }

    public function getOutput(): string
    {
        return $this->outputConnection;
    }

    public function getInputTablePrefix(): ?string
    {
        return $this->inputTablePrefix;
    }

    public function getOutputTablePrefix(): ?string
    {
        return $this->outputTablePrefix;
    }

    public function getCdnPrefix(): ?string
    {
        return $this->cdnPrefix;
    }

    public function getDataTypes(): ?string
    {
        return $this->dataTypes;
    }
}
