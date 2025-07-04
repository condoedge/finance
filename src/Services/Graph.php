<?php

namespace Condoedge\Finance\Services;

class Graph
{
    /**
     * Array of links between nodes where keys are parents and values are arrays of children
     *
     * @var array
     */
    protected $links = [];

    /**
     * Create a new graph instance.
     *
     * @param array $links
     */
    public function __construct(array $links = [])
    {
        $this->links = $links;
    }

    public function new(array $links = [])
    {
        $this->setLinks($links);

        return $this;
    }

    /**
     * Set links for the graph.
     *
     * @param array $links
     *
     * @return self
     */
    public function setLinks(array $links): self
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Get current links.
     *
     * @return array
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Add a link to the graph.
     *
     * @param mixed $parent
     * @param mixed $child
     *
     * @return self
     */
    public function addLink($parent, $child): self
    {
        if (!isset($this->links[$parent])) {
            $this->links[$parent] = [];
        }

        if (!in_array($child, $this->links[$parent], true)) {
            $this->links[$parent][] = $child;
        }

        return $this;
    }

    /**
     * Remove a link from the graph.
     *
     * @param mixed $parent
     * @param mixed $child
     *
     * @return self
     */
    public function removeLink($parent, $child): self
    {
        if (isset($this->links[$parent])) {
            $index = array_search($child, $this->links[$parent], true);
            if ($index !== false) {
                unset($this->links[$parent][$index]);
                $this->links[$parent] = array_values($this->links[$parent]);
            }
        }

        return $this;
    }

    /**
     * Get all nodes in the graph using Breadth-First Search.
     *
     * @return array
     */
    public function getAllNodesBFS(): array
    {
        $visited = [];
        $allNodes = [];
        $childNodes = [];

        // Collect all nodes and identify which ones are children
        foreach ($this->links as $parent => $children) {
            $allNodes[$parent] = true;
            foreach ($children as $child) {
                $allNodes[$child] = true;
                $childNodes[$child] = true;
            }
        }

        // Find root nodes (nodes with no incoming edges)
        $roots = array_keys(array_diff_key($allNodes, $childNodes));

        // Start BFS from root nodes
        $queue = $roots;

        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!in_array($node, $visited, true)) {
                $visited[] = $node;
                if (isset($this->links[$node])) {
                    foreach ($this->links[$node] as $child) {
                        if (!in_array($child, $visited, true) && !in_array($child, $queue, true)) {
                            $queue[] = $child;
                        }
                    }
                }
            }
        }

        // Handle disconnected components
        foreach (array_keys($allNodes) as $node) {
            if (!in_array($node, $visited, true)) {
                $queue[] = $node;
                while (!empty($queue)) {
                    $n = array_shift($queue);
                    if (!in_array($n, $visited, true)) {
                        $visited[] = $n;
                        if (isset($this->links[$n])) {
                            foreach ($this->links[$n] as $child) {
                                if (!in_array($child, $visited, true) && !in_array($child, $queue, true)) {
                                    $queue[] = $child;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $visited;
    }

    /**
     * Get graph roots (nodes with no incoming edges).
     *
     * @return array
     */
    public function getGraphRoots(): array
    {
        $allNodes = [];
        $childNodes = [];

        foreach ($this->links as $parent => $children) {
            $allNodes[$parent] = true;
            foreach ($children as $child) {
                $allNodes[$child] = true;
                $childNodes[$child] = true;
            }
        }

        return array_keys(array_diff_key($allNodes, $childNodes));
    }

    /**
     * Get all ancestors of a node.
     *
     * @param mixed $node
     *
     * @return array
     */
    public function getAncestors($node): array
    {
        $parentMap = $this->buildParentMap();
        $ancestors = [];
        $visited = [];

        $this->findAncestorsRecursive($node, $parentMap, $ancestors, $visited);

        return $ancestors;
    }

    /**
     * Helper method to recursively find ancestors.
     *
     * @param mixed $node
     * @param array $parentMap
     * @param array $ancestors
     * @param array $visited
     */
    protected function findAncestorsRecursive($node, array $parentMap, array &$ancestors, array &$visited): void
    {
        if (in_array($node, $visited, true)) {
            return;
        }

        $visited[] = $node;

        if (isset($parentMap[$node])) {
            foreach ($parentMap[$node] as $parent) {
                if (!in_array($parent, $ancestors, true)) {
                    $ancestors[] = $parent;
                }
                $this->findAncestorsRecursive($parent, $parentMap, $ancestors, $visited);
            }
        }
    }

    /**
     * Get all descendants of a node.
     *
     * @param mixed $node
     * @param array $visited
     *
     * @return array
     */
    public function getDescendants($node, array &$visited = []): array
    {
        $descendants = [];
        if (isset($this->links[$node])) {
            foreach ($this->links[$node] as $child) {
                if (!in_array($child, $visited, true)) {
                    $visited[] = $child;
                    $descendants[] = $child;
                    // Recursively get the child's descendants
                    $descendants = array_merge($descendants, $this->getDescendants($child, $visited));
                }
            }
        }
        return $descendants;
    }

    /**
     * Build a map of children to their parents.
     *
     * @return array
     */
    public function buildParentMap(): array
    {
        $parentMap = [];
        foreach ($this->links as $parent => $children) {
            foreach ($children as $child) {
                if (!isset($parentMap[$child])) {
                    $parentMap[$child] = [];
                }
                $parentMap[$child][] = $parent;
            }
        }
        return $parentMap;
    }
}
