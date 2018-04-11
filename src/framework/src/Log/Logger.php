<?php

namespace Swoft\Log;

use Swoft\App;
use Swoft\Core\Coroutine;
use Swoft\Core\RequestContext;

/**
 * 日志类
 *
 * @uses      Logger
 * @version   2017年05月11日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 Swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class Logger extends \Monolog\Logger
{

    /**
     * trace 日志级别
     */
    const TRACE = 650;

    /**
     * @var string 日志系统名称
     */
    protected $name = APP_NAME;

    /**
     * @var int 刷新日志条数
     */
    protected $flushInterval = 1;

    /**
     * @var bool 每个请求完成刷新一次日志到磁盘，默认未开启
     */
    protected $flushRequest = false;

    /**
     * @var array 性能日志
     */
    protected $profiles = [];

    /**
     * @var array 计算日志
     */
    protected $countings = [];

    /**
     * @var array 标记日志
     */
    protected $pushlogs = [];

    /**
     * @var array 标记栈
     */
    protected $profileStacks = [];

    /**
     * @var array 日志数据记录
     */
    protected $messages = [];

    /**
     * @var array
     */
    protected $processors = [];

    /**
     * @var bool
     */
    protected $enable = false;


    /**
     * @var array 日志级别对应名称
     */
    protected static $levels = array(
        self::DEBUG     => 'debug',
        self::INFO      => 'info',
        self::NOTICE    => 'notice',
        self::WARNING   => 'warning',
        self::ERROR     => 'error',
        self::CRITICAL  => 'critical',
        self::ALERT     => 'alert',
        self::EMERGENCY => 'emergency',
        self::TRACE     => 'trace'
    );

    public function __construct()
    {
        parent::__construct(APP_NAME);
    }

    /**
     * 记录日志
     *
     * @param int   $level   日志级别
     * @param mixed $message 信息
     * @param array $context 附加信息
     * @return bool
     */
    public function addRecord($level, $message, array $context = array())
    {
        if (! $this->enable) {
            return true;
        }

        $levelName = static::getLevelName($level);

        if (! static::$timezone) {
            static::$timezone = new \DateTimeZone(date_default_timezone_get() ? : 'UTC');
        }

        // php7.1+ always has microseconds enabled, so we do not need this hack
        if ($this->microsecondTimestamps && PHP_VERSION_ID < 70100) {
            $ts = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)), static::$timezone);
        } else {
            $ts = new \DateTime(null, static::$timezone);
        }

        $ts->setTimezone(static::$timezone);

        $message = $this->formatMessage($message);
        $message = $this->getTrace($message);
        $record = $this->formateRecord($message, $context, $level, $levelName, $ts, []);

        foreach ($this->processors as $processor) {
            $record = \Swoole\Coroutine::call_user_func($processor, $record);
        }

        $this->messages[] = $record;

        if (App::$isInTest || \count($this->messages) >= $this->flushInterval) {
            $this->flushLog();
        }

        return true;
    }

    /**
     * 格式化一条日志记录
     *
     * @param string    $message   信息
     * @param array     $context    上下文信息
     * @param int       $level     级别
     * @param string    $levelName 级别名
     * @param \DateTime $ts        时间
     * @param array     $extra     附加信息
     * @return array
     */
    public function formateRecord($message, $context, $level, $levelName, $ts, $extra)
    {
        $record = array(
            'logid'      => RequestContext::getLogid(),
            'spanid'     => RequestContext::getSpanid(),
            'messages'   => $message,
            'context'    => $context,
            'level'      => $level,
            'level_name' => $levelName,
            'channel'    => $this->name,
            'datetime'   => $ts,
            'extra'      => $extra,
        );

        return $record;
    }

    /**
     * pushlog日志
     *
     * @param string $key 记录KEY
     * @param mixed  $val 记录值
     */
    public function pushLog($key, $val)
    {
        if (! $this->enable || ! (\is_string($key) || is_numeric($key))) {
            return;
        }

        $key = urlencode($key);
        $cid = Coroutine::tid();
        if (\is_array($val)) {
            $this->pushlogs[$cid][] = "$key=" . json_encode($val);
        } elseif (\is_bool($val)) {
            $this->pushlogs[$cid][] = "$key=" . var_export($val, true);
        } elseif (\is_string($val) || is_numeric($val)) {
            $this->pushlogs[$cid][] = "$key=" . urlencode($val);
        } elseif (null === $val) {
            $this->pushlogs[$cid][] = "$key=";
        }
    }

    /**
     * 标记开始
     *
     * @param string $name 标记名称
     */
    public function profileStart($name)
    {
        if (! $this->enable || \is_string($name) === false || empty($name)) {
            return;
        }
        $cid = Coroutine::tid();
        $this->profileStacks[$cid][$name]['start'] = microtime(true);
    }

    /**
     * 标记开始
     *
     * @param string $name 标记名称
     */
    public function profileEnd($name)
    {
        if (! $this->enable || \is_string($name) === false || empty($name)) {
            return;
        }

        $cid = Coroutine::tid();
        if (! isset($this->profiles[$cid][$name])) {
            $this->profiles[$cid][$name] = [
                'cost'  => 0,
                'total' => 0,
            ];
        }

        $this->profiles[$cid][$name]['cost'] += microtime(true) - $this->profileStacks[$cid][$name]['start'];
        $this->profiles[$cid][$name]['total'] += 1;
    }

    /**
     * 组装profiles
     */
    public function getProfilesInfos()
    {
        $profileAry = [];
        $cid = Coroutine::tid();
        $profiles = $this->profiles[$cid] ?? [];
        foreach ($profiles as $key => $profile) {
            if (!isset($profile['cost'], $profile['total'])) {
                continue;
            }
            $cost = sprintf('%.2f', $profile['cost'] * 1000);
            $profileAry[] = "$key=" . $cost . '(ms)/' . $profile['total'];
        }

        return implode(',', $profileAry);
    }

    /**
     * 缓存命中率计算
     *
     * @param string $name  计算KEY
     * @param int    $hit   命中数
     * @param int    $total 总数
     */
    public function counting($name, $hit, $total = null)
    {
        if (! \is_string($name) || empty($name)) {
            return;
        }

        $cid = Coroutine::tid();
        if (! isset($this->countings[$cid][$name])) {
            $this->countings[$cid][$name] = ['hit' => 0, 'total' => 0];
        }
        $this->countings[$cid][$name]['hit'] += (int)$hit;
        if ($total !== null) {
            $this->countings[$cid][$name]['total'] += (int)$total;
        }
    }

    /**
     * 组装字符串
     */
    public function getCountingInfo()
    {
        $cid = Coroutine::tid();
        if (! isset($this->countings[$cid]) || empty($this->countings[$cid])) {
            return '';
        }

        $countAry = [];
        $countings = $this->countings[$cid];
        foreach ($countings ?? [] as $name => $counter) {
            if (isset($counter['hit'], $counter['total']) && $counter['total'] !== 0) {
                $countAry[] = "$name=" . $counter['hit'] . '/' . $counter['total'];
            } elseif (isset($counter['hit'])) {
                $countAry[] = "$name=" . $counter['hit'];
            }
        }
        return implode(',', $countAry);
    }

    /**
     * 写入日志信息格式化
     *
     * @param $message
     * @return string
     */
    public function formatMessage($message)
    {
        if (\is_array($message)) {
            return json_encode($message);
        }
        return $message;
    }

    /**
     * 计算调用trace
     *
     * @param $message
     * @return string
     */
    public function getTrace($message): string
    {
        $traces = debug_backtrace();
        $count = \count($traces);
        $ex = '';
        if ($count >= 7) {
            $info = $traces[6];
            if (isset($info['file'], $info['line'])) {
                $filename = basename($info['file']);
                $lineNum = $info['line'];
                $ex = "$filename:$lineNum";
            }
        }
        if ($count >= 8) {
            $info = $traces[7];
            if (isset($info['class'], $info['type'], $info['function'])) {
                $ex .= ',' . $info['class'] . $info['type'] . $info['function'];
            } elseif (isset($info['function'])) {
                $ex .= ',' . $info['function'];
            }
        }

        if (!empty($ex)) {
            $message = "trace[$ex] " . $message;
        }


        return $message;
    }

    /**
     * 刷新日志到handlers
     */
    public function flushLog()
    {
        if (empty($this->messages)) {
            return;
        }

        reset($this->handlers);

        while ($handler = current($this->handlers)) {
            $handler->handleBatch($this->messages);
            next($this->handlers);
        }

        // 清空数组
        $this->messages = [];
    }

    /**
     * 请求完成追加一条notice日志
     *
     * @param bool $flush 是否刷新日志
     */
    public function appendNoticeLog($flush = false)
    {
        if (! $this->enable) {
            return;
        }
        $cid = Coroutine::tid();
        $ts = $this->getLoggerTime();

        // php耗时单位ms毫秒
        $timeUsed = sprintf('%.2f', (microtime(true) - $this->getRequestTime()) * 1000);

        // php运行内存大小单位M
        $memUsed = sprintf('%.0f', memory_get_peak_usage() / (1024 * 1024));

        $profileInfo = $this->getProfilesInfos();
        $countingInfo = $this->getCountingInfo();
        $pushlogs = $this->pushlogs[$cid] ?? [];

        $messageAry = array(
            "[$timeUsed(ms)]",
            "[$memUsed(MB)]",
            "[{$this->getUri()}]",
            '[' . implode(' ', $pushlogs) . ']',
            'profile[' . $profileInfo . ']',
            'counting[' . $countingInfo . ']'
        );


        $message = implode(' ', $messageAry);

        unset($this->profiles[$cid], $this->countings[$cid], $this->pushlogs[$cid], $this->profileStacks[$cid]);

        $levelName = self::$levels[self::NOTICE];
        $message = $this->formateRecord($message, [], self::NOTICE, $levelName, $ts, []);

        $this->messages[] = $message;

        // 一个请求完成刷新一次或达到刷新的次数
        $isReached = \count($this->messages) >= $this->flushInterval;
        if ($this->flushRequest || $isReached || $flush) {
            $this->flushLog();
        }
    }

    /**
     * 获取去日志时间
     *
     * @return \DateTime
     */
    private function getLoggerTime()
    {
        if (! static::$timezone) {
            static::$timezone = new \DateTimeZone(date_default_timezone_get() ? : 'UTC');
        }

        // php7.1+ always has microseconds enabled, so we do not need this hack
        if ($this->microsecondTimestamps && PHP_VERSION_ID < 70100) {
            $ts = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)), static::$timezone);
        } else {
            $ts = new \DateTime(null, static::$timezone);
        }

        $ts->setTimezone(static::$timezone);
        return $ts;
    }

    /**
     * 日志初始化
     */
    public function initialize()
    {
        $this->profiles = [];
        $this->countings = [];
        $this->pushlogs = [];
        $this->profileStacks = [];

        $this->messages[] = [];
    }

    /**
     * 添加一条trace日志
     *
     * @param string $message 日志信息
     * @param array $context 附加信息
     * @return bool
     */
    public function addTrace($message, array $context = array())
    {
        return $this->addRecord(static::TRACE, $message, $context);
    }

    /**
     * @param int $flushInterval
     */
    public function setFlushInterval(int $flushInterval)
    {
        $this->flushInterval = $flushInterval;
    }

    /**
     * 请求URI
     *
     * @return string
     */
    private function getUri(): string
    {
        $contextData = RequestContext::getContextData();

        return $contextData['uri'] ?? '';
    }

    /**
     * 请求开始时间
     *
     * @return int
     */
    private function getRequestTime(): int
    {
        $contextData = RequestContext::getContextData();

        return $contextData['requestTime'] ?? 0;
    }
}
