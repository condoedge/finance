<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Facades\Graph;
use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Illuminate\Support\Facades\DB;

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
            if (!in_array(HasIntegrityCheck::class, all_class_uses($node), true)) {
                throw new \Exception("Model $node must use the HasIntegrityCheck trait.");
            }
        }
    }

    /**
     * Set custom relationships between models.
     *
     * @param array $modelRelations
     *
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
     *
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
     *
     * @return self
     */
    public function removeRelation(string $parent, string $child): self
    {
        $this->graph->removeLink($parent, $child);
        return $this;
    }

    /**
     * Check integrity of all models in the proper order.
     */
    public function checkFullIntegrity(): void
    {
        $nodes = array_reverse($this->graph->getAllNodesBFS());

        foreach ($nodes as $node) {
            $this->runCheckIntegrityOn($node::getMainClass());
        }
    }

    /**
     * Check only rows created since the cutoff, propagating each batch to its parents,
     * so an old parent whose child is recent is still recalculated. This is the daily
     * scoped pass; the full (chunked) pass sweeps older drift on its own schedule.
     */
    public function checkRecentIntegrity(\DateTimeInterface $cutoff): void
    {
        $nodes = array_reverse($this->graph->getAllNodesBFS());

        foreach ($nodes as $node) {
            $class = $node::getMainClass();

            $ids = DB::table((new $class())->getTable())
                ->where('created_at', '>=', $cutoff)
                ->pluck('id')
                ->all();

            if ($ids) {
                $this->checkModelThenParents($class, $ids);
            }
        }
    }

    /**
     * Check integrity of children first, then the specified model.
     * Used when you want to ensure a model's integrity by first ensuring its dependencies.
     *
     * @param string $class Model class
     * @param array|number|null $ids Specific IDs to check
     */
    public function checkChildrenThenModel(string $class, $ids = null): void
    {
        $children = $this->graph->getDescendants($class::getMainClass());

        $currentRelationClass = $class;
        $childrenIds = [];

        if ($ids) {
            foreach ($children as $child) {
                $relationClass = $child::getRelationships($currentRelationClass)[0] ?? null;
                // An unresolvable relation means the scope is unknown, not "every row".
                $childrenIds[$child] = !$relationClass ? [] : $child::whereHas($relationClass[0], fn ($q) => $q->whereIn((new $relationClass[1]())->getTable() . '.id', $this->parseIds($ids))->withTrashed())->pluck('id')->all();

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
     */
    public function checkModelThenParents(string $class, $ids = null): void
    {
        $ancestors = $this->graph->getAncestors($class::getMainClass());

        $this->runCheckIntegrityOn($class::getMainClass(), $ids);

        if ($ids === null) {
            foreach ($ancestors as $ancestor) {
                $this->runCheckIntegrityOn($ancestor, null);
            }

            return;
        }

        // Ids resolved so far, keyed by the class they belong to. An ancestor can hang
        // off ANY already-resolved branch — Customer and PaymentInstallmentPeriod are
        // both parents of Invoice — so walking the list as a chain (each ancestor mapped
        // from the PREVIOUS one) found no relation between siblings and widened the
        // scope to the whole table: every invoice save rewrote all installment periods.
        $resolved = [$class => $this->parseIds($ids) ?? []];

        foreach ($ancestors as $ancestor) {
            $ancestorIds = [];
            $anyRelationFound = false;

            foreach ($resolved as $sourceClass => $sourceIds) {
                $relationsClass = $ancestor::getRelationships($sourceClass) ?? [];

                if (!$relationsClass) {
                    continue;
                }

                $anyRelationFound = true;

                if (!$sourceIds) {
                    continue;
                }

                foreach ($relationsClass as $relationClass) {
                    $ancestorIds = array_merge($ancestorIds, $ancestor::whereHas($relationClass[0], function ($query) use ($relationClass, $sourceIds) {
                        $query->whereIn((new $relationClass[1]())->getTable() . '.id', $sourceIds);
                    })->withTrashed()->pluck('id')->all());
                }
            }

            // An unresolvable relation means the scope is unknown, not "every row".
            $resolved[$ancestor] = $anyRelationFound ? array_values(array_unique($ancestorIds)) : [];

            $this->runCheckIntegrityOn($ancestor, $resolved[$ancestor]);
        }
    }

    /**
     * One UPDATE per id-chunk: a single full-table statement holds every row lock for
     * the whole recalculation, which blocked all concurrent invoice writes (1205s).
     */
    protected function runCheckIntegrityOn($class, $ids = null): void
    {
        if (!method_exists($class, 'checkIntegrity')) {
            return;
        }

        if (count($class::columnsIntegrityCalculations()) === 0) {
            return;
        }

        $ids = $this->parseIds($ids);
        $chunkSize = max(1, (int) config('kompo-finance.integrity-chunk-size', 500));

        if ($ids === null) {
            DB::table((new $class())->getTable())
                ->select('id')
                ->orderBy('id')
                ->chunkById($chunkSize, fn ($rows) => $class::checkIntegrity($rows->pluck('id')->all()));

            return;
        }

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $class::checkIntegrity($chunk);
        }
    }

    /**
     * Null means "no filter"; an empty array means "no rows". Collapsing the second into
     * the first makes a scoped check rewrite the whole table.
     */
    protected function parseIds($ids): array|null
    {
        if (is_array($ids)) {
            return $ids;
        }

        return $ids ? [$ids] : null;
    }
}
