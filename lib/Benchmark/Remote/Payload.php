<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PhpBench\Benchmark\Remote;

use PhpBench\Benchmark\Remote\Exception\ScriptErrorException;
use Symfony\Component\Process\Process;

/**
 * Class representing the context from which a script can be generated and executed by a PHP binary.
 */
class Payload
{
    /**
     * Path to script template.
     */
    private $template;

    /**
     * Wrapper for PHP binary, e.g. "blackfire".
     */
    private $wrapper;

    /**
     * Associative array of PHP INI settings.
     */
    private $phpConfig = [];

    /**
     * Path to PHP binary.
     */
    private $phpBinary;

    /**
     * Tokens to substitute in the script template.
     */
    private $tokens = [];

    /**
     * Symfony Process instance.
     */
    private $process;

    /**
     * Create a new Payload object with the given script template.
     * The template must be the path to a script template.
     *
     * @param string $template
     */
    public function __construct($template, array $tokens = [], $phpBinary = PHP_BINARY, Process $process = null)
    {
        $this->template = $template;
        $this->process = $process ?: new Process($phpBinary);
        $this->tokens = $tokens;

        // disable timeout.
        $this->process->setTimeout(null);
        $this->phpBinary = $phpBinary;
    }

    public function setWrapper($wrapper)
    {
        $this->wrapper = $wrapper;
    }

    public function mergePhpConfig(array $phpConfig)
    {
        $this->phpConfig = array_merge(
            $this->phpConfig,
            $phpConfig
        );
    }

    public function setPhpPath($phpBinary)
    {
        $this->phpBinary = $phpBinary;
    }

    public function launch()
    {
        if (!file_exists($this->template)) {
            throw new \RuntimeException(sprintf(
                'Could not find script template "%s"',
                $this->template
            ));
        }

        $tokenSubs = [];
        foreach ($this->tokens as $key => $value) {
            $tokenSubs['{{ ' . $key . ' }}'] = $value;
        }

        $templateBody = file_get_contents($this->template);
        $script = str_replace(
            array_keys($tokenSubs),
            array_values($tokenSubs),
            $templateBody
        );

        $scriptPath = tempnam(sys_get_temp_dir(), 'PhpBench');
        file_put_contents($scriptPath, $script);

        $wrapper = '';
        if ($this->wrapper) {
            $wrapper = $this->wrapper . ' ';
        }

        $cmdLine = $wrapper . $this->phpBinary . $this->getIniString() . ' ' . escapeshellarg($scriptPath);

        $this->process->setCommandLine($cmdLine);
        $this->process->run();
        unlink($scriptPath);

        if (false === $this->process->isSuccessful()) {
            throw new ScriptErrorException(sprintf(
                '%s%s',
                $this->process->getErrorOutput(),
                $this->process->getOutput()
            ));
        }

        $output = $this->process->getOutput();
        $result = json_decode($output, true);

        if (null === $result) {
            throw new \RuntimeException(sprintf(
                'Could not decode return value from script from template "%s" (should be a JSON encoded string): %s',
                $this->template,
                $output
            ));
        }

        return $result;
    }

    private function getIniString()
    {
        if (empty($this->phpConfig)) {
            return '';
        }

        $string = [];
        foreach ($this->phpConfig as $key => $value) {
            $string[] = sprintf('-d%s=%s', $key, $value);
        }

        return ' ' . implode(' ', $string) . ' ';
    }
}
