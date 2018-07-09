<?php

declare(strict_types=1);

namespace Soluble\MediaTools;

use Soluble\MediaTools\Config\FFProbeConfigInterface;
use Soluble\MediaTools\Exception\FileNotFoundException;
use Soluble\MediaTools\Util\Assert\PathAssertionsTrait;
use Soluble\MediaTools\Video\Info;
use Soluble\MediaTools\Video\InfoServiceInterface;
use Symfony\Component\Process\Process;

class VideoInfoService implements InfoServiceInterface
{
    use PathAssertionsTrait;

    /** @var FFProbeConfigInterface */
    protected $ffprobeConfig;

    public function __construct(FFProbeConfigInterface $ffProbeConfig)
    {
        $this->ffprobeConfig = $ffProbeConfig;
    }

    /**
     * Return ready-to-run symfony process object that you can use
     * to `run()` or `start()` programmatically. Useful if you want to make
     * things your way...
     *
     * @see https://symfony.com/doc/current/components/process.html
     *
     * @throws FileNotFoundException when inputFile does not exists
     */
    public function getFFProbeProcess(string $inputFile): Process
    {
        $this->ensureFileExists($inputFile);

        $ffprobeCmd = trim(sprintf(
            '%s %s %s',
            $this->ffprobeConfig->getBinary(),
            implode(' ', [
                '-v quiet',
                '-print_format json',
                '-show_format',
                '-show_streams',
            ]),
            sprintf('-i %s', escapeshellarg($inputFile))
        ));

        $process = new Process($ffprobeCmd);
        $process->setTimeout($this->ffprobeConfig->getTimeout());
        $process->setIdleTimeout($this->ffprobeConfig->getIdleTimeout());
        $process->setEnv($this->ffprobeConfig->getEnv());

        return $process;
    }

    /**
     * @throws FileNotFoundException
     * @throws \Throwable
     */
    public function getInfo(string $file): Info
    {
        $process = $this->getFFProbeProcess($file);

        try {
            $process->mustRun();
            $output = $process->getOutput();
        } catch (\Throwable $e) {
            throw $e;
        }

        return Info::createFromFFProbeJson($file, $output);
    }
}
