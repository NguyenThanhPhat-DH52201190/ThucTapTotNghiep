<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $audits = DB::table('audit_trails')->leftJoin('users', 'audit_trails.user_id', '=', 'users.id')
            ->select('audit_trails.*', 'users.name as user_name')
            ->when($request->filled('event_type'), fn ($query) => $query->where('audit_trails.event_type', $request->event_type))
            ->orderByDesc('audit_trails.id')->paginate(50)->withQueryString();
        return view('admin.audit-trails.index', compact('audits'));
    }
}
