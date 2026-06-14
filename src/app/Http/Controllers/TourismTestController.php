<?php

namespace App\Http\Controllers;

use App\Models\TourismTestSearch;
use App\Services\TourismTest\TourismSpotSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TourismTestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Debug/Tourism', [
            'recentSearches' => $this->formatRecentSearches(),
            'latestResult' => null,
            'error' => null,
        ]);
    }

    public function search(Request $request, TourismSpotSearchService $service): Response|RedirectResponse
    {
        $validated = $request->validate([
            'location_name' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        try {
            $result = $service->search($validated['location_name']);

            return Inertia::render('Debug/Tourism', [
                'recentSearches' => $this->formatRecentSearches(),
                'latestResult' => $this->formatSearch($result['search']),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            return Inertia::render('Debug/Tourism', [
                'recentSearches' => $this->formatRecentSearches(),
                'latestResult' => null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatRecentSearches(): array
    {
        return TourismTestSearch::query()
            ->with('spots')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (TourismTestSearch $search) => $this->formatSearch($search))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSearch(TourismTestSearch $search): array
    {
        return [
            'id' => $search->id,
            'location_name' => $search->location_name,
            'latitude' => $search->latitude,
            'longitude' => $search->longitude,
            'status' => $search->status,
            'error_message' => $search->error_message,
            'created_at' => $search->created_at?->toIso8601String(),
            'spots' => $search->spots
                ->map(fn ($spot) => [
                    'name' => $spot->name,
                    'latitude' => $spot->latitude,
                    'longitude' => $spot->longitude,
                    'distance_km' => $spot->distance_km,
                    'distance_text' => $spot->distance_text,
                    'description' => $spot->description,
                    'image_url' => $spot->image_url,
                ])
                ->values()
                ->all(),
        ];
    }
}
