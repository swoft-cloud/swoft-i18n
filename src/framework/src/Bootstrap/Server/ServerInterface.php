<?php

namespace Swoft\Bootstrap\Server;

use Swoole\Server;

/**
 * Interface ServerInterface
 *
 * @package Swoft\Bootstrap\Server
 */
interface ServerInterface
{
    /**
     * Start
     */
    public function start();

    /**
     * Stop server
     *
     * @return bool
     */
    public function stop(): bool;

    /**
     * Reload workers
     *
     * @param bool $onlyTask Only reload TaskWorkers
     */
    public function reload($onlyTask = false);

    /**
     * Is server running ?
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Get server
     *
     * @return Server
     */
    public function getServer(): Server;

    /**
     * Get TCP setting
     *
     * @return array
     */
    public function getTcpSetting(): array;

    /**
     * Get HTTP setting
     *
     * @return array
     */
    public function getHttpSetting(): array;

    /**
     * Get Server setting
     *
     * @return array
     */
    public function getServerSetting(): array;

    /**
     * Set server to Daemonize
     */
    public function setDaemonize();
}
