<?php

namespace Swoft\I18n;

use Swoft\App;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Helper\ArrayHelper;

/**
 * @Bean()
 */
class Translator
{
    /**
     * Source languages
     *
     * @var string
     * @Value(name="${config.Translator.languageDir}", env="${TRANSLATOR_LANG_DIR}")
     */
    public $languageDir = '@resources/languages/';

    /**
     * Translation messages
     *
     * @var array
     */
    private $messages = [];

    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @return void
     * @throws \RuntimeException
     */
    public function init()
    {
        $sourcePath = App::getAlias($this->languageDir);
        if (! $sourcePath || ! file_exists($sourcePath)) {
            return;
        }
        if (! is_readable($sourcePath)) {
            throw new \RuntimeException(sprintf('%s dir is not readable', $sourcePath));
        }
        $this->loadLanguages($sourcePath);
    }

    /**
     * @param string $sourcePath
     * @return void
     */
    protected function loadLanguages(string $sourcePath)
    {
        if ($this->loaded === false) {
            $iterator = new \RecursiveDirectoryIterator($sourcePath);
            $files = new \RecursiveIteratorIterator($iterator);
            foreach ($files as $file) {
                // Only load php file
                // TODO add .mo .po support
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }
                $messages = str_replace([$sourcePath, '.php'], '', $file);
                list($language, $category) = explode('/', $messages);
                $this->messages[$language][$category] = require $file;
            }
            $this->loaded = true;
        }
    }

    /**
     * Translate
     *
     * @param string $key "category.key" or "locale.category.key"
     * @param array  $params
     * @param string $locale
     * @return string
     * @throws \InvalidArgumentException
     */
    public function translate(string $key, array $params, string $locale = null): string
    {
        $realKey = null !== $locale ? implode('.', [$locale, $key]) : $key;
        if (! ArrayHelper::has($this->messages, $realKey)) {
            $exceptionMessage = sprintf('Translate error, key %s does not exist', $realKey);
            throw new \InvalidArgumentException($exceptionMessage);
        }
        $message = ArrayHelper::get($this->messages, $realKey);
        if (! \is_string($message)) {
            throw new \InvalidArgumentException(sprintf('Message type error, possibly incorrectly key'));
        }
        return $this->formatMessage($message, $params);
    }

    /**
     * Format message
     *
     * @param string $message
     * @param array  $params
     * @return string
     */
    private function formatMessage(string $message, array $params): string
    {
        $params = array_values($params);
        array_unshift($params, $message);
        return sprintf(...$params);
    }
}
