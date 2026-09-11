<?php

namespace Condoedge\Finance\Kompo\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Kompo\Core\KompoInfo;
use Kompo\Core\KompoTarget;

/**
 * One document per rendered form, however many times that form is submitted.
 *
 * A request the gateway gives up on still commits: the 504 reaches the browser while PHP
 * keeps writing. The retry that follows posts the SAME rendered form, so the form's own
 * boot payload — reissued only on a re-render — is the idempotency key.
 *
 * Only a successful call is recorded, so a submit rejected for validation can be corrected
 * and sent again from the same form.
 */
trait PreventsDuplicateSubmit
{
    protected $duplicateSubmitTtl = 60;

    /**
     * Run a document-creating call once per rendered form, returning what it created —
     * on a retry, the record the first call actually wrote.
     */
    protected function submitOnce(callable $create)
    {
        $key = $this->duplicateSubmitKey();

        if ($done = Cache::get($key)) {
            return $this->resolveDoneSubmit($done);
        }

        // Outlives the gateway's patience on purpose: the retry arrives while the first
        // call is still writing, and that is the collision this exists to stop.
        $lock = Cache::lock($key . ':running', 180);

        if (!$lock->get()) {
            abort(409, __('finance-submit-still-running'));
        }

        try {
            $result = $create();

            Cache::put($key, [
                'class' => $result instanceof Model ? get_class($result) : null,
                'id' => $result instanceof Model ? $result->getKey() : null,
            ], now()->addMinutes($this->duplicateSubmitTtl));

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * The record is looked up again rather than cached: it has been recalculated since,
     * and a caller redirecting to it needs the current row.
     */
    protected function resolveDoneSubmit($done)
    {
        if (!$done['class']) {
            return null;
        }

        if (!$record = $done['class']::find($done['id'])) {
            abort(409, __('finance-submit-already-done'));
        }

        return $record;
    }

    /**
     * Scoped to one render of one component: a re-render reissues the boot payload, which
     * is what makes reopening the form a new operation rather than a replay. The target is
     * part of it because several handlers can share a single render.
     */
    protected function duplicateSubmitKey()
    {
        return 'finance:submit-once:' . sha1(implode('|', [
            static::class,
            (string) auth()->id(),
            (string) (request()->header(KompoInfo::$key) ?: json_encode(request()->except(['_token']))),
            (string) request()->header(KompoTarget::$key),
        ]));
    }
}
