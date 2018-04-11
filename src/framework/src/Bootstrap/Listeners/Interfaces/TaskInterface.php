<?php

namespace Swoft\Bootstrap\Listeners\Interfaces;

use Swoole\Server;

/**
 *
 *
 * @uses      TaskInterface
 * @version   2018年01月13日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
interface TaskInterface
{
    public function onTask(Server $server, int $taskId, int $workerId, $data);
}