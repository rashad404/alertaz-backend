<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }

    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }

    public function destroy($id)
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }

    public function uploadImage(Request $request, $id)
    {
        return response()->json(['message' => 'Blog controller not implemented yet']);
    }
}