<?php

namespace App\Http\Controllers;

use App\Enums\EvacuationCenterStatus;
use App\Enums\Permission;
use App\Http\Requests\StoreEvacuationCenterRequest;
use App\Models\EvacuationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Feature 4: Evacuation Center Management.
 *
 * Every authenticated user may see the roster (residents need to know where to
 * go); only roles holding `evacuationCenters.manage` may change it.
 */
class EvacuationCenterController extends Controller
{
    public function index(): View
    {
        $this->authorize(Permission::EvacuationCentersView->value);

        $centers = EvacuationCenter::orderBy('name')->get();

        return view('evacuation-centers.index', [
            'centers' => $centers,
            'totals' => [
                'centers' => $centers->count(),
                'capacity' => $centers->sum('capacity'),
                'occupancy' => $centers->sum('current_occupancy'),
                'accepting' => $centers->filter->canAcceptEvacuees()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize(Permission::EvacuationCentersManage->value);

        return view('evacuation-centers.create', [
            'statuses' => EvacuationCenterStatus::options(),
        ]);
    }

    public function store(StoreEvacuationCenterRequest $request): RedirectResponse
    {
        $center = EvacuationCenter::create($request->validated());

        return redirect()->route('evacuation-centers.index')
            ->with('status', "{$center->name} added to the evacuation center roster.");
    }

    public function edit(EvacuationCenter $evacuationCenter): View
    {
        $this->authorize(Permission::EvacuationCentersManage->value);

        return view('evacuation-centers.edit', [
            'center' => $evacuationCenter,
            'statuses' => EvacuationCenterStatus::options(),
        ]);
    }

    public function update(StoreEvacuationCenterRequest $request, EvacuationCenter $evacuationCenter): RedirectResponse
    {
        $evacuationCenter->update($request->validated());

        return redirect()->route('evacuation-centers.index')
            ->with('status', "{$evacuationCenter->name} updated.");
    }

    public function destroy(EvacuationCenter $evacuationCenter): RedirectResponse
    {
        $this->authorize(Permission::EvacuationCentersManage->value);

        if ($evacuationCenter->current_occupancy > 0) {
            return back()->with('status', "Can't remove {$evacuationCenter->name} — it still has evacuees. Move them out first.");
        }

        $name = $evacuationCenter->name;
        $evacuationCenter->delete();

        return redirect()->route('evacuation-centers.index')->with('status', "{$name} removed.");
    }
}
