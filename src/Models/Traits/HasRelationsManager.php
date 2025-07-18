<?php

namespace Condoedge\Finance\Models\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasRelationsManager
{
    public static function getRelationships($relatedClass = null)
    {
        return Cache::rememberForever(static::class . 'relations' . $relatedClass, function () use ($relatedClass) {
            $instance = new static();

            // Get public methods declared without parameters and non inherited
            $class = get_class($instance);
            $allMethods = (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC);
            $methods = array_filter(
                $allMethods,
                function ($method) use ($class) {
                    return $method->class === $class
                        && !$method->isStatic()                        // relationships are not static
                        && !$method->getParameters()                  // relationships have no parameters
                        && $method->getName() !== 'getRelationships'; // prevent infinite recursion
                }
            );

            $relations = [];

            DB::beginTransaction();
            foreach ($methods as $method) {
                try {
                    // Try to call the method to see if it is a relationship
                    $returnValue = $instance->{$method->name}();

                    // Check if the return is an instance of a relationship
                    if ($returnValue instanceof Relation) {
                        // Get the related model
                        $relatedModel = get_class($returnValue->getRelated());

                        // Compare if this relationship points to the child model we are looking for
                        if (!$relatedClass || $relatedModel === $relatedClass) {
                            $relations[] = [$method->getName(), $relatedModel];
                        }
                    }
                } catch (\Throwable $th) {
                    continue;
                }
            }
            DB::rollBack();

            return $relations;
        });
    }
}
