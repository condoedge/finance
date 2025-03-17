<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Condoedge\Finance\Services\Graph setLinks(array $links)
 * @method static array getLinks()
 * @method static \Condoedge\Finance\Services\Graph addLink(mixed $parent, mixed $child)
 * @method static \Condoedge\Finance\Services\Graph removeLink(mixed $parent, mixed $child)
 * @method static array getAllNodesBFS()
 * @method static array getGraphRoots()
 * @method static array getAncestors(mixed $node)
 * @method static array getDescendants(mixed $node, array $visited = [])
 * @method static array buildParentMap()
 * 
 * @see \Condoedge\Finance\Services\Graph
 */
class Graph extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'finance.graph';
    }
}