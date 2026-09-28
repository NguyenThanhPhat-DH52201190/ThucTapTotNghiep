<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PpicTeamMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredTeam): Response
    {
        $user = $request->user();
        if ($user?->isAdmin() || $user?->role !== User::ROLE_PPIC) {
            return $next($request);
        }

        $allowed = $requiredTeam === 'view'
            ? in_array($user->ppic_team, [User::PPIC_TEAM_TRACK, User::PPIC_TEAM_CREATE, User::PPIC_TEAM_BOTH], true)
            : in_array($user->ppic_team, [$requiredTeam, User::PPIC_TEAM_BOTH], true);
        abort_unless($allowed, 403);

        return $next($request);
    }
}
