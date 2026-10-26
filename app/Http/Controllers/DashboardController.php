<?php

namespace App\Http\Controllers;

use App\Models\UtilityBill;
use App\Services\Dashboard\OwnerDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, OwnerDashboardService $dashboard): View
    {
        $notification = $this->utilityNotification();

        if ($request->user()?->role !== 'developer') {
            return view('home', compact('notification'));
        }

        [$from, $to] = $this->period($request);
        $summary = $dashboard->summary($from, $to);

        return view('dashboard.index', compact('summary', 'notification'));
    }

    public function data(Request $request, OwnerDashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->role === 'developer', 403);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'refresh' => ['nullable', 'boolean'],
        ]);
        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        abort_if($from->diffInDays($to) > 730, 422, 'Dashboard date range cannot exceed two years.');

        $summary = $dashboard->summary($from, $to, $request->boolean('refresh'));

        return response()->json([
            'status' => 'success',
            'html' => view('dashboard.partials.summary', compact('summary'))->render(),
            'summary' => $summary,
        ]);
    }

    private function period(Request $request): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
            $to = $request->filled('to') ? Carbon::parse($request->string('to')) : today();
        } catch (\Throwable) {
            $from = now()->startOfMonth();
            $to = today();
        }

        if ($from->isAfter($to) || $from->diffInDays($to) > 730) {
            $from = now()->startOfMonth();
            $to = today();
        }

        return [$from, $to];
    }

    private function utilityNotification(): array
    {
        $count = UtilityBill::query()->where('is_paid', false)
            ->where(fn ($query) => $query->whereBetween('due_date', [today(), today()->addDays(5)])->orWhereDate('due_date', '<', today()))
            ->count();

        return $count > 0 ? [
            'title' => 'Utility Bill Reminder',
            'message' => $count.' Utility Bill'.($count === 1 ? '' : 's').' Unpaid or Due Soon',
        ] : [];
    }
}
