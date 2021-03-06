<?php namespace GeneaLabs\LaravelModelCaching;

use GeneaLabs\LaravelModelCaching\Traits\BuilderCaching;
use GeneaLabs\LaravelModelCaching\Traits\Caching;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class CachedBuilder extends EloquentBuilder
{
    use BuilderCaching;
    use Caching;

    public function avg($column)
    {
        if (! $this->isCachable()) {
            return parent::avg($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-avg_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function count($columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::count($columns);
        }

        $cacheKey = $this->makeCacheKey($columns, null, "-count");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function delete()
    {
        $this->cache($this->makeCacheTags())
            ->flush();

        return parent::delete();
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function find($id, $columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::find($id, $columns);
        }

        $cacheKey = $this->makeCacheKey($columns, null, "-find_{$id}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function first($columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::first($columns);
        }

        $cacheKey = $this->makeCacheKey($columns, null, "-first");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function get($columns = ["*"])
    {
        if (! $this->isCachable()) {
            return parent::get($columns);
        }

        $cacheKey = $this->makeCacheKey($columns);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function insert(array $values)
    {
        $this->checkCooldownAndFlushAfterPersiting($this->model);

        return parent::insert($values);
    }

    public function max($column)
    {
        if (! $this->isCachable()) {
            return parent::max($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-max_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function min($column)
    {
        if (! $this->isCachable()) {
            return parent::min($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-min_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function paginate(
        $perPage = null,
        $columns = ["*"],
        $pageName = "page",
        $page = null
    ) {
        if (! $this->isCachable()) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        }

        $page = request("page", $page ?: 1);
        $cacheKey = $this->makeCacheKey($columns, null, "-paginate_by_{$perPage}_{$pageName}_{$page}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function pluck($column, $key = null)
    {
        if (! $this->isCachable()) {
            return parent::pluck($column, $key);
        }

        $keyDifferentiator = "-pluck_{$column}" . ($key ? "_{$key}" : "");
        $cacheKey = $this->makeCacheKey([$column], null, $keyDifferentiator);

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function sum($column)
    {
        if (! $this->isCachable()) {
            return parent::sum($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-sum_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function update(array $values)
    {
        $this->checkCooldownAndFlushAfterPersiting($this->model);

        return parent::update($values);
    }

    public function value($column)
    {
        if (! $this->isCachable()) {
            return parent::value($column);
        }

        $cacheKey = $this->makeCacheKey(["*"], null, "-value_{$column}");

        return $this->cachedValue(func_get_args(), $cacheKey);
    }

    public function cachedValue(array $arguments, string $cacheKey)
    {
        $method = debug_backtrace()[1]['function'];
        $cacheTags = $this->makeCacheTags();
        $hashedCacheKey = sha1($cacheKey);
        $result = $this->retrieveCachedValue(
            $arguments,
            $cacheKey,
            $cacheTags,
            $hashedCacheKey,
            $method
        );

        return $this->preventHashCollision(
            $result,
            $arguments,
            $cacheKey,
            $cacheTags,
            $hashedCacheKey,
            $method
        );
    }

    protected function preventHashCollision(
        array $result,
        array $arguments,
        string $cacheKey,
        array $cacheTags,
        string $hashedCacheKey,
        string $method
    ) {
        if ($result["key"] === $cacheKey) {
            return $result["value"];
        }

        $this->cache()
            ->tags($cacheTags)
            ->forget($hashedCacheKey);

        return $this->retrieveCachedValue(
            $arguments,
            $cacheKey,
            $cacheTags,
            $hashedCacheKey,
            $method
        );
    }

    protected function retrieveCachedValue(
        array $arguments,
        string $cacheKey,
        array $cacheTags,
        string $hashedCacheKey,
        string $method
    ) {
        $this->checkCooldownAndRemoveIfExpired($this->model);

        return $this->cache($cacheTags)
            ->rememberForever(
                $hashedCacheKey,
                function () use ($arguments, $cacheKey, $method) {
                    return [
                        "key" => $cacheKey,
                        "value" => parent::{$method}(...$arguments),
                    ];
                }
            );
    }
}
