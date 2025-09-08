<?php

namespace Porter;

class FileTransfer
{
    protected string $avatarsSourceFolder;
    protected string $avatarsTargetFolder;
    protected string $avatarsSourcePrefix = '';
    protected string $avatarsTargetPrefix = '';
    protected string $avatarThumbnailsSourceFolder;
    protected string $avatarThumbnailsTargetFolder;
    protected string $avatarThumbnailsSourcePrefix = '';
    protected string $avatarThumbnailsTargetPrefix = '';
    protected string $attachmentsSourceFolder;
    protected string $attachmentsTargetFolder;

    protected Source $source;
    protected Target $target;
    protected Storage $inputStorage;
    public function __construct(Source $source, Target $target, Storage $inputStorage)
    {
        $this->source = $source;
        $this->target = $target;
        $this->inputStorage = $inputStorage;
        // @todo Determine support.
    }

    /**
     * Run the file migration.
     */
    public function run(): void
    {
        $this->avatars();
        $this->avatarThumbnails();

        $data = null;
        if (is_a($this->inputStorage, '\Porter\Storage\Database')) {
            $data = $this->source->attachmentsData($this->inputStorage->getConnection()->connection());
        }
        $this->attachments($data);
    }

    /**
     * Create folder if it doesn't exit.
     *
     * @param string $path
     */
    protected function touchFolder(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0665, true)) {
                trigger_error("Folder '{$path}' could not be created."); // @todo Log, not abort.
            }
        }
    }

    /**
     * Throw an error if the folder doesn't exist.
     *
     * @param string $path
     */
    protected function verifyFolder(string $path): void
    {
        if (!is_dir($path)) {
            trigger_error("Folder '{$path}' does not exist."); // @todo Log, not abort.
        }
    }

    /**
     * Replace the first character(s) of a filename.
     *
     * @param string $path
     * @param string $oldPrefix
     * @param string $newPrefix
     * @return string
     */
    protected function rePrefixFiles(string $path, string $oldPrefix, string $newPrefix): string
    {
        $i = pathinfo($path);
        return $i['dirname'] . '/' .  $newPrefix . substr($i['basename'], strlen($oldPrefix));
    }

    protected function copyFiles(
        string $inputFolder,
        string $outputFolder,
        ?callable $callback = null,
        array $params = []
    ): void {
        $this->verifyFolder($inputFolder);
        $this->touchFolder($outputFolder);
        $resourceFolder = opendir($inputFolder);

        while (($file = readdir($resourceFolder)) !== false) {
            // Skip Unix files.
            if ($file == '.' || $file == '..') {
                continue;
            }

            // Recursively follow folders.
            $path = $inputFolder . '/' . $file;
            if (is_dir($path)) {
                $this->copyFiles($path, $outputFolder, $callback, $params);
                continue;
            }

            // Get Target path & name.
            $newPath = str_replace($inputFolder, $outputFolder, $path);
            $newPath = call_user_func_array($callback, [$newPath, $params]);
            copy($path, $newPath); // @todo Reporting.
        }
    }

    /**
     * Export avatars.
     */
    public function avatars(): void
    {
        $this->copyFiles(
            $this->avatarsSourceFolder . '/' . $this->avatarsSourcePrefix,
            $this->avatarsTargetFolder,
            [$this, 'rePrefixFiles'],
            [$this->avatarsSourcePrefix, $this->avatarsTargetPrefix]
        );
    }

    /**
     * Export avatar thumbnails.
     */
    public function avatarThumbnails(): void
    {
        $this->copyFiles(
            $this->avatarThumbnailsSourceFolder . '/' . $this->avatarThumbnailsSourcePrefix,
            $this->avatarThumbnailsTargetFolder,
            [$this, 'rePrefixFiles'],
            [$this->avatarThumbnailsSourcePrefix, $this->avatarThumbnailsTargetPrefix]
        );
    }

    /**
     * Export attachments.
     */
    public function attachments(?\Illuminate\Database\Query\Builder $query = null): void
    {
        if (is_a($query, '\Illuminate\Database\Query\Builder')) {
            $query->get()->map(function ($file) {
                copy(
                    $this->attachmentsSourceFolder . '/' . $file->sourcename,
                    $this->attachmentsTargetFolder . '/' . $file->targetname
                ); // @todo Reporting.
            });
        } else {
            $this->copyFiles(
                $this->attachmentsSourceFolder,
                $this->attachmentsTargetFolder
            );
        }
    }
}
