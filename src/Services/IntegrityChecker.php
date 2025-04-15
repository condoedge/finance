<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Facades\Graph;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;

class IntegrityChecker
{
    /**
     * Graph representing relationships between models
     * 
     * @var \Condoedge\Finance\Services\Graph
     */
    protected $graph;
    
    /**
     * Configuration of relationships between models
     * 
     * @var array
     */
    protected $modelRelations = [];

    /**
     * Create a new instance of IntegrityChecker.
     *
     * @param array $modelRelations Model relationships or null to use configuration
     */
    public function __construct()
    {
        $this->modelRelations = config('kompo-finance.model_integrity_relations', []);
        $this->graph = Graph::new($this->modelRelations);

        foreach ($this->graph->getAllNodesBFS() as $node) {
            if (!in_array(HasIntegrityCheck::class, all_class_uses($node))) {
                throw new \Exception("Model $node must use the HasIntegrityCheck trait.");
            }
        }
    }

    /**
     * Set custom relationships between models.
     *
     * @param array $modelRelations
     * @return self
     */
    public function setModelRelations(array $modelRelations): self
    {
        $this->modelRelations = $modelRelations;
        $this->graph->setLinks($modelRelations);
        return $this;
    }
    
    /**
     * Add a relationship to the graph.
     *
     * @param string $parent Parent model class
     * @param string $child Child model class
     * @return self
     */
    public function addRelation(string $parent, string $child): self
    {
        $this->graph->addLink($parent, $child);
        return $this;
    }
    
    /**
     * Remove a relationship from the graph.
     *
     * @param string $parent Parent model class
     * @param string $child Child model class
     * @return self
     */
    public function removeRelation(string $parent, string $child): self
    {
        $this->graph->removeLink($parent, $child);
        return $this;
    }
    
    /**
     * Check integrity of all models in the proper order.
     *
     * @return void
     */
    public function checkFullIntegrity(): void
    {
        $nodes = array_reverse($this->graph->getAllNodesBFS());
        
        foreach ($nodes as $node) {
            $this->runCheckIntegrityOn($node::getMainClass());
        }
    }
    
    /**
     * Check integrity of children first, then the specified model.
     * Used when you want to ensure a model's integrity by first ensuring its dependencies.
     *
     * @param string $class Model class
     * @param array|number|null $ids Specific IDs to check
     * @return void
     */
    public function checkChildrenThenModel(string $class, $ids = null): void
    {
        $children = $this->graph->getDescendants($class::getMainClass());

        $currentRelationClass = $class;
        $childrenIds = [];

        if ($ids) {
            foreach ($children as $child) {
                $relationClass = $child::getRelationships($currentRelationClass)[0] ?? null;
                $childrenIds[$child] = !$relationClass ? null : $child::whereHas($relationClass, fn($q) => $q->whereIn((new $relationClass[1])->getTable() . '.id', $this->parseIds($ids))->withTrashed())->pluck('id')->all();

                $currentRelationClass = $child;
            }
        }

        $childrenInOrderToCheck = array_reverse($children);

        foreach ($childrenInOrderToCheck as $child) {
            $this->runCheckIntegrityOn($child, $childrenIds[$child] ?? null);
        }

        $this->runCheckIntegrityOn($class::getMainClass(), $ids);
    }
    
    /**
     * Check integrity of a model and propagate changes to its parents.
     * Used when a model is modified and we need to recalculate its parent models.
     *
     * @param string $class Model class
     * @param array|number|null $ids Specific IDs to check
     * @return void
     */
    public function checkModelThenParents(string $class, $ids = null): void
    {
        $ancestors = $this->graph->getAncestors($class::getMainClass());

        $this->runCheckIntegrityOn($class::getMainClass(), $ids);

        $currentRelationClass = $class;

        foreach ($ancestors as $ancestor) {
            if ($ids) {
                $relationClass = $ancestor::getRelationships($currentRelationClass)[0] ?? null;
                $ids = !$relationClass ? null : $ancestor::whereHas($relationClass[0], fn($q) => $q->whereIn((new $relationClass[1])->getTable() .'.id', $this->parseIds($ids))->withTrashed())->pluck('id')->all();
            }

            $this->runCheckIntegrityOn($ancestor, $ids);

            $currentRelationClass = $ancestor;
        }
    }

    protected function runCheckIntegrityOn($class, $ids = null): void
    {
        if (method_exists($class, 'checkIntegrity')) {
            $ids = $this->parseIds($ids);

            $class::checkIntegrity($ids);
        }
    }

    protected function parseIds($ids): array|null
    {
        if (!$ids) {
            return null;
        }

        return is_array($ids) ? $ids : [$ids];
    }
}