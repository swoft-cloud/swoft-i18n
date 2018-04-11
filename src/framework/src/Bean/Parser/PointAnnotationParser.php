<?php

namespace Swoft\Bean\Parser;

use Swoft\Bean\Annotation\PointAnnotation;
use Swoft\Bean\Collector\AspectCollector;

/**
 * the point annotation of parser
 *
 * @uses      PointAnnotationParser
 * @version   2017年12月24日
 * @author    stelin <phpcrazy@126.com>
 * @copyright Copyright 2010-2016 swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class PointAnnotationParser extends AbstractParser
{
    /**
     * pointAnnotation parsing
     *
     * @param string          $className
     * @param PointAnnotation $objectAnnotation
     * @param string          $propertyName
     * @param string          $methodName
     * @param null            $propertyValue
     *
     * @return mixed
     */
    public function parser(string $className, $objectAnnotation = null, string $propertyName = "", string $methodName = "", $propertyValue = null)
    {
        AspectCollector::collect($className, $objectAnnotation, $propertyName, $methodName, $propertyValue);

        return null;
    }
}
