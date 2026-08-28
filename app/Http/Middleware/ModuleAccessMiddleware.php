<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleAccessMiddleware
{
    /** Restrict each ERP module while retaining an administrator override. */
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->user()?->role;
        if ($role === 'admin') return $next($request);

        $uri = trim($request->route()?->uri() ?? '', '/');
        $segment = explode('/', preg_replace('#^admin/?#', '', $uri))[0] ?? '';
        $allowedRoles = [
            'masterplan' => ['ppic'],
            'mps-schedules' => ['ppic'],
            'work-orders' => ['ppic'],
            'mrp' => ['ppic'],
            'procurement' => ['ppic'],
            'bom' => ['ie'],
            'shopfloor' => ['prod'],
            'inventory' => ['warehouse'],
            'finance' => ['accountant'],
            'revenue' => ['prod'],
        ];

        abort_unless(in_array($role, $allowedRoles[$segment] ?? [], true), 403);
        // PPIC may plan/update a master plan, but only an administrator may remove it.
        if ($segment === 'masterplan' && $request->isMethod('DELETE')) abort(403);
        // Accountants have read access to costing reports; financial master changes remain admin-only.
        if ($segment === 'finance' && !in_array($request->method(), ['GET', 'HEAD'], true)) abort(403);
        return $next($request);
    }
}
