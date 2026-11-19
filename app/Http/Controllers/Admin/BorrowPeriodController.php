<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BorrowPeriodRequest;
use App\Models\BorrowPeriod;
use App\Support\DatabaseError;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BorrowPeriodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Periods/Index', [
            'periods' => BorrowPeriod::orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(BorrowPeriodRequest $request): RedirectResponse
    {
        $this->guardOverlap(fn () => BorrowPeriod::create($request->validated()));

        return back()->with('success', '已新增期間。');
    }

    public function update(BorrowPeriodRequest $request, BorrowPeriod $period): RedirectResponse
    {
        $this->guardOverlap(fn () => $period->update($request->validated()));

        return back()->with('success', '已更新期間。');
    }

    public function destroy(BorrowPeriod $period): RedirectResponse
    {
        $period->delete();

        return back()->with('success', '已刪除期間。');
    }

    private function guardOverlap(Closure $write): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            if (DatabaseError::is($e, DatabaseError::EXCLUSION_VIOLATION)) {
                throw ValidationException::withMessages(['starts_on' => '這段期間和既有的期間重疊了。']);
            }

            throw $e;
        }
    }
}
