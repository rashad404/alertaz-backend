<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Get all menus with hierarchy
     */
    public function index(Request $request, $locale = 'az')
    {
        // Get header menus
        $headerMenus = Menu::active()
            ->header()
            ->main()
            ->with(['activeChildren'])
            ->orderBy('position')
            ->get()
            ->map(function ($menu) use ($locale) {
                return $this->formatMenu($menu, $locale);
            });

        // Get footer menus
        $footerMenus = Menu::active()
            ->footer()
            ->main()
            ->with(['activeChildren'])
            ->orderBy('position')
            ->get()
            ->map(function ($menu) use ($locale) {
                return $this->formatMenu($menu, $locale);
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'header' => $headerMenus,
                'footer' => $footerMenus
            ]
        ]);
    }

    /**
     * Get menus by location
     */
    public function byLocation(Request $request, $locale = 'az', $location = 'header')
    {
        $query = Menu::active()->main()->with(['activeChildren']);

        if ($location === 'header') {
            $query->header();
        } elseif ($location === 'footer') {
            $query->footer();
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid menu location'
            ], 400);
        }

        $menus = $query->orderBy('position')->get()->map(function ($menu) use ($locale) {
            return $this->formatMenu($menu, $locale);
        });

        return response()->json([
            'status' => 'success',
            'data' => $menus
        ]);
    }

    /**
     * Format menu item for API response
     */
    private function formatMenu($menu, $locale)
    {
        $formatted = [
            'id' => $menu->id,
            'title' => $menu->getTranslation('title', $locale),
            'slug' => $menu->slug,
            'url' => $menu->getFormattedUrl($locale),
            'target' => $menu->target,
            'has_dropdown' => $menu->has_dropdown,
            'icon' => $menu->icon,
        ];

        // Add children if they exist
        if ($menu->has_dropdown && $menu->activeChildren->count() > 0) {
            $formatted['children'] = $menu->activeChildren->map(function ($child) use ($locale) {
                return [
                    'id' => $child->id,
                    'title' => $child->getTranslation('title', $locale),
                    'slug' => $child->slug,
                    'url' => $child->getFormattedUrl($locale),
                    'target' => $child->target,
                    'icon' => $child->icon,
                ];
            });
        }

        return $formatted;
    }
}