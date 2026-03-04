<?php

namespace App\Livewire\Pages;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public int $days = 30; // période pour l’évolution (30 derniers jours)

    public function setDays(int $days): void
    {
        $this->days = max(7, min($days, 365));
    }

    public function render()
    {
        $user = Auth::user();
        $provinceName = null;

        // Scope: superadmin voit tout; sinon limité à la province du user
        $isSuper = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->user_role === 'superadmin');
        $provinceScope = $isSuper ? null : $user->code_province;

        if ($provinceScope) {
            $provinceName = DB::table('provinces')
                ->where('provinces.code_province', $provinceScope)
                ->value('nom_province');
        }

        // --------- KPI Users ----------
        $usersActiveQuery = DB::table('users')->where('is_active', true);
        $usersPendingQuery = DB::table('users')->where('is_active', false);

        if ($provinceScope) {
            $usersActiveQuery->where('code_province', $provinceScope);
            $usersPendingQuery->where('code_province', $provinceScope);
        }

        $usersActive = (int) $usersActiveQuery->count();
        $usersPending = (int) $usersPendingQuery->count();

        // --------- Incidents base query ----------
        // incidents.date_incident, incidents.statut_incident, incidents.code_province :contentReference[oaicite:1]{index=1}
        $incBase = DB::table('incidents');

        if ($provinceScope) {
            $incBase->where('incidents.code_province', $provinceScope);
        }

        // --------- Incidents par province ----------
        // Join provinces (code_province, nom_province) :contentReference[oaicite:2]{index=2}
        $byProvince = (clone $incBase)
            ->leftJoin('provinces', 'incidents.code_province', '=', 'provinces.code_province')
            ->selectRaw("COALESCE(provinces.nom_province, incidents.code_province, 'N/A') as label, COUNT(*)::int as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        //------Pourcentage d'incidents par province (par rapport au total)------
        $byProvinceTotal = (int) $byProvince->sum('total');

        $byProvinceTable = $byProvince->map(function ($row) use ($byProvinceTotal) {
            $pct = $byProvinceTotal > 0 ? round(($row->total / $byProvinceTotal) * 100, 1) : 0;
            return [
                'label' => $row->label,
                'total' => (int) $row->total,
                'pct'   => $pct,
            ];
        })->values();

        // --------- Incidents par statut ----------
        $byStatus = (clone $incBase)
            ->selectRaw("COALESCE(incidents.statut_incident, 'N/A') as label, COUNT(*)::int as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        // --------- Evolution incidents (X jours) ----------
        // On groupe par jour sur date_incident (timestamp) :contentReference[oaicite:3]{index=3}
        $evolution = (clone $incBase)
            ->whereNotNull('incidents.date_incident')
            ->where('incidents.date_incident', '>=', now()->subDays($this->days)->startOfDay())
            ->selectRaw("to_char(incidents.date_incident::date, 'YYYY-MM-DD') as d, COUNT(*)::int as total")
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // Préparer datasets pour Chart.js
        $chart = [
            'users' => [
                'active' => $usersActive,
                'pending' => $usersPending,
            ],
            'byProvince' => [
                'labels' => $byProvince->pluck('label')->values(),
                'data' => $byProvince->pluck('total')->values(),
                'table' => $byProvinceTable,          // ✅ mini tableau
                'sum'   => $byProvinceTotal,          // ✅ total pour affichage
            ],
            'byStatus' => [
                'labels' => $byStatus->pluck('label')->values(),
                'data' => $byStatus->pluck('total')->values(),
            ],
            'evolution' => [
                'labels' => $evolution->pluck('d')->values(),
                'data' => $evolution->pluck('total')->values(),
            ],
            'scope' => [
                'isSuper' => $isSuper,
                'code_province' => $provinceScope,
                'nom_province' => $provinceName,
            ],
        ];

        return view('livewire.pages.dashboard', [
            'chart' => $chart,
        ]);
    }
}
