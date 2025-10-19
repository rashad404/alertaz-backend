<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::with(['parent', 'children'])
            ->whereNull('parent_id')  // Only get parent menus
            ->orderBy('menu_location')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(function ($menu) {
                // Decode JSON fields if they're strings
                if (is_string($menu->title)) {
                    $menu->title = json_decode($menu->title, true) ?: $menu->title;
                }
                // Ensure is_active is properly cast to boolean
                $menu->is_active = (bool) $menu->is_active;
                
                // Process children if they exist
                if ($menu->children) {
                    $menu->children = $menu->children->map(function ($child) {
                        if (is_string($child->title)) {
                            $child->title = json_decode($child->title, true) ?: $child->title;
                        }
                        $child->is_active = (bool) $child->is_active;
                        return $child;
                    });
                }
                
                return $menu;
            });
            
        return response()->json($menus);
    }

    public function show($id)
    {
        $menu = Menu::with('parent', 'children')->findOrFail($id);
        
        // Decode JSON fields if they're strings
        if (is_string($menu->title)) {
            $menu->title = json_decode($menu->title, true) ?: $menu->title;
        }
        
        // Ensure is_active is properly cast to boolean
        $menu->is_active = (bool) $menu->is_active;
        
        return response()->json($menu);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|array',
            'title.az' => 'required|string',
            'title.en' => 'required|string',
            'title.ru' => 'required|string',
            'slug' => 'required|string|unique:menus',
            'url' => 'nullable|string',
            'parent_id' => 'nullable|exists:menus,id',
            'position' => 'nullable|integer',
            'target' => 'nullable|in:_self,_blank',
            'has_dropdown' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'menu_location' => 'required|in:header,footer,both',
            'icon' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        $menu = Menu::create($validated);
        
        return response()->json($menu, 201);
    }

    public function update(Request $request, $id)
    {
        $menu = Menu::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'required|array',
            'title.az' => 'required|string',
            'title.en' => 'required|string',
            'title.ru' => 'required|string',
            'slug' => 'required|string|unique:menus,slug,' . $id,
            'url' => 'nullable|string',
            'parent_id' => 'nullable|exists:menus,id',
            'position' => 'nullable|integer',
            'target' => 'nullable|in:_self,_blank',
            'has_dropdown' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'menu_location' => 'required|in:header,footer,both',
            'icon' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        // Prevent self-referencing parent
        if ($validated['parent_id'] == $id) {
            return response()->json([
                'message' => 'Menu cannot be its own parent'
            ], 422);
        }

        $menu->update($validated);
        
        return response()->json($menu);
    }

    public function destroy($id)
    {
        $menu = Menu::findOrFail($id);
        
        // Check if menu has children
        if ($menu->children()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete menu with child items. Delete children first.'
            ], 422);
        }
        
        $menu->delete();
        
        return response()->json(['message' => 'Menu deleted successfully']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menus,id',
            'items.*.position' => 'required|integer',
        ]);

        foreach ($validated['items'] as $item) {
            Menu::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Menu order updated successfully']);
    }

    public function toggleStatus($id)
    {
        $menu = Menu::findOrFail($id);
        $menu->is_active = !$menu->is_active;
        $menu->save();

        return response()->json([
            'message' => 'Menu status toggled successfully',
            'is_active' => $menu->is_active
        ]);
    }
}