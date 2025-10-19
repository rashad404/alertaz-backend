<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use App\Http\Resources\Admin\SubscriberResource;

class SubscriberController extends Controller
{
    /**
     * Display a listing of subscribers
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $status = $request->get('status');
        $language = $request->get('language');
        $search = $request->get('search');

        $query = Subscriber::query();

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by language
        if ($language) {
            $query->where('language', $language);
        }

        // Search by email
        if ($search) {
            $query->where('email', 'like', '%' . $search . '%');
        }

        $subscribers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return SubscriberResource::collection($subscribers);
    }

    /**
     * Store a newly created subscriber
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:subscribers,email',
            'language' => 'required|in:az,en,ru',
            'status' => 'required|in:active,unsubscribed'
        ]);

        $subscriber = Subscriber::create([
            'email' => $request->email,
            'language' => $request->language,
            'status' => $request->status,
            'ip_address' => $request->ip()
        ]);

        return new SubscriberResource($subscriber);
    }

    /**
     * Display the specified subscriber
     */
    public function show($id)
    {
        $subscriber = Subscriber::findOrFail($id);
        return new SubscriberResource($subscriber);
    }

    /**
     * Update the specified subscriber
     */
    public function update(Request $request, $id)
    {
        $subscriber = Subscriber::findOrFail($id);

        $request->validate([
            'email' => 'required|email|unique:subscribers,email,' . $id,
            'language' => 'required|in:az,en,ru',
            'status' => 'required|in:active,unsubscribed'
        ]);

        $subscriber->update([
            'email' => $request->email,
            'language' => $request->language,
            'status' => $request->status
        ]);

        return new SubscriberResource($subscriber);
    }

    /**
     * Remove the specified subscriber
     */
    public function destroy($id)
    {
        $subscriber = Subscriber::findOrFail($id);
        $subscriber->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subscriber deleted successfully'
        ]);
    }

    /**
     * Export subscribers to CSV
     */
    public function export(Request $request)
    {
        $status = $request->get('status', 'active');
        $language = $request->get('language');

        $query = Subscriber::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($language) {
            $query->where('language', $language);
        }

        $subscribers = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=subscribers_' . date('Y-m-d_H-i-s') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $columns = ['ID', 'Email', 'Language', 'Status', 'Subscribed At', 'Created At'];

        $callback = function() use ($subscribers, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($subscribers as $subscriber) {
                fputcsv($file, [
                    $subscriber->id,
                    $subscriber->email,
                    $subscriber->language,
                    $subscriber->status,
                    $subscriber->subscribed_at ? $subscriber->subscribed_at->format('Y-m-d H:i:s') : '',
                    $subscriber->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get statistics about subscribers
     */
    public function stats()
    {
        $total = Subscriber::count();
        $active = Subscriber::where('status', 'active')->count();
        $unsubscribed = Subscriber::where('status', 'unsubscribed')->count();

        $byLanguage = Subscriber::where('status', 'active')
            ->selectRaw('language, count(*) as count')
            ->groupBy('language')
            ->pluck('count', 'language');

        $recentSubscribers = Subscriber::where('status', 'active')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'total' => $total,
            'active' => $active,
            'unsubscribed' => $unsubscribed,
            'by_language' => $byLanguage,
            'recent_30_days' => $recentSubscribers
        ]);
    }
}